<?php
/**
 * Grouping tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class GroupingTest extends TestCase {

    /**
     * Test grouped WHERE conditions.
     *
     * @return void
     */
    public function test_where_group() : void {

        $query = smliserQueryBuilder()
            ->select( '*' )
            ->from( 'wp_users' )
            ->where( 'id', '=', 1 )
            ->where_group( function ( $q ) {

                $q->where_null( 'deleted_at' )
                    ->or_where( 'legacy_id', '>', 0 );

            });

        $engine = smliser_db()->get_driver();

        if ( $engine === 'mysql' ) {

            $this->assertSame(
                'SELECT * FROM `wp_users` WHERE `id` = ? AND (`deleted_at` IS NULL OR `legacy_id` > ?);',
                $query->build()
            );

        } else {

            $this->assertSame(
                'SELECT * FROM "wp_users" WHERE "id" = ? AND ("deleted_at" IS NULL OR "legacy_id" > ?);',
                $query->build()
            );
        }

        $this->assertSame(
            [ 1, 0 ],
            $query->get_bindings()
        );
    }

    /**
     * Test nested grouped conditions.
     *
     * @return void
     */
    public function test_nested_where_group() : void {

        $query = smliserQueryBuilder()
            ->select( '*' )
            ->from( 'wp_users' )
            ->where_group( function ( $q ) {

                $q->where( 'status', '=', 'active' )
                    ->or_where_group( function ( $q2 ) {

                        $q2->where( 'role', '=', 'admin' )
                            ->where_null( 'deleted_at' );

                    });

            });

        $engine = smliser_db()->get_driver();

        if ( $engine === 'mysql' ) {

            $this->assertSame(
                'SELECT * FROM `wp_users` WHERE (`status` = ? OR (`role` = ? AND `deleted_at` IS NULL));',
                $query->build()
            );

        } else {

            $this->assertSame(
                'SELECT * FROM "wp_users" WHERE ("status" = ? OR ("role" = ? AND "deleted_at" IS NULL));',
                $query->build()
            );
        }

        $this->assertSame(
            [ 'active', 'admin' ],
            $query->get_bindings()
        );
    }
}