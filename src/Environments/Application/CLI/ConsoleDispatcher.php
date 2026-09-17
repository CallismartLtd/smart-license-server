<?php
/**
 * The console dispatcher class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\CLI
 * @since 0.2.0
 */

namespace SmartLicenseServer\Environments\Application\CLI;

use Callismart\DBPrism\Database;
use SmartLicenseServer\Console\AbstractCommandRouter;
use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\ConsoleInput;
use SmartLicenseServer\Console\ConsoleOutput;
use SmartLicenseServer\Console\HistoryAwareInput;
use SmartLicenseServer\Console\LogoMode;
use SmartLicenseServer\Console\Runners\InteractiveShell;
use SmartLicenseServer\Console\Runners\NonInteractiveRunner;
use SmartLicenseServer\Console\Runners\RunnerInterface;
use SmartLicenseServer\Console\SignalManager;
use SmartLicenseServer\Console\Terminal;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Class ConsoleDispatcher
 *
 * Manages stream contexts, checks positional argument tokens, and dispatches
 * to either an InteractiveShell or NonInteractiveRunner.
 *
 * @package SmartLicenseServer\Environments\Application\CLI
 * @since 0.2.0
 */
class ConsoleDispatcher {

    /**
     * The currently resolved console runner instance.
     */
    protected RunnerInterface $runner;

    /**
     * Standard input stream.
     * 
     * @var resource
     */
    protected mixed $stdin = \STDIN;

    /**
     * Standard output stream.
     * 
     * @var resource
     */
    protected mixed $stdout = \STDOUT;

    /**
     * Standard error output stream.
     * 
     * @var resource
     */
    protected mixed $stderr = \STDERR;

    /**
     * Tokenized `Argument Vector($argv)` array.
     * 
     * @var string[]
     */
    protected array $tokens;

    /**
     * Class constructor.
     *
     * @param CommandRegistry $registry
     * @param Terminal        $terminal
     * @param SignalManager   $signal
     * @param ConsoleInput    $input
     * @param ConsoleOutput   $output
     * @param Guard           $guard
     * @param Database        $db
     */
    public function __construct(
        protected CommandRegistry $registry,
        protected Terminal $terminal,
        protected SignalManager $signal,
        protected ConsoleInput $input,
        protected ConsoleOutput $output,
        protected Guard $guard,
        protected Database $db
    ) {
        $this->tokens = $_SERVER['argv'] ?? [];
    }

    /**
     * Resolves live CLI dynamics to execute a command.
     *
     * @return RunnerInterface
     */
    public function dispatch() : RunnerInterface {
        $this->build_runner();

        if ( is_subclass_of( $this->runner, AbstractCommandRouter::class ) ) {
            $this->output->set_verbosity(
                $this->runner->resolve_verbosity( $this->tokens )
            );
        }

        return $this->runner;
    }

    /**
     * Build the appropriate runner for this invocation.
     *
     * One-shot dispatch (`smliser <command> ...`) gets a NonInteractiveRunner.
     * An invocation with no tokens, or with only verbosity/quiet flags
     * (`smliser`, `smliser -v`, `smliser -vvv`, `smliser --verbose`,
     * `smliser -q`, or any combination of these), gets the interactive shell.
     *
     * @return RunnerInterface
     */
    protected function build_runner() : RunnerInterface {

        if ( $this->is_interactive_invocation( $this->tokens ) ) {

            $this->runner = new InteractiveShell(
                registry: $this->registry,
                io: $this->build_shell_input( $this->input, $this->terminal ),
                output: $this->output,
                terminal: $this->terminal,
                signal: $this->signal,
                logo_mode: LogoMode::from_env(),
                guard: $this->guard,
                db: $this->db
            );
        } else {
            $this->runner = new NonInteractiveRunner(
                registry: $this->registry,
                tokens: $this->tokens,
                io: $this->input,
                output: $this->output,
                terminal: $this->terminal,
                signal: $this->signal,
                guard: $this->guard
            );
        }

        return $this->runner;
    }

    /**
     * Determine whether this invocation should enter the interactive shell.
     *
     * True only when `--interactive` or `-i` is explicitly present among the
     * tokens after the script path, AND stdin is a real, attached terminal.
     * The explicit flag alone is not enough — a non-TTY stdin (piped input,
     * cron, CI, `< file`) falls through to non-interactive dispatch even
     * with `--interactive` present, since there'd be no one there to type
     * into the shell. Every other invocation is non-interactive, including
     * a bare `smliser` with no tokens at all.
     *
     * @param array<int, string> $tokens Raw CLI tokens, tokens[0] being the script path.
     * @return bool
     */
    protected function is_interactive_invocation( array $tokens ): bool {

        $count  = count( $tokens );

        for ( $i = 1; $i < $count; $i++ ) {

            $token = $tokens[ $i ];

            if ( ! is_string( $token ) ) {
                continue;
            }

            if ( '--interactive' === $token || '-i' === $token ) {
                return $this->terminal->is_tty( $this->stdin );
            }
        }

        return false;
    }
    /**
     * Wrap the base ConsoleInput with history-aware (↑/↓) reading for
     * the interactive shell.
     *
     * @param ConsoleInput $input
     * @param Terminal     $terminal
     * @return HistoryAwareInput
     */
    protected function build_shell_input( ConsoleInput $input, Terminal $terminal ) : HistoryAwareInput {
        return new HistoryAwareInput( $input, $terminal, $this->shell_history_path() );
    }

    /**
     * Absolute path to the interactive shell's persisted history file.
     *
     * @return string
     */
    protected function shell_history_path() : string {
        return \SMLISER_STORAGE_DIR . 'logs/.shell_history';
    }

    /**
     * Set/override standard input stream.
     *
     * @param mixed $stream Stream resource.
     * @return static
     */
    public function with_stdin( mixed $stream ) : static {
        $this->stdin = $this->validate_stream( $stream, 'stdin' );

        return $this;
    }

    /**
     * Set/override standard output stream.
     *
     * @param mixed $stream Stream resource.
     * @return static
     */
    public function with_stdout( mixed $stream ) : static {
        $this->stdout = $this->validate_stream( $stream, 'stdout' );

        return $this;
    }

    /**
     * Set/override standard error output stream.
     *
     * @param mixed $stream Stream resource.
     * @return static
     */
    public function with_stderr( mixed $stream ) : static {
        $this->stderr = $this->validate_stream( $stream, 'stderr' );

        return $this;
    }

    /**
     * Override all streams at once.
     *
     * @param mixed $stdin  Stream resource for STDIN.
     * @param mixed $stdout Stream resource for STDOUT.
     * @param mixed $stderr Stream resource for STDERR.
     * @return static
     */
    public function with_streams( mixed $stdin, mixed $stdout, mixed $stderr ) : static {
        return $this->with_stdin( $stdin )
            ->with_stdout( $stdout )
            ->with_stderr( $stderr );
    }

    /**
     * Validates that the provided argument is an active stream resource.
     *
     * @param mixed  $stream Stream variable to validate.
     * @param string $name   Name of stream for exception context.
     * @return resource
     * @throws \InvalidArgumentException If stream is invalid.
     */
    protected function validate_stream( mixed $stream, string $name ) {
        if ( ! is_resource( $stream ) || 'stream' !== get_resource_type( $stream ) ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid %s stream provided. Expected stream resource, given: %s.',
                    $name,
                    get_debug_type( $stream )
                )
            );
        }

        return $stream;
    }
}