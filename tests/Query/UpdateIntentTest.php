<?php
/**
 * UpdateIntent tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class UpdateIntentTest extends TestCase {

    /**
     * Test UPDATE rendering and bindings.
     *
     * @return void
     */
    public function test_basic_update_query() : void {

        $query = smliserQueryBuilder()
            ->update( 'smwoo_licenses' )
            ->set([
                'status' => 'expired'
            ])
            ->where( 'license_key', '=', 'SMW-123-ABC' );

        $engine = smliser_db()->get_driver();

        if ( $engine === 'mysql' ) {

            $this->assertSame(
                'UPDATE `smwoo_licenses` SET `status` = ? WHERE `license_key` = ?;',
                $query->build()
            );

        } else {

            $this->assertSame(
                'UPDATE "smwoo_licenses" SET "status" = ? WHERE "license_key" = ?;',
                $query->build()
            );
        }

        $this->assertSame(
            [ 'expired', 'SMW-123-ABC' ],
            $query->get_bindings()
        );
    }

    /**
     * Test UPDATE with grouped criteria.
     *
     * @return void
     */
    public function test_update_with_grouped_criteria() : void {

        $query = smliserQueryBuilder()
            ->update( 'smwoo_licenses' )
            ->values([
                'status'        => 'active',
                'activated_at'  => '2026-05-10 12:00:00'
            ])
            ->where( 'license_key', '=', 'ABC-123' )
            ->where_group( function ( $q ) {

                $q->where_null( 'deleted_at' )
                    ->or_where( 'legacy_id', '>', 0 );

            });

        $engine = smliser_db()->get_driver();

        if ( $engine === 'mysql' ) {

            $this->assertSame(
                'UPDATE `smwoo_licenses` SET `status` = ?, `activated_at` = ? WHERE `license_key` = ? AND (`deleted_at` IS NULL OR `legacy_id` > ?);',
                $query->build()
            );

        } else {

            $this->assertSame(
                'UPDATE "smwoo_licenses" SET "status" = ?, "activated_at" = ? WHERE "license_key" = ? AND ("deleted_at" IS NULL OR "legacy_id" > ?);',
                $query->build()
            );
        }

        $this->assertSame(
            [
                'active',
                '2026-05-10 12:00:00',
                'ABC-123',
                0
            ],
            $query->get_bindings()
        );
    }
}