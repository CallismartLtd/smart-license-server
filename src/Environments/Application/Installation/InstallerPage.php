<?php
/**
 * Installer page class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Installation
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Installation;

/**
 * Renders the web installer's pages.
 *
 * Every step method returns a page: array{title: string, html: string}, where
 * `html` is the content of the installer card. document() wraps a page in the
 * full HTML document for normal requests; the installer's script swaps only
 * the card content when it navigates with fetch().
 *
 * Self-contained: inline styles and one inline script (allowed by a CSP
 * nonce), no external assets, so the pages work before the application's
 * assets exist. Every form also works without JavaScript. Every dynamic
 * value is escaped.
 *
 * @package SmartLicenseServer\Environments\Application\Installation
 * @since 0.2.0
 */
class InstallerPage {

	/**
	 * Stepper labels, keyed by step.
	 */
	protected const STEPS = array(
		'access'   => 'Access',
		'database' => 'Database',
		'setup'    => 'Setup',
		'admin'    => 'Administrator',
		'finish'   => 'Finish',
	);

	/**
	 * Setup tasks the script runs one by one, keyed by task with display labels.
	 */
	public const SETUP_TASKS = array(
		'site_url'    => 'Save the site address',
		'directories' => 'Create the storage folders',
		'assets'      => 'Publish the public assets',
		'tables'      => 'Create the database tables',
		'roles'       => 'Install the default roles',
		'htaccess'    => 'Write the web server rules (.htaccess)',
	);

	/*
	|--------
	| Steps
	|--------
	*/

	/**
	 * Setup token form.
	 *
	 * @param string      $action_url Installer URL.
	 * @param string      $token_path Token file path relative to the application folder.
	 * @param string|null $error      Error or notice to show.
	 * @return array{title: string, html: string}
	 */
	public function token( string $action_url, string $token_path, ?string $error = null ) : array {
		$notices = null === $error ? array() : array( array( 'type' => 'error', 'message' => $error ) );
		$app     = $this->e( \SMLISER_APP_NAME );
		$dir     = $this->e( dirname( $token_path ) . '/' );
		$file    = $this->e( basename( $token_path ) );

		$body = <<<HTML
			<p class="lead">Welcome! Setting up {$app} takes a few minutes. First, confirm that this is your server, so nobody else can take over the installation.</p>
			<ol class="guide">
				<li>Open your hosting control panel&rsquo;s <strong>File Manager</strong>, or connect with your FTP client.</li>
				<li>Go to the folder you uploaded {$app} to, then open <code>{$dir}</code>.</li>
				<li>Open <code>{$file}</code> and copy the value after <code>"token":</code> &mdash; the 32 letters and numbers between the quotes.</li>
			</ol>
			<details class="more">
				<summary>Have terminal (SSH) access instead?</summary>
				<p>Run this in the folder you uploaded {$app} to, and copy the line it prints:</p>
				<pre><code>php smliser installer token</code></pre>
			</details>
			<form method="post" action="{$this->e( $action_url )}" class="form" data-async data-busy-label="Checking&hellip;">
				{$this->field(
					'setup_token',
					'Setup token',
					'<input id="setup_token" name="setup_token" type="text" autocomplete="off" spellcheck="false" autocapitalize="off" required autofocus>',
					'The token is valid for 24 hours and is deleted when the installation finishes.'
				)}
				<div class="actions"><button type="submit" class="btn btn-accent">Start installation</button></div>
			</form>
			<p class="hint">While you install, visitors see a short &ldquo;setting things up&rdquo; notice.</p>
			HTML;

		return $this->page( 'access', 'Install ' . \SMLISER_APP_NAME, $body, $notices );
	}

