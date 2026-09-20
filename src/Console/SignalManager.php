<?php
declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

/**
 * Manages process-level OS signal listeners.
 */
class SignalManager {

    /**
     * Default set of signals monitored for interactive CLI sessions.
     */
    public const DEFAULT_SIGNALS = [
        'SIGINT',   // Ctrl+C (Interrupt)
        'SIGTERM',  // Termination request
        'SIGHUP',   // Terminal line hangup / disconnect
        'SIGQUIT',  // Ctrl+\ (Quit)
        'SIGWINCH', // Window resize
        'SIGTSTP',  // Ctrl+Z (Suspend process)
        'SIGCONT',  // Resumed from background (fg)
    ];

    /**
     * Registered signal listeners, indexed by signal constant, then by a
     * canonical identity key computed from the callback itself (see
     * callback_key()) rather than by sequential position.
     *
     * Keying by identity — instead of a plain list scanned with === —
     * means remove_listener() is an O(1) unset() rather than an O(n)
     * scan, and re-registering the same callback overwrites its entry
     * instead of adding a second one that would fire twice.
     *
     * This does NOT make two separately-defined closures with identical
     * bodies equal — that's not an implementation gap, it's inherent:
     * two closure objects are different values no matter how alike
     * their code looks, so removing one requires passing the exact
     * closure instance that was registered (same as before). What
     * changes is everything else: string callables, and array callables
     * ([$obj, 'method'] / [Class::class, 'method']), are now matched by
     * stable identity rather than by how the array literal happens to be
     * constructed.
     *
     * @var array<int, array<string, callable>>
     */
    private array $listeners = [];

    /**
     * @var array<int, bool> Set of signals already hooked into pcntl_signal.
     */
    private array $registered_signals = [];

    /**
     * @var bool Whether pcntl_async_signals has been enabled.
     */
    private bool $async_enabled = false;

    public function __construct( private Terminal $terminal ) {}

    /**
     * Check if signal handling is supported in this environment.
     */
    public function is_supported(): bool {
        return ! $this->terminal->is_windows()
            && extension_loaded( 'pcntl' )
            && $this->terminal->function_available( 'pcntl_signal' )
            && $this->terminal->function_available( 'pcntl_async_signals' );
    }

    /**
     * Register a default or custom set of OS signal handlers.
     *
     * @param string[]|int[] $signals Signal names (e.g. ['SIGINT', 'SIGWINCH']) or integers.
     * @return bool
     */
    public function register( array $signals = self::DEFAULT_SIGNALS ): bool {
        if ( ! $this->is_supported() ) {
            return false;
        }

        if ( ! $this->async_enabled ) {
            pcntl_async_signals( true );
            $this->async_enabled = true;
        }

        foreach ( $signals as $sig ) {
            $signo = $this->resolve_signal( $sig );

            if ( null !== $signo && ! isset( $this->registered_signals[ $signo ] ) ) {
                pcntl_signal( $signo, [ $this, 'handle_signal' ] );
                $this->registered_signals[ $signo ] = true;
            }
        }

        return true;
    }

    /**
     * Attach a listener callback to a specific signal.
     * Automatically hooks the signal in pcntl if supported.
     *
     * Registering the same callback again for the same signal (by
     * identity — see callback_key()) replaces its existing entry rather
     * than adding a second one, so a callback can never end up firing
     * twice per dispatch from a duplicate on() call.
     *
     * @param int|string $signal Signal constant (e.g. SIGWINCH) or name ('SIGWINCH').
     * @param callable   $callback
     * @return static
     */
    public function on( int|string $signal, callable $callback ): static {
        $signo = $this->resolve_signal( $signal );

        if ( null === $signo ) {
            return $this;
        }

        // Auto-register with pcntl if supported and not already hooked
        if ( $this->is_supported() && ! isset( $this->registered_signals[ $signo ] ) ) {
            if ( ! $this->async_enabled ) {
                pcntl_async_signals( true );
                $this->async_enabled = true;
            }
            pcntl_signal( $signo, [ $this, 'handle_signal' ] );
            $this->registered_signals[ $signo ] = true;
        }

        $this->listeners[ $signo ][ $this->callback_key( $callback ) ] = $callback;
        return $this;
    }

