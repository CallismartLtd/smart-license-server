<?php
/**
 * Web installer class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Installation
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Installation;

use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Boot\BootModeResolver;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;
use SmartLicenseServer\Environments\Application\Web\DowntimeExecutionHandler;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Execution handler for web requests while the application is in Installation mode.
 *
 * Bound by DowntimeBootstrapper in place of DowntimeExecutionHandler. Three
 * kinds of request reach it:
 *
 *  - The installer owner (holds the cookie of the active InstallerSession
 *    claim): sees the installer at the installer URL; any other URL redirects
 *    there.
 *
 * The installer URL is /install, /index.php/install or /?install (see the
 * URL_* constants), so it works before any rewrite rules exist.
 *  - Anyone at the installer URL while nobody owns the installer: sees the
 *    setup token form. The token is in storage/setup/setup-token.json, or
 *    printed by `smliser installer token`; submitting it claims the installer.
 *  - Everyone else: a 503 response. Before anyone has started installing, it
 *    says the site is not set up yet and links to the installer; once an
 *    installation is in progress (browser claim or CLI), it is the plain
 *    "setting things up" notice, exactly like the downtime handler. JSON
 *    requests always get the plain notice. Normal maintenance never
 *    reaches this class.
 *
 * The installer's step is never taken from the request: it is derived on
 * every request from the server's real state (the .env file, the database
 * connection, AppInstaller::installation_issues()). A POST only runs when its
 * action matches that step, so each step, including creating the
 * administrator, runs once even if a form is resubmitted.
 *
 * The application session is not used: SessionManager depends on
 * SMLISER_SECRET, which this installer writes. See InstallerSession.
 *
 * @package SmartLicenseServer\Environments\Application\Installation
 * @since 0.2.0
 */
final class WebInstaller implements ExecutionHandlerInterface {

	/**
	 * Installer path, relative to the application's base path.
	 */
	public const PATH = '/install';

	/**
	 * Query variable that opens the installer without URL rewriting.
	 */
	public const QUERY_VAR = 'install';

	/**
	 * Ways the installer URL can be written, from prettiest to most portable.
	 *
	 *  - path:      /install             Needs URL rewriting (.htaccess, nginx try_files).
	 *  - path_info: /index.php/install   Needs PATH_INFO, on by default in Apache.
	 *  - query:     /?install            Works wherever index.php is the directory index.
	 *
	 * A fresh upload has no .htaccess yet (the installer writes it), so every
	 * form is accepted and the installer keeps using the form it was opened with.
	 */
	public const URL_PATH      = 'path';
	public const URL_PATH_INFO = 'path_info';
	public const URL_QUERY     = 'query';

	/**
	 * Steps derived from the server state.
	 */
	public const STEP_DATABASE = 'database';
	public const STEP_SETUP    = 'setup';
	public const STEP_ADMIN    = 'admin';
	public const STEP_FINISH   = 'finish';

	/**
	 * The generated HTTP response.
	 *
	 * @var Response|null
	 */
	private ?Response $response = null;

	/**
	 * Class constructor.
	 *
	 * @param AppInstaller     $installer The application installer.
	 * @param InstallerSession $session   The single-owner installer session.
	 * @param SetupToken       $token     The setup token.
	 * @param BootModeResolver $resolver  The request's boot mode resolver.
	 * @param FileSystem       $fs        Filesystem API.
	 * @param InstallerPage    $page      The installer page renderer.
	 */
	public function __construct(
		private readonly AppInstaller $installer,
		private readonly InstallerSession $session,
		private readonly SetupToken $token,
		private readonly BootModeResolver $resolver,
		private readonly FileSystem $fs,
		private readonly InstallerPage $page = new InstallerPage()
	) {}