	/**
	 * Database connection form.
	 *
	 * @param array{action_url: string, csrf: string, notices: array} $context Shared page context.
	 * @param array<string, string>                                   $values  Current values.
	 * @param array<string, array{label: string, help: string, port: int|null, charset: string|null}> $drivers Driver details.
	 * @param string                                                  $sqlite_dir Default SQLite folder.
	 * @return array{title: string, html: string}
	 */
	public function database( array $context, array $values, array $drivers, string $sqlite_dir ) : array {
		$v = fn ( string $key ) : string => $this->e( (string) ( $values[ $key ] ?? '' ) );

		$current = (string) ( $values['db_driver'] ?? '' );
		$options = '';

		foreach ( $drivers as $driver => $info ) {
			$options .= sprintf(
				'<label class="choice"><input type="radio" name="db_driver" value="%1$s" data-port="%2$s" data-charset="%6$s"%3$s required><span><strong>%4$s</strong><small>%5$s</small></span></label>',
				$this->e( $driver ),
				null === $info['port'] ? '' : (int) $info['port'],
				$current === $driver ? ' checked' : '',
				$this->e( $info['label'] ),
				$this->e( $info['help'] ),
				$this->e( (string) ( $info['charset'] ?? '' ) )
			);
		}

		$port_hint = array();
		foreach ( $drivers as $info ) {
			if ( null !== $info['port'] ) {
				$port_hint[] = $info['label'] . ' ' . $info['port'];
			}
		}

		$default_port = $drivers[ $current ]['port'] ?? null;
		$port_ph      = null === $default_port ? '' : (string) $default_port;
		$charset_ph   = (string) ( $drivers[ $current ]['charset'] ?? '' );

		$charset_hint = array();
		foreach ( $drivers as $info ) {
			if ( null !== ( $info['charset'] ?? null ) ) {
				$charset_hint[] = $info['label'] . ' <code>' . $this->e( $info['charset'] ) . '</code>';
			}
		}

		// Keep Advanced open when it holds values, so they (and their errors) stay visible.
		$advanced_open = ( '' !== (string) ( $values['db_prefix'] ?? '' ) || '' !== (string) ( $values['db_charset'] ?? '' ) ) ? ' open' : '';

		$charset_help = 'How text is stored and sent. Leave empty for the recommended value (' . implode( ', ', $charset_hint ) . '), which supports every language and emoji. Change it only if your host requires another.';
		$app          = $this->e( \SMLISER_APP_NAME );

		$fields = <<<HTML
			<p class="lead">{$app} stores its data in a database. Create an empty database and a database user in your hosting control panel (often under <strong>MySQL Databases</strong>), then enter the details here.</p>
			<p class="hint">Nothing is saved until the connection works.</p>
			<fieldset class="field">
				<legend>Database type</legend>
				<div class="choices">{$options}</div>
			</fieldset>
			{$this->field(
				'db_name',
				'Database name',
				"<input id=\"db_name\" name=\"db_name\" type=\"text\" value=\"{$v( 'db_name' )}\" autocomplete=\"off\" spellcheck=\"false\" required>",
				'<span class="net-only">The name of the empty database you created, for example <code>myaccount_licenses</code>. Some hosts add your account name in front.</span><span class="sqlite-only">The database file name, for example <code>smliser</code>. <code>.db</code> is added when there is no extension. The file is created if it does not exist.</span>'
			)}
			<div class="grid net-only">
				{$this->field(
					'db_host',
					'Server address',
					"<input id=\"db_host\" name=\"db_host\" type=\"text\" value=\"{$v( 'db_host' )}\" placeholder=\"localhost\" autocomplete=\"off\" spellcheck=\"false\">",
					'Usually <code>localhost</code>. Your host lists it if the database is on another server.',
					true
				)}
				{$this->field(
					'db_port',
					'Port',
					"<input id=\"db_port\" name=\"db_port\" type=\"text\" inputmode=\"numeric\" value=\"{$v( 'db_port' )}\" placeholder=\"{$this->e( $port_ph )}\">",
					'Leave empty for the standard port (' . $this->e( implode( ', ', $port_hint ) ) . ').',
					true
				)}
			</div>
			<div class="grid net-only">
				{$this->field(
					'db_user',
					'Database username',
					"<input id=\"db_user\" name=\"db_user\" type=\"text\" value=\"{$v( 'db_user' )}\" autocomplete=\"off\" spellcheck=\"false\">",
					'The database user you created and gave access to this database.'
				)}
				{$this->field(
					'db_password',
					'Database password',
					'<input id="db_password" name="db_password" type="password" autocomplete="new-password">',
					'The password of that database user. It is stored in the <code>.env</code> file.'
				)}
			</div>
			{$this->field(
				'db_path',
				'Database folder',
				"<input id=\"db_path\" name=\"db_path\" type=\"text\" value=\"{$v( 'db_path' )}\" placeholder=\"{$this->e( $sqlite_dir )}\" spellcheck=\"false\">",
				'The folder that holds the database file. Leave empty to use the application&rsquo;s storage folder. Keep it outside your public web folder.',
				true,
				'sqlite-only'
			)}
			{$this->field(
				'db_encryption_key',
				'Encryption key',
				'<input id="db_encryption_key" name="db_encryption_key" type="password" autocomplete="off">',
				'Encrypts the database file. Only works when your server&rsquo;s SQLite supports encryption; leave empty if unsure.',
				true,
				'sqlite-only'
			)}
			<details class="more"{$advanced_open}>
				<summary>Advanced</summary>
				{$this->field(
					'db_prefix',
					'Table prefix',
					"<input id=\"db_prefix\" name=\"db_prefix\" type=\"text\" value=\"{$v( 'db_prefix' )}\" placeholder=\"smliser_\" spellcheck=\"false\">",
					'Added to the start of every table name, so the database can be shared with other applications. Leave empty for <code>smliser_</code>.',
					true
				)}
				{$this->field(
					'db_charset',
					'Character set',
					"<input id=\"db_charset\" name=\"db_charset\" type=\"text\" value=\"{$v( 'db_charset' )}\" placeholder=\"{$this->e( $charset_ph )}\" spellcheck=\"false\" autocomplete=\"off\">",
					$charset_help,
					true,
					'net-only'
				)}
			</details>
			HTML;

		return $this->page(
			'database',
			'Connect your database',
			$this->form( $context, 'database', $fields, 'Test connection and continue', 'Connecting&hellip;' ),
			$context['notices']
		);
	}

