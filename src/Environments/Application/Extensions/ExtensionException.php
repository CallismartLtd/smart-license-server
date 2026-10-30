<?php
/**
 * Extension exception class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Extensions
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Extensions;

/**
 * An extension cannot be loaded, enabled or disabled: a missing or invalid
 * extension.json, an unsupported app version, an unmet dependency, a
 * namespace clash, or an enabled-list that cannot be written.
 */
final class ExtensionException extends \RuntimeException {}
