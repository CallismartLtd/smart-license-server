<?php
/**
 * Organization: the organization the user acts for, its members and invitations.
 *
 * @author Callistus Nwachukwu
 * @var array{name: string, slug: string, created: string} $organization
 * @var list<array{id: int, name: string, email: string, role: string, role_label: string, joined: string, you: bool}> $members
 * @var array<string, string> $roles Assignable roles, slug => label.
 * @var array{update: bool, delete: bool, update_members: bool, remove_members: bool, invite: bool} $can
 * @var bool $invitations_available Whether invitations can be kept on this site.
 * @var string $invitations_unavailable Why not, when they cannot.
 * @var list<array{id: string, email: string, role: string, invited: string, expires: string}> $invitations
 * @var array<string, string> $actions
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<p class="dash-lead"><?php echo escHtml( sprintf( 'Created %s. %d member(s).', $organization['created'], count( $members ) ) ); ?></p>

<section class="dash-section" aria-labelledby="members-title">
	<h2 id="members-title">Members</h2>
	<ul class="records">
		<?php foreach ( $members as $member ) : ?>
			<li class="record record-compact">
				<div class="record-head">
					<div>
						<h3><?php echo escHtml( $member['you'] ? sprintf( '%s (you)', $member['name'] ) : $member['name'] ); ?></h3>
						<span class="muted"><?php echo escHtml( $member['email'] ); ?></span>
					</div>
					<span class="badge"<?php echo 'organization_admin' === $member['role'] ? ' data-tone="success"' : ''; ?>><?php echo escHtml( $member['role_label'] ); ?></span>
				</div>

				<?php if ( ! $member['you'] && ( $can['update_members'] || $can['remove_members'] ) ) : ?>
					<div class="record-actions">
						<?php if ( $can['update_members'] ) : ?>
							<form class="inline-form" method="post" action="<?php echo escUrl( $actions['member_role'] ); ?>" data-smliser-form>
								<input type="hidden" name="user_id" value="<?php echo escAttr( (string) $member['id'] ); ?>">
								<label>
									<span class="screen-reader-text"><?php echo escHtml( sprintf( 'Role of %s', $member['name'] ) ); ?></span>
									<select name="role">
										<?php foreach ( $roles as $slug => $label ) : ?>
											<option value="<?php echo escAttr( $slug ); ?>"<?php echo $slug === $member['role'] ? ' selected' : ''; ?>><?php echo escHtml( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<button type="submit" class="btn btn-small">Change role</button>
								<div class="notice" data-smliser-form-message hidden></div>
							</form>
						<?php endif; ?>
						<?php if ( $can['remove_members'] ) : ?>
							<form method="post" action="<?php echo escUrl( $actions['member_remove'] ); ?>" data-smliser-form
								data-confirm="<?php echo escAttr( sprintf( 'Remove %s from %s?', $member['name'], $organization['name'] ) ); ?>" data-confirm-label="Remove" data-confirm-danger>
								<input type="hidden" name="user_id" value="<?php echo escAttr( (string) $member['id'] ); ?>">
								<div class="notice" data-smliser-form-message hidden></div>
								<button type="submit" class="btn btn-small">Remove</button>
							</form>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>

<?php if ( $can['invite'] ) : ?>
	<section class="dash-section" aria-labelledby="invite-title">
		<h2 id="invite-title">Invite someone</h2>

		<?php if ( ! $invitations_available ) : ?>
			<div class="notice" data-state="error" role="status"><?php echo escHtml( $invitations_unavailable ); ?></div>
		<?php else : ?>
			<p class="muted">They get an email with a link to join, valid for 48 hours. Someone without an account can create one from the link.</p>
			<form class="dash-form" method="post" action="<?php echo escUrl( $actions['invite'] ); ?>" data-smliser-form>
				<div class="notice" data-smliser-form-message hidden></div>
				<label class="field">
					<span class="field-label">Email address</span>
					<input type="email" name="email" required>
				</label>
				<label class="field">
					<span class="field-label">Role</span>
					<select name="role">
						<?php foreach ( $roles as $slug => $label ) : ?>
							<option value="<?php echo escAttr( $slug ); ?>"<?php echo 'viewer' === $slug ? ' selected' : ''; ?>><?php echo escHtml( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button type="submit" class="btn btn-accent">Send invitation</button>
			</form>

			<?php if ( [] !== $invitations ) : ?>
				<h3 class="dash-subheading">Waiting for an answer</h3>
				<ul class="records">
					<?php foreach ( $invitations as $invitation ) : ?>
						<li class="record record-compact">
							<div class="record-head">
								<div>
									<h3><?php echo escHtml( $invitation['email'] ); ?></h3>
									<span class="muted"><?php echo escHtml( sprintf( 'Invited %s, valid until %s', $invitation['invited'], $invitation['expires'] ) ); ?></span>
								</div>
								<span class="badge"><?php echo escHtml( $invitation['role'] ); ?></span>
							</div>
							<div class="record-actions">
								<form method="post" action="<?php echo escUrl( $actions['invitation_resend'] ); ?>" data-smliser-form>
									<input type="hidden" name="id" value="<?php echo escAttr( $invitation['id'] ); ?>">
									<div class="notice" data-smliser-form-message hidden></div>
									<button type="submit" class="btn btn-small">Send again</button>
								</form>
								<form method="post" action="<?php echo escUrl( $actions['invitation_revoke'] ); ?>" data-smliser-form
									data-confirm="<?php echo escAttr( sprintf( 'Revoke the invitation to %s?', $invitation['email'] ) ); ?>" data-confirm-label="Revoke" data-confirm-danger>
									<input type="hidden" name="id" value="<?php echo escAttr( $invitation['id'] ); ?>">
									<div class="notice" data-smliser-form-message hidden></div>
									<button type="submit" class="btn btn-small">Revoke</button>
								</form>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $can['update'] ) : ?>
	<section class="dash-section" aria-labelledby="rename-title">
		<h2 id="rename-title">Name</h2>
		<form class="dash-form" method="post" action="<?php echo escUrl( $actions['rename'] ); ?>" data-smliser-form>
			<div class="notice" data-smliser-form-message hidden></div>
			<label class="field">
				<span class="field-label">Organization name</span>
				<input type="text" name="name" maxlength="100" value="<?php echo escAttr( $organization['name'] ); ?>" required>
			</label>
			<button type="submit" class="btn">Rename</button>
		</form>
	</section>
<?php endif; ?>

<?php if ( $can['delete'] ) : ?>
	<section class="dash-section dash-danger" aria-labelledby="delete-title">
		<h2 id="delete-title">Delete the organization</h2>
		<p class="muted">Its members, invitations and service accounts go with it. An organization that owns apps cannot be deleted until they are deleted or moved.</p>
		<form method="post" action="<?php echo escUrl( $actions['delete'] ); ?>" data-smliser-form
			data-confirm="<?php echo escAttr( sprintf( 'Delete %s? This cannot be undone.', $organization['name'] ) ); ?>" data-confirm-label="Delete" data-confirm-danger>
			<div class="notice" data-smliser-form-message hidden></div>
			<button type="submit" class="btn">Delete <?php echo escHtml( $organization['name'] ); ?></button>
		</form>
	</section>
<?php endif; ?>