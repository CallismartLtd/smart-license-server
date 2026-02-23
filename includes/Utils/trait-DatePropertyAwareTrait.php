<?php
/**
 * Date property aware trait file
 */

namespace SmartLicenseServer\Utils;

use DateTimeImmutable;
use Throwable;

trait DatePropertyAwareTrait {
    use SanitizeAwareTrait;
    private function set_date_prop( mixed $date, string $prop ) : static {
        if ( $date instanceof DateTimeImmutable ) {
            $this->{$prop} = $date;
            return $this;
        }

        if ( ! is_string( $date ) ){
            return $this;
        }

        $check   = static::sanitize_date( $date, null );

        if ( \is_null( $check ) ) {
            $this->{$prop} = null;
            return $this;
        }

        try {
            $date   = new DateTimeImmutable( $date );
        } catch ( Throwable $e ) {
            return $this;
        }

        $this->{$prop} = $date;

        return $this;
    }
}