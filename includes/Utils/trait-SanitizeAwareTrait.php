<?php
/**
 * Sanitize Aware Trait
 * 
 * Provides automatic sanitization methods for model setters.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Utils
 * @since 0.3.0
 */

namespace SmartLicenseServer\Utils;

use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\Utils\Sanitizer;

/**
 * Trait SanitizeAwareTrait
 * 
 * Provides convenient sanitization methods that can be used in model setters
 * to ensure data integrity before property assignment.
 * 
 * Note: This trait only sanitizes input - it does NOT validate.
 * Validation should be performed separately before or after sanitization.
 * 
 * Example usage:
 * ```php
 * class User {
 *     use SanitizeAwareTrait;
 *     
 *     private string $name;
 *     private string $email;
 *     
 *     public function setName(string $name): void {
 *         $this->name = self::sanitize_text($name);
 *     }
 *     
 *     public function setEmail(string $email): void {
 *         $this->email = self::sanitize_email($email);
 *     }
 * }
 * ```
 */
trait SanitizeAwareTrait {

    /**
     * Sanitization rules for batch sanitization.
     * 
     * Override this in your model to define field-specific rules.
     * 
     * @var array<string, string> Field name => sanitization method
     */
    protected array $sanitization_rules = [];

    /*
    |--------------------------------------------------------------------------
    | TEXT SANITIZATION
    |--------------------------------------------------------------------------
    */

    /**
     * Sanitize text field (strips HTML, normalizes whitespace).
     * 
     * @param mixed $value The input value.
     * @return string Sanitized text.
     */
    protected static function sanitize_text( $value ): string {
        return Sanitizer::sanitize_text_field( self::unslash( $value ) );
    }

    /**
     * Sanitize textarea field (preserves line breaks).
     * 
     * @param mixed $value The input value.
     * @return string Sanitized textarea content.
     */
    protected static function sanitize_textarea( $value ): string {
        return Sanitizer::sanitize_textarea_field( $value );
    }

    /**
     * Sanitize HTML content (allows safe HTML tags).
     * 
     * @param string $value The HTML content.
     * @return string Sanitized HTML.
     */
    protected static function sanitize_html( string $value ): string {
        return Sanitizer::sanitize_html( $value );
    }

    /**
     * Strict sanitization - removes script tags and dangerous content.
     * 
     * @param string $value The input value.
     * @return string Sanitized value (empty string if malicious content detected).
     */
    protected static function sanitize_strict( string $value ): string {
        $result = Sanitizer::strict_sanitize( $value );
        return $result !== false ? $result : '';
    }

    /*
    |--------------------------------------------------------------------------
    | NUMERIC SANITIZATION
    |--------------------------------------------------------------------------
    */

    /**
     * Sanitize integer value.
     * 
     * @param mixed $value The input value.
     * @param int $default Default value for non-numeric input. Default 0.
     * @return int Sanitized integer.
     */
    protected static function sanitize_int( $value, int $default = 0 ): int {
        $result = Sanitizer::sanitize_int( $value );
        return $result !== false ? $result : $default;
    }

    /**
     * Sanitize float value.
     * 
     * @param mixed $value The input value.
     * @param float $default Default value for non-numeric input. Default 0.0.
     * @return float Sanitized float.
     */
    protected static function sanitize_float( $value, float $default = 0.0 ): float {
        $result = Sanitizer::sanitize_float( $value );
        return $result !== false ? $result : $default;
    }

    /**
     * Sanitize number string (extracts digits only).
     * 
     * @param mixed $value The input value.
     * @return string String containing only digits.
     */
    protected static function sanitize_number( $value ): string {
        return Sanitizer::sanitize_number( $value );
    }

    /**
     * Sanitize positive integer (converts negative to positive).
     * 
     * @param mixed $value The input value.
     * @param int $default Default value for non-numeric input. Default 0.
     * @return int Positive integer.
     */
    protected static function sanitize_positive_int( $value, int $default = 0 ): int {
        $int = static::sanitize_int( $value, $default );
        return abs( $int );
    }

