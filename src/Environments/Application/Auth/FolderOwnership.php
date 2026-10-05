<?php
/**
 * Folder ownership class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Auth;

/**
 * Tells whether the user running this PHP process owns a folder.
 *
 * Owning the application folder is how the command line proves it is run by
 * the person who installed the application: ConsoleIdentityProvider requires
 * it for the "root" CLI key, and the installer requires it to create the
 * first administrator.
 */
class FolderOwnership {

    /**
     * Whether the user running this process owns the folder.
     *
     * @param string $dir Folder path.
     * @return bool False when the folder does not exist or ownership cannot be determined.
     */
    public function is_owner( string $dir ) : bool {
        if ( ! \is_dir( $dir ) ) {
            return false;
        }

        if ( '\\' === \DIRECTORY_SEPARATOR ) {
            return $this->windows_is_owner( $dir );
        }

        return $this->unix_is_owner( $dir );
    }

    /**
     * Name of the folder's owner, for messages.
     *
     * @param string $dir Folder path.
     * @return string|null The user name (or numeric ID when names are unavailable), null when unknown.
     */
    public function owner_name( string $dir ) : ?string {
        if ( '\\' === \DIRECTORY_SEPARATOR ) {
            $owners = $this->windows_owners( $dir );

            return false === $owners ? null : $owners[0];
        }

        $uid = @\fileowner( $dir );

        if ( false === $uid ) {
            return null;
        }

        if ( \function_exists( 'posix_getpwuid' ) ) {
            $info = @\posix_getpwuid( $uid );

            if ( \is_array( $info ) && isset( $info['name'] ) ) {
                return (string) $info['name'];
            }
        }

        return (string) $uid;
    }

    /**
     * Unix: compare the folder owner with the effective user ID.
     *
     * @param string $dir Folder path.
     * @return bool
     */
    protected function unix_is_owner( string $dir ) : bool {
        if ( ! \function_exists( 'posix_geteuid' ) ) {
            return false;
        }

        $owner_uid = @\fileowner( $dir );

        if ( false === $owner_uid ) {
            return false;
        }

        return $owner_uid === \posix_geteuid();
    }

    /**
     * Windows: compare the folder owner with the owner of a file this process creates in it.
     *
     * A new file is owned by the account that created it (or by the
     * Administrators group when that account is an elevated administrator,
     * which also applies to the folder), so equal owners mean the folder
     * belongs to whoever runs this process.
     *
     * @param string $dir Folder path.
     * @return bool
     */
    protected function windows_is_owner( string $dir ) : bool {
        $tmp_file = @\tempnam( $dir, '.smliser-' );

        if ( false === $tmp_file ) {
            return false;
        }

        try {
            $owners = $this->windows_owners( $dir, $tmp_file );

            return false !== $owners
                && 2 === \count( $owners )
                && 0 === \strcasecmp( $owners[0], $owners[1] );
        } finally {
            @\unlink( $tmp_file );
        }
    }

    /**
     * Windows: the owners of one or more paths, in order.
     *
     * Uses PowerShell's Get-Acl. icacls is not used: it lists access
     * entries, not the owner.
     *
     * @param string ...$paths Paths.
     * @return string[]|false One owner per path, or false when they cannot be read.
     */
    protected function windows_owners( string ...$paths ) : array|false {
        if ( ! \function_exists( 'exec' ) ) {
            return false;
        }

        $script = \implode(
            '; ',
            \array_map(
                static fn ( string $path ) : string => "(Get-Acl -LiteralPath '" . \str_replace( "'", "''", $path ) . "').Owner",
                $paths
            )
        );

        $output = array();
        $status = -1;

        @\exec( 'powershell -NoProfile -NonInteractive -Command ' . \escapeshellarg( $script ) . ' 2>NUL', $output, $status );

        $owners = \array_values( \array_filter( \array_map( 'trim', $output ), static fn ( string $line ) : bool => '' !== $line ) );

        if ( 0 !== $status || \count( $owners ) !== \count( $paths ) ) {
            return false;
        }

        return $owners;
    }
}