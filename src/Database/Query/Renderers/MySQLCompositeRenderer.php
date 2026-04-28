<?php
namespace SmartLicenseServer\Database\Query\Renderers;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * MySQL Composite Renderer.
 */
class MySQLCompositeRenderer implements CompositeRenderer {

    /**
     * Render UNION query.
     *
     * @param SQLBuilder[] $queries
     * @param string       $type
     * @param array        $intent
     *
     * @return string
     */
    public function render_union(
        array $queries,
        string $type,
        array $intent
    ) : string {

        $parts = [];

        foreach ( $queries as $query ) {
            $parts[] = $query->build();
        }

        $sql = 'SELECT * FROM ( '
            . implode( " {$type} ", $parts )
            . ' ) AS combined';

        if ( ! empty( $intent['order_by'] ) ) {
            $orders = [];

            foreach ( $intent['order_by'] as $order ) {
                $orders[] = $order['column']
                    . ' '
                    . $order['direction'];
            }

            $sql .= ' ORDER BY ' . implode( ', ', $orders );
        }

        if ( isset( $intent['limit'] ) ) {
            $sql .= ' LIMIT ?';
        }

        if ( isset( $intent['offset'] ) ) {
            $sql .= ' OFFSET ?';
        }

        return $sql;
    }
}