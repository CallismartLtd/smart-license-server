<?php
/**
 * Organizations: the ones the user belongs to, creating one, and an invitation.
 *
 * @author Callistus Nwachukwu
 * @var list<array{id: int, name: string, role: string, status: string, current: bool,
 *     can_switch: bool, context: string}> $organizations
 * @var bool $can_create Whether the user can create organizations.
 * @var array{token: string, organization: string, role: string, inviter: string, email: string,
 *     expires: string, for_you: bool}|null $invitation The invitation opened from an email.
 * @var bool $invitation_missing Whether the opened invitation is expired, revoked or unknown.
 * @var array{create: string, leave: string, switch: string, accept: string, decline: string} $actions
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<?php if ( $invitation_missing ) : ?>
	<div class="notice" data-state="error" role="alert">This invitation has expired or was revoked. Ask the organization for a new one.</div>
<?php endif; ?>

<?php if ( null !== $invitation ) : ?>
	<section class="record record-highlight" aria-labelledby="invitation-title">
		<div class="record-head">
			<h3 id="invitation-title"><?php echo escHtml( sprintf( 'Join %s', $invitation['organization'] ) ); ?></h3>
			<span class="badge"><?php echo escHtml( $invitation['role'] ); ?></span>
		</div>
		<p><?php echo escHtml( sprintf( '%s invited %s to join %s as %s. The invitation is valid until %s.', $invitation['inviter'], $invitation['email'], $invitation['organization'], $invitation['role'], $invitation['expires'] ) ); ?></p>

		<?php if ( $invitation['for_you'] ) : ?>
			<div class="record-actions">
				<form method="post" action="<?php echo escUrl( $actions['accept'] ); ?>" data-smliser-form>
					<input type="hidden" name="token" value="<?php echo escAttr( $invitation['token'] ); ?>">
					<div class="notice" data-smliser-form-message hidden></div>
					<button type="submit" class="btn btn-accent">Join <?php echo escHtml( $invitation['organization'] ); ?></button>
				</form>
				<form method="post" action="<?php echo escUrl( $actions['decline'] ); ?>" data-smliser-form data-confirm="<?php echo escAttr( sprintf( 'Decline the invitation to %s?', $invitation['organization'] ) ); ?>" data-confirm-label="Decline">
					<input type="hidden" name="token" value="<?php echo escAttr( $invitation['token'] ); ?>">
					<div class="notice" data-smliser-form-message hidden></div>
					<button type="submit" class="btn">Decline</button>
				</form>
			</div>
		<?php else : ?>
			<div class="notice" data-state="error" role="alert"><?php echo escHtml( sprintf( 'This invitation is for %s. Sign out, then sign in or sign up with that address to answer it.', $invitation['email'] ) ); ?></div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<p class="dash-lead">Organizations you belong to. Switch to one to work for it; what you can do there depends on your role.</p>

<?php if ( [] === $organizations ) : ?>
	<div class="cell">
		<h3>No organizations yet</h3>
		<p><?php echo escHtml( $can_create ? 'Create one below, or join one when you are invited.' : 'You join an organization when one of its administrators invites you.' ); ?></p>
	</div>
<?php else : ?>
	<ul class="records">
		<?php foreach ( $organizations as $organization ) : ?>
			<li class="record">
				<div class="record-head">
					<h3><?php echo escHtml( $organization['name'] ); ?></h3>
					<?php if ( $organization['current'] ) : ?>
						<span class="badge" data-tone="success">You act for it</span>
					<?php endif; ?>
				</div>

				<dl class="record-facts">
					<div>
						<dt>Your role</dt>
						<dd><?php echo escHtml( $organization['role'] ); ?></dd>
					</div>
					<div>
						<dt>Status</dt>
						<dd><?php echo escHtml( $organization['status'] ); ?></dd>
					</div>
				</dl>

				<div class="record-actions">
					<?php if ( $organization['can_switch'] && ! $organization['current'] ) : ?>
						<form method="post" action="<?php echo escUrl( $actions['switch'] ); ?>" data-smliser-form>
							<input type="hidden" name="context" value="<?php echo escAttr( $organization['context'] ); ?>">
							<div class="notice" data-smliser-form-message hidden></div>
							<button type="submit" class="btn btn-small btn-accent">Switch to it</button>
						</form>
					<?php endif; ?>
					<form method="post" action="<?php echo escUrl( $actions['leave'] ); ?>" data-smliser-form
						data-confirm="<?php echo escAttr( sprintf( 'Leave %s? You will need a new invitation to come back.', $organization['name'] ) ); ?>" data-confirm-label="Leave" data-confirm-danger>
						<input type="hidden" name="organization_id" value="<?php echo escAttr( (string) $organization['id'] ); ?>">
						<div class="notice" data-smliser-form-message hidden></div>
						<button type="submit" class="btn btn-small">Leave</button>
					</form>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php if ( $can_create ) : ?>
	<section class="dash-section" aria-labelledby="create-organization">
		<h2 id="create-organization">Create an organization</h2>
		<p class="muted">You will run it as its Organization Administrator, and can invite others to it.</p>
		<form class="dash-form" method="post" action="<?php echo escUrl( $actions['create'] ); ?>" data-smliser-form>
			<div class="notice" data-smliser-form-message hidden></div>
			<label class="field">
				<span class="field-label">Name</span>
				<input type="text" name="name" maxlength="100" required>
			</label>
			<button type="submit" class="btn btn-accent">Create organization</button>
		</form>
	</section>
<?php endif; ?>