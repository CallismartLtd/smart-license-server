<?php
/**
 * Release packager class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build;

use SmartLicenseServer\Build\Targets\AbstractTarget;

/**
 * Packages a finished build into the release artifacts.
 *
 * Every release has the same set, named <name>-<version>-<target>:
 *  - .zip           For File Manager / FTP uploads and Windows.
 *  - .tar.gz        For servers with a shell; keeps file permissions.
 *  - -setup.php     Single-file installer: the .zip appended to a PHP script
 *                   that unpacks it on the server (see tools/build/stubs/setup.php).
 *  - .sha256        SHA-256 checksums of the three files above, in the
 *                   format `sha256sum -c` reads.
 *
 * Both archives hold the build in one top-level folder named <name>, so
 * extracting never spills files into the current directory. Unix permissions
 * are stored in both, so the CLI entry stays executable. Only PHP's zip and
 * zlib extensions are used; no external zip or tar program is needed.
 */
final class ReleasePackager {

	/**
	 * Constructor.
	 *
	 * @param BuildContext $context Build context.
	 * @param BuildConsole $console Console.
	 */
	public function __construct(
		private readonly BuildContext $context,
		private readonly BuildConsole $console
	) {}

	/**
	 * Write every release artifact for a finished build.
	 *
	 * @param AbstractTarget $target Target.
	 * @param int            $time   Build time (Unix timestamp), stored as every entry's modification time.
	 * @return void
	 * @throws BuildException When an artifact cannot be written.
	 */
	public function run( AbstractTarget $target, int $time ): void {
		$this->console->step( 'Packaging release' );
		$this->check_extensions();

		$dir = $this->context->release_dir;

		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			throw new BuildException( "Could not create the release directory {$dir}." );
		}

		$project = $this->context->project;
		$base    = "{$project->name}-{$project->version}-{$target->name()}";
		$files   = ReleaseManifest::files( $this->context->out_dir );
		$dirs    = $this->directories( $this->context->out_dir );

		$artifacts = array(
			$this->zip( "{$dir}/{$base}.zip", $dirs, $files, $time ),
			$this->tar_gz( "{$dir}/{$base}.tar.gz", $dirs, $files, $time ),
		);

		$artifacts[] = $this->setup( "{$dir}/{$base}-setup.php", $artifacts[0] );

		$this->checksums( "{$dir}/{$base}.sha256", $artifacts );

