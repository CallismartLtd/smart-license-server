<?php
/**
 * WordPress database adapter class file.
 *
 * @author Callistus Nwachukwu
 * @package Callismart\DBPrism\Adapters
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\WordPress;

use Callismart\DBPrism\Adapters\MysqliAdapter;
use Callismart\DBPrism\DBConfigDTO;
use mysqli;
use wpdb;

/**
 * Runs DBPrism on WordPress's own database connection.
 *
 * Borrows the mysqli connection that $wpdb opened, so every query runs
 * through MysqliAdapter as a real prepared statement: values are bound by
 * type (null stays NULL, floats keep every digit, streams are binary), and
 * a "?" or "%" inside an SQL literal is just text.
 *
 * The connection belongs to WordPress. This adapter never opens a second
 * one and never closes it: close() only detaches the adapter. When WordPress
 * reconnects, the adapter follows the new connection.
 *
 * Requires WordPress on MySQL or MariaDB through mysqli, its default. A wpdb
 * drop-in that connects another way (such as the SQLite Database Integration
 * plugin) is refused with an error.
 *
 * WordPress settings apply to every query run through this adapter:
 *
 * - wpdb removes MySQL's strict modes from the session, so an over-long
 *   string is truncated rather than refused. The migration operators turn
 *   strict mode on for each change they make.
 * - WordPress reports mysqli errors as return values (MYSQLI_REPORT_OFF);
 *   the adapter reads them that way and does not change the setting.
 * - Queries do not pass through wpdb::query(), so the "query" filter and
 *   SAVEQUERIES logging do not see them.
 * - While a stream() is open, the connection cannot run any other query,
 *   WordPress's included: close the stream before anything else, hooks
 *   included, reads from the database.
 */
class WPDBAdapter extends MysqliAdapter {

    /**
     * Class constructor.
     *
     * @param wpdb $wpdb WordPress database object, normally the global $wpdb.
     */
    public function __construct( protected wpdb $wpdb ) {
        parent::__construct( static::config_from( $wpdb ) );
    }

    /**
     * Attach to WordPress's connection, asking WordPress to connect first when it has not.
     *
     * @return bool True when the adapter has a connection to use.
     */
    public function connect() : bool {
        if ( $this->is_connected() ) {
            return true;
        }

        $this->mysqli = null;

        if ( null === $this->wpdb->dbh ) {
            // false: report a failure instead of ending the request with wp_die().
            $this->wpdb->db_connect( false );
        }

        $dbh = $this->wpdb->dbh;

        if ( null === $dbh ) {
            // wpdb keeps connection errors out of last_error; mysqli still has the cause.
            $reason           = $this->wpdb->last_error ?: mysqli_connect_error() ?: 'no reason given.';
            $this->last_error = 'WordPress could not connect to the database: ' . $reason;
            return false;
        }

        if ( ! $dbh instanceof mysqli ) {
            $this->last_error = sprintf(
                'WPDBAdapter needs WordPress on MySQL or MariaDB through mysqli; this wpdb connects with %s.',
                get_debug_type( $dbh )
            );
            return false;
        }

        $this->mysqli = $dbh;

        return true;
    }

    /**
     * Detach from WordPress's connection without closing it.
     *
     * @return void
     */
    public function close() : void {
        $this->mysqli = null;
    }

    /**
     * Whether the adapter is attached to WordPress's current connection.
     *
     * False after WordPress replaced its connection (a reconnect), so the
     * next query attaches to the new one.
     *
     * @return bool
     */
    public function is_connected() : bool {
        return $this->mysqli instanceof mysqli && $this->mysqli === $this->wpdb->dbh;
    }

    /**
     * Build the configuration from WordPress's database settings.
     *
     * DB_HOST may carry a port, a socket, or both ("localhost:3307",
     * "localhost:/run/mysqld.sock"); wpdb's own parser splits them.
     *
     * @param wpdb $wpdb WordPress database object.
     * @return DBConfigDTO
     */
    protected static function config_from( wpdb $wpdb ) : DBConfigDTO {
        $parsed = $wpdb->parse_db_host( (string) $wpdb->dbhost );

        [ $host, $port, $socket ] = false === $parsed ? [ (string) $wpdb->dbhost, null, null ] : $parsed;

        return new DBConfigDTO( [
            'driver'   => 'mysql',
            'host'     => '' === $host ? 'localhost' : $host,
            'port'     => $port,
            'socket'   => $socket,
            'dbname'   => $wpdb->dbname,
            'username' => $wpdb->dbuser,
            'password' => $wpdb->dbpassword,
            'charset'  => $wpdb->charset ?: null,
        ] );
    }
}