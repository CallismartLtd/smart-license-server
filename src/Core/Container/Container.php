<?php
/**
 * Dependency Injection Container.
 *
 * Provides explicit dependency registration and automatic constructor
 * dependency resolution for concrete classes.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Core\Container
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use RuntimeException;
use Throwable;

/**
 * Dependency Injection container with constructor autowiring.
 *
 * Concrete classes are automatically resolvable without explicit registration.
 * Interfaces and abstract classes must be explicitly bound to an implementation
 * or factory.
 */
final class Container
{
    /**
     * Registered service definitions.
     *
     * @var array<string, Closure|object>
     */
    private array $definitions = [];

    /**
     * Shared service definitions.
     *
     * @var array<string, Closure|object>
     */
    private array $shared = [];

    /**
     * Resolved shared service instances.
     *
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Service aliases.
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Services currently being resolved, in resolution order.
     *
     * Used to detect circular dependencies and, since it is the live
     * resolution path at any point in time, to trace a failure back to the
     * top-level service that triggered it.
     *
     * @var array<string, true>
     */
    private array $resolving = [];

    /**
     * Register a shared service.
     *
     * The definition is resolved once and the resulting instance is reused
     * for subsequent calls to get(). Omit $definition to let the container
     * autowire the concrete class on first resolution.
     *
     * @param class-string $id
     * @param Closure|object|null $definition
     *
     * @return void
     */
    public function singleton( string $id, ?object $definition = null ): void {
        $id = $this->normalize_id( $id );

        $this->shared[$id] = $definition ?? fn() => $this->autowire( $id );

        unset( $this->instances[$id] );
    }

    /**
     * Register a transient service factory.
     *
     * The factory is invoked every time the service is requested. Omit
     * $factory to let the container autowire the concrete class on each
     * resolution.
     *
     * @param class-string $id
     * @param Closure|null $factory
     *
     * @return void
     */
    public function factory( string $id, ?Closure $factory = null ): void {
        $id = $this->normalize_id( $id );

        $this->definitions[$id] = $factory ?? fn() => $this->autowire( $id );

        unset( $this->shared[$id], $this->instances[$id] );
    }

    /**
     * Register a service as a shared concrete object.
     *
     * @param class-string $id
     * @param object $service
     *
     * @return void
     */
    public function set( string $id, object $service ): void {
        $id = $this->normalize_id( $id );

        $this->shared[$id] = $service;

        unset( $this->definitions[$id], $this->instances[$id] );
    }

    /**
     * Register an alias for another service.
     *
     * @param class-string $alias
     * @param class-string $id
     *
     * @return void
     */
    public function alias( string $alias, string $id ): void {
        $alias = $this->normalize_id( $alias );
        $id    = $this->normalize_id( $id );

        if ( $alias === $id ) {
            throw new RuntimeException(
                "Cannot create an alias from '{$alias}' to itself."
            );
        }

        $this->aliases[$alias] = $id;
    }

    /**
     * Determine whether a service can be resolved.
     *
     * Concrete classes are considered resolvable through autowiring even when
     * they have not been explicitly registered.
     *
     * @param class-string $id
     *
     * @return bool
     */
    public function has( string $id ): bool {
        $id = $this->normalize_id( $id );

        if ( isset( $this->definitions[$id] ) ) {
            return true;
        }

        if ( isset( $this->shared[$id] ) ) {
            return true;
        }

        if ( isset( $this->aliases[$id] ) ) {
            return $this->has( $this->aliases[$id] );
        }

        if ( ! class_exists( $id ) ) {
            return false;
        }

        try {
            $reflection = new ReflectionClass( $id );

            return $reflection->isInstantiable();
        } catch ( ReflectionException ) {
            return false;
        }
    }

    /**
     * Resolve a service.
     *
     * Concrete classes are automatically autowired when no explicit
     * definition exists.
     *
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     *
     * @throws ContainerException When the service cannot be resolved. The
     *         message includes the full resolution chain that led to the
     *         failure (e.g. "resolving: A -> B -> C -> D"), not just the
     *         class that ultimately failed.
     */
    public function get( string $id ): object {
        /** @var class-string<T> $id */
        $id = $this->normalize_id( $id );

        $id = $this->resolve_alias( $id );

        if ( isset( $this->instances[$id] ) ) {
            /** @var T */
            return $this->instances[$id];
        }

        if ( isset( $this->shared[$id] ) ) {
            $instance = $this->resolve_definition(
                $id,
                $this->shared[$id]
            );

            $this->instances[$id] = $instance;

            /** @var T */
            return $instance;
        }

        if ( isset( $this->definitions[$id] ) ) {
            /** @var T */
            return $this->resolve_definition(
                $id,
                $this->definitions[$id]
            );
        }

        /** @var T */
        return $this->autowire( $id );
    }

