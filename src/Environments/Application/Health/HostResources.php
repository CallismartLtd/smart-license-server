<?php
/**
 * Host resources class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Health
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Health;

/**
 * Live usage of the machine the application runs on: CPU, memory, swap,
 * disk, load and uptime, plus this PHP process's own memory.
 *
 * Philosophy:
 *
 *  - Measured, never estimated. Every figure comes from the kernel or PHP;
 *    a figure this platform cannot provide is null, and a front end shows
 *    it as unavailable rather than guessing.
 *  - Cheap enough to sample every second: a few small reads, no commands
 *    run, no folders walked.
 *  - CPU usage is a rate, so it needs two samples: this object keeps the
 *    previous one, and the first snapshot has no CPU usage yet.
 *
 * CPU, memory, swap and uptime come from Linux's /proc files. They are the
 * local kernel's, so they are read with PHP's own file functions on
 * purpose, not through the application's FileSystem, which may point at
 * remote storage. On other systems, or when open_basedir hides /proc,
 * those figures are null. Load average needs sys_getloadavg() (not on
 * Windows); disk space works everywhere.
 */
class HostResources {

	/**
	 * The previous /proc/stat CPU sample: busy and total jiffies.
	 *
	 * @var array{busy: int, total: int}|null
	 */
	private ?array $last_cpu = null;

	/**
	 * Number of CPUs, once read.
	 *
	 * @var int|null|false False until read; null when it cannot be known.
	 */
	private int|null|false $cpus = false;

	/**
	 * Constructor.
	 *
	 * @param string $disk_path A path on the disk to report, normally the application root.
	 */
	public function __construct( private readonly string $disk_path = \SMLISER_ROOT ) {}

	/**
	 * Take a snapshot of the host's resources.
	 *
	 * Byte figures are bytes; percentages are 0-100.
	 *
	 * @return array{
	 *     cpu_percent: ?float,
	 *     cpus: ?int,
	 *     load: ?array{0: float, 1: float, 2: float},
	 *     memory: ?array{total: int, used: int},
	 *     swap: ?array{total: int, used: int},
	 *     disk: ?array{total: int, used: int, path: string},
	 *     uptime: ?int,
	 *     php_memory: array{used: int, peak: int, limit: ?int}
	 * }
	 */
	public function snapshot() : array {
		$meminfo = $this->meminfo();

		return [
			'cpu_percent' => $this->cpu_percent(),
			'cpus'        => $this->cpus(),
			'load'        => $this->load(),
			'memory'      => null === $meminfo || ! isset( $meminfo['MemTotal'], $meminfo['MemAvailable'] )
				? null
				: [ 'total' => $meminfo['MemTotal'], 'used' => $meminfo['MemTotal'] - $meminfo['MemAvailable'] ],
			'swap'        => null === $meminfo || ! isset( $meminfo['SwapTotal'], $meminfo['SwapFree'] )
				? null
				: [ 'total' => $meminfo['SwapTotal'], 'used' => $meminfo['SwapTotal'] - $meminfo['SwapFree'] ],
			'disk'        => $this->disk(),
			'uptime'      => $this->uptime(),
			'php_memory'  => [
				'used'  => \memory_get_usage( true ),
				'peak'  => \memory_get_peak_usage( true ),
				'limit' => $this->memory_limit(),
			],
		];
	}

	/*
	|-----------------
	| MEASUREMENTS
	|-----------------
	*/

	/**
	 * CPU usage across all cores since the previous snapshot.
	 *
	 * @return float|null Null on the first snapshot, or where /proc/stat is unavailable.
	 */
	private function cpu_percent() : ?float {
		$line = $this->first_line( '/proc/stat' );

		if ( null === $line || ! \str_starts_with( $line, 'cpu ' ) ) {
			return null;
		}

		// user nice system idle iowait irq softirq steal ...
		$fields = \array_map( 'intval', \preg_split( '/\s+/', \trim( \substr( $line, 4 ) ) ) ?: [] );

		if ( \count( $fields ) < 4 ) {
			return null;
		}

		$idle   = $fields[3] + ( $fields[4] ?? 0 );
		$total  = \array_sum( \array_slice( $fields, 0, 8 ) );
		$sample = [ 'busy' => $total - $idle, 'total' => $total ];
		$last   = $this->last_cpu;

		$this->last_cpu = $sample;

		if ( null === $last || $sample['total'] <= $last['total'] ) {
			return null;
		}

		return \max( 0.0, \min( 100.0, 100 * ( $sample['busy'] - $last['busy'] ) / ( $sample['total'] - $last['total'] ) ) );
	}

