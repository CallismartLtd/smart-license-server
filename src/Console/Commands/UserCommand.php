<?php
/**
 * User command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Background\Jobs\Accounts\EmailVerificationJob;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Console\Traits\CLIUtilsTrait;
use SmartLicenseServer\Core\AvatarManager;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\Session\UserSessionCutoff;
use SmartLicenseServer\Security\Authentication\UserAccounts;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Permission\Role;

/**
 * Manage user accounts from the CLI.
 *
 * The CLI follows the same rules as the admin dashboard, through the same
 * models and services, so an account looks the same whichever made it:
 *
 * - Every subcommand needs a principal (SMLISER_CLI_API_KEY) with the
 *   matching capability: security.user.view to read, security.user.create,
 *   security.user.update or security.user.delete to write, and
 *   security.role.assign to give any role other than "viewer", the role
 *   a signup gets. A system administrator has them all.
 * - Roles are only assigned here, never edited.
 * - The administrator lockout rules apply (ContextServiceProvider): no
 *   change may leave the platform without an active system administrator.
 *   Making someone an active administrator is always allowed, so the CLI
 *   can restore access when the dashboard is out of reach.
 * - Passwords follow UserAccounts::validate_password(). They are asked for
 *   without echo unless --password is given (which shell history keeps).
 * - A password change and "signout" end the user's sessions on every device.
 *
 * A <user> is a user ID or an email address.
 */
class UserCommand extends AbstractCommand {
	use CLIUtilsTrait, QueueAwareTrait;

	/**
	 * The role a user gets when none is given, as at signup.
	 *
	 * @var string
	 */
	public const DEFAULT_ROLE = 'viewer';

	/**
	 * @param Guard             $guard    The principal running the command.
	 * @param UserAccounts      $accounts User account rules (passwords, verification).
	 * @param UserSessionCutoff $cutoff   Ends a user's sessions everywhere.
	 * @param AvatarManager     $avatars  Renames the avatar when the email changes.
	 * @param JobQueue          $job_queue Sends the verification email.
	 * @param InputInterface    $io
	 * @param OutputInterface   $output
	 * @param ScriptName        $script_name
	 */
	public function __construct(
		Guard $guard,
		protected UserAccounts $accounts,
		protected UserSessionCutoff $cutoff,
		protected AvatarManager $avatars,
		JobQueue $job_queue,
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name
	) {
		$this->guard     = $guard;
		$this->job_queue = $job_queue;

		parent::__construct( $io, $output, $script_name );
	}

	/*
	|-------------------
	| CommandInterface
	|-------------------
	*/

	public static function name() : string {
		return 'user';
	}

	public function description() : string {
		return 'Manage user accounts.';
	}

	public function help() : string {
		$prefix = $this->command_prefix();

		return implode( PHP_EOL, [
			'A <user> is a user ID or an email address.',
			'',
			'Subcommands:',
			'  list                       List users, newest first.',
			'  search <term>              Find users by name or email address.',
			'  get <user>                 Show a user.',
			'  create                     Create a user.',
			'  update <user>              Change a user\'s name, email, status or role.',
			'  set-password <user>        Set a new password; signs the user out everywhere.',
			'  verify <user>              Mark the email address verified and link its licenses.',
			'  signout <user>             Sign the user out on every device.',
			'  delete <user>              Delete a user. Licenses issued to them are kept.',
			'  roles                      List the roles that can be assigned.',
			'  help                       Show this help message.',
			'',
			'Options:',
			'  --email=<email>            Email address (create, update).',
			'  --name=<name>              Display name (create, update).',
			sprintf( '  --role=<slug>              Role (create, update). Default for create: %s.', self::DEFAULT_ROLE ),
			sprintf( '  --status=<status>          %s (create, update, list, search).', implode( '|', User::get_allowed_statuses() ) ),
			'  --password=<password>      Password (create, set-password). Asked for without echo when left out.',
			'  --verified                 Create the user with the email address already verified.',
			'                             Otherwise a verification email is sent.',
			'  --page=<n>, --limit=<n>    Pagination (list, search). Default: 1, 20.',
			'  -y, --yes, -f, --force     Do not ask for confirmation (delete).',
			'',
			'Who may run what:',
			'  list, search, get, roles   security.user.view',
			'  create                     security.user.create, and security.role.assign for a role other than ' . self::DEFAULT_ROLE,
			'  update, set-password,      security.user.update, and security.role.assign to change the role',
			'  verify, signout',
			'  delete                     security.user.delete',
			'  No change may leave the platform without an active system administrator.',
			'',
			'Examples:',
			"  {$prefix} list --status=active",
			"  {$prefix} search jane",
			"  {$prefix} create --email=jane@example.com --name=\"Jane Doe\" --role=license_manager",
			"  {$prefix} update jane@example.com --status=suspended",
			"  {$prefix} set-password 12",
			"  {$prefix} delete jane@example.com -y",
		] );
	}

