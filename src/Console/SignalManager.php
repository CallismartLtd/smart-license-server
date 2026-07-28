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
     * @var array<int, array<int, callable>> Registered signal listeners indexed by signal constant.
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

        $this->listeners[ $signo ][] = $callback;
        return $this;
    }

    /**
     * Remove a specific callable listener from a signal.
     *
     * @param int|string $signal Signal constant or name.
     * @param callable   $callback The exact closure/callable instance to remove.
     * @return static
     */
    public function remove_listener( int|string $signal, callable $callback ): static {
        $signo = $this->resolve_signal( $signal );

        if ( null === $signo || empty( $this->listeners[ $signo ] ) ) {
            return $this;
        }

        foreach ( $this->listeners[ $signo ] as $index => $registered ) {
            if ( $registered === $callback ) {
                unset( $this->listeners[ $signo ][ $index ] );
            }
        }

        // Re-index array keys to keep list clean
        $this->listeners[ $signo ] = array_values( $this->listeners[ $signo ] );

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
}