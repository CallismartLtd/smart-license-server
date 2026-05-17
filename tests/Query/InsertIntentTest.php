<?php
/**
 * InsertIntent tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class InsertIntentTest extends TestCase {

    /**
     * Test INSERT rendering and bindings.
     *
     * @return void
     */
    public function test_insert_query() : void {

        $query = smliserQueryBuilder()
            ->insert( 'smwoo_licenses' )
            ->values([
                'license_key' => 'SMW-123-ABC',
                'status'      => 'active',
                'created_at'  => '2026-05-10 12:00:00'
            ]);

        $engine = smliser_db()->get_driver();

        if ( $engine === 'mysql' ) {

            $this->assertSame(
                'INSERT INTO `smwoo_licenses` (`license_key`, `status`, `created_at`) VALUES (?, ?, ?);',
                $query->build()
            );

        } else {

            // sqlite / pgsql both use double quotes in your current renderer
            $this->assertSame(
                'INSERT INTO "smwoo_licenses" ("license_key", "status", "created_at") VALUES (?, ?, ?);',
                $query->build()
            );
        }

        $this->assertSame(
            [
                'SMW-123-ABC',
                'active',
                '2026-05-10 12:00:00'
            ],
            $query->get_bindings()
        );
    }
}