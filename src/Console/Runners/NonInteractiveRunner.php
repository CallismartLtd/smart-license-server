<?php
/**
 * CLI runner class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Runners
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Runners;

use SmartLicenseServer\Console\AbstractCommandRouter;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\OptionParser;
use SmartLicenseServer\Console\SignalManager;
use SmartLicenseServer\Console\Terminal;

/**
 * Plain PHP CLI runner — one-shot dispatch for `smliser <command>
 * [<subcommand>] [args...]`.
 *
 * Supported help patterns:
 *   smliser help              → global command listing
 *   smliser help <command>    → per-command synopsis + help
 *   smliser <command> --help  → same as above (via args_request_help())
 *   smliser <command> -h      → same as above
 */
class NonInteractiveRunner extends AbstractCommandRouter implements RunnerInterface {

    /**
     * @param CommandRegistry $registry
     * @param array<int, string> $tokens The raw argument vector array from the CLI
     *                                 entry point, script name included
     *                                 at index 0.
     * @param InputInterface  $io
     * @param OutputInterface $output
     * @param Terminal $terminal
     */
    public function __construct(
        CommandRegistry $registry,
        private array $tokens,
        InputInterface $io,
        OutputInterface $output,
        Terminal $terminal,
        SignalManager $signal,
        string $script_name,
    ) {
        parent::__construct(
            registry: $registry,
            io: $io,
            output: $output, 
            terminal: $terminal, 
            script_name: $script_name, 
            signal: $signal
        );
    }

    /*
    |----------------------
    | RunnerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     */
    public function init(): int {
        // Strip the script name (token[0]) before splitting — everything
        // AbstractCommandRouter deals with is relative to the command
        // name, not the invoking script.
        [ $command, $subcommand, $args ] = $this->split_invocation( array_slice( $this->tokens, 1 ) );

        $option_parser = new OptionParser();
        $parsed        = $option_parser->parse( $args );

        $command_input = new CommandInput(
            (array) ( $parsed['arguments'] ?? [] ),
            (array) ( $parsed['options'] ?? [] )
        );

        return $this->route_command( $command_input, $command, $subcommand );
    }

    /**
     * {@inheritdoc}
     */
    protected function print_contextual_help(): void {
        $this->print_global_help();
    }
}