	public function run( CommandInput $input ) : int {
		$this->output->info( $this->description() );
		$this->output->newline();
		$this->output->writeln( sprintf( 'Run `%s` to see available subcommands.', $this->command_prefix( 'help' ) ) );

		return 0;
	}

	public function get_subcommands() : array {
		return [
			'list'         => [ $this, 'handle_list' ],
			'search'       => [ $this, 'handle_search' ],
			'get'          => [ $this, 'handle_get' ],
			'create'       => [ $this, 'handle_create' ],
			'update'       => [ $this, 'handle_update' ],
			'set-password' => [ $this, 'handle_set_password' ],
			'verify'       => [ $this, 'handle_verify' ],
			'signout'      => [ $this, 'handle_signout' ],
			'delete'       => [ $this, 'handle_delete' ],
			'roles'        => [ $this, 'handle_roles' ],
			'help'         => [ $this, 'handle_help' ],
		];
	}

	/*
	|----------
	| READING
	|----------
	*/

	/**
	 * List users, newest first.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_list( CommandInput $input ) : int {
		if ( ! $this->require_capability( 'security.user.view' ) ) {
			return 1;
		}

		return $this->print_users( '', $input );
	}

	/**
	 * Find users by name or email address.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_search( CommandInput $input ) : int {
		$term = trim( (string) $input->get_argument( 0, '' ) );

		if ( ! $this->require_args( [ 'term' => $term ], sprintf( '%s <term> [--status=<status>]', $this->command_prefix( 'search' ) ) ) ) {
			return 1;
		}

		if ( ! $this->require_capability( 'security.user.view' ) ) {
			return 1;
		}

		return $this->print_users( $term, $input );
	}

	/**
	 * Show a user.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_get( CommandInput $input ) : int {
		if ( ! $this->require_capability( 'security.user.view' ) ) {
			return 1;
		}

		$user = $this->resolve_user( $input, 'get' );

		if ( ! $user ) {
			return 1;
		}

		$this->print_user( $user );

		return 0;
	}

	/**
	 * Print one user's details.
	 *
	 * @param User $user
	 * @return void
	 */
	private function print_user( User $user ) : void {
		$role = ContextServiceProvider::get_principal_role( $user );

		$this->output->table(
			[ 'Field', 'Value' ],
			[
				[ 'ID', (string) $user->get_id() ],
				[ 'Name', $user->get_display_name() ],
				[ 'Email', $user->get_email() ],
				[ 'Email verified', $this->format_date( $user->get_email_verified_at(), 'No' ) ],
				[ 'Status', $user->get_status() ],
				[ 'Role', $role ? sprintf( '%s (%s)', $role->get_label(), $role->get_slug() ) : 'None' ],
				[ 'Created', $this->format_date( $user->get_created_at() ) ],
				[ 'Updated', $this->format_date( $user->get_updated_at() ) ],
			]
		);
	}

