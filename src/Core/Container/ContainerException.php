<?php
/**
 * Container-specific exception type.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Core\Container
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Core\Container;

use RuntimeException;

/**
 * Thrown when the container cannot resolve a service.
 *
 * Kept distinct from a bare RuntimeException so the container can tell its
 * own resolution failures apart from an unrelated RuntimeException that an
 * application class happens to throw from inside its own constructor. Without
 * that distinction, an already-wrapped failure deep in a dependency chain and
 * a fresh, unrelated failure look identical to the code trying to decide
 * whether to add context — which is exactly how chain context used to get
 * silently dropped on the way back up.
 */
class ContainerException extends RuntimeException {}