	/**
	 * Directories, tables and roles step.
	 *
	 * @param array{action_url: string, csrf: string, notices: array} $context Shared page context.
	 * @param string[]                                                $issues  What is still missing.
	 * @param string                                                  $app_url The site address to prefill.
	 * @return array{title: string, html: string}
	 */
	public function setup( array $context, array $issues, string $app_url ) : array {
		$tasks = '';
		foreach ( static::SETUP_TASKS as $task => $label ) {
			$tasks .= sprintf(
				'<li data-task="%1$s" data-state="pending"><span class="mark" aria-hidden="true"></span><span class="task-body"><span class="task-label">%2$s</span><span class="task-detail"></span></span></li>',
				$this->e( $task ),
				$this->e( $label )
			);
		}

		$missing = '';
		foreach ( $issues as $issue ) {
			$missing .= '<li>' . $this->e( $issue ) . '</li>';
		}

		$fields = '<p class="lead">The database is connected. The installer now prepares everything the application needs. Anything that already exists is kept as it is, so it is safe to run this again.</p>'
			. $this->field(
				'app_url',
				'Site address',
				'<input id="app_url" name="app_url" type="text" inputmode="url" autocomplete="url" value="' . $this->e( $app_url ) . '" placeholder="https://licenses.example.com" spellcheck="false" required>',
				'The web address people use to reach this site, detected from your browser. Change it only if it is wrong, for example to use <code>https://</code> or your final domain. Links in emails and API responses use it.'
			)
			. '<ul class="tasks" aria-live="polite">' . $tasks . '</ul>'
			. ( '' === $missing ? '' : '<details class="more"><summary>What is still missing</summary><ul class="issues">' . $missing . '</ul></details>' );

		return $this->page(
			'setup',
			'Prepare the application',
			$this->form( $context, 'setup', $fields, 'Run setup', 'Setting up&hellip;', true ),
			$context['notices']
		);
	}

	/**
	 * Administrator account form.
	 *
	 * @param array{action_url: string, csrf: string, notices: array} $context    Shared page context.
	 * @param array<string, string>                                   $values     Submitted values.
	 * @param int                                                     $min_length Minimum password length.
	 * @return array{title: string, html: string}
	 */
	public function admin( array $context, array $values, int $min_length ) : array {
		$name  = $this->e( (string) ( $values['admin_name'] ?? '' ) );
		$email = $this->e( (string) ( $values['admin_email'] ?? '' ) );

		$fields = <<<HTML
			<p class="lead">Create your administrator account. You will use it to sign in and manage everything else, including other users.</p>
			{$this->field(
				'admin_name',
				'Your name',
				"<input id=\"admin_name\" name=\"admin_name\" type=\"text\" autocomplete=\"name\" value=\"{$name}\" required>",
				'Shown to other users of the dashboard.'
			)}
			{$this->field(
				'admin_email',
				'Email address',
				"<input id=\"admin_email\" name=\"admin_email\" type=\"email\" autocomplete=\"email\" value=\"{$email}\" required>",
				'You sign in with this address, and password resets are sent to it.'
			)}
			<div class="grid">
				{$this->field(
					'admin_password',
					'Password',
					"<input id=\"admin_password\" name=\"admin_password\" type=\"password\" autocomplete=\"new-password\" minlength=\"{$min_length}\" required>",
					"At least {$min_length} characters. A short phrase is easier to remember and harder to guess."
				)}
				{$this->field(
					'admin_password_confirm',
					'Confirm password',
					"<input id=\"admin_password_confirm\" name=\"admin_password_confirm\" type=\"password\" autocomplete=\"new-password\" minlength=\"{$min_length}\" required>",
					'Type the same password again.'
				)}
			</div>
			HTML;

		return $this->page(
			'admin',
			'Create your administrator account',
			$this->form( $context, 'admin', $fields, 'Create account and finish', 'Creating account&hellip;' ),
			$context['notices']
		);
	}

