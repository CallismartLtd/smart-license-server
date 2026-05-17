<?php
/**
 * SelectionIntent tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class SelectionIntentTest extends TestCase {

    private function engine(): string {
        return smliser_db()->get_driver();
    }

    private function quote(string $identifier): string {
        return match ( $this->engine() ) {
            'mysql'  => "`{$identifier}`",
            'sqlite' => "\"{$identifier}\"",
            'pgsql'  => "\"{$identifier}\"",
            default  => $identifier,
        };
    }

    /**
     * Test basic WHERE rendering and bindings.
     */
    public function test_basic_where_query() : void {

        $query = smliserQueryBuilder()
            ->select( '*' )
            ->from( 'smwoo_licenses' )
            ->where( 'license_key', '=', 'ABC-123' );

        $this->assertSame(
            "SELECT * FROM {$this->quote('smwoo_licenses')} WHERE {$this->quote('license_key')} = ?;",
            $query->build()
        );

        $this->assertSame(
            [ 'ABC-123' ],
            $query->get_bindings()
        );
    }

    /**
     * Test OR WHERE rendering and bindings.
     */
    public function test_or_where_query() : void {

        $query = smliserQueryBuilder()
            ->select( '*' )
            ->from( 'wp_users' )
            ->where( 'id', '=', 1 )
            ->or_where( 'status', '=', 'active' );

        $this->assertSame(
            "SELECT * FROM {$this->quote('wp_users')} WHERE {$this->quote('id')} = ? OR {$this->quote('status')} = ?;",
            $query->build()
        );

        $this->assertSame(
            [ 1, 'active' ],
            $query->get_bindings()
        );
    }

    /**
     * Test WHERE NULL rendering.
     */
    public function test_where_null() : void {

        $query = smliserQueryBuilder()
            ->select( '*' )
            ->from( 'smwoo_licenses' )
            ->where_null( 'deleted_at' );

        $this->assertSame(
            "SELECT * FROM {$this->quote('smwoo_licenses')} WHERE {$this->quote('deleted_at')} IS NULL;",
            $query->build()
        );

        $this->assertSame(
            [],
            $query->get_bindings()
        );
    }

    /**
     * Test WHERE NOT NULL rendering.
     */
    public function test_where_not_null() : void {

        $query = smliserQueryBuilder()
            ->select( '*' )
            ->from( 'wp_users' )
            ->where_not_null( 'deleted_at' );

        $this->assertSame(
            "SELECT * FROM {$this->quote('wp_users')} WHERE {$this->quote('deleted_at')} IS NOT NULL;",
            $query->build()
        );

        $this->assertSame(
            [],
            $query->get_bindings()
        );
    }

    /**
     * Test WHERE IN rendering.
     */
    public function test_where_in() : void {

        $query = smliserQueryBuilder()
            ->select( '*' )
            ->from( 'smwoo_licenses' )
            ->where_in( 'status', [ 'active', 'expired', 'suspended' ] );

        $this->assertSame(
            "SELECT * FROM {$this->quote('smwoo_licenses')} WHERE {$this->quote('status')} IN (?, ?, ?);",
            $query->build()
        );

        $this->assertSame(
            [ 'active', 'expired', 'suspended' ],
            $query->get_bindings()
        );
    }

    /**
     * Test WHERE NOT IN rendering.
     */
    public function test_where_not_in() : void {

        $query = smliserQueryBuilder()
            ->select( '*' )
            ->from( 'wp_users' )
            ->where_not_in( 'role', [ 'admin', 'root' ] );

        $this->assertSame(
            "SELECT * FROM {$this->quote('wp_users')} WHERE {$this->quote('role')} NOT IN (?, ?);",
            $query->build()
        );

        $this->assertSame(
            [ 'admin', 'root' ],
            $query->get_bindings()
        );
    }

    /**
     * Test grouped WHERE conditions.
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

        $this->assertSame(
            "SELECT * FROM {$this->quote('wp_users')} WHERE {$this->quote('id')} = ? AND ({$this->quote('deleted_at')} IS NULL OR {$this->quote('legacy_id')} > ?);",
            $query->build()
        );

        $this->assertSame(
            [ 1, 0 ],
            $query->get_bindings()
        );
    }

    /**
     * Test nested grouped WHERE conditions.
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

        $this->assertSame(
            "SELECT * FROM {$this->quote('wp_users')} WHERE ({$this->quote('status')} = ? OR ({$this->quote('role')} = ? AND {$this->quote('deleted_at')} IS NULL));",
            $query->build()
        );

        $this->assertSame(
            [ 'active', 'admin' ],
            $query->get_bindings()
        );
    }

    /**
     * Test JOIN, GROUP BY, ORDER BY, LIMIT and OFFSET rendering.
     */
    public function test_complex_selection_query() : void {

        $query = smliserQueryBuilder()
            ->select( 'l.id', 'l.license_key', 'm.meta_value' )
            ->from( 'smwoo_licenses l' )
            ->left_join( 'smwoo_meta m', 'l.id', '=', 'm.license_id' )
            ->where( 'l.status', '=', 'active' )
            ->group_by( 'l.id' )
            ->order_by( 'l.created_at', 'DESC' )
            ->limit( 20 )
            ->offset( 40 );

        $this->assertSame(
            "SELECT `l`.`id`, `l`.`license_key`, `m`.`meta_value` "
            . "FROM `smwoo_licenses` `l` "
            . "LEFT JOIN `smwoo_meta` `m` ON `l`.`id` = `m`.`license_id` "
            . "WHERE `l`.`status` = ? "
            . "GROUP BY `l`.`id` "
            . "ORDER BY `l`.`created_at` DESC "
            . "LIMIT 20 OFFSET 40;",
            $query->build()
        );

        $this->assertSame(
            [ 'active' ],
            $query->get_bindings()
        );
    }
}