	/**
	 * List the roles that can be assigned.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_roles( CommandInput $input ) : int {
		if ( ! $this->require_capability( 'security.user.view' ) ) {
			return 1;
		}

		$rows = array_map(
			static fn ( Role $role ) : array => [
				$role->get_slug(),
				$role->get_label(),
				$role->get_is_canonical() ? 'Built-in' : 'Custom',
				(string) count( $role->get_capabilities() ),
			],
			Role::all()
		);

		if ( [] === $rows ) {
			$this->output->warning( 'No roles exist.' );
			return 0;
		}

		$this->output->table( [ 'Slug', 'Label', 'Type', 'Capabilities' ], $rows );

		return 0;
	}

	/*
	|----------
	| WRITING
	|----------
	*/

	/**
	 * Create a user.
	 *
	 * Without --verified a verification email is sent, as at signup. With it,
	 * the address counts as verified at once and licenses issued to it are
	 * linked to the user. If the role cannot be given, the user is removed
	 * again, so nothing is left half-made.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_create( CommandInput $input ) : int {
		$usage  = sprintf( '%s --email=<email> [--name=<name>] [--role=<slug>] [--status=<status>] [--password=<password>] [--verified]', $this->command_prefix( 'create' ) );
		$email  = trim( (string) $input->get_option( 'email', '' ) );
		$name   = trim( (string) $input->get_option( 'name', '' ) );
		$slug   = trim( (string) $input->get_option( 'role', self::DEFAULT_ROLE ) );
		$status = trim( (string) $input->get_option( 'status', User::STATUS_ACTIVE ) );

		if ( ! $this->require_options( [ 'email' => $email ], [ 'email' ], $usage ) ) {
			return 1;
		}

		if ( ! $this->require_capability( 'security.user.create' ) ) {
			return 1;
		}

		if ( self::DEFAULT_ROLE !== $slug && ! $this->require_capability( 'security.role.assign' ) ) {
			return 1;
		}

		$role = $this->resolve_role( $slug );

		if ( ! $role || ! $this->check_email( $email ) || ! $this->check_status( $status ) ) {
			return 1;
		}

		$password = $this->read_password( $input );

		if ( null === $password ) {
			return 1;
		}

		try {
			$user = $this->accounts->create_user( $email, $password, $name );
		} catch ( \Throwable $e ) {
			$this->output->error( sprintf( 'The user could not be created: %s', $e->getMessage() ) );
			return 1;
		}

		try {
			if ( User::STATUS_ACTIVE !== $status && ! $user->set_status( $status )->save() ) {
				throw new Exception( 'user_save_error', 'The status could not be saved.' );
			}

			ContextServiceProvider::save_actor_role( $user, $role );
		} catch ( \Throwable $e ) {
			try {
				ContextServiceProvider::delete_user( $user );
			} catch ( \Throwable ) {
				$this->output->warning( sprintf( 'User #%d was created without its role; delete it with `%s`.', $user->get_id(), $this->command_prefix( 'delete ' . $user->get_id() ) ) );
			}

			$this->output->error( sprintf( 'The user could not be created: %s', $e->getMessage() ) );
			return 1;
		}

		$this->output->success( sprintf( 'User #%d created: %s <%s>, %s, %s.', $user->get_id(), $user->get_display_name(), $user->get_email(), $role->get_label(), $user->get_status() ) );

		if ( true === $input->get_option( 'verified' ) ) {
			return $this->verify( $user );
		}

		$this->dispatch_job( EmailVerificationJob::class, [ 'user_id' => $user->get_id() ] );
		$this->output->writeln( sprintf( 'A verification email is queued for %s.', $user->get_email() ) );

		return 0;
	}

	/**
	 * Change a user's name, email address, status or role.
	 *
	 * Checked against the administrator lockout rules before anything is
	 * written. A new email address is unverified until the user verifies it.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_update( CommandInput $input ) : int {
		if ( ! $this->require_capability( 'security.user.update' ) ) {
			return 1;
		}

		$user = $this->resolve_user( $input, 'update' );

		if ( ! $user ) {
			return 1;
		}

		$changes = array_intersect_key( $input->get_options(), array_flip( [ 'name', 'email', 'status', 'role' ] ) );

		if ( [] === $changes ) {
			$this->output->error( 'Nothing to change. Give at least one of --name, --email, --status or --role.' );
			$this->output->writeln( sprintf( 'Usage: %s <user> [--name=<name>] [--email=<email>] [--status=<status>] [--role=<slug>]', $this->command_prefix( 'update' ) ) );
			return 1;
		}

		$current_role = ContextServiceProvider::get_principal_role( $user );
		$role         = $current_role;
		$status       = isset( $changes['status'] ) ? trim( (string) $changes['status'] ) : $user->get_status();
		$email        = isset( $changes['email'] ) ? trim( (string) $changes['email'] ) : $user->get_email();
		$name         = isset( $changes['name'] ) ? trim( (string) $changes['name'] ) : $user->get_display_name();

		if ( isset( $changes['role'] ) ) {
			if ( ! $this->require_capability( 'security.role.assign' ) ) {
				return 1;
			}

			$role = $this->resolve_role( trim( (string) $changes['role'] ) );

			if ( ! $role ) {
				return 1;
			}
		}

		if ( '' === $name ) {
			$this->output->error( 'The name cannot be empty.' );
			return 1;
		}

		if ( ! $this->check_status( $status ) ) {
			return 1;
		}

		if ( strtolower( $email ) !== strtolower( $user->get_email() ) && ! $this->check_email( $email ) ) {
			return 1;
		}

		try {
			ContextServiceProvider::assert_can_change_user_access(
				$user,
				$this->guard->get_principal()?->get_actor(),
				(string) $role?->get_slug(),
				$status
			);
		} catch ( Exception $e ) {
			$this->output->error( $e->get_error_message() );
			return 1;
		}

		$old_uid = $user->get_unique_identifier();

		$user->set_display_name( $name )->set_email( $email )->set_status( $status );

		try {
			if ( ! $user->save() ) {
				throw new Exception( 'user_save_error', 'The user could not be saved.' );
			}

			if ( $role && $role->get_id() !== $current_role?->get_id() ) {
				ContextServiceProvider::save_actor_role( $user, $role );
			}
		} catch ( \Throwable $e ) {
			$this->output->error( sprintf( 'The user could not be updated: %s', $e->getMessage() ) );
			return 1;
		}

		// The avatar is stored under an ID made from the email address.
		if ( $old_uid !== $user->get_unique_identifier() ) {
			$this->avatars->rename( $user->get_type(), $old_uid, $user->get_unique_identifier() );
		}

		$this->output->success( sprintf( 'User #%d updated.', $user->get_id() ) );
		$this->print_user( $user );

		return 0;
	}

	/**
	 * Set a new password and sign the user out everywhere.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_set_password( CommandInput $input ) : int {
		if ( ! $this->require_capability( 'security.user.update' ) ) {
			return 1;
		}

		$user = $this->resolve_user( $input, 'set-password' );

		if ( ! $user ) {
			return 1;
		}

		$password = $this->read_password( $input );

		if ( null === $password ) {
			return 1;
		}

		try {
			$this->accounts->change_password( $user, $password );
		} catch ( \Throwable $e ) {
			$this->output->error( sprintf( 'The password could not be changed: %s', $e->getMessage() ) );
			return 1;
		}

		// Whoever held the old password may hold a session: end them all.
		$this->cutoff->revoke_all( $user );

		$this->output->success( sprintf( 'Password changed for %s. They are signed out everywhere.', $user->get_email() ) );

		return 0;
	}

	/**
	 * Mark the email address verified and link the licenses issued to it.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_verify( CommandInput $input ) : int {
		if ( ! $this->require_capability( 'security.user.update' ) ) {
			return 1;
		}

		$user = $this->resolve_user( $input, 'verify' );

		return $user ? $this->verify( $user ) : 1;
	}

	/**
	 * Sign the user out on every device.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_signout( CommandInput $input ) : int {
		if ( ! $this->require_capability( 'security.user.update' ) ) {
			return 1;
		}

		$user = $this->resolve_user( $input, 'signout' );

		if ( ! $user ) {
			return 1;
		}

		if ( ! $this->cutoff->revoke_all( $user ) ) {
			$this->output->error( 'The sign-out could not be saved.' );
			return 1;
		}

		$this->output->success( sprintf( '%s is signed out on every device.', $user->get_email() ) );

		return 0;
	}

	/**
	 * Delete a user.
	 *
	 * Removes the account, its role assignments, memberships, resource owner
	 * and the service accounts of that owner (ContextServiceProvider::delete_user()).
	 * Licenses issued to the user are kept.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_delete( CommandInput $input ) : int {
		if ( ! $this->require_capability( 'security.user.delete' ) ) {
			return 1;
		}

		$user = $this->resolve_user( $input, 'delete' );

		if ( ! $user ) {
			return 1;
		}

		try {
			ContextServiceProvider::assert_can_delete_user( $user, $this->guard->get_principal()?->get_actor() );
		} catch ( Exception $e ) {
			$this->output->error( $e->get_error_message() );
			return 1;
		}

		$question = sprintf( 'Delete user #%d (%s)? Their service accounts go too. This cannot be undone.', $user->get_id(), $user->get_email() );

		if ( ! $this->is_confirmed( $input ) && ! $this->io->confirm( $question, false ) ) {
			$this->output->writeln( 'Aborted.' );
			return 0;
		}

		try {
			ContextServiceProvider::delete_entity( $user );
		} catch ( Exception $e ) {
			$this->output->error( $e->get_error_message() );
			return 1;
		}

		$this->output->success( sprintf( 'User #%d (%s) deleted.', $user->get_id(), $user->get_email() ) );

		return 0;
	}

	/*
	|-----------------
	| PRIVATE HELPERS
	|-----------------
	*/

