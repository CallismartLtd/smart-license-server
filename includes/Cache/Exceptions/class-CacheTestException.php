<?php
/**
 * Cache test exception class file.
 *
 * @author Callistus Nwachukwu
 * @since  0.2.0
 */
declare( strict_types = 1 );
namespace SmartLicenseServer\Cache\Exceptions;

/**
 * Thrown by a cache adapter's test() method when a connection probe or
 * round-trip operation fails.
 *
 * Each throw site supplies a human-readable message that describes the
 * exact failure point so it can be surfaced directly to the admin without
 * further translation.
 *
 * Extends RuntimeException because test failures represent operational
 * conditions (unreachable server, bad credentials, misconfigured path)
 * rather than programming errors.
 *
 * @since 0.2.0
 */
class CacheTestException extends \RuntimeException {}