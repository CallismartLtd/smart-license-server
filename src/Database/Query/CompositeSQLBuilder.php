<?php
/**
 * Composite SQL Query Builder - Compound Intent Layer
 *
 * Supports UNION / UNION ALL composition of SQLBuilder queries.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Query;

use SmartLicenseServer\Database\Query\Renderers\CompositeRenderer;
use SmartLicenseServer\Database\Query\Renderers\MySQLCompositeRenderer;
use SmartLicenseServer\Database\Query\Renderers\PostgreSQLCompositeRenderer;
use SmartLicenseServer\Database\Query\Renderers\SQLiteCompositeRenderer;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * CompositeSQLBuilder
 *
 * Responsibility:
 * Compose multiple SQLBuilder instances into one compound query.
 *
 * Current Features:
 * - UNION
 * - UNION ALL
 * - ORDER BY
 * - LIMIT
 * - OFFSET
 *
 * @since 0.2.0
 */
class CompositeSQLBuilder {

    /**
     * Compound query type.
     *
     * @var string|null
     */
    private ?string $type = null;

    /**
     * Child queries.
     *
     * @var SQLBuilder[]
     */
    private array $queries = [];

    /**
     * Compound intent.
     *
     * @var array
     */
    private array $intent = [];

    /**
     * Engine name.
     *
     * @var string
     */
    private string $engine;

    /**
     * Constructor.
     *
     * @param string $engine
     */
    public function __construct( string $engine ) {
        $this->engine = strtolower( $engine );
    }

    /**
     * Build UNION query.
     *
     * @param SQLBuilder ...$queries
     * @return self
     */
    public function union( SQLBuilder ...$queries ) : self {
        return $this->set_compound_type( 'UNION', $queries );
    }

    /**
     * Build UNION ALL query.
     *
     * @param SQLBuilder ...$queries
     * @return self
     */
    public function union_all( SQLBuilder ...$queries ) : self {
        return $this->set_compound_type( 'UNION ALL', $queries );
    }

    /**
     * Add ORDER BY clause.
     *
     * @param string $column
     * @param string $direction
     * @return self
     */
    public function order_by( string $column, string $direction = 'ASC' ) : self {
        if ( ! isset( $this->intent['order_by'] ) ) {
            $this->intent['order_by'] = [];
        }

        $this->intent['order_by'][] = [
            'column'    => $column,
            'direction' => strtoupper( $direction ),
        ];

        return $this;
    }

    /**
     * Add LIMIT.
     *
     * @param int $limit
     * @return self
     */
    public function limit( int $limit ) : self {
        $this->intent['limit'] = $limit;
        return $this;
    }

    /**
     * Add OFFSET.
     *
     * @param int $offset
     * @return self
     */
    public function offset( int $offset ) : self {
        $this->intent['offset'] = $offset;
        return $this;
    }

    /**
     * Build SQL.
     *
     * @return string
     * @throws \Exception
     */
    public function build() : string {
        if ( ! $this->type ) {
            throw new \Exception( 'Composite query type not set.' );
        }

        $renderer = $this->get_renderer();

        return match ( $this->type ) {
            'UNION',
            'UNION ALL' => $renderer->render_union(
                $this->queries,
                $this->type,
                $this->intent
            ),

            default => throw new \Exception(
                "Unsupported composite query type: {$this->type}"
            ),
        };
    }

    /**
     * Get merged bindings from child queries.
     *
     * @return array
     */
    public function get_bindings() : array {
        $bindings = [];

        foreach ( $this->queries as $query ) {
            $bindings = array_merge(
                $bindings,
                $query->get_bindings()
            );
        }

        if ( isset( $this->intent['limit'] ) ) {
            $bindings[] = $this->intent['limit'];
        }

        if ( isset( $this->intent['offset'] ) ) {
            $bindings[] = $this->intent['offset'];
        }

        return $bindings;
    }

    /**
     * Reset builder.
     *
     * @return self
     */
    public function reset() : self {
        $this->type    = null;
        $this->queries = [];
        $this->intent  = [];
        return $this;
    }

    /**
     * Set compound type.
     *
     * @param string $type
     * @param array $queries
     * @return self
     * @throws \Exception
     */
    private function set_compound_type(
        string $type,
        array $queries
    ) : self {

        if ( count( $queries ) < 2 ) {
            throw new \Exception(
                "{$type} requires at least two queries."
            );
        }

        $this->reset();

        foreach ( $queries as $query ) {
            if ( 'SELECT' !== $query->get_type() ) {
                throw new \Exception(
                    "{$type} only supports SELECT queries."
                );
            }
        }

        $this->type    = $type;
        $this->queries = $queries;

        return $this;
    }

    /**
     * Get renderer.
     *
     * @return CompositeRenderer
     * @throws \Exception
     */
    private function get_renderer() : CompositeRenderer {
        return match ( $this->engine ) {
            'mysql'  => new MySQLCompositeRenderer(),
            'pgsql'  => new PostgreSQLCompositeRenderer(),
            'sqlite' => new SQLiteCompositeRenderer(),

            default => throw new \Exception(
                "Unsupported database engine: {$this->engine}"
            ),
        };
    }
}