	/**
	 * {@inheritdoc}
	 */
	public function handle() : int {
		try {
			$response = $this->dispatch();
		} catch ( \Throwable $e ) {
			\smliser_log_error( '[WebInstaller] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );

			$response = $this->respond(
				$this->page->failure(
					'The installer hit an unexpected error. Details were written to the error log. '
					. 'Fix the cause, then reload this page; completed steps are not repeated.'
				),
				500
			);
		}

		if ( null !== $response ) {
			$this->response = $response;
			$response->send();
		}

		return 1;
	}

	/**
	 * Get the resolved response instance.
	 *
	 * @return Response|null
	 */
	public function getResponse() : ?Response {
		return $this->response;
	}

	/*
	|----------
	| Routing
	|----------
	*/

	/**
	 * Route the request to the owner, the claim form or the visitor response.
	 *
	 * @return Response|null Null when a delegated handler already sent its response.
	 */
	private function dispatch() : ?Response {
		$cookie = $_COOKIE[ InstallerSession::COOKIE ] ?? null;
		$cookie = is_string( $cookie ) ? $cookie : null;

		if ( $this->session->is_owner( $cookie ) ) {
			if ( ! $this->is_installer_path() ) {
				return Response::redirect( $this->installer_url(), 303 );
			}

			$this->session->touch();

			return $this->is_post() ? $this->owner_post() : $this->owner_get();
		}

		if ( ! $this->is_installer_path() ) {
			return $this->is_not_started() && ! $this->wants_json()
				? $this->not_set_up()
				: $this->visitor();
		}

		if ( $this->session->is_claimed() ) {
			return $this->visitor(
				sprintf(
					'An installation is already in progress in another browser. If it was abandoned, '
					. 'the installer can be claimed again after %d minutes of inactivity.',
					(int) ceil( $this->session->idle_timeout() / 60 )
				)
			);
		}

		// A cookie that no longer matches belongs to a stale or released claim.
		$expired = null !== $cookie;

		if ( $expired ) {
			$this->forget_cookie();
		}

		return $this->is_post() ? $this->claim() : $this->token_form( $expired ? 'Your installer session expired. Enter the setup token to continue.' : null );
	}

	/**
	 * Whether nobody has started installing: no installation flag and no active claim.
	 *
	 * @return bool
	 */
	private function is_not_started() : bool {
		return ! $this->resolver->installation_in_progress() && ! $this->session->is_claimed();
	}

	/**
	 * Tell visitors the site is not set up yet, with a way for the owner to start.
	 *
	 * Sent as 503 so search engines do not index it. Links to the query form
	 * of the installer URL, which works before any rewrite rules exist.
	 *
	 * @return Response
	 */
	private function not_set_up() : Response {
		$response = $this->respond(
			$this->page->not_set_up( $this->base_path() . '/?' . self::QUERY_VAR ),
			503
		);

		$response->set_header( 'Retry-After', (string) $this->resolver->retry_after() );

		return $response;
	}

	/**
	 * Send the 503 visitors see during an installation.
	 *
	 * @param string|null $message Optional message; the resolver's installation message by default.
	 * @return null The downtime handler sends its own response.
	 */
	private function visitor( ?string $message = null ) : ?Response {
		$handler = new DowntimeExecutionHandler(
			$message ?? $this->resolver->message(),
			$this->resolver->retry_after()
		);

		$handler->handle();
		$this->response = $handler->getResponse();

		return null;
	}

	/**
	 * Continue after a successful action.
	 *
	 * Plain form posts are redirected (Post/Redirect/Get); fetch() requests
	 * receive the current step's page directly.
	 *
	 * @return Response
	 */
	private function next() : Response {
		if ( $this->wants_json() ) {
			return $this->owner_get();
		}

		return Response::redirect( $this->installer_url(), 303 );
	}

	/*
	|-----------
	| Claiming
	|-----------
	*/

	/**
	 * Render the setup token form.
	 *
	 * @param string|null $error  Error or notice to show.
	 * @param int         $status HTTP status.
	 * @return Response
	 */
	private function token_form( ?string $error = null, int $status = 200 ) : Response {
		// Creates the token on first view, so the operator has something to read.
		$this->token->get();

		return $this->respond(
			$this->page->token(
				$this->installer_url(),
				$this->display_path( $this->token->path() ),
				$error
			),
			$status
		);
	}

	/**
	 * Claim the installer with the submitted setup token.
	 *
	 * @return Response|null
	 */
	private function claim() : ?Response {
		if ( ! $this->is_same_origin() ) {
			return $this->token_form( 'The request did not come from this site. Reload the page and try again.', 403 );
		}

		$input = $_POST['setup_token'] ?? '';

		if ( ! is_string( $input ) || ! $this->token->verify( $input ) ) {
			// Slows down guessing; the token itself is 128 bits.
			usleep( 500000 );
			return $this->token_form(
				'That setup token is not right. Copy it again from the file (only the letters and numbers between the quotes) and try again.',
				403
			);
		}

		try {
			$cookie = $this->session->claim();
		} catch ( \LogicException ) {
			return $this->visitor( 'Another browser claimed the installer a moment ago.' );
		}

		$this->installer->begin_installation();
		$this->set_cookie( $cookie );

		return $this->next();
	}

	/*
	|--------
	| Owner
	|--------
	*/

	/**
	 * Render the owner's current step.
	 *
	 * @return Response
	 */
	private function owner_get() : Response {
		return $this->render_step( $this->assess() );
	}

	/**
	 * Handle an owner form submission.
	 *
	 * @return Response
	 */
	private function owner_post() : Response {
		if ( ! $this->is_same_origin() || ! $this->session->verify_csrf( $_POST['_csrf'] ?? null ) ) {
			$this->session->flash( 'error', 'The form expired. Please try again.' );
			return $this->next();
		}

		$action = is_string( $_POST['action'] ?? null ) ? $_POST['action'] : '';

		if ( 'release' === $action ) {
			$this->session->release();
			$this->forget_cookie();

			return $this->wants_json()
				? $this->token_form()
				: Response::redirect( $this->installer_url(), 303 );
		}

		$lock = $this->lock();

		try {
			$assessment = $this->assess();

			// A resubmitted or out-of-date form: show the current step instead.
			if ( $action !== $assessment['step'] && ! $this->is_late_setup_task( $action, $assessment['step'] ) ) {
				return $this->next();
			}

			return match ( $action ) {
				self::STEP_DATABASE => $this->save_database( $assessment ),
				self::STEP_SETUP    => $this->run_setup(),
				self::STEP_ADMIN    => $this->save_admin( $assessment ),
				self::STEP_FINISH   => $this->finish(),
			};
		} finally {
			$this->unlock( $lock );
		}
	}

	/**
	 * Whether this is a setup task the script sends after setup already met the requirements.
	 *
	 * The step moves on to the administrator as soon as the tables and roles
	 * exist, while the script still has tasks to run (e.g. .htaccess). Those
	 * tasks are idempotent, so they are allowed until the administrator exists.
	 *
	 * @param string $action The submitted action.
	 * @param string $step   The current step.
	 * @return bool
	 */
	private function is_late_setup_task( string $action, string $step ) : bool {
		return self::STEP_SETUP === $action
			&& self::STEP_ADMIN === $step
			&& $this->wants_json()
			&& isset( InstallerPage::SETUP_TASKS[ (string) ( $_POST['task'] ?? '' ) ] );
	}

	/**
	 * Derive the current step from the server's real state.
	 *
	 * @return array{step: string, issues: string[], notices: array, config: array<string, mixed>}
	 */
	private function assess() : array {
		$result = array(
			'step'    => self::STEP_DATABASE,
			'issues'  => array(),
			'notices' => array(),
			'config'  => array(),
		);

		try {
			$config = $this->installer->read_database_config();
		} catch ( DatabaseException ) {
			// Not configured yet: the normal starting point.
			return $result;
		} catch ( \InvalidArgumentException | \RuntimeException $e ) {
			$result['notices'][] = $this->error( 'The database settings in the .env file are not valid. Enter them again below.', $e->getMessage() );
			return $result;
		}

		$result['config'] = array(
			'db_driver' => (string) $config->driver,
			'db_host'   => (string) ( $config->host ?? '' ),
			'db_port'   => null === $config->port ? '' : (string) $config->port,
			'db_name'   => (string) $config->dbname,
			'db_user'   => (string) ( $config->username ?? '' ),
			'db_prefix' => (string) ( $config->prefix ?? '' ),
			'db_charset' => (string) ( $config->charset ?? '' ),
			'db_path'   => (string) ( $config->path ?? '' ),
		);

		try {
			if ( ! $this->installer->has_database_connection() ) {
				$this->installer->use_connection( $this->installer->test_db_connection( $config ) );
			}

			$issues = $this->installer->installation_issues();
		} catch ( DatabaseException $e ) {
			$result['notices'][] = $this->error(
				'The database saved in the .env file could not be reached. ' . DatabaseSettings::explain_error( $e->getMessage(), (string) $config->driver ),
				$e->getMessage()
			);
			return $result;
		}

		$result['issues'] = $issues;

		if ( array() === $issues ) {
			$result['step'] = self::STEP_FINISH;
		} elseif ( array( 'No user account exists.' ) === $issues ) {
			$result['step'] = self::STEP_ADMIN;
		} else {
			$result['step'] = self::STEP_SETUP;
		}

		return $result;
	}

	/**
	 * Render a step page.
	 *
	 * @param array{step: string, issues: string[], notices: array, config: array<string, mixed>} $assessment Current state.
	 * @param array<string, string> $values  Submitted values to keep in the form.
	 * @param array                 $notices Notices from the submission ({type, message, detail?}).
	 * @param int                   $status  HTTP status.
	 * @return Response
	 */
	private function render_step( array $assessment, array $values = array(), array $notices = array(), int $status = 200 ) : Response {
		$context = array(
			'action_url' => $this->installer_url(),
			'csrf'       => $this->session->csrf(),
			'notices'    => array_merge( $this->session->pull_flash(), $assessment['notices'], $notices ),
		);

		$page = match ( $assessment['step'] ) {
			self::STEP_DATABASE => $this->page->database(
				$context,
				$values + $assessment['config'] + array( 'db_driver' => 'mysql' ),
				DatabaseSettings::DRIVERS,
				DatabaseSettings::default_sqlite_dir()
			),
			self::STEP_SETUP    => $this->page->setup(
				$context,
				$assessment['issues'],
				$values['app_url'] ?? $this->installer->read_app_url() ?? $this->detect_app_url()
			),
			self::STEP_ADMIN    => $this->page->admin( $context, $values, AppInstaller::MIN_ADMIN_PASSWORD_LENGTH ),
			default             => $this->page->finish( $context ),
		};

		return $this->respond( $page, $status );
	}

	/**
	 * Test, then save, the submitted database settings.
	 *
	 * Empty server address, port and SQLite folder fall back to their
	 * standard values (localhost, the engine's default port, the storage folder).
	 *
	 * @param array{step: string, issues: string[], notices: array, config: array<string, mixed>} $assessment Current state.
	 * @return Response
	 */
	private function save_database( array $assessment ) : Response {
		$fields = array(
			'db_driver'         => 'driver',
			'db_host'           => 'host',
			'db_port'           => 'port',
			'db_name'           => 'dbname',
			'db_user'           => 'username',
			'db_password'       => 'password',
			'db_prefix'         => 'prefix',
			'db_charset'        => 'charset',
			'db_path'           => 'path',
			'db_encryption_key' => 'encryption_key',
		);

		$input  = array();
		$values = array();

		foreach ( $fields as $name => $field ) {
			$value = $_POST[ $name ] ?? '';
			$value = is_string( $value ) ? $value : '';

			// Secrets are used exactly as typed and never sent back to the browser.
			if ( 'db_password' !== $name && 'db_encryption_key' !== $name ) {
				$value           = trim( $value );
				$values[ $name ] = $value;
			}

			$input[ $field ] = $value;
		}

		$assessment['notices'] = array();
		$retry                 = fn ( string $message, ?string $detail = null ) : Response => $this->render_step(
			$assessment,
			$values,
			array( $this->error( $message, $detail ) ),
			422
		);

		try {
			$config = DatabaseSettings::normalize( $input );
		} catch ( \InvalidArgumentException $e ) {
			return $retry( $e->getMessage(), $e->getPrevious()?->getMessage() );
		}

		$driver = (string) $config->driver;

		try {
			$adapter = $this->installer->test_db_connection( $config );
		} catch ( DatabaseException $e ) {
			return $retry( 'Could not connect to the database. ' . DatabaseSettings::explain_error( $e->getMessage(), $driver ), $e->getMessage() );
		}

		try {
			$this->installer->make_dot_env_file();
			$this->installer->write_database_config( $config );
			$this->installer->generate_app_secrets();
		} catch ( \RuntimeException $e ) {
			return $retry(
				'The connection works, but the settings could not be saved to the .env file. Make sure the application folder is writable by the web server.',
				$e->getMessage()
			);
		}

		$this->installer->use_connection( $adapter );
		$this->session->flash( 'success', 'Connected. Your database settings were saved.' );

		return $this->next();
	}

	/**
	 * Run the setup: all tasks for a plain form post, or the one task the script asks for.
	 *
	 * @return Response
	 */
	private function run_setup() : Response {
		$task = is_string( $_POST['task'] ?? null ) ? $_POST['task'] : '';

		if ( $this->wants_json() && isset( InstallerPage::SETUP_TASKS[ $task ] ) ) {
			return Response::json(
				array( 'task' => $this->run_task( $task ) ),
				200,
				array( 'Cache-Control' => 'no-store' )
			);
		}

		$failed = false;

		foreach ( array_keys( InstallerPage::SETUP_TASKS ) as $name ) {
			$result = $this->run_task( $name );

			if ( 'done' !== $result['status'] ) {
				$this->session->flash( 'failed' === $result['status'] ? 'error' : 'warning', InstallerPage::SETUP_TASKS[ $name ] . ': ' . $result['detail'] );
			}

			if ( 'failed' === $result['status'] ) {
				$failed = true;
				break;
			}
		}

		if ( ! $failed ) {
			$this->session->flash( 'success', 'Folders, database tables and default roles are in place.' );
		}

		return $this->next();
	}

	/**
	 * Run one setup task.
	 *
	 * @param string $task Task key from InstallerPage::SETUP_TASKS.
	 * @return array{status: string, detail: string} Status is "done", "warning" or "failed".
	 */
	private function run_task( string $task ) : array {
		$created  = 0;
		$existing = 0;
		$failures = array();

		$collect = static function ( string ...$args ) use ( &$created, &$existing, &$failures ) : void {
			$message = (string) end( $args );

			if ( in_array( $message, array( 'Exists', 'Role Exists' ), true ) ) {
				++$existing;
			} elseif ( in_array( $message, array( 'Created', '✔ Installed' ), true ) ) {
				++$created;
			} else {
				$failures[] = $args[0] . ': ' . $message;
			}
		};

		$summary = static function ( string $noun ) use ( &$created, &$existing, &$failures ) : array {
			if ( array() !== $failures ) {
				return array(
					'status' => 'failed',
					'detail' => implode( ' ', $failures ),
				);
			}

			$parts = array();
			if ( $created > 0 ) {
				$parts[] = sprintf( '%d %s created', $created, $noun );
			}
			if ( $existing > 0 ) {
				$parts[] = sprintf( '%d already existed', $existing );
			}

			return array(
				'status' => 'done',
				'detail' => implode( ', ', $parts ),
			);
		};

		try {
			switch ( $task ) {
				case 'site_url':
					$input = $_POST['app_url'] ?? '';
					$input = is_string( $input ) && '' !== trim( $input ) ? $input : $this->detect_app_url();

					try {
						return array(
							'status' => 'done',
							'detail' => $this->installer->write_app_url( $input ),
						);
					} catch ( \InvalidArgumentException $e ) {
						return array(
							'status' => 'failed',
							'detail' => $e->getMessage() . ' Correct the site address above and run setup again.',
						);
					} catch ( \RuntimeException $e ) {
						return array(
							'status' => 'failed',
							'detail' => 'The site address could not be saved to the .env file. Make sure the application folder is writable by the web server. ' . $e->getMessage(),
						);
					}

				case 'directories':
					$this->installer->create_required_directories( $collect, $collect );
					$result = $summary( 'folders' );

					if ( 'failed' === $result['status'] ) {
						$result['detail'] = 'Some folders could not be created. Make sure the application folder is writable by the web server. ' . $result['detail'];
					}

					return $result;

				case 'assets':
					try {
						$result = $this->installer->link_public_assets();
					} catch ( \RuntimeException $e ) {
						return array(
							'status' => 'failed',
							'detail' => $e->getMessage(),
						);
					}

					return match ( $result ) {
						AppInstaller::ASSETS_LINKED    => array( 'status' => 'done', 'detail' => 'Linked public/assets to system/assets.' ),
						AppInstaller::ASSETS_COPIED    => array( 'status' => 'done', 'detail' => 'Your server does not allow links, so the assets were copied. Run setup again after each update.' ),
						AppInstaller::ASSETS_UNCHANGED => array( 'status' => 'done', 'detail' => 'Already linked.' ),
						default                        => array( 'status' => 'warning', 'detail' => 'public/assets already exists and was not created by the installer, so it was left as it is. Remove or rename it and run setup again to use the bundled assets.' ),
					};

				case 'tables':
					$this->installer->create_tables( $collect, $collect );
					return $summary( 'tables' );

				case 'roles':
					$this->installer->install_default_roles( $collect, $collect );
					return $summary( 'roles' );

				case 'htaccess':
					try {
						$this->installer->make_htaccess_file();
					} catch ( \RuntimeException $e ) {
						return array(
							'status' => 'warning',
							'detail' => 'Skipped: ' . $e->getMessage() . ' Not needed unless your server is Apache; otherwise point your web server at public/index.php.',
						);
					}

					return array(
						'status' => 'done',
						'detail' => '',
					);
			}
		} catch ( DatabaseException $e ) {
			return array(
				'status' => 'failed',
				'detail' => $e->getMessage(),
			);
		}

		return array(
			'status' => 'failed',
			'detail' => 'Unknown task.',
		);
	}

	/**
	 * Create the administrator account, then finish when nothing else is missing.
	 *
	 * @param array{step: string, issues: string[], notices: array, config: array<string, mixed>} $assessment Current state.
	 * @return Response
	 */
	private function save_admin( array $assessment ) : Response {
		$name     = trim( is_string( $_POST['admin_name'] ?? null ) ? $_POST['admin_name'] : '' );
		$email    = trim( is_string( $_POST['admin_email'] ?? null ) ? $_POST['admin_email'] : '' );
		$password = is_string( $_POST['admin_password'] ?? null ) ? $_POST['admin_password'] : '';
		$confirm  = is_string( $_POST['admin_password_confirm'] ?? null ) ? $_POST['admin_password_confirm'] : '';
		$values   = array(
			'admin_name'  => $name,
			'admin_email' => $email,
		);
		$messages = $this->installer->validate_admin( $name, $email, $password );

		if ( ! isset( $messages['password'] ) && ! hash_equals( $password, $confirm ) ) {
			$messages['password'] = 'The two passwords do not match. Type them again.';
		}

		$errors = array_map( fn ( string $message ) : array => $this->error( $message ), array_values( $messages ) );

		if ( array() !== $errors ) {
			return $this->render_step( $assessment, $values, $errors, 422 );
		}

		try {
			$this->installer->create_admin( $name, $email, $password );
		} catch ( DatabaseException | \InvalidArgumentException $e ) {
			return $this->render_step( $assessment, $values, array( $this->error( 'The account could not be created.', $e->getMessage() ) ), 422 );
		}

		if ( self::STEP_FINISH === $this->assess()['step'] ) {
			return $this->finish();
		}

		$this->session->flash( 'success', 'Administrator account created.' );

		return $this->next();
	}

	/**
	 * Record the installation as complete and end the installer session.
	 *
	 * @return Response
	 */
	private function finish() : Response {
		$this->installer->mark_installed();
		$this->token->delete();
		$this->session->release();
		$this->forget_cookie();
		$this->resolver->refresh();

		// The lock is still held by owner_post(); deleting it is safe and leaves no residue.
		$this->fs->delete( $this->lock_file() );
		$this->fs->rmdir( dirname( $this->lock_file() ) );

		return $this->respond( $this->page->done( $this->base_path() . '/' ) );
	}

	/**
	 * Build an error notice.
	 *
	 * @param string      $message Plain-language message.
	 * @param string|null $detail  Technical detail, shown collapsed.
	 * @return array{type: string, message: string, detail: ?string}
	 */
	private function error( string $message, ?string $detail = null ) : array {
		return array(
			'type'    => 'error',
			'message' => $message,
			'detail'  => $detail,
		);
	}

	/*
	|----------
	| Helpers
	|----------
	*/

	/**
	 * Send a page: as a full HTML document, or as JSON for the installer's fetch() requests.
	 *
	 * @param array{title: string, html: string} $page   The page.
	 * @param int                                $status HTTP status.
	 * @return Response
	 */
	private function respond( array $page, int $status = 200 ) : Response {
		if ( $this->wants_json() ) {
			return Response::json(
				array( 'page' => $page ),
				$status,
				array(
					'Cache-Control'          => 'no-store',
					'X-Content-Type-Options' => 'nosniff',
				)
			);
		}

		$nonce = base64_encode( random_bytes( 16 ) );

		return Response::make(
			$this->page->document( $page, $nonce ),
			$status,
			array(
				'Content-Type'            => 'text/html; charset=utf-8',
				'Cache-Control'           => 'no-store, no-cache, must-revalidate',
				'Content-Security-Policy' => "default-src 'none'; script-src 'nonce-{$nonce}'; connect-src 'self'; style-src 'unsafe-inline'; img-src data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
				'X-Frame-Options'         => 'DENY',
				'X-Content-Type-Options'  => 'nosniff',
				'Referrer-Policy'         => 'same-origin',
				'X-Robots-Tag'            => 'noindex, nofollow',
			)
		);
	}

	/**
	 * Whether the request comes from the installer's script (asks for JSON).
	 *
	 * @return bool
	 */
	private function wants_json() : bool {
		return false !== stripos( (string) ( $_SERVER['HTTP_ACCEPT'] ?? '' ), 'application/json' );
	}

	/**
	 * Whether the request is a POST.
	 *
	 * @return bool
	 */
	private function is_post() : bool {
		return 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
	}

	/**
	 * Whether a POST came from this site, judged by the Origin header when sent.
	 *
	 * @return bool
	 */
	private function is_same_origin() : bool {
		$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

		if ( ! is_string( $origin ) || '' === $origin ) {
			return true;
		}

		$host = parse_url( $origin, PHP_URL_HOST );
		$port = parse_url( $origin, PHP_URL_PORT );

		$origin_host = strtolower( (string) $host . ( null === $port ? '' : ':' . $port ) );
		$server_host = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) );