		$this->console->info( "Release artifacts in {$dir}" );
	}

	/**
	 * Fail early, with a clear message, when the packaging extensions are missing.
	 *
	 * @return void
	 * @throws BuildException
	 */
	private function check_extensions(): void {
		$missing = array_filter(
			array( 'zip' => \ZipArchive::class, 'zlib' => 'gzopen' ),
			static fn ( string $symbol ): bool => ! class_exists( $symbol ) && ! function_exists( $symbol )
		);

		if ( array() !== $missing ) {
			throw new BuildException( sprintf( 'Packaging needs the PHP extension(s) %s on the build machine.', implode( ', ', array_keys( $missing ) ) ) );
		}
	}

	/**
	 * Write the .zip archive.
	 *
	 * Entries are added in sorted order with the same modification time, and
	 * carry their Unix permissions.
	 *
	 * @param string   $path  Archive path.
	 * @param string[] $dirs  Build-relative directories, so empty ones are kept.
	 * @param string[] $files Build-relative files.
	 * @param int      $time  Modification time for every entry.
	 * @return string The archive path.
	 * @throws BuildException
	 */
	private function zip( string $path, array $dirs, array $files, int $time ): string {
		@unlink( $path );

		$zip  = new \ZipArchive();
		$root = $this->context->project->name;

		if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::EXCL ) ) {
			throw new BuildException( "Could not create {$path}." );
		}

		foreach ( $dirs as $relative ) {
			$name = "{$root}/{$relative}/";

			$zip->addEmptyDir( rtrim( $name, '/' ) );
			$zip->setExternalAttributesName( $name, \ZipArchive::OPSYS_UNIX, ( 040000 | ( fileperms( $this->context->target( $relative ) ) & 0777 ) ) << 16 );
			$zip->setMtimeName( $name, $time );
		}

		foreach ( $files as $relative ) {
			$source = $this->context->target( $relative );
			$name   = "{$root}/{$relative}";

			if ( ! $zip->addFile( $source, $name ) ) {
				$zip->close();
				throw new BuildException( "Could not add {$relative} to the zip archive." );
			}

			$zip->setCompressionName( $name, \ZipArchive::CM_DEFLATE );
			$zip->setExternalAttributesName( $name, \ZipArchive::OPSYS_UNIX, ( 0100000 | ( fileperms( $source ) & 0777 ) ) << 16 );
			$zip->setMtimeName( $name, $time );
		}

		if ( ! $zip->close() ) {
			throw new BuildException( "Could not write {$path}: " . $zip->getStatusString() );
		}

		$this->report( $path );

		return $path;
	}

	/**
	 * Write the .tar.gz archive.
	 *
	 * Written directly as POSIX ustar (GNU long-name entries for paths that do
	 * not fit), gzip-compressed with zlib. PharData is not used: it stores a
	 * modification time of 0 (1970) for every entry.
	 *
	 * @param string   $path  Archive path.
	 * @param string[] $dirs  Build-relative directories, so empty ones are kept.
	 * @param string[] $files Build-relative files.
	 * @param int      $time  Modification time for every entry.
	 * @return string The archive path.
	 * @throws BuildException
	 */
	private function tar_gz( string $path, array $dirs, array $files, int $time ): string {
		@unlink( $path );

		$out  = @gzopen( $path, 'wb9' );
		$root = $this->context->project->name;

		if ( false === $out ) {
			throw new BuildException( "Could not create {$path}." );
		}

		try {
			foreach ( $dirs as $relative ) {
				$this->tar_entry( $out, "{$root}/{$relative}", '', fileperms( $this->context->target( $relative ) ) & 0777, $time, '5' );
			}

			foreach ( $files as $relative ) {
				$source   = $this->context->target( $relative );
				$contents = file_get_contents( $source );

				if ( false === $contents ) {
					throw new BuildException( "Could not read {$relative} for the tar archive." );
				}

				$this->tar_entry( $out, "{$root}/{$relative}", $contents, fileperms( $source ) & 0777, $time );
			}

			// End of archive: two empty blocks.
			gzwrite( $out, str_repeat( "\0", 1024 ) );
		} finally {
			gzclose( $out );
		}

		$this->report( $path );

		return $path;
	}

	/**
	 * Write one entry (header, then contents padded to 512 bytes) to a tar stream.
	 *
	 * @param resource $out      gzopen() stream.
	 * @param string   $name     Path inside the archive.
	 * @param string   $contents File contents; empty for a directory.
	 * @param int      $mode     Permission bits.
	 * @param int      $time     Modification time.
	 * @param string   $type     Type flag: "0" file, "5" directory.
	 * @return void
	 */
	private function tar_entry( $out, string $name, string $contents, int $mode, int $time, string $type = '0' ): void {
		[ $prefix, $short ] = $this->tar_split_name( $name );

		// Too long for ustar: a GNU long-name entry carries the full path first.
		if ( null === $short ) {
			$data = $name . "\0";
			gzwrite( $out, $this->tar_header( '././@LongLink', '', strlen( $data ), 0644, 0, 'L' ) );
			gzwrite( $out, $this->tar_pad( $data ) );
			[ $prefix, $short ] = array( '', substr( $name, 0, 100 ) );
		}

		gzwrite( $out, $this->tar_header( $short, $prefix, strlen( $contents ), $mode, $time, $type ) );
		gzwrite( $out, $this->tar_pad( $contents ) );
	}

	/**
	 * Split a path into ustar's prefix (155 bytes) and name (100 bytes) fields.
	 *
	 * @param string $name Path.
	 * @return array{0: string, 1: string|null} Prefix and name; name is null when the path does not fit.
	 */
	private function tar_split_name( string $name ): array {
		if ( strlen( $name ) <= 100 ) {
			return array( '', $name );
		}

		// Split at a slash so the name part is at most 100 and the prefix at most 155 bytes.
		for ( $i = strlen( $name ) - 1; $i > 0; $i-- ) {
			if ( '/' === $name[ $i ] && $i <= 155 && strlen( $name ) - $i - 1 <= 100 ) {
				return array( substr( $name, 0, $i ), substr( $name, $i + 1 ) );
			}
		}

		return array( '', null );
	}

	/**
	 * Build a 512-byte ustar header.
	 *
	 * @param string $name   Name field.
	 * @param string $prefix Prefix field.
	 * @param int    $size   Data size in bytes.
	 * @param int    $mode   Permission bits.
	 * @param int    $time   Modification time.
	 * @param string $type   Type flag ("0" file, "5" directory, "L" GNU long name).
	 * @return string
	 */
	private function tar_header( string $name, string $prefix, int $size, int $mode, int $time, string $type ): string {
		$header = pack(
			'a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
			$name,
			sprintf( '%07o', $mode ),
			sprintf( '%07o', 0 ),
			sprintf( '%07o', 0 ),
			sprintf( '%011o', $size ),
			sprintf( '%011o', $time ),
			str_repeat( ' ', 8 ),
			$type,
			'',
			'ustar',
			'00',
			'',
			'',
			'',
			'',
			$prefix,
			''
		);

		$checksum = array_sum( array_map( 'ord', str_split( $header ) ) );

		return substr_replace( $header, sprintf( '%06o', $checksum ) . "\0 ", 148, 8 );
	}

	/**
	 * Pad data to a whole number of 512-byte blocks.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	private function tar_pad( string $data ): string {
		$remainder = strlen( $data ) % 512;

		return 0 === $remainder ? $data : $data . str_repeat( "\0", 512 - $remainder );
	}

	/**
	 * Write the single-file installer: the setup script with the zip appended.
	 *
	 * @param string $path Installer path.
	 * @param string $zip  Zip archive to embed.
	 * @return string The installer path.
	 * @throws BuildException
	 */
	private function setup( string $path, string $zip ): string {
		$stub = dirname( __DIR__ ) . '/stubs/setup.php';

		if ( ! is_file( $stub ) ) {
			throw new BuildException( 'The installer template tools/build/stubs/setup.php is missing.' );
		}

		$project = $this->context->project;
		$values  = array(
			'TITLE'      => $project->title(),
			'VERSION'    => $project->version,
			'ROOT'       => $project->name,
			'SHA256'     => (string) hash_file( 'sha256', $zip ),
			'PHP'        => (string) $project->php_minor(),
			'EXTENSIONS' => $project->extensions,
		);

		$script = rtrim( (string) file_get_contents( $stub ) );

		foreach ( $values as $key => $value ) {
			$script = str_replace( "'{{{$key}}}'", var_export( $value, true ), $script );
		}

		// The archive must start right after __halt_compiler(); — no newline in between.
		if ( ! str_ends_with( $script, '__halt_compiler();' ) || str_contains( $script, '{{' ) ) {
			throw new BuildException( 'The installer template must end with __halt_compiler(); and fill every placeholder.' );
		}

		$out = @fopen( $path, 'wb' );
		$in  = @fopen( $zip, 'rb' );

		if ( ! $out || ! $in || false === fwrite( $out, $script ) || false === stream_copy_to_stream( $in, $out ) ) {
			throw new BuildException( "Could not write {$path}." );
		}

		fclose( $in );
		fclose( $out );
		chmod( $path, 0644 );

		$this->report( $path );

		return $path;
	}

	/**
	 * Write the checksums file for the artifacts.
	 *
	 * @param string   $path      Checksums file path.
	 * @param string[] $artifacts Artifact paths.
	 * @return void
	 * @throws BuildException
	 */
	private function checksums( string $path, array $artifacts ): void {
		$lines = '';

		foreach ( $artifacts as $artifact ) {
			$lines .= hash_file( 'sha256', $artifact ) . '  ' . basename( $artifact ) . "\n";
		}

		if ( false === file_put_contents( $path, $lines ) ) {
			throw new BuildException( "Could not write {$path}." );
		}

		$this->console->success( basename( $path ) );
	}

	/**
	 * Every directory under a directory, relative to it, parents first.
	 *
	 * @param string $dir Directory.
	 * @return string[]
	 */
	private function directories( string $dir ): array {
		$dirs     = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			if ( $item->isDir() ) {
				$dirs[] = str_replace( '\\', '/', substr( $item->getPathname(), strlen( $dir ) + 1 ) );
			}
		}

		sort( $dirs, SORT_STRING );

		return $dirs;
	}

	/**
	 * Report an artifact with its size.
	 *
	 * @param string $path Artifact path.
	 * @return void
	 */
	private function report( string $path ): void {
		$bytes = (int) filesize( $path );

		$this->console->success(
			sprintf( '%s  %s', basename( $path ), $bytes >= 1048576 ? sprintf( '%.1f MB', $bytes / 1048576 ) : sprintf( '%.1f KB', $bytes / 1024 ) )
		);
	}
}