	/**
	 * Final confirmation step, shown when nothing is missing.
	 *
	 * @param array{action_url: string, csrf: string, notices: array} $context Shared page context.
	 * @return array{title: string, html: string}
	 */
	public function finish( array $context ) : array {
		return $this->page(
			'finish',
			'Ready to go',
			$this->form(
				$context,
				'finish',
				'<p class="lead">Everything is in place. Finishing records the installation, closes this installer and opens the site to everyone.</p>',
				'Finish installation',
				'Finishing&hellip;'
			),
			$context['notices']
		);
	}

	/**
	 * Installation complete page.
	 *
	 * @param string $home_url Application URL.
	 * @return array{title: string, html: string}
	 */
	public function done( string $home_url ) : array {
		$app       = $this->e( \SMLISER_APP_NAME );
		$login_url = rtrim( $home_url, '/' ) . '/auth/';
		$body      = <<<HTML
			<p class="lead">{$app} is installed and open to visitors. Sign in with the administrator account you just created.</p>
			<p class="hint">The installer is closed and the setup token has been deleted.</p>
			<div class="actions">
				<a class="btn btn-accent" href="{$this->e( $home_url )}">Open {$app}</a>
				<a class="btn" href="{$this->e( $login_url )}">Sign in</a>
			</div>
			HTML;

		return $this->page( 'done', 'Installation complete', $body );
	}

	/**
	 * Page for visitors to a site whose installation has not started yet.
	 *
	 * @param string $start_url Installer URL.
	 * @return array{title: string, html: string}
	 */
	public function not_set_up( string $start_url ) : array {
		$app  = $this->e( \SMLISER_APP_NAME );
		$body = <<<HTML
			<p class="lead">{$app} has been uploaded to this server but has not been set up yet.</p>
			<p>If this is your site, start the setup. It takes a few minutes, and you will need your database details and access to your hosting files (File Manager or FTP).</p>
			<div class="actions"><a class="btn btn-accent" href="{$this->e( $start_url )}">Start setup</a></div>
			<p class="hint">Not the owner? This site is not ready yet; please check back later.</p>
			HTML;

		return $this->page( '', 'This site is not set up yet', $body );
	}

	/**
	 * Unexpected failure page.
	 *
	 * @param string $message Message to show.
	 * @return array{title: string, html: string}
	 */
	public function failure( string $message ) : array {
		return $this->page( '', 'Something went wrong', '<p class="lead">' . $this->e( $message ) . '</p>' );
	}

	/**
	 * The page shown when the server or the uploaded files are not ready for installation.
	 *
	 * @param string[] $problems Plain-language problems.
	 * @return array{title: string, html: string}
	 */
	public function not_ready( array $problems ) : array {
		$items = '';

		foreach ( $problems as $problem ) {
			$items .= '<li>' . $this->e( $problem ) . '</li>';
		}

		return $this->page(
			'',
			'This server is not ready yet',
			'<p class="lead">Before setting anything up, the installer checked this server and the uploaded files. These problems would stop '
				. $this->e( \SMLISER_APP_NAME ) . ' from working:</p>'
				. '<ul class="issues">' . $items . '</ul>'
				. '<p>Fix them, then reload this page.</p>'
		);
	}
	
	/**
	 * Wrap a page in the full HTML document.
	 *
	 * @param array{title: string, html: string} $page  The page.
	 * @param string                             $nonce CSP nonce for the inline script.
	 * @return string
	 */
	public function document( array $page, string $nonce ) : string {
		$app   = $this->e( \SMLISER_APP_NAME );
		$title = $this->e( $page['title'] );
		$nonce = $this->e( $nonce );

		return <<<HTML
			<!DOCTYPE html>
			<html lang="en">
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<meta name="color-scheme" content="light dark">
				<meta name="robots" content="noindex, nofollow">
				<title>{$title} · {$app} installer</title>
				<style>{$this->styles()}</style>
			</head>
			<body>
				<main class="card" id="installer">{$page['html']}</main>
				<script nonce="{$nonce}">{$this->script()}</script>
			</body>
			</html>
			HTML;
	}

	/*
	|-----------
	| Building
	|-----------
	*/