    /**
     * Sanitize boolean value.
     * 
     * @param mixed $value The input value.
     * @return bool Sanitized boolean.
     */
    protected static function sanitize_bool( $value ): bool {
        return Sanitizer::esc_bool( $value );
    }

    /*
    |--------------------------------------------------------------------------
    | CONTACT INFORMATION
    |--------------------------------------------------------------------------
    */

    /**
     * Sanitize email address.
     * 
     * @param string $value The email address.
     * @return string Sanitized email (empty string if malformed).
     */
    protected static function sanitize_email( string $value ): string {
        return Sanitizer::sanitize_email( $value );
    }

    /**
     * Sanitize URL.
     * 
     * @param string $value The URL.
     * @param array $protocols Allowed protocols.
     * @return string Sanitized URL (empty string if invalid protocol).
     */
    protected static function sanitize_url( string $value, array $protocols = [] ): string {
        return Sanitizer::esc_url( $value, $protocols );
    }

    /**
     * Sanitize web URL (HTTP/HTTPS only).
     * 
     * @param string $value The URL.
     * @return string Sanitized web URL.
     */
    protected static function sanitize_web_url( string $value ): string {
        return Sanitizer::esc_url( $value, ['http', 'https'] );
    }

    /*
    |--------------------------------------------------------------------------
    | IDENTIFIER SANITIZATION
    |--------------------------------------------------------------------------
    */

    /**
     * Sanitize slug (lowercase alphanumeric with hyphens).
     * 
     * @param string $value The input value.
     * @return string Sanitized slug.
     */
    protected static function sanitize_slug( string $value ): string {
        $slug = static::sanitize_text( $value );
        $slug = strtolower( $slug );
        $slug = preg_replace( '/[^a-z0-9\-_]/', '-', $slug );
        $slug = preg_replace( '/-+/', '-', $slug );
        $slug = trim( $slug, '-' );
        
        return $slug;
    }

    /**
     * Sanitize CSS class name.
     * 
     * @param string $value The class name.
     * @return string Sanitized class name.
     */
    protected static function sanitize_class( string $value ): string {
        return Sanitizer::esc_class( $value );
    }

    /**
     * Sanitize HTML ID attribute.
     * 
     * @param string $value The ID value.
     * @return string Sanitized ID.
     */
    protected static function sanitize_html_id( string $value ): string {
        $id = static::sanitize_text( $value );
        $id = preg_replace( '/[^a-zA-Z0-9\-_]/', '', $id );
        
        // Ensure it starts with a letter
        if ( ! empty( $id ) && ! preg_match( '/^[a-zA-Z]/', $id ) ) {
            $id = 'id-' . $id;
        }
        
        return $id;
    }

    /**
     * Sanitize key (alphanumeric with underscores).
     * 
     * @param string $value The input value.
     * @return string Sanitized key.
     */
    protected static function sanitize_key( string $value ): string {
        $key = static::sanitize_text( $value );
        $key = strtolower( $key );
        $key = preg_replace( '/[^a-z0-9_]/', '_', $key );
        $key = preg_replace( '/_+/', '_', $key );
        $key = trim( $key, '_' );
        
        return $key;
    }

    /*
    |--------------------------------------------------------------------------
    | DATE & TIME SANITIZATION
    |--------------------------------------------------------------------------
    */

    /**
     * Sanitize date string to Y-m-d format.
     * 
     * @param string $value The date string.
     * @param string|null $default Default value for invalid dates.
     * @return string|null Formatted date or default.
     */
    protected static function sanitize_date( string $value, ?string $default = null ): ?string {
        $timestamp = strtotime( $value );
        
        if ( false === $timestamp ) {
            return $default;
        }
        
        return gmdate( 'Y-m-d', $timestamp );
    }

