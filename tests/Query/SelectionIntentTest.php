<?php
/**
 * SelectionIntent tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class SelectionIntentTest extends TestCase {

    /**
     * Test basic WHERE rendering and bindings.
     *
     * @return void
     */
    public function test_basic_where_query() : void {

        $query = smliserQueryBuilder()
            ->select( '*' )
            ->from( 'smwoo_licenses' )
            ->where( 'license_key', '=', 'ABC-123' );

        $this->assertSame(
            'SELECT * FROM `smwoo_licenses` WHERE `license_key` = ?;',
            $query->build()
        );

        $this->assertSame(
            [ 'ABC-123' ],
            $query->get_bindings()
        );
    }

    /**
     * Test WHERE NULL rendering.
     *
     * @return void
     */
    public function test_where_null() : void {

        $query = smliserQueryBuilder()
            ->select( '*' )
            ->from( 'smwoo_licenses' )
            ->where_null( 'deleted_at' );

        $this->assertSame(
            'SELECT * FROM `smwoo_licenses` WHERE `deleted_at` IS NULL;',
            $query->build()
        );

        $this->assertSame(
            [],
            $query->get_bindings()
        );
    }

    /**
     * Test WHERE IN rendering and binding order.
     *
     * @return void
     */
    public function test_where_in() : void {

        $query = smliserQueryBuilder()
            ->select( '*' )
            ->from( 'smwoo_licenses' )
            ->where_in( 'status', [ 'active', 'expired', 'suspended' ] );

        $this->assertSame(
            'SELECT * FROM `smwoo_licenses` WHERE `status` IN (?, ?, ?);',
            $query->build()
        );

        $this->assertSame(
            [ 'active', 'expired', 'suspended' ],
            $query->get_bindings()
        );
    }
}