	/**
	 * Build a page: stepper, heading, notices and body.
	 *
	 * @param string $step    Current step key ("done" marks every step complete, "" hides the stepper).
	 * @param string $title   Page heading.
	 * @param string $body    Inner HTML.
	 * @param array  $notices List of {type, message, detail?}.
	 * @return array{title: string, html: string}
	 */
	protected function page( string $step, string $title, string $body, array $notices = array() ) : array {
		$alerts = '';
		foreach ( $notices as $notice ) {
			$alerts .= $this->notice(
				(string) ( $notice['type'] ?? 'warning' ),
				(string) ( $notice['message'] ?? '' ),
				isset( $notice['detail'] ) ? (string) $notice['detail'] : null
			);
		}

		$html = sprintf(
			'<p class="eyebrow">%1$s installer</p>%2$s<h1 tabindex="-1">%3$s</h1><div class="alerts" aria-live="assertive">%4$s</div>%5$s',
			$this->e( \SMLISER_APP_NAME ),
			$this->stepper( $step ),
			$this->e( $title ),
			$alerts,
			$body
		);

		return array(
			'title' => $title,
			'html'  => $html,
		);
	}

	/**
	 * Render one notice.
	 *
	 * @param string      $type    "success", "warning" or "error".
	 * @param string      $message Plain-language message.
	 * @param string|null $detail  Optional technical detail, shown collapsed.
	 * @return string
	 */
	protected function notice( string $type, string $message, ?string $detail = null ) : string {
		$type = in_array( $type, array( 'success', 'warning', 'error' ), true ) ? $type : 'warning';

		return sprintf(
			'<div class="notice notice-%1$s" role="%2$s"><p>%3$s</p>%4$s</div>',
			$type,
			'error' === $type ? 'alert' : 'status',
			$this->e( $message ),
			null === $detail || '' === $detail ? '' : '<details><summary>Technical details</summary><code>' . $this->e( $detail ) . '</code></details>'
		);
	}

	/**
	 * Render a labelled field with its description.
	 *
	 * The input HTML must carry the given id; the description is linked to it
	 * with aria-describedby.
	 *
	 * @param string $id       Input id.
	 * @param string $label    Label text.
	 * @param string $input    Input HTML.
	 * @param string $help     Description HTML (trusted markup).
	 * @param bool   $optional Whether to mark the field optional.
	 * @param string $class    Extra wrapper classes.
	 * @return string
	 */
	protected function field( string $id, string $label, string $input, string $help, bool $optional = false, string $class = '' ) : string {
		$help_id = $id . '-help';
		$input   = preg_replace( '/^<(input|select|textarea)\b/', '<$1 aria-describedby="' . $this->e( $help_id ) . '"', $input, 1 );

		return sprintf(
			'<div class="field %1$s"><label for="%2$s">%3$s%4$s</label>%5$s<p class="help" id="%6$s">%7$s</p></div>',
			$this->e( $class ),
			$this->e( $id ),
			$this->e( $label ),
			$optional ? ' <span class="opt">optional</span>' : '',
			$input,
			$this->e( $help_id ),
			$help
		);
	}

	/**
	 * Wrap step fields in the owner form, with the CSRF token, action and a leave button.
	 *
	 * @param array{action_url: string, csrf: string, notices: array} $context    Shared page context.
	 * @param string                                                  $action     Step action.
	 * @param string                                                  $fields     Inner HTML.
	 * @param string                                                  $submit     Submit button label.
	 * @param string                                                  $busy_label Button label while working (HTML entities allowed).
	 * @param bool                                                    $tasks      Whether the script runs the setup tasks one by one.
	 * @return string
	 */
	protected function form( array $context, string $action, string $fields, string $submit, string $busy_label, bool $tasks = false ) : string {
		$url   = $this->e( $context['action_url'] );
		$csrf  = $this->e( $context['csrf'] );
		$extra = $tasks ? ' data-tasks' : '';

		return <<<HTML
			<form method="post" action="{$url}" class="form" data-async{$extra} data-busy-label="{$busy_label}">
				<input type="hidden" name="_csrf" value="{$csrf}">
				<input type="hidden" name="action" value="{$this->e( $action )}">
				{$fields}
				<div class="actions"><button type="submit" class="btn btn-accent">{$this->e( $submit )}</button></div>
			</form>
			<form method="post" action="{$url}" class="leave" data-async data-busy-label="Leaving&hellip;">
				<input type="hidden" name="_csrf" value="{$csrf}">
				<input type="hidden" name="action" value="release">
				<button type="submit" class="link">Leave the installer</button>
				<span class="hint">Lets someone else continue with the setup token. Your progress is kept.</span>
			</form>
			HTML;
	}

