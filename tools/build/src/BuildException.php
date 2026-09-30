<?php
/**
 * Build exception class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build;

/**
 * Thrown when a build step cannot continue.
 */
final class BuildException extends \RuntimeException {}