	/**
	 * Print a page of users.
	 *
	 * @param string       $search Part of the name or email; empty for all.
	 * @param CommandInput $input  For --status, --page and --limit.
	 * @return int
	 */
	private function print_users( string $search, CommandInput $input ) : int {
		$status = trim( (string) $input->get_option( 'status', '' ) );

		if ( '' !== $status && ! $this->check_status( $status ) ) {
			return 1;
		}

		$result = User::find_all( $search, $status, (int) $input->get_option( 'page', 1 ), (int) $input->get_option( 'limit', 20 ) );
		$page   = $result['pagination'];

		if ( [] === $result['items'] ) {
			$this->output->warning( 'No users found.' );
			return 0;
		}

		$rows = array_map(
			fn ( User $user ) : array => [
				(string) $user->get_id(),
				$user->get_display_name(),
				$user->get_email(),
				$user->get_status(),
				(string) ContextServiceProvider::get_principal_role( $user )?->get_slug() ?: '-',
				$user->is_email_verified() ? 'Yes' : 'No',
			],
			$result['items']
		);

		$this->output->table( [ 'ID', 'Name', 'Email', 'Status', 'Role', 'Verified' ], $rows );
		$this->output->writeln( sprintf( 'Page %d of %d, %d user(s).', $page['page'], max( 1, $page['total_pages'] ), $page['total'] ) );

		return 0;
	}

