<?php
/**
 * UpdateException class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Update
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Update;

use RuntimeException;

/**
 * An update that cannot go ahead, or could not complete.
 *
 * The message is written for the person running the update: what went
 * wrong and, where there is one, what to do about it.
 */
class UpdateException extends RuntimeException {}