	/**
	 * Number of CPUs, from /proc/cpuinfo.
	 *
	 * @return int|null
	 */
	private function cpus() : ?int {
		if ( false === $this->cpus ) {
			$cpuinfo    = $this->read( '/proc/cpuinfo' );
			$count      = null === $cpuinfo ? 0 : \preg_match_all( '/^processor\s*:/m', $cpuinfo );
			$this->cpus = $count > 0 ? $count : null;
		}

		return $this->cpus;
	}

	/**
	 * Load average over 1, 5 and 15 minutes.
	 *
	 * @return array{0: float, 1: float, 2: float}|null
	 */
	private function load() : ?array {
		if ( ! \function_exists( 'sys_getloadavg' ) ) {
			return null;
		}

		$load = @\sys_getloadavg();

		return \is_array( $load ) ? [ (float) $load[0], (float) $load[1], (float) $load[2] ] : null;
	}

	/**
	 * /proc/meminfo figures, in bytes.
	 *
	 * @return array<string, int>|null
	 */
	private function meminfo() : ?array {
		$meminfo = $this->read( '/proc/meminfo' );

		if ( null === $meminfo ) {
			return null;
		}

		\preg_match_all( '/^(\w+):\s+(\d+)\s*kB$/m', $meminfo, $matches, \PREG_SET_ORDER );

		$values = [];

		foreach ( $matches as $match ) {
			$values[ $match[1] ] = (int) $match[2] * 1024;
		}

		return [] === $values ? null : $values;
	}

	/**
	 * Size and use of the disk holding $disk_path.
	 *
	 * @return array{total: int, used: int, path: string}|null
	 */
	private function disk() : ?array {
		$total = @\disk_total_space( $this->disk_path );
		$free  = @\disk_free_space( $this->disk_path );

		if ( false === $total || false === $free || $total <= 0 ) {
			return null;
		}

		return [ 'total' => (int) $total, 'used' => (int) ( $total - $free ), 'path' => $this->disk_path ];
	}

	/**
	 * Seconds since the machine booted.
	 *
	 * @return int|null
	 */
	private function uptime() : ?int {
		$line    = $this->first_line( '/proc/uptime' );
		$seconds = null === $line ? '' : \explode( ' ', $line )[0];

		return \is_numeric( $seconds ) ? (int) (float) $seconds : null;
	}

	/**
	 * PHP's memory_limit in bytes.
	 *
	 * @return int|null Null when unlimited.
	 */
	private function memory_limit() : ?int {
		$limit = \trim( (string) \ini_get( 'memory_limit' ) );

		if ( '' === $limit || '-1' === $limit ) {
			return null;
		}

		$unit  = \strtolower( \substr( $limit, -1 ) );
		$value = (int) $limit;

		return match ( $unit ) {
			'g'     => $value * 1024 ** 3,
			'm'     => $value * 1024 ** 2,
			'k'     => $value * 1024,
			default => $value,
		};
	}

	/*
	|-----------------
	| /proc READS
	|-----------------
	*/

	/**
	 * Read a small local file, or null when it cannot be read.
	 *
	 * @param string $path
	 * @return string|null
	 */
	private function read( string $path ) : ?string {
		if ( ! @\is_readable( $path ) ) {
			return null;
		}

		$contents = @\file_get_contents( $path );

		return false === $contents ? null : $contents;
	}

	/**
	 * The first line of a small local file.
	 *
	 * @param string $path
	 * @return string|null
	 */
	private function first_line( string $path ) : ?string {
		$contents = $this->read( $path );

		return null === $contents ? null : \strtok( $contents, "\n" );
	}
}