	/**
	 * Find the <user> given as the first argument, by ID or email address.
	 *
	 * @param CommandInput $input
	 * @param string       $subcommand For the usage line.
	 * @return User|null Null, after saying why, when there is no such user.
	 */
	private function resolve_user( CommandInput $input, string $subcommand ) : ?User {
		$ref = trim( (string) $input->get_argument( 0, '' ) );

		if ( ! $this->require_args( [ 'user' => $ref ], sprintf( '%s <user>', $this->command_prefix( $subcommand ) ) ) ) {
			return null;
		}

		$user = ctype_digit( $ref ) ? User::get_by_id( (int) $ref ) : User::get_by_email( $ref );

		if ( ! $user ) {
			$this->output->error( sprintf( 'No user has the ID or email address "%s".', $ref ) );
		}

		return $user;
	}

	/**
	 * Find a role by slug.
	 *
	 * @param string $slug
	 * @return Role|null Null, after listing the roles, when there is no such role.
	 */
	private function resolve_role( string $slug ) : ?Role {
		$role = '' !== $slug ? Role::get_by_slug( $slug ) : null;

		if ( ! $role ) {
			$this->output->error( sprintf( 'There is no role "%s". Roles: %s.', $slug, implode( ', ', array_map( static fn ( Role $r ) : string => $r->get_slug(), Role::all() ) ) ) );
		}

		return $role;
	}

