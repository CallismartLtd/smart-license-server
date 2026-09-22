<?php
/**
 * Signal info value object file.
 *
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

use DateTimeImmutable;

/**
 * A normalized, structured view of a dispatched OS signal.
 *
 * pcntl's raw siginfo array shape differs per signal (SIGCHLD carries
 * pid/uid/status, SIGPOLL carries band/fd, most others carry nothing),
 * and a synthetic dispatch() call may carry no kernel data at all.
 * This object hides that variance: listeners always receive the same
 * shape, with unavailable fields simply null, and can check
 * `synthetic` to know whether the value came from the kernel or was
 * triggered manually (e.g. in tests).
 *
 * Carries both `signal_name` (the canonical SIG* token — stable,
 * searchable, what to grep journald or another system's logs for)
 * and `description` (a plain-English sentence fragment — what an
 * admin should actually read in a log line, without needing to know
 * what SIGHUP means off the top of their head).
 */
final class SignalInfo {

    /**
     * Human-readable descriptions for common POSIX signals.
     *
     * Intentionally short, lowercase sentence fragments so they read
     * naturally inline, e.g. "Received {description} ({signal_name})."
     *
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        'SIGHUP'    => 'terminal hangup or disconnect',
        'SIGINT'    => 'interrupt request (Ctrl+C)',
        'SIGQUIT'   => 'quit request (Ctrl+\\)',
        'SIGILL'    => 'illegal instruction',
        'SIGTRAP'   => 'trace/breakpoint trap',
        'SIGABRT'   => 'abort signal',
        'SIGBUS'    => 'bus error',
        'SIGFPE'    => 'floating-point exception',
        'SIGKILL'   => 'forceful kill (uncatchable)',
        'SIGUSR1'   => 'user-defined signal 1',
        'SIGSEGV'   => 'segmentation fault',
        'SIGUSR2'   => 'user-defined signal 2',
        'SIGPIPE'   => 'broken pipe',
        'SIGALRM'   => 'alarm timer expired',
        'SIGTERM'   => 'termination request',
        'SIGCHLD'   => 'child process state change',
        'SIGCONT'   => 'resume after stop',
        'SIGSTOP'   => 'forceful stop (uncatchable)',
        'SIGTSTP'   => 'suspend request (Ctrl+Z)',
        'SIGTTIN'   => 'background read from terminal',
        'SIGTTOU'   => 'background write to terminal',
        'SIGURG'    => 'urgent socket condition',
        'SIGXCPU'   => 'CPU time limit exceeded',
        'SIGXFSZ'   => 'file size limit exceeded',
        'SIGVTALRM' => 'virtual timer expired',
        'SIGPROF'   => 'profiling timer expired',
        'SIGWINCH'  => 'terminal window resized',
        'SIGPOLL'   => 'I/O now possible',
        'SIGSYS'    => 'bad system call',
    ];

    /**
     * @param int               $signal      Signal number (e.g. SIGTERM).
     * @param string            $signal_name Resolved signal name (e.g. 'SIGTERM').
     * @param string            $description Plain-English description (e.g. 'termination request').
     * @param bool              $synthetic   True if raised via dispatch(), false if from the kernel.
     * @param DateTimeImmutable $received_at When this SignalInfo was constructed.
     * @param int|null          $pid         Sending process ID, when the kernel provides one (e.g. SIGCHLD).
     * @param int|null          $uid         Sending process's real user ID, when provided.
     * @param int|null          $status      Exit/child status, when provided (SIGCHLD).
     * @param int|null          $code        Signal-specific code, when provided.
     * @param int|null          $band        Band event, when provided (SIGPOLL).
     * @param int|null          $fd          File descriptor, when provided (SIGPOLL).
     * @param array<string,mixed> $raw       The untouched original siginfo array from pcntl, if any.
     * @param mixed             $payload     Arbitrary data passed to a synthetic dispatch() call.
     */
    public function __construct(
        public readonly int $signal,
        public readonly string $signal_name,
        public readonly string $description,
        public readonly bool $synthetic,
        public readonly DateTimeImmutable $received_at,
        public readonly ?int $pid = null,
        public readonly ?int $uid = null,
        public readonly ?int $status = null,
        public readonly ?int $code = null,
        public readonly ?int $band = null,
        public readonly ?int $fd = null,
        public readonly array $raw = [],
        public readonly mixed $payload = null,
    ) {}

    /**
     * Build a SignalInfo from a real pcntl siginfo array.
     *
     * @param int    $signal
     * @param string $signal_name
     * @param array<string,mixed> $raw Raw siginfo array as delivered by pcntl.
     * @return self
     */
    public static function from_kernel( int $signal, string $signal_name, array $raw ): self {
        return new self(
            signal:      $signal,
            signal_name: $signal_name,
            description: self::describe( $signal_name ),
            synthetic:   false,
            received_at: new DateTimeImmutable(),
            pid:         isset( $raw['pid'] )    ? (int) $raw['pid']    : null,
            uid:         isset( $raw['uid'] )    ? (int) $raw['uid']    : null,
            status:      isset( $raw['status'] ) ? (int) $raw['status'] : null,
            code:        isset( $raw['code'] )   ? (int) $raw['code']  : null,
            band:        isset( $raw['band'] )   ? (int) $raw['band']  : null,
            fd:          isset( $raw['fd'] )     ? (int) $raw['fd']    : null,
            raw:         $raw,
        );
    }

    /**
     * Build a SignalInfo for a manually-triggered (non-kernel) signal.
     *
     * @param int    $signal
     * @param string $signal_name
     * @param mixed  $payload Arbitrary caller-supplied data for the dispatch() call.
     * @return self
     */
    public static function synthetic( int $signal, string $signal_name, mixed $payload = null ): self {
        return new self(
            signal:      $signal,
            signal_name: $signal_name,
            description: self::describe( $signal_name ),
            synthetic:   true,
            received_at: new DateTimeImmutable(),
            payload:     $payload,
        );
    }

    /**
     * Look up a plain-English description for a signal name.
     *
     * Falls back to a generic, still-honest label for signals not in
     * the table (e.g. real-time signals like SIGRTMIN+3) rather than
     * an empty string, so log output never silently drops the field.
     *
     * @param string $signal_name
     * @return string
     */
    private static function describe( string $signal_name ): string {
        return self::DESCRIPTIONS[ $signal_name ] ?? "signal {$signal_name}";
    }
}