<?php
/**
 * The admin background processing page template.
 *
 * What runs background work now (from the heartbeats), and the
 * configuration of each way to run it, written for this server.
 * background-setup.js switches modes, copies snippets and refreshes the
 * live status.
 *
 * @author Callistus Nwachukwu
 * @var array<string, array{label: string, badge: string, icon: string, description: string}> $modes
 * @var array{mode: ?string, cron: bool, worker: bool, url: bool, last: ?array} $detected
 * @var string $selected The mode shown first.
 * @var array<string, array{steps: array<int, array{title: string, content: ?string, note: ?string}>, notes: string[]}> $configs
 * @var array<int, array{source: string, label: string, alive: bool, age: ?int, at: ?string, summary: string}> $runners
 * @var array{tone: string, icon: string, title: string, message: string} $overview Status banner (BackgroundSetup::overview()).
 * @var SmartLicenseServer\Admin\ContentHandlers\ToolsPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

$menu_args = $page_handler->get_top_menu_args( $request );
$running   = $detected['mode'];

/*
|---------------
| OVERALL STATUS
|---------------
*/

[ 'tone' => $tone, 'icon' => $icon, 'title' => $title, 'message' => $message ] = $overview;

$chevron = '<svg class="smliser-diagnostics-panel-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9" /></svg>';
?>
<div class="smliser-admin-page" id="smliser-background-setup">
	<?php smliser_print_admin_content_header( $menu_args ); ?>

	<div class="smliser-health-overview smliser-health-tone--<?php echo escAttr( $tone ); ?>" id="smliser-background-overview">
		<span class="smliser-health-overview-icon"><i class="<?php echo escAttr( $icon ); ?>"></i></span>
		<div class="smliser-health-overview-text">
			<strong class="smliser-health-overview-title"><?php echo escHtml( $title ); ?></strong>
			<p class="smliser-health-overview-message"><?php echo escHtml( $message ); ?></p>
		</div>
	</div>

	<div class="smliser-diagnostics-grid" id="smliser-diagnostics-grid">

		<?php /* Live status. */ ?>
		<details class="smliser-diagnostics-panel smliser-health-panel smliser-health-tone--<?php echo escAttr( $tone ); ?>" id="smliser-background-live">
			<summary class="smliser-diagnostics-panel-summary">
				<span class="smliser-diagnostics-panel-summary-left">
					<span class="smliser-diagnostics-panel-icon"><i class="ti ti-activity-heartbeat"></i></span>
					<span class="smliser-diagnostics-panel-title">Live status</span>
				</span>
				<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
			</summary>
			<div class="smliser-diagnostics-panel-body">
				<ul class="smliser-health-checks" id="smliser-background-runners">
					<?php foreach ( $runners as $runner ) : ?>
						<?php $runner_tone = $runner['alive'] ? 'pass' : ( null === $runner['age'] ? 'info' : 'warning' ); ?>
						<li class="smliser-health-check smliser-health-tone--<?php echo escAttr( $runner_tone ); ?>" data-runner="<?php echo escAttr( $runner['source'] ); ?>">
							<span class="smliser-health-check-icon">
								<i class="<?php echo escAttr( $runner['alive'] ? 'ti ti-circle-check' : ( null === $runner['age'] ? 'ti ti-circle-dashed' : 'ti ti-alert-triangle' ) ); ?>"></i>
							</span>
							<div class="smliser-health-check-body">
								<div class="smliser-health-check-header">
									<span class="smliser-health-check-label"><?php echo escHtml( $runner['label'] ); ?></span>
								</div>
								<p class="smliser-health-check-message" data-runner-summary><?php echo escHtml( $runner['summary'] ); ?></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
				<div class="smliser-health-async-footer">
					<span class="smliser-health-async-time" id="smliser-background-checked">Each runner records its last run. This list refreshes every 30 seconds while the page is open.</span>
					<button type="button" class="smliser-btn smliser-btn-glass" id="smliser-background-refresh"><i class="ti ti-refresh"></i> Check again</button>
				</div>
			</div>
		</details>

		<?php /* Mode picker. */ ?>
		<details class="smliser-diagnostics-panel smliser-health-panel smliser-health-tone--info" open>
			<summary class="smliser-diagnostics-panel-summary">
				<span class="smliser-diagnostics-panel-summary-left">
					<span class="smliser-diagnostics-panel-icon"><i class="ti ti-adjustments"></i></span>
					<span class="smliser-diagnostics-panel-title">How should background work run?</span>
				</span>
				<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
			</summary>
			<div class="smliser-diagnostics-panel-body">
				<ul class="smliser-health-checks">
					<?php foreach ( $modes as $mode => $info ) : ?>
						<li class="smliser-health-check">
							<label class="smliser-health-check-body">
								<span class="smliser-health-check-header">
									<input type="radio" name="smliser_background_mode" value="<?php echo escAttr( $mode ); ?>" <?php echo $mode === $selected ? 'checked' : ''; ?>>
									<i class="<?php echo escAttr( $info['icon'] ); ?>"></i>
									<span class="smliser-health-check-label"><?php echo escHtml( $info['label'] ); ?></span>
									<span class="smliser-bg-badge"><?php echo escHtml( $info['badge'] ); ?></span>
									<span class="smliser-bg-badge smliser-bg-badge--running" data-running-badge="<?php echo escAttr( $mode ); ?>" <?php echo $mode === $running ? '' : 'hidden'; ?>>Running now</span>
								</span>
								<span class="smliser-health-check-message"><?php echo escHtml( $info['description'] ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</details>

		<?php /* Configuration for each mode; background-setup.js shows the chosen one. */ ?>
		<?php foreach ( $configs as $mode => $config ) : ?>
			<details
				class="smliser-diagnostics-panel smliser-health-panel smliser-health-tone--info"
				data-mode-config="<?php echo escAttr( $mode ); ?>"
				<?php echo $mode === $selected ? 'open' : 'hidden open'; ?>
			>
				<summary class="smliser-diagnostics-panel-summary">
					<span class="smliser-diagnostics-panel-summary-left">
						<span class="smliser-diagnostics-panel-icon"><i class="ti ti-terminal-2"></i></span>
						<span class="smliser-diagnostics-panel-title">Set up: <?php echo escHtml( $modes[ $mode ]['label'] ); ?></span>
					</span>
					<span class="smliser-diagnostics-panel-summary-right"><?php echo $chevron; // phpcs:ignore -- static markup. ?></span>
				</summary>
				<div class="smliser-diagnostics-panel-body">
					<ol class="smliser-health-checks smliser-bg-steps">
						<?php foreach ( $config['steps'] as $step ) : ?>
							<li class="smliser-health-check">
								<div class="smliser-health-check-body">
									<div class="smliser-health-check-header">
										<span class="smliser-health-check-label"><?php echo escHtml( $step['title'] ); ?></span>
									</div>
									<?php if ( null !== $step['content'] ) : ?>
										<div class="smliser-bg-snippet">
											<pre><code><?php echo escHtml( $step['content'] ); ?></code></pre>
											<button type="button" class="smliser-btn smliser-btn-glass" data-copy-snippet title="Copy"><i class="ti ti-copy"></i></button>
										</div>
									<?php endif; ?>
									<?php if ( null !== $step['note'] ) : ?>
										<p class="smliser-health-check-message"><?php echo escHtml( $step['note'] ); ?></p>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ol>

					<?php foreach ( $config['notes'] as $note ) : ?>
						<p class="smliser-health-check-recommendation">
							<i class="ti ti-bulb"></i>
							<span><?php echo escHtml( $note ); ?></span>
						</p>
					<?php endforeach; ?>

					<p class="smliser-health-check-recommendation">
						<i class="ti ti-activity-heartbeat"></i>
						<span>Once it is set up, its first run shows under Live status, usually within a few minutes.</span>
					</p>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
</div>