	/**
	 * Whether an email address is valid and no account uses it.
	 *
	 * @param string $email
	 * @return bool
	 */
	private function check_email( string $email ) : bool {
		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$this->output->error( sprintf( '"%s" is not a valid email address.', $email ) );
			return false;
		}

		if ( User::email_exists( $email ) ) {
			$this->output->error( sprintf( 'An account with the email address %s already exists.', $email ) );
			return false;
		}

		return true;
	}

	/**
	 * Whether a status is one a user can have.
	 *
	 * @param string $status
	 * @return bool
	 */
	private function check_status( string $status ) : bool {
		if ( in_array( $status, User::get_allowed_statuses(), true ) ) {
			return true;
		}

		$this->output->error( sprintf( '"%s" is not a user status. Statuses: %s.', $status, implode( ', ', User::get_allowed_statuses() ) ) );

		return false;
	}

	/**
	 * The new password: --password, or asked for twice without echo.
	 *
	 * @param CommandInput $input
	 * @return string|null Null, after saying why, when it is not acceptable.
	 */
	private function read_password( CommandInput $input ) : ?string {
		if ( $input->has_option( 'password' ) ) {
			$password = (string) $input->get_option( 'password', '' );
			$confirm  = $password;
		} else {
			// Only line endings from terminal input are removed; spaces are part of the password.
			$password = rtrim( $this->io->secret( sprintf( 'Password (at least %d characters): ', UserAccounts::MIN_PASSWORD_LENGTH ) ), "\r\n" );
			$confirm  = rtrim( $this->io->secret( 'Type the password again: ' ), "\r\n" );
		}

		$problem = $this->accounts->validate_password( $password, $confirm );

		if ( null !== $problem ) {
			$this->output->error( $problem->get_error_message() );
			return null;
		}

		return $password;
	}

	/**
	 * Mark a user's email address verified and report the licenses linked.
	 *
	 * @param User $user
	 * @return int
	 */
	private function verify( User $user ) : int {
		try {
			$linked = $this->accounts->mark_email_verified( $user );
		} catch ( \Throwable $e ) {
			$this->output->error( sprintf( 'The email address could not be verified: %s', $e->getMessage() ) );
			return 1;
		}

		$this->output->success( sprintf( '%s is verified. %d license(s) linked to the account.', $user->get_email(), $linked ) );

		return 0;
	}

	/**
	 * A date as people read it.
	 *
	 * @param \DateTimeImmutable|null $date
	 * @param string                  $none Shown when there is no date.
	 * @return string
	 */
	private function format_date( ?\DateTimeImmutable $date, string $none = '-' ) : string {
		return $date ? $date->format( \smliser_datetime_format() ) : $none;
	}
}