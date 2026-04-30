<?php
/**
 * SQL Query Builder - Intent Layer (Engine-Agnostic)
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Query;

use SmartLicenseServer\Database\Query\QueryIntents\CreateTableIntent;
use SmartLicenseServer\Database\Query\QueryIntents\AlterTableIntent;
use SmartLicenseServer\Database\Query\QueryIntents\DeleteIntent;
use SmartLicenseServer\Database\Query\QueryIntents\PersistenceIntent;
use SmartLicenseServer\Database\Query\QueryIntents\SelectionIntent;
use SmartLicenseServer\Database\Query\Renderers\AbstractQueryRenderer;
use SmartLicenseServer\Database\Query\Renderers\MySQLRenderer;
use SmartLicenseServer\Database\Query\Renderers\PostgreSQLRenderer;
use SmartLicenseServer\Database\Query\Renderers\SQLiteRenderer;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * SQLBuilder - Intent Layer Only
 *
 * Responsibility: Collect and normalize query intent.
 * Rendering is delegated to engine-specific renderers.
 *
 * @since 0.2.0
 */
class SQLBuilder {

    /**
     * Query type constant.
     *
     * @var string
     */
    private ?string $type = null;

    /**
     * Raw table name (UNQUOTED).
     *
     * @var string
     */
    private string $table = '';

    /**
     * Query intent components.
     *
     * Stored in normalized, engine-agnostic format.
     * Rendering happens via AbstractQueryRenderer.
     *
     * @var array
     */
    private array $intent = [];

    /**
     * Target database engine.
     *
     * @var string ('mysql', 'pgsql', 'sqlite')
     */
    private string $engine;

    /**
     * The active intent object.
     * 
     * @var mixed $active_intent
     */
    private mixed $active_intent;

    /**
     * Constructor.
     *
     * @param string $engine The database engine ('mysql', 'pgsql', 'sqlite')
     */
    public function __construct( string $engine ) {
        $this->engine = strtolower( $engine );
    }

    /*
    |------------------------------------
    |QUERY TYPE BUILDERS (Intent Layer)
    |------------------------------------
    */

    /**
     * Start a SELECT query.
     *
     * @param string ...$columns
     * @return SelectionIntent
     */
    public function select( string ...$columns ) : SelectionIntent {
        $this->reset_intent();
        $this->type             = 'SELECT';
        $this->active_intent    = SelectionIntent::make( $this )->select( ...$columns );
        
        return $this->active_intent;
    }

    /**
     * Start an INSERT query.
     *
     * @param string $table Table name (raw, unquoted)
     *
     * @return PersistenceIntent
     */
    public function insert( string $table ) : PersistenceIntent {
        $this->reset_intent();
        $this->type          = 'INSERT';
        $this->table         = $table;
        $this->active_intent = PersistenceIntent::make( $table, $this );
        
        return $this->active_intent;
    }

    /**
     * Start an UPDATE query.
     *
     * @param string $table
     * @return PersistenceIntent
     */
    public function update( string $table ) : PersistenceIntent {
        $this->reset_intent();
        $this->type          = 'UPDATE';
        $this->active_intent = PersistenceIntent::make( $table, $this );
        
        return $this->active_intent;
    }

    /**
     * Start a DELETE query.
     *
     * @param string $table Table name (raw, unquoted)
     *
     * @return DeleteIntent
     */
    public function delete( string $table ) : DeleteIntent {
        $this->reset_intent();
        $this->type   = 'DELETE';
        $this->active_intent = DeleteIntent::make( $table, $this );

        return $this->active_intent;
    }

    /**
     * Start a CREATE TABLE query.
     *
     * @param string $table Table name (raw, unquoted)
     *
     * @return CreateTableIntent
     */
    public function create_table( string $table ) : CreateTableIntent {
        $this->reset_intent();
        $this->type          = 'CREATE TABLE';
        $this->table         = $table;
        $this->active_intent = CreateTableIntent::make( $table, $this );
        
        return $this->active_intent;
    }

