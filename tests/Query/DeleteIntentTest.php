<?php
/**
 * DeleteIntent tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class DeleteIntentTest extends TestCase {

    /**
     * Test DELETE rendering and bindings.
     *
     * @return void
     */
    public function test_delete_query() : void {

        $query = smliserQueryBuilder()
            ->delete( 'smwoo_licenses' )
            ->where( 'status', '=', 'expired' )
            ->where_null( 'last_checked' )
            ->or_where( 'id', '<', 100 );

        $engine = smliser_db()->get_driver();

        if ( $engine === 'mysql' ) {

            $this->assertSame(
                'DELETE FROM `smwoo_licenses` WHERE `status` = ? AND `last_checked` IS NULL OR `id` < ?;',
                $query->build()
            );

        } elseif ( $engine === 'pgsql' ) {

            $this->assertSame(
                'DELETE FROM "smwoo_licenses" WHERE "status" = ? AND "last_checked" IS NULL OR "id" < ?;',
                $query->build()
            );

        } elseif ( $engine === 'sqlite' ) {

            $this->assertSame(
                'DELETE FROM "smwoo_licenses" WHERE "status" = ? AND "last_checked" IS NULL OR "id" < ?;',
                $query->build()
            );

        } else {
            $this->fail( 'Unsupported DB engine: ' . $engine );
        }

        $this->assertSame(
            [ 'expired', 100 ],
            $query->get_bindings()
        );
    }
}