	/**
	 * Render the step indicator.
	 *
	 * @param string $current Current step key.
	 * @return string
	 */
	protected function stepper( string $current ) : string {
		if ( '' === $current ) {
			return '';
		}

		$keys     = array_keys( static::STEPS );
		$position = 'done' === $current ? count( $keys ) : array_search( $current, $keys, true );
		$items    = '';

		foreach ( $keys as $index => $key ) {
			$state  = $index < $position ? 'complete' : ( $index === $position ? 'current' : 'upcoming' );
			$aria   = 'current' === $state ? ' aria-current="step"' : '';
			$items .= sprintf(
				'<li class="%1$s"%2$s><span class="dot">%3$d</span><span class="name">%4$s</span></li>',
				$state,
				$aria,
				$index + 1,
				$this->e( static::STEPS[ $key ] )
			);
		}

		return '<ol class="stepper">' . $items . '</ol>';
	}

	/**
	 * The installer script: submits forms with fetch() and runs the setup tasks.
	 *
	 * Protocol: every request sends "Accept: application/json". Page responses
	 * are {page: {title, html}}; setup task responses are {task: {status, detail}};
	 * other errors are the standard {error: {message}} body.
	 *
	 * @return string
	 */
	protected function script() : string {
		$app = json_encode( \SMLISER_APP_NAME, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );

		return <<<JS
			(() => {
				'use strict';
				const app = {$app};
				const main = document.getElementById('installer');

				// Note: form.action is shadowed by the hidden input named "action"; read the attribute.
				const request = async (url, init = {}) => {
					const res = await fetch(url, Object.assign({ credentials: 'same-origin', headers: { Accept: 'application/json' } }, init));
					let data = null;
					try { data = await res.json(); } catch (e) { /* not JSON */ }
					if (!data) { throw new Error('The server sent an unexpected response (HTTP ' + res.status + '). Reload the page to continue.'); }
					return data;
				};

				const render = (page) => {
					main.innerHTML = page.html;
					document.title = page.title + ' · ' + app + ' installer';
					window.scrollTo({ top: 0, behavior: 'smooth' });
					const heading = main.querySelector('h1');
					if (heading) { heading.focus({ preventScroll: true }); }
					syncDriver();
				};

				const alert = (message) => {
					const box = main.querySelector('.alerts');
					if (!box) { return; }
					box.innerHTML = '';
					const div = document.createElement('div');
					div.className = 'notice notice-error';
					div.setAttribute('role', 'alert');
					const p = document.createElement('p');
					p.textContent = message;
					div.appendChild(p);
					box.appendChild(div);
				};

				const handle = (data) => {
					if (data.page) { render(data.page); return true; }
					alert((data.error && data.error.message) || 'Something went wrong. Reload the page and try again.');
					return false;
				};

				const runTasks = async (form) => {
					const items = Array.from(form.querySelectorAll('[data-task]'));
					for (const item of items) {
						if (item.dataset.state === 'done') { continue; }
						item.dataset.state = 'running';
						const body = new FormData(form);
						body.set('task', item.dataset.task);
						const data = await request(form.getAttribute('action'), { method: 'POST', body });
						if (!data.task) { handle(data); return; }
						item.dataset.state = data.task.status;
						item.querySelector('.task-detail').textContent = data.task.detail || '';
						if (data.task.status === 'failed') { return; }
					}
					handle(await request(form.getAttribute('action')));
				};

				const busy = (form, on) => {
					const button = form.querySelector('[type=submit]');
					if (!button) { return; }
					if (on) {
						button.dataset.label = button.innerHTML;
						button.innerHTML = '<span class="spinner" aria-hidden="true"></span>' + (form.dataset.busyLabel || 'Working…');
					} else if (button.dataset.label) {
						button.innerHTML = button.dataset.label;
					}
					button.disabled = on;
					form.setAttribute('aria-busy', on ? 'true' : 'false');
				};

				document.addEventListener('submit', async (event) => {
					const form = event.target.closest('form[data-async]');
					if (!form || !window.fetch) { return; }
					event.preventDefault();
					if (form.dataset.working) { return; }
					form.dataset.working = '1';
					busy(form, true);
					try {
						if (form.hasAttribute('data-tasks')) {
							await runTasks(form);
						} else {
							handle(await request(form.getAttribute('action'), { method: 'POST', body: new FormData(form) }));
						}
					} catch (error) {
						alert(error.message || 'The connection to the server was lost. Check your internet connection and try again.');
					} finally {
						if (document.contains(form)) {
							delete form.dataset.working;
							busy(form, false);
						}
					}
				});

				const syncDriver = () => {
					const checked = main.querySelector('input[name=db_driver]:checked');
					const port = main.querySelector('#db_port');
					const charset = main.querySelector('#db_charset');
					if (checked && port) { port.placeholder = checked.dataset.port || ''; }
					if (checked && charset) {
						// A charset typed for one engine is usually invalid for another.
						if (charset.dataset.engine && charset.dataset.engine !== checked.value) { charset.value = ''; }
						charset.dataset.engine = checked.value;
						charset.placeholder = checked.dataset.charset || '';
					}
				};

				document.addEventListener('change', (event) => {
					if (event.target.name === 'db_driver') { syncDriver(); }
				});

				syncDriver();
			})();
			JS;
	}

