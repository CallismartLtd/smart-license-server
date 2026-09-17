<?php
/**
 * Script name value object file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Console;

/**
 * Class ScriptName
 *
 * Represents the CLI invocation binary or script name (e.g., $argv[0]).
 *
 * @package SmartLicenseServer\Console
 * @since 0.2.0
 */
final readonly class ScriptName {

    /**
     * Class constructor.
     *
     * @param string $value The script invocation name.
     */
    public function __construct( public string $value ) {}

    /**
     * Resolve default invocation name from global $argv server variable.
     *
     * @return self
     */
    public static function fromGlobals() : self {
        $script = $_SERVER['argv'][0] ?? 'smliser';
        return new self( \basename( $script ) );
    }

    /**
     * Magic string cast.
     *
     * @return string
     */
    public function __toString() : string {
        return $this->value;
    }
}