    /**
     * Remove a registered service.
     *
     * This does not remove aliases pointing to the service.
     *
     * @param class-string $id
     *
     * @return void
     */
    public function remove( string $id ): void {
        $id = $this->normalize_id( $id );

        unset(
            $this->definitions[$id],
            $this->shared[$id],
            $this->instances[$id]
        );
    }

    /**
     * Remove an alias.
     *
     * @param class-string $alias
     *
     * @return void
     */
    public function remove_alias( string $alias ): void {
        unset( $this->aliases[$this->normalize_id( $alias )] );
    }

    /**
     * Clear all resolved shared instances.
     *
     * Registered definitions remain intact.
     *
     * @return void
     */
    public function reset(): void {
        $this->instances = [];
    }

    /**
     * Resolve a service definition.
     *
     * @param string $id
     * @param Closure|object $definition
     *
     * @return object
     */
    private function resolve_definition(
        string $id,
        object $definition
    ): object {
        $service = $this->guarded( $id, function () use ( $id, $definition ) {
            try {
                return $definition instanceof Closure ? $definition( $this ) : $definition;
            } catch ( Throwable $exception ) {
                if ( $exception instanceof ContainerException ) {
                    throw $exception;
                }

                throw $this->unresolvable(
                    "Failed to resolve service '{$id}': {$exception->getMessage()}",
                    $exception
                );
            }
        } );

        if ( ! is_object( $service ) ) {
            throw $this->unresolvable(
                "Service definition '{$id}' must resolve to an object; " .
                get_debug_type( $service ) . ' returned.'
            );
        }

        return $service;
    }