    /**
     * Start an ALTER TABLE query.
     *
     * @param string $table Table name (raw, unquoted)
     *
     * @return AlterTableIntent
     */
    public function alter_table( string $table ) : AlterTableIntent {
        $this->reset_intent();
        $this->type          = 'ALTER TABLE';
        $this->table         = $table;
        $this->active_intent = AlterTableIntent::make( $table, $this );
        
        return $this->active_intent;
    }

    /**
     * Start a DROP TABLE query.
     *
     * @param string $table Table name (raw, unquoted)
     *
     * @return self
     */
    public function drop_table( string $table ) : self {
        $this->reset_intent();
        $this->type  = 'DROP TABLE';
        $this->table = $table;
        
        return $this;
    }

    /**
     * Set IF EXISTS flag for DROP TABLE.
     */
    public function if_exists() : self {
        $this->intent['if_exists'] = true;
        return $this;
    }

    /**
     * Set storage engine (MySQL only - intent storage).
     *
     * @param string $engine Engine name
     *
     * @return self
     */
    public function engine( string $engine ) : self {
        $this->intent['engine'] = $engine;
        return $this;
    }

    /*
    |-------------------------------------------
    |RENDERING (Delegation to Engine Renderer)
    |-------------------------------------------
    */

    /**
     * Build the SQL query.
     *
     * Validates intent and delegates to engine-specific renderer.
     *
     * @return string The rendered SQL statement
     *
     * @throws \Exception If query is invalid or unsupported
     */
    public function build() : string {
        if ( ! $this->type ) {
            throw new \Exception( 'Query type not set' );
        }
    
        // Get the appropriate renderer
        $renderer = $this->get_renderer();
    
        // Render based on query type
        $sql = match ( $this->type ) {
            'SELECT'       => $renderer->render_select( $this->active_intent ),
            'INSERT'       => $renderer->render_insert( $this->active_intent ),
            'UPDATE'       => $renderer->render_update( $this->active_intent ),
            'DELETE'       => $renderer->render_delete( $this->active_intent ),
            'CREATE TABLE' => $renderer->render_create_table( $this->active_intent ),
            'ALTER TABLE'  => $renderer->render_alter_table( $this->active_intent ),
            'DROP TABLE'   => $renderer->render_drop_table( $this->table, $this->intent ),
            default        => throw new \Exception( "Unknown query type: {$this->type}" )
        };
    
        return $sql;
    }



    /**
     * Get the appropriate engine renderer.
     *
     * @return AbstractQueryRenderer
     *
     * @throws \Exception If engine not supported
     */
    private function get_renderer() : AbstractQueryRenderer {
        return match ( $this->engine ) {
            'mysql'  => new MySQLRenderer(),
            'pgsql'  => new PostgreSQLRenderer(),
            'sqlite' => new SQLiteRenderer(),
            default  => throw new \Exception( "Unsupported database engine: {$this->engine}" )
        };
    }

    /*
    |-------------------------
    | RENDERING HELPERS
    |-------------------------
    */

    /**
     * Reset the builder.
     *
     * @return self
     */
    public function reset() : self {
        $this->type = null;
        $this->table = '';
        $this->intent = [];
        return $this;
    }

    /**
     * Get query type.
     *
     * @return string|null
     */
    public function get_type() : ?string {
        return $this->type;
    }

    /**
     * Get raw table name.
     *
     * @return string
     */
    public function get_table() : string {
        return $this->table;
    }

    /**
     * Get raw intent (for testing/debugging).
     *
     * @return array
     */
    public function get_intent() : array {
        return $this->intent;
    }

    /**
     * Get database engine.
     *
     * @return string
     */
    public function get_engine() : string {
        return $this->engine;
    }

    /**
     * Reset intent components.
     *
     * @return void
     */
    private function reset_intent() : void {
        $this->intent = [];
    }

}