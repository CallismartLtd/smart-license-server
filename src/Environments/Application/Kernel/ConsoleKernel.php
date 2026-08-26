<?php
/**
 * Console kernel class file.
 * 
 * @author Callistus Nwachukwu.
 * @package SmartLicenseServer
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\Application\Kernel;

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
use SmartLicenseServer\Environments\Application\Auth\ConsoleIdentityProvider;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Console kernel class coordinates console command lifecycle.
 */
class ConsoleKernel extends Kernel {

    /**
     * The current CLI runner.
     * 
     * @var RunnerInterface
     */
    protected RunnerInterface $runner;

    /**
     * Command registry instance.
     * 
     * @var CommandRegistry
     */
    protected CommandRegistry $registry;

    /**
     * Terminal abstraction instance.
     * 
     * @var Terminal
     */
    protected Terminal $terminal;

    /**
     * Signal manager instance.
     * 
     * @var SignalManager
     */
    protected SignalManager $signal;

    /**
     * Console output instance.
     * 
     * @var ConsoleOutput
     */
    protected ConsoleOutput $output;

    /**
     * Console input instance.
     * 
     * @var ConsoleInput
     */
    protected ConsoleInput $input;

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
     * Exit code.
     * 
     * @var int
     */
    protected int $exit_code = 0;

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
     * {@inheritdoc}
     */
    public function boot() : static {
        ( new ConsoleIdentityProvider( new Guard ) )->authenticate();
        
        $this->init_runner();

        $this->build_runner();

        if ( is_subclass_of( $this->runner, AbstractCommandRouter::class ) ) {
            $this->output->set_verbosity(
                $this->runner->resolve_verbosity( $this->tokens )
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run() : static {
        $this->exit_code = $this->runner->init();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function terminate() : never {
        exit( $this->exit_code );
    }

    /*
    |--------------------------
    | INTERNAL BOOT HELPERS
    |--------------------------
    */

    /**
     * Initialize the runner dependencies.
     * 
     * @return void
     */
    protected function init_runner() : void {
        $this->registry = CommandRegistry::instance();
        $this->terminal = new Terminal();
        $this->signal   = new SignalManager( $this->terminal );
        $this->output   = new ConsoleOutput( $this->terminal, $this->stdout, $this->stderr );
        $this->input    = new ConsoleInput( $this->terminal, $this->stdin, $this->stdout );
        $this->tokens   = $this->environment->request()->server()['argv'];
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

    /**
     * Build the appropriate runner for this invocation.
     *
     * One-shot dispatch (`smliser <command> ...`) gets a NonInteractiveRunner.
     * No command argument at all (`smliser`) gets the interactive shell.
     *
     * @return RunnerInterface
     */
    protected function build_runner() : RunnerInterface {
        $script_name = $this->tokens[0] ?? 'smliser';

        if ( isset( $this->tokens[1] ) ) {
            $this->runner = new NonInteractiveRunner( 
                registry: $this->registry,
                tokens: $this->tokens, 
                io: $this->input,
                output: $this->output,
                terminal: $this->terminal,
                script_name: basename( $script_name ),
                signal: $this->signal
            );
        } else {
            $this->runner = new InteractiveShell(
                registry: $this->registry,
                io: $this->build_shell_input( $this->input, $this->terminal ),
                output: $this->output,
                terminal: $this->terminal,
                script_name: basename( $script_name ),
                signal: $this->signal,
                logo_mode: LogoMode::from_env()
            );
        }

        return $this->runner;
    }

    /**
     * Wrap the base ConsoleInput with history-aware (↑/↓) reading for
     * the interactive shell. NonInteractiveRunner does not need this — a one-shot
     * invocation has no session to navigate history within.
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
}