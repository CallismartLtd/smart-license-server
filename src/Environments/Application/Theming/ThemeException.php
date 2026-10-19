<?php
/**
 * Theme exception class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

/**
 * A theme cannot be loaded or cannot render a page: a missing or invalid
 * theme.json, an unknown parent, a missing template, partial or asset.
 */
final class ThemeException extends \RuntimeException {}