    /**
     * Automatically construct a concrete class.
     *
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function autowire( string $id ): object {
        if ( ! class_exists( $id ) ) {
            throw $this->unresolvable(
                "Unable to resolve '{$id}': the class does not exist."
            );
        }

        try {
            $reflection = new ReflectionClass( $id );
        } catch ( ReflectionException $exception ) {
            throw $this->unresolvable(
                "Unable to reflect service '{$id}'.",
                $exception
            );
        }

        if ( ! $reflection->isInstantiable() ) {
            throw $this->unresolvable(
                "Unable to autowire '{$id}': the class is not instantiable. " .
                'Interfaces and abstract classes must be explicitly bound.'
            );
        }

        /** @var T */
        return $this->guarded( $id, function () use ( $id, $reflection ) {
            try {
                $constructor = $reflection->getConstructor();

                if ( $constructor === null ) {
                    return $reflection->newInstance();
                }

                $arguments = array_map(
                    fn( ReflectionParameter $parameter ) => $this->resolve_parameter( $parameter ),
                    $constructor->getParameters()
                );

                return $reflection->newInstanceArgs( $arguments );
            } catch ( Throwable $exception ) {
                if ( $exception instanceof ContainerException ) {
                    throw $exception;
                }

                throw $this->unresolvable(
                    "Unable to autowire '{$id}': {$exception->getMessage()}",
                    $exception
                );
            }
        } );
    }

    /**
     * Resolve a constructor parameter.
     *
     * @param ReflectionParameter $parameter
     *
     * @return object
     */
    private function resolve_parameter( ReflectionParameter $parameter ): object {
        $type = $parameter->getType();

        if ( $type === null ) {
            throw $this->unresolvable(
                $this->format_unresolvable_parameter_message(
                    $parameter,
                    'has no type declaration'
                )
            );
        }

        if ( $type instanceof ReflectionUnionType ) {
            throw $this->unresolvable(
                $this->format_unresolvable_parameter_message(
                    $parameter,
                    'uses a union type'
                ) .
                ' Union-typed dependencies must be explicitly registered.'
            );
        }

        if ( ! $type instanceof ReflectionNamedType ) {
            throw $this->unresolvable(
                $this->format_unresolvable_parameter_message(
                    $parameter,
                    'uses an unsupported type declaration'
                )
            );
        }

        if ( $type->isBuiltin() ) {
            if ( $parameter->isDefaultValueAvailable() ) {
                throw $this->unresolvable(
                    $this->format_unresolvable_parameter_message(
                        $parameter,
                        'is a built-in type with a default value'
                    ) .
                    ' The default value cannot be injected automatically; ' .
                    'consider registering a factory for this service.'
                );
            }

            throw $this->unresolvable(
                $this->format_unresolvable_parameter_message(
                    $parameter,
                    'is a built-in type'
                ) .
                ' Scalar and built-in constructor dependencies must be ' .
                'provided explicitly through a factory.'
            );
        }

        $dependency = $type->getName();

        /*
         * Allow the container itself to be injected without an explicit
         * registration.
         */
        if ( is_a( $this::class, $dependency, true ) ) {
            return $this;
        }

        /*
         * Nullable object dependencies are still resolvable when their class
         * can be resolved. We intentionally do not inject null merely because
         * the dependency is nullable.
         */
        if ( ! $this->has( $dependency ) ) {
            if ( $parameter->isDefaultValueAvailable() ) {
                return $parameter->getDefaultValue();
            }

            throw $this->unresolvable(
                $this->format_unresolvable_parameter_message(
                    $parameter,
                    "cannot resolve dependency '{$dependency}'"
                )
            );
        }

        return $this->get( $dependency );
    }

    /**
     * Resolve an alias chain.
     *
     * @param class-string $id
     *
     * @return class-string
     */
    private function resolve_alias( string $id ): string {
        $visited = [];

        while ( isset( $this->aliases[$id] ) ) {
            if ( isset( $visited[$id] ) ) {
                $chain = implode(
                    ' -> ',
                    array_keys( $visited )
                );

                throw new ContainerException(
                    "Circular service alias detected: {$chain} -> {$id}."
                );
            }

            $visited[$id] = true;
            $id            = $this->aliases[$id];
        }

        return $id;
    }

    /**
     * Run a resolution callback guarded against circular dependencies.
     *
     * Marks $id as "resolving" for the duration of $work, always clearing
     * that mark afterward — this is the shared bookkeeping that both
     * resolve_definition() and autowire() need around otherwise-different
     * failure handling.
     *
     * @template T
     *
     * @param string $id
     * @param Closure(): T $work
     *
     * @return T
     */
    private function guarded( string $id, Closure $work ): mixed {
        $this->begin_resolution( $id );

        try {
            return $work();
        } finally {
            $this->end_resolution( $id );
        }
    }

    /**
     * Begin resolving a service and detect circular dependencies.
     *
     * @param string $id
     *
     * @return void
     */
    private function begin_resolution( string $id ): void {
        if ( isset( $this->resolving[$id] ) ) {
            $chain = array_keys( $this->resolving );
            $chain[] = $id;

            throw new ContainerException(
                'Circular dependency detected: ' .
                implode( ' -> ', $chain ) . '.'
            );
        }

        $this->resolving[$id] = true;
    }

    /**
     * End resolution of a service.
     *
     * @param string $id
     *
     * @return void
     */
    private function end_resolution( string $id ): void {
        unset( $this->resolving[$id] );
    }

    /**
     * Build a resolution-failure exception carrying the live resolution
     * chain.
     *
     * Must be called while the failing service (and its ancestry, if any) is
     * still marked as "resolving" — i.e. from inside the try/catch that
     * detected the failure, before guarded()'s finally block unwinds
     * $this->resolving. That is what lets a failure four levels deep report
     * "resolving: A -> B -> C -> D" instead of just "D".
     *
     * @param string $message
     * @param Throwable|null $previous
     *
     * @return ContainerException
     */
    private function unresolvable( string $message, ?Throwable $previous = null ): ContainerException {
        $chain = implode( ' -> ', array_keys( $this->resolving ) );

        $full_message = $chain === ''
            ? $message
            : "{$message} (resolving: {$chain})";

        /*
         * A wrapped exception's own getFile()/getLine() always points here,
         * inside Container.php — never the actual throw site, since that is
         * where `new ContainerException(...)` runs. $previous is the raw
         * exception we caught, so its file/line IS the real throw site. Bake
         * it into the message so it survives however this gets logged,
         * rather than relying on the logger to walk getPrevious() itself.
         */
        if ( $previous !== null ) {
            $full_message .= " [thrown at {$previous->getFile()}:{$previous->getLine()}]";
        }

        return new ContainerException( $full_message, 0, $previous );
    }

    /**
     * Normalize a service identifier.
     *
     * @param string $id
     *
     * @return string
     */
    private function normalize_id( string $id ): string {
        return ltrim( $id, '\\' );
    }

    /**
     * Format an error for an unresolvable constructor parameter.
     *
     * @param ReflectionParameter $parameter
     * @param string $reason
     *
     * @return string
     */
    private function format_unresolvable_parameter_message(
        ReflectionParameter $parameter,
        string $reason
    ): string {
        $declaring_class = $parameter->getDeclaringClass();

        $class = $declaring_class !== null
            ? $declaring_class->getName()
            : 'unknown class';

        return sprintf(
            "Unable to resolve parameter \$%s of %s::__construct(): %s.",
            $parameter->getName(),
            $class,
            $reason
        );
    }
}