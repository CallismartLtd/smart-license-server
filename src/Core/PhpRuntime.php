<?php
/**
 * PHP runtime helper class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Core
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Core;

/**
 * Changes PHP's runtime limits safely on hosts that restrict them.
 *
 * Shared hosts often list set_time_limit, ini_set or ignore_user_abort in
 * disable_functions. Since PHP 8 a disabled function does not exist at
 * all, so calling it is a fatal Error, not a warning that "@" could hide.
 * Every call here checks first and reports whether it worked.
 */
final class PhpRuntime {

	/**
	 * Let the script run for up to $seconds (0: no limit).
	 *
	 * @param int $seconds
	 * @return bool Whether the limit could be changed.
	 */
	public static function set_time_limit( int $seconds ) : bool {
		return function_exists( 'set_time_limit' ) && @set_time_limit( $seconds );
	}

	/**
	 * Change a php.ini setting for this request.
	 *
	 * @param string $option
	 * @param string $value
	 * @return bool Whether the setting now has the value.
	 */
	public static function ini_set( string $option, string $value ) : bool {
		if ( ! function_exists( 'ini_set' ) ) {
			return false;
		}

		@ini_set( $option, $value );

		return (string) ini_get( $option ) === $value;
	}

	/**
	 * Keep running when the client disconnects.
	 *
	 * @return bool Whether it is now ignored.
	 */
	public static function ignore_user_abort() : bool {
		if ( ! function_exists( 'ignore_user_abort' ) ) {
			return false;
		}

		@ignore_user_abort( true );

		return (bool) ignore_user_abort();
	}

	/**
	 * Give a request that does long work room to finish it: time, and no
	 * stopping when the browser tab closes.
	 *
	 * @param int $seconds Time limit; 0 for none.
	 * @return void
	 */
	public static function allow_long_work( int $seconds ) : void {
		self::set_time_limit( $seconds );
		self::ignore_user_abort();
	}
}