    /**
     * Remove a specific callable listener from a signal.
     *
     * Matches by the same identity key on() stores under (see
     * callback_key()), not by array position — an O(1) removal that
     * works reliably for string and array callables regardless of how
     * the caller's array literal was constructed, as long as it points
     * at the same underlying function/object+method. A closure can only
     * be removed by passing the exact instance that was registered.
     *
     * @param int|string $signal Signal constant or name.
     * @param callable   $callback The callback to remove.
     * @return static
     */
    public function remove_listener( int|string $signal, callable $callback ): static {
        $signo = $this->resolve_signal( $signal );

        if ( null === $signo || empty( $this->listeners[ $signo ] ) ) {
            return $this;
        }

        unset( $this->listeners[ $signo ][ $this->callback_key( $callback ) ] );

        return $this;
    }

    /**
     * Remove all listeners for a given signal or clear all listeners across all signals.
     *
     * @param int|string|null $signal Signal constant/name, or null to clear all.
     * @return static
     */
    public function off( int|string|null $signal = null ): static {
        if ( null === $signal ) {
            $this->listeners = [];
            return $this;
        }

        $signo = $this->resolve_signal( $signal );

        if ( null !== $signo ) {
            unset( $this->listeners[ $signo ] );
        }

        return $this;
    }

    /**
     * Manually trigger/dispatch a signal event programmatically.
     * Useful for synthetic testing, simulating SIGWINCH/SIGINT, or forced redraws.
     *
     * @param int|string $signal Signal constant or name (e.g., 'SIGWINCH' or SIGWINCH).
     * @param mixed      $siginfo Optional metadata to pass along to listeners.
     * @return static
     */
    public function dispatch( int|string $signal, mixed $siginfo = null ): static {
        $signo = $this->resolve_signal( $signal );

        if ( null !== $signo ) {
            $this->handle_signal( $signo, $siginfo );
        }

        return $this;
    }

    /**
     * Internal handler executed by pcntl_signal or manually via dispatch().
     *
     * @param int   $signal
     * @param mixed $siginfo
     * @return void
     */
    public function handle_signal( int $signal, mixed $siginfo = null ): void {
        if ( empty( $this->listeners[ $signal ] ) ) {
            return;
        }

        foreach ( $this->listeners[ $signal ] as $callback ) {
            $callback( $signal, $siginfo );
        }
    }

    /**
     * Resolve a signal constant or string name to its integer signal number.
     *
     * @param int|string $signal
     * @return int|null
     */
    private function resolve_signal( int|string $signal ): ?int {
        if ( is_int( $signal ) ) {
            return $signal;
        }

        return defined( $signal ) ? constant( $signal ) : null;
    }

    /**
     * Compute a canonical identity key for any callable shape, so
     * listener storage can be keyed by "what this callback actually is"
     * instead of relying on === against a possibly-freshly-constructed
     * value.
     *
     *   - string callable   ('my_function')            -> the function name itself, which
     *                                                       is already a stable value.
     *   - array, instance    ([$obj, 'method'])          -> spl_object_id($obj) + the method
     *                                                       name. Stable for as long as $obj
     *                                                       is referenced — and it is, since
     *                                                       a reference to it lives inside
     *                                                       the stored callable itself.
     *   - array, static      ([Class::class, 'method'])  -> the class name + method name.
     *   - Closure / invokable object                     -> spl_object_id($callback). Two
     *                                                       separately-created closures always
     *                                                       get different ids, by design — see
     *                                                       the $listeners property docblock.
     *
     * @param callable $callback
     * @return string
     */
    private function callback_key( callable $callback ): string {
        if ( is_string( $callback ) ) {
            return 'function:' . $callback;
        }

        if ( is_array( $callback ) ) {
            [ $target, $method ] = $callback;

            return is_object( $target )
                ? 'method:' . spl_object_id( $target ) . '::' . $method
                : 'static:' . $target . '::' . $method;
        }

        // Closure or invokable object.
        return 'object:' . spl_object_id( $callback );
    }
}