	/**
	 * Page styles, using the same tokens as the HTTP error document.
	 *
	 * @return string
	 */
	protected function styles() : string {
		return <<<'CSS'
			:root {
				color-scheme: light dark;
				--bg: #f5f6f8; --dot: #dde1e6; --surface: #ffffff; --ink: #12151a;
				--muted: #5b6270; --border: #d8dce2; --accent: #0f766e; --accent-bg: #ecfeff;
				--on-accent: #ffffff; --error: #b91c1c; --error-bg: #fef2f2;
				--warning: #a16207; --warning-bg: #fefce8; --success: #15803d; --success-bg: #f0fdf4;
			}
			@media (prefers-color-scheme: dark) {
				:root {
					--bg: #0b0e14; --dot: #1c212b; --surface: #11151d; --ink: #eef1f5;
					--muted: #8b93a1; --border: #232935; --accent: #2dd4bf; --accent-bg: #0b2b2b;
					--on-accent: #04201d; --error: #f87171; --error-bg: #2a1212;
					--warning: #facc15; --warning-bg: #2a2408; --success: #4ade80; --success-bg: #0d2416;
				}
			}
			* { box-sizing: border-box; }
			html, body { margin: 0; min-height: 100%; }
			body {
				display: flex; justify-content: center; align-items: flex-start;
				min-height: 100dvh; padding: 48px 16px;
				background: radial-gradient(var(--dot) 1px, transparent 1px) 0 0 / 24px 24px, var(--bg);
				color: var(--ink); font: 15px/1.6 ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
				-webkit-font-smoothing: antialiased;
			}
			.card {
				width: 100%; max-width: 600px; padding: 32px;
				background: var(--surface); border: 1px solid var(--border); border-radius: 14px;
			}
			.eyebrow { margin: 0 0 20px; color: var(--muted); font-size: 13px; letter-spacing: .04em; text-transform: uppercase; }
			h1 { margin: 24px 0 12px; font-size: 22px; line-height: 1.3; outline: none; }
			p { margin: 0 0 14px; }
			.lead { color: var(--ink); }
			code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; }
			code { padding: 1px 5px; border-radius: 5px; background: var(--bg); overflow-wrap: anywhere; }
			pre { margin: 0 0 14px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); overflow-x: auto; }
			pre code { padding: 0; background: none; }
			.guide { margin: 0 0 14px; padding-left: 22px; }
			.guide li { margin-bottom: 6px; }
			.more { margin: 0 0 14px; }
			.more > summary { cursor: pointer; color: var(--accent); font-weight: 600; font-size: 14px; }
			.more[open] > summary { margin-bottom: 10px; }
			.stepper { display: flex; gap: 6px; margin: 0; padding: 0; list-style: none; }
			.stepper li { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; font-size: 12px; color: var(--muted); text-align: center; }
			.stepper .dot {
				display: grid; place-items: center; width: 26px; height: 26px; border-radius: 50%;
				border: 1.5px solid var(--border); font-weight: 600; font-size: 12px;
			}
			.stepper .current { color: var(--ink); font-weight: 600; }
			.stepper .current .dot { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
			.stepper .complete .dot { border-color: var(--accent); background: var(--accent); color: var(--on-accent); }
			.form { display: flex; flex-direction: column; gap: 16px; margin-top: 18px; }
			.field { display: flex; flex-direction: column; gap: 6px; min-width: 0; margin: 0; padding: 0; border: 0; }
			.field legend { padding: 0; margin-bottom: 6px; }
			label, legend { font-weight: 600; font-size: 14px; }
			.opt { color: var(--muted); font-weight: 400; font-size: 12px; }
			.help { margin: 0; color: var(--muted); font-size: 13px; }
			input[type=text], input[type=email], input[type=password] {
				width: 100%; padding: 9px 11px; border: 1px solid var(--border); border-radius: 8px;
				background: var(--bg); color: var(--ink); font: inherit;
			}
			input::placeholder { color: var(--muted); opacity: .7; }
			input:focus-visible, .btn:focus-visible, .link:focus-visible, summary:focus-visible, .choice:has(input:focus-visible) {
				outline: 2px solid var(--accent); outline-offset: 2px;
			}
			.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
			@media (max-width: 520px) { .grid { grid-template-columns: 1fr; } .card { padding: 22px; } }
			.choices { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; }
			.choice {
				display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; cursor: pointer;
				border: 1px solid var(--border); border-radius: 8px; font-weight: 400;
			}
			.choice span { display: flex; flex-direction: column; }
			.choice strong { font-size: 14px; }
			.choice small { color: var(--muted); font-size: 12px; line-height: 1.4; }
			.choice:has(input:checked) { border-color: var(--accent); background: var(--accent-bg); }
			.choice input { accent-color: var(--accent); margin: 3px 0 0; }
			form:has(input[name=db_driver][value=sqlite]:checked) .net-only { display: none; }
			form:not(:has(input[name=db_driver][value=sqlite]:checked)) .sqlite-only { display: none; }
			.hint { color: var(--muted); font-size: 13px; font-weight: 400; }
			.actions { display: flex; gap: 10px; margin-top: 4px; }
			.actions + .hint { margin-top: 14px; }
			.btn {
				display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px;
				border: 1px solid var(--border); border-radius: 8px; background: transparent; color: var(--ink);
				font: inherit; font-weight: 600; text-decoration: none; cursor: pointer;
			}
			.btn-accent { border-color: var(--accent); background: var(--accent); color: var(--on-accent); }
			.btn:disabled { opacity: .75; cursor: progress; }
			.spinner {
				width: 14px; height: 14px; border-radius: 50%;
				border: 2px solid currentColor; border-right-color: transparent;
				animation: spin .7s linear infinite;
			}
			@keyframes spin { to { transform: rotate(360deg); } }
			@media (prefers-reduced-motion: reduce) { .spinner { animation-duration: 2s; } }
			.leave { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px; margin-top: 22px; padding-top: 16px; border-top: 1px solid var(--border); }
			.link { padding: 0; border: 0; background: none; color: var(--muted); font: inherit; font-size: 13px; text-decoration: underline; cursor: pointer; }
			.issues { margin: 0; padding-left: 20px; color: var(--muted); font-size: 14px; }
			.tasks { display: flex; flex-direction: column; gap: 2px; margin: 0; padding: 0; list-style: none; border: 1px solid var(--border); border-radius: 10px; }
			.tasks li { display: flex; gap: 12px; align-items: flex-start; padding: 10px 14px; }
			.tasks li + li { border-top: 1px solid var(--border); }
			.task-body { display: flex; flex-direction: column; }
			.task-detail { color: var(--muted); font-size: 13px; }
			.task-detail:empty { display: none; }
			.mark { flex: none; display: grid; place-items: center; width: 20px; height: 20px; margin-top: 2px; border-radius: 50%; border: 1.5px solid var(--border); font-size: 12px; font-weight: 700; }
			[data-state=running] .mark { border-color: var(--accent); border-right-color: transparent; animation: spin .7s linear infinite; }
			[data-state=done] .mark { border-color: var(--success); background: var(--success); color: var(--surface); }
			[data-state=done] .mark::before { content: "\2713"; }
			[data-state=warning] .mark { border-color: var(--warning); color: var(--warning); }
			[data-state=warning] .mark::before { content: "!"; }
			[data-state=failed] .mark { border-color: var(--error); color: var(--error); }
			[data-state=failed] .mark::before { content: "\00d7"; }
			[data-state=failed] .task-detail { color: var(--error); }
			.alerts:empty { display: none; }
			.notice { margin: 0 0 12px; padding: 10px 12px; border-radius: 8px; border: 1px solid; font-size: 14px; overflow-wrap: anywhere; }
			.notice p { margin: 0; }
			.notice details { margin-top: 6px; font-size: 13px; }
			.notice summary { cursor: pointer; }
			.notice details code { display: block; margin-top: 6px; padding: 6px 8px; background: transparent; border: 1px solid; border-radius: 6px; }
			.notice-error { color: var(--error); background: var(--error-bg); }
			.notice-warning { color: var(--warning); background: var(--warning-bg); }
			.notice-success { color: var(--success); background: var(--success-bg); }
			CSS;
	}

	/**
	 * Escape for HTML text and attributes.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function e( string $value ) : string {
		return \escHtml( $value );
	}
}