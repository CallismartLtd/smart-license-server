<?php
/**
 * AWS Signature Version 4 Signer.
 *
 * Computes the Authorization header and required signing headers
 * for any AWS API request using the AWS Signature Version 4 process.
 *
 * @see     https://docs.aws.amazon.com/general/latest/gr/sigv4-create-canonical-request.html
 * @package SmartLicenseServer\Http
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Http;

defined( 'SMLISER_ABSPATH' ) || exit;

class AwsSignatureV4 {

    protected const ALGORITHM     = 'AWS4-HMAC-SHA256';
    protected const SERVICE_SES   = 'ses';
    protected const AWS_REQUEST   = 'aws4_request';

    /**
     * @param string $access_key  AWS Access Key ID.
     * @param string $secret_key  AWS Secret Access Key.
     * @param string $region      AWS region. e.g. "us-east-1".
     * @param string $service     AWS service name. e.g. "ses".
     */
    public function __construct(
        protected readonly string $access_key,
        protected readonly string $secret_key,
        protected readonly string $region,
        protected readonly string $service = self::SERVICE_SES,
    ) {}

    /*
    |------------------
    | PUBLIC INTERFACE
    |------------------
    */

    /**
     * Sign an HttpRequest and return it with all required AWS headers added.
     *
     * Adds:
     *   - X-Amz-Date       : timestamp in ISO8601 basic format
     *   - X-Amz-Content-Sha256 : hex-encoded SHA256 hash of the request body
     *   - Authorization    : AWS4-HMAC-SHA256 credential/signature string
     *
     * @param HttpRequest $request
     * @return HttpRequest  A new immutable request with signing headers applied.
     */
    public function sign( HttpRequest $request ): HttpRequest {
        $now           = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
        $amz_date      = $now->format( 'Ymd\THis\Z' );
        $date_stamp    = $now->format( 'Ymd' );
        $body_hash     = hash( 'sha256', $request->body );
        $parsed_url    = parse_url( $request->url );
        $host          = $parsed_url['host'] ?? '';

        // Merge signing headers with request headers before canonicalisation.
        $headers = array_merge( $request->headers, [
            'Host'                 => $host,
            'X-Amz-Date'          => $amz_date,
            'X-Amz-Content-Sha256' => $body_hash,
        ] );

        $canonical_request = $this->build_canonical_request(
            $request->method,
            $parsed_url,
            $headers,
            $body_hash
        );

        $credential_scope = $this->build_credential_scope( $date_stamp );
        $string_to_sign   = $this->build_string_to_sign( $amz_date, $credential_scope, $canonical_request );
        $signing_key      = $this->derive_signing_key( $date_stamp );
        $signature        = hash_hmac( 'sha256', $string_to_sign, $signing_key );
        $signed_headers   = $this->get_signed_header_names( $headers );

        $authorization = sprintf(
            '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            self::ALGORITHM,
            $this->access_key,
            $credential_scope,
            $signed_headers,
            $signature
        );

        // Return a new immutable request with all signing headers applied.
        return new HttpRequest(
            method        : $request->method,
            url           : $request->url,
            headers       : array_merge( $headers, [ 'Authorization' => $authorization ] ),
            body          : $request->body,
            timeout       : $request->timeout,
            verify_ssl    : $request->verify_ssl,
            max_redirects : $request->max_redirects,
            cookies       : $request->cookies,
        );
    }

    /*
    |------------------
    | CANONICAL REQUEST
    |------------------
    */

    /**
     * Build the canonical request string.
     *
     * Format (each component on its own line):
     *   HTTPMethod
     *   CanonicalURI
     *   CanonicalQueryString
     *   CanonicalHeaders
     *   SignedHeaders
     *   HexEncode(Hash(RequestPayload))
     *
     * @param string               $method
     * @param array<string, mixed> $parsed_url
     * @param array<string,string> $headers
     * @param string               $body_hash
     * @return string
     */
    protected function build_canonical_request(
        string $method,
        array  $parsed_url,
        array  $headers,
        string $body_hash
    ): string {
        $canonical_uri     = $this->build_canonical_uri( $parsed_url );
        $canonical_query   = $this->build_canonical_query( $parsed_url );
        $canonical_headers = $this->build_canonical_headers( $headers );
        $signed_headers    = $this->get_signed_header_names( $headers );

        return implode( "\n", [
            $method,
            $canonical_uri,
            $canonical_query,
            $canonical_headers,
            $signed_headers,
            $body_hash,
        ] );
    }

    /**
     * Build the canonical URI component.
     *
     * The URI must be URI-encoded but preserve existing slashes.
     *
     * @param array<string, mixed> $parsed_url
     * @return string
     */
    protected function build_canonical_uri( array $parsed_url ): string {
        $path = $parsed_url['path'] ?? '/';

        if ( $path === '' ) {
            return '/';
        }

        // URI-encode each path segment individually, preserving slashes.
        $segments = explode( '/', $path );
        $encoded  = array_map( fn( $s ) => rawurlencode( rawurldecode( $s ) ), $segments );

        return implode( '/', $encoded );
    }

    /**
     * Build the canonical query string.
     *
     * Parameters must be sorted by name, then by value.
     * Each name and value must be URI-encoded.
     *
     * @param array<string, mixed> $parsed_url
     * @return string
     */
    protected function build_canonical_query( array $parsed_url ): string {
        if ( empty( $parsed_url['query'] ) ) {
            return '';
        }

        parse_str( $parsed_url['query'], $params );

        $pairs = [];
        foreach ( $params as $key => $value ) {
            $pairs[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
        }

        sort( $pairs );

        return implode( '&', $pairs );
    }

    /**
     * Build the canonical headers string.
     *
     * Header names must be lowercased and sorted alphabetically.
     * Header values must have leading/trailing whitespace trimmed and
     * consecutive internal spaces collapsed to a single space.
     *
     * Each header is terminated with a newline — including the last one.
     *
     * @param array<string,string> $headers
     * @return string
     */
    protected function build_canonical_headers( array $headers ): string {
        $normalised = [];

        foreach ( $headers as $name => $value ) {
            $normalised[ strtolower( $name ) ] = preg_replace( '/\s+/', ' ', trim( $value ) );
        }

        ksort( $normalised );

        $lines = '';
        foreach ( $normalised as $name => $value ) {
            $lines .= "{$name}:{$value}\n";
        }

        return $lines;
    }

    /**
     * Return a semicolon-separated list of signed header names in lowercase sorted order.
     *
     * @param array<string,string> $headers
     * @return string
     */
    protected function get_signed_header_names( array $headers ): string {
        $names = array_map( 'strtolower', array_keys( $headers ) );
        sort( $names );
        return implode( ';', $names );
    }

    /*
    |----------------
    | STRING TO SIGN
    |----------------
    */

    /**
     * Build the string-to-sign.
     *
     * Format:
     *   Algorithm
     *   RequestDateTime
     *   CredentialScope
     *   HexEncode(Hash(CanonicalRequest))
     *
     * @param string $amz_date
     * @param string $credential_scope
     * @param string $canonical_request
     * @return string
     */
    protected function build_string_to_sign(
        string $amz_date,
        string $credential_scope,
        string $canonical_request
    ): string {
        return implode( "\n", [
            self::ALGORITHM,
            $amz_date,
            $credential_scope,
            hash( 'sha256', $canonical_request ),
        ] );
    }

    /**
     * Build the credential scope string.
     *
     * Format: {date}/{region}/{service}/aws4_request
     *
     * @param string $date_stamp  Date in Ymd format.
     * @return string
     */
    protected function build_credential_scope( string $date_stamp ): string {
        return implode( '/', [
            $date_stamp,
            $this->region,
            $this->service,
            self::AWS_REQUEST,
        ] );
    }

    /*
    |-------------
    | SIGNING KEY
    |-------------
    */

    /**
     * Derive the signing key using a chain of HMAC-SHA256 operations.
     *
     * The derived key is scoped to a specific date, region, and service
     * so that a compromised signing key cannot be reused across contexts.
     *
     * Process:
     *   kDate    = HMAC-SHA256("AWS4" + SecretKey, Date)
     *   kRegion  = HMAC-SHA256(kDate, Region)
     *   kService = HMAC-SHA256(kRegion, Service)
     *   kSigning = HMAC-SHA256(kService, "aws4_request")
     *
     * @param string $date_stamp  Date in Ymd format.
     * @return string  Raw binary signing key.
     */
    protected function derive_signing_key( string $date_stamp ): string {
        $k_date    = hash_hmac( 'sha256', $date_stamp,         'AWS4' . $this->secret_key, true );
        $k_region  = hash_hmac( 'sha256', $this->region,       $k_date,    true );
        $k_service = hash_hmac( 'sha256', $this->service,      $k_region,  true );
        $k_signing = hash_hmac( 'sha256', self::AWS_REQUEST,   $k_service, true );

        return $k_signing;
    }
}