		return '' !== $origin_host && ( $origin_host === $server_host || (string) $host === $server_host );
	}

	/**
	 * The application's base path, without a trailing slash ("" at the web root).
	 *
	 * @return string
	 */
	private function base_path() : string {
		$script = str_replace( '\\', '/', (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) );

		// Only a front controller script reveals the base path (not a rewritten request path).
		if ( ! str_ends_with( strtolower( $script ), '.php' ) ) {
			return '';
		}

		$base = rtrim( str_replace( '\\', '/', dirname( $script ) ), '/.' );

		return '' === $base ? '' : '/' . ltrim( $base, '/' );
	}

	/**
	 * The installer's URL, in the form the current request used.
	 *
	 * Requests that are not for the installer get the query form, which
	 * works without URL rewriting or PATH_INFO.
	 *
	 * @return string
	 */
	private function installer_url() : string {
		return match ( $this->installer_url_form() ?? self::URL_QUERY ) {
			self::URL_PATH      => $this->base_path() . self::PATH,
			self::URL_PATH_INFO => $this->script_path() . self::PATH,
			default             => $this->base_path() . '/?' . self::QUERY_VAR,
		};
	}

	/**
	 * Which installer URL form the request uses, if any.
	 *
	 * @return string|null One of the URL_* constants, or null when the request is not for the installer.
	 */
	private function installer_url_form() : ?string {
		$path = parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
		$path = rtrim( rawurldecode( is_string( $path ) ? $path : '/' ), '/' );
		$base = $this->base_path();

		return match ( true ) {
			$base . self::PATH === $path                => self::URL_PATH,
			$this->script_path() . self::PATH === $path => self::URL_PATH_INFO,
			array_key_exists( self::QUERY_VAR, $_GET ) && in_array( $path, array( $base, $this->script_path() ), true )
				=> self::URL_QUERY,
			default                                     => null,
		};
	}

	/**
	 * URL path of the front controller script, e.g. "/index.php" or "/app/index.php".
	 *
	 * @return string
	 */
	private function script_path() : string {
		$script = str_replace( '\\', '/', (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) );

		return str_ends_with( strtolower( $script ), '.php' )
			? '/' . ltrim( $script, '/' )
			: $this->base_path() . '/index.php';
	}

	/**
	 * The site address as seen by this request: scheme, host and base path.
	 *
	 * @return string
	 */
	private function detect_app_url() : string {
		$host = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost' ) );

		return ( $this->is_https() ? 'https' : 'http' ) . '://' . $host . $this->base_path();
	}

	/**
	 * Whether the request is for the installer, in any of its URL forms.
	 *
	 * @return bool
	 */
	private function is_installer_path() : bool {
		return null !== $this->installer_url_form();
	}

	/**
	 * Whether the request arrived over HTTPS.
	 *
	 * @return bool
	 */
	private function is_https() : bool {
		$https = strtolower( (string) ( $_SERVER['HTTPS'] ?? '' ) );

		return ( '' !== $https && 'off' !== $https )
			|| '443' === (string) ( $_SERVER['SERVER_PORT'] ?? '' )
			|| 'https' === strtolower( (string) ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) );
	}

	/**
	 * Give the owner the installer cookie.
	 *
	 * @param string $value Cookie value.
	 * @return void
	 */
	private function set_cookie( string $value ) : void {
		setcookie(
			InstallerSession::COOKIE,
			$value,
			array(
				'expires'  => 0,
				'path'     => $this->base_path() . '/',
				'secure'   => $this->is_https(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
	}

	/**
	 * Expire the installer cookie in the browser.
	 *
	 * @return void
	 */
	private function forget_cookie() : void {
		setcookie(
			InstallerSession::COOKIE,
			'',
			array(
				'expires'  => 1,
				'path'     => $this->base_path() . '/',
				'secure'   => $this->is_https(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
	}

	/**
	 * Show a path relative to the application root when it lies inside it.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	private function display_path( string $path ) : string {
		$root = rtrim( str_replace( '\\', '/', \SMLISER_ROOT ), '/' ) . '/';
		$path = str_replace( '\\', '/', $path );

		return str_starts_with( $path, $root ) ? substr( $path, strlen( $root ) ) : $path;
	}

	/**
	 * Absolute path to the owner-action lock file.
	 *
	 * @return string
	 */
	private function lock_file() : string {
		return rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/setup/installer.lock';
	}

	/**
	 * Serialize owner actions, so a double-submitted form cannot run a step twice.
	 *
	 * Uses a native advisory lock: the FileSystem API has no locking primitive.
	 * Best effort; where the lock cannot be opened, the action runs unlocked.
	 *
	 * @return resource|null
	 */
	private function lock() {
		$handle = @fopen( $this->lock_file(), 'c' );

		if ( false === $handle ) {
			return null;
		}

		flock( $handle, LOCK_EX );

		return $handle;
	}

	/**
	 * Release the lock taken by lock().
	 *
	 * @param resource|null $handle Lock handle.
	 * @return void
	 */
	private function unlock( $handle ) : void {
		if ( is_resource( $handle ) ) {
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
	}
}