    /**
     * Sanitize datetime string to Y-m-d H:i:s format.
     * 
     * @param string $value The datetime string.
     * @param string|null $default Default value for invalid datetimes.
     * @return string|null Formatted datetime or default.
     */
    protected static function sanitize_datetime( string $value, ?string $default = null ): ?string {
        $timestamp = strtotime( $value );
        
        if ( false === $timestamp ) {
            return $default;
        }
        
        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Sanitize timestamp (converts to valid Unix timestamp).
     * 
     * @param mixed $value The timestamp value.
     * @param int|null $default Default value for invalid timestamps.
     * @return int|null Unix timestamp or default.
     */
    protected static function sanitize_timestamp( $value, ?int $default = null ): ?int {
        if ( is_numeric( $value ) ) {
            $timestamp = (int) $value;
            
            // Ensure reasonable timestamp range (1970-2100)
            if ( $timestamp >= 0 && $timestamp <= 4102444800 ) {
                return $timestamp;
            }
        }
        
        $timestamp = strtotime( (string) $value );
        return $timestamp !== false ? $timestamp : $default;
    }

    /*
    |--------------------------------------------------------------------------
    | ENUM & CHOICE SANITIZATION
    |--------------------------------------------------------------------------
    */

    /**
     * Sanitize value against allowed choices.
     * 
     * Returns the value if it exists in allowed list, otherwise returns default.
     * 
     * @param mixed $value The input value.
     * @param array $allowed_values Array of allowed values.
     * @param mixed $default Default value if not in allowed list.
     * @return mixed Sanitized value or default.
     */
    protected static function sanitize_choice( $value, array $allowed_values, $default = null ): mixed {
        if ( in_array( $value, $allowed_values, true ) ) {
            return $value;
        }
        
        return $default;
    }

    /**
     * Sanitize enum value (case-insensitive matching).
     * 
     * @param string $value The input value.
     * @param array $allowed_values Array of allowed values.
     * @param string|null $default Default value if not in allowed list.
     * @return string|null Sanitized value or default.
     */
    protected static function sanitize_enum( string $value, array $allowed_values, ?string $default = null ): ?string {
        $value = static::sanitize_text( $value );
        $value_lower = strtolower( $value );
        
        foreach ( $allowed_values as $allowed ) {
            if ( strtolower( $allowed ) === $value_lower ) {
                return $allowed;
            }
        }
        
        return $default;
    }

    /*
    |--------------------------------------------------------------------------
    | ARRAY SANITIZATION
    |--------------------------------------------------------------------------
    */

    /**
     * Recursively sanitize a given value.
     * 
     * @param mixed $value The input value.
     * @return mixed Sanitized array (empty if input is not array).
     */
    protected static function sanitize_deep( $value ): mixed {
        return Sanitizer::sanitize_deep( $value );
    }

    /**
     * Sanitize comma-separated list into array.
     * 
     * @param string $value Comma-separated string.
     * @return array Array of sanitized values.
     */
    protected static function sanitize_csv( string $value ): array {
        if ( empty( $value ) ) {
            return [];
        }
        
        $items = explode( ',', $value );
        $items = array_map( 'trim', $items );
        $items = array_filter( $items, fn($item) => $item !== '' );
        
        return array_map( [ __CLASS__, 'sanitize_text' ], $items );
    }

    /*
    |--------------------------------------------------------------------------
    | FILE & PATH SANITIZATION
    |--------------------------------------------------------------------------
    */

    /**
     * Sanitize file name (removes directory traversal attempts).
     * 
     * @param string $value The file name.
     * @return string Sanitized file name.
     */
    protected static function sanitize_filename( string $value ): string {
        $filename   = FileSystemHelper::sanitize_filename( $value );
        
        return $filename;
    }

    /**
     * Sanitize file extension.
     * 
     * @param string $value The file extension.
     * @param array $allowed_extensions Optional. Allowed extensions (returns empty if not in list).
     * @return string Sanitized extension.
     */
    protected static function sanitize_file_extension( string $value, array $allowed_extensions = [] ): string {
        $ext = strtolower( trim( $value, '. ' ) );
        $ext = preg_replace( '/[^a-z0-9]/', '', $ext );
        
        if ( ! empty( $allowed_extensions ) && ! in_array( $ext, $allowed_extensions, true ) ) {
            return '';
        }
        
        return $ext;
    }

    /**
     * Remove slashes from value (useful for magic_quotes_gpc data).
     * 
     * @param mixed $value The input value.
     * @return mixed Value with slashes removed.
     */
    protected static function unslash( $value ): mixed {
        return Sanitizer::unslash( $value );
    }
}