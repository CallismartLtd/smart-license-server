<?php
namespace SmartLicenseServer\Database\Query\Renderers;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Composite Renderer Contract.
 */
interface CompositeRenderer {

    /**
     * Render UNION / UNION ALL query.
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
    ) : string;
}