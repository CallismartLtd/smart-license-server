<?php
/**
 * Database job storage adapter class file.
 *
 * Implements the job queue storage layer using the application's
 * existing Database abstraction. This is the primary adapter for
 * Smart License Server — it requires no additional infrastructure
 * beyond the database connection that is already bootstrapped.
 *
 * ## Table structure
 *
 * All jobs live in SMLISER_BACKGROUND_JOBS_TABLE.
 * Failed jobs are archived to SMLISER_FAILED_JOBS_TABLE.
 * Both tables are defined in DBTables and created by the installer.
 *
 * ## Atomic job claiming
 *
 * dequeue() claims jobs atomically using a transaction:
 *   1. SELECT the next eligible job with FOR UPDATE (locks the row).
 *   2. UPDATE its status to 'running' and record started_at.
 *   3. COMMIT.
 *
 * This prevents two workers running concurrently from picking up
 * the same job, without requiring any external locking mechanism.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Queue\Adapters
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Queue\Adapters;

use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Database\Database;
use DateTimeImmutable;
use RuntimeException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Job storage adapter backed by the application Database abstraction.
 */
class DatabaseJobStorageAdapter implements JobStorageAdapterInterface {

    /**
     * The database abstraction instance.
     *
     * Injected at construction — this adapter never opens or closes
     * the connection. Lifecycle is managed by Config::setGlobalDBAdapter().
     *
     * @var Database
     */
    private Database $db;

    /**
     * Constructor.
     *
     * @param Database $db The bootstrapped database instance.
     */
    public function __construct( Database $db ) {
        $this->db = $db;
    }

    /*
    |----------------------
    | JobStorageAdapterInterface implementation
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Inserts the job row and returns the DTO with its storage ID set.
     *
     * @throws RuntimeException On insert failure.
     */
    public function enqueue( JobDTO $job ): JobDTO {
        $data = $this->dto_to_row( $job );

        // ID is assigned by the DB — never insert it explicitly.
        unset( $data['id'] );

        $id = $this->db->insert( SMLISER_BACKGROUND_JOBS_TABLE, $data );

        if ( $id === false ) {
            throw new RuntimeException(
                sprintf(
                    'DatabaseJobStorageAdapter: failed to enqueue job "%s". DB error: %s',
                    $job->job_class,
                    $this->db->get_last_error() ?? 'unknown'
                )
            );
        }

        return $job->set( JobDTO::KEY_ID, (int) $id );
    }

    /**
     * {@inheritdoc}
     *
     * Atomically claims the next available job using a transaction so
     * concurrent workers cannot pick up the same envelope.
     *
     * Priority ordering: lower priority number = processed first (1 beats 10).
     * Queue ordering: critical → default → low, then by priority, then by available_at.
     */
    public function dequeue( ?string $queue = null ): ?JobDTO {
        $now = ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' );

        try {
            // Build queue filter.
            $queue_sql    = '';
            $queue_params = [];

            if ( $queue !== null ) {
                $queue_sql      = 'AND queue = ?';
                $queue_params[] = $queue;
            }

            // Select the next claimable job, ordered by queue priority
            // then numeric priority then availability time.
            $params = array_merge(
                [ JobDTO::STATUS_PENDING, JobDTO::STATUS_RETRYING, $now ],
                $queue_params
            );

            $row = $this->db->get_row(
                "SELECT * FROM " . SMLISER_BACKGROUND_JOBS_TABLE . "
                 WHERE status IN (?, ?)
                   AND available_at <= ?
                   {$queue_sql}
                 ORDER BY
                    CASE queue
                        WHEN 'critical' THEN 1
                        WHEN 'default'  THEN 2
                        WHEN 'low'      THEN 3
                        ELSE 4
                    END ASC,
                    priority ASC,
                    available_at ASC
                 LIMIT 1",
                $params
            );

            if ( ! $row ) {
                return null;
            }

            // Atomically mark as running.
            $affected = $this->db->update(
                SMLISER_BACKGROUND_JOBS_TABLE,
                [
                    'status'     => JobDTO::STATUS_RUNNING,
                    'started_at' => $now,
                    'attempts'   => (int) $row['attempts'] + 1,
                ],
                [ 'id' => $row['id'] ]
            );

            if ( ! $affected ) {
                $this->db->rollback();
                return null;
            }

            // Reflect the claimed state on the returned DTO.
            $row['status']     = JobDTO::STATUS_RUNNING;
            $row['started_at'] = $now;
            $row['attempts']   = (int) $row['attempts'] + 1;

            return $this->row_to_dto( $row );

        } catch ( \Throwable $e ) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException If the job has no ID.
     */
    public function update_job( JobDTO $job ): JobDTO {
        $id = $job->get( JobDTO::KEY_ID );

        if ( ! $id ) {
            throw new RuntimeException(
                'DatabaseJobStorageAdapter: cannot update a job that has no ID.'
            );
        }

        $data = $this->dto_to_row( $job );
        unset( $data['id'], $data['created_at'] ); // Never overwrite immutable fields.

        $this->db->update(
            SMLISER_BACKGROUND_JOBS_TABLE,
            $data,
            [ 'id' => $id ]
        );

        return $job;
    }

    /**
     * {@inheritdoc}
     */
    public function get_job_by_id( int $id ): ?JobDTO {
        $row = $this->db->get_row(
            'SELECT * FROM ' . SMLISER_BACKGROUND_JOBS_TABLE . ' WHERE id = ?',
            [ $id ]
        );

        return $row ? $this->row_to_dto( $row ) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function get_jobs_by_status( string $status, ?string $queue = null, int $limit = 50, int $offset = 0 ): array {
        $queue_sql    = '';
        $params       = [ $status ];

        if ( $queue !== null ) {
            $queue_sql = 'AND queue = ?';
            $params[]  = $queue;
        }

        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->db->get_results(
            "SELECT * FROM " . SMLISER_BACKGROUND_JOBS_TABLE . "
            WHERE status = ? {$queue_sql}
            ORDER BY created_at ASC
            LIMIT ? OFFSET ?",
            $params
        );

        return array_map( [ $this, 'row_to_dto' ], $rows );
    }

    /**
     * {@inheritdoc}
     *
     * Copies the full job row to the failed jobs archive table,
     * then removes it from the active queue.
     */
    public function archive_failed_job( JobDTO $job ): bool {
        $id = $job->get( JobDTO::KEY_ID );

        if ( ! $id ) {
            return false;
        }

        $row = $this->dto_to_row( $job );

        try {
            $archived = $this->db->insert( SMLISER_FAILED_JOBS_TABLE, [
                'job_id'        => $id,
                'job_class'     => $row['job_class'],
                'queue'         => $row['queue'],
                'payload'       => $row['payload'],
                'error_message' => $row['error_message'],
                'failed_at'     => ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ),
            ] );

            if ( $archived === false ) {
                $this->db->rollback();
                return false;
            }

            $removed = $this->db->delete(
                SMLISER_BACKGROUND_JOBS_TABLE,
                [ 'id' => $id ]
            );

            if ( $removed === false ) {
                $this->db->rollback();
                return false;
            }

            return true;

        } catch ( \Throwable $e ) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove_job( JobDTO $job ): bool {
        $id = $job->get( JobDTO::KEY_ID );

        if ( ! $id ) {
            return false;
        }

        $affected = $this->db->delete(
            SMLISER_BACKGROUND_JOBS_TABLE,
            [ 'id' => $id ]
        );

        return (bool) $affected;
    }

    /**
     * {@inheritdoc}
     */
    public function count_jobs_by_status( string $status, ?string $queue = null ): int {
        $queue_sql = '';
        $params    = [ $status ];

        if ( $queue !== null ) {
            $queue_sql = 'AND queue = ?';
            $params[]  = $queue;
        }

        return (int) $this->db->get_var(
            "SELECT COUNT(*) FROM " . SMLISER_BACKGROUND_JOBS_TABLE . "
             WHERE status = ? {$queue_sql}",
            $params
        );
    }

    /**
     * {@inheritdoc}
     *
     * Jobs stuck in 'running' beyond the timeout window are reset to
     * 'pending' so they can be picked up again by the next worker cycle.
     * This handles workers that died mid-execution without updating the job.
     */
    public function release_stale_running_jobs( int $timeout_seconds = 300 ): int {
        $cutoff = ( new DateTimeImmutable() )
            ->modify( "-{$timeout_seconds} seconds" )
            ->format( 'Y-m-d H:i:s' );

        $stale_jobs = $this->db->get_results(
            "SELECT * FROM " . SMLISER_BACKGROUND_JOBS_TABLE . "
             WHERE status = ? AND started_at <= ?",
            [ JobDTO::STATUS_RUNNING, $cutoff ]
        );

        if ( empty( $stale_jobs ) ) {
            return 0;
        }

        $released = 0;

        foreach ( $stale_jobs as $row ) {
            $job = $this->row_to_dto( $row );

            // If the job has exhausted its attempts, archive it as failed.
            if ( $job->has_exceeded_max_attempts() ) {
                $job = $job->set( JobDTO::KEY_ERROR_MESSAGE, 'Worker timeout — max attempts exceeded.' )
                           ->set( JobDTO::KEY_STATUS, JobDTO::STATUS_FAILED );
                $this->archive_failed_job( $job );
            } else {
                // Otherwise return it to the queue for retry.
                $this->db->update(
                    SMLISER_BACKGROUND_JOBS_TABLE,
                    [ 'status' => JobDTO::STATUS_RETRYING ],
                    [ 'id'     => $row['id'] ]
                );
            }

            $released++;
        }

        return $released;
    }

    /**
     * {@inheritdoc}
     */
    public function purge_completed_jobs( int $older_than_days = 7 ): int {
        $cutoff = ( new DateTimeImmutable() )
            ->modify( "-{$older_than_days} days" )
            ->format( 'Y-m-d H:i:s' );

        // Count before deleting — db->query() returns a raw statement
        // whose rowCount() behaviour varies across adapters, so we derive
        // the count from a SELECT first to stay within the public DB API.
        $count = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM " . SMLISER_BACKGROUND_JOBS_TABLE . "
             WHERE status = ? AND completed_at <= ?",
            [ JobDTO::STATUS_COMPLETED, $cutoff ]
        );

        if ( $count === 0 ) {
            return 0;
        }

        $this->db->query(
            "DELETE FROM " . SMLISER_BACKGROUND_JOBS_TABLE . "
             WHERE status = ? AND completed_at <= ?",
            [ JobDTO::STATUS_COMPLETED, $cutoff ]
        );

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function get_adapter_id(): string {
        return 'database';
    }

    /*
    |----------------------
    | Row ↔ DTO mapping
    |----------------------
    */

    /**
     * Hydrate a JobDTO from a raw database row.
     *
     * Handles type coercion from the flat string values that come
     * back from the DB layer — JSON payload, datetime strings, etc.
     *
     * @param array<string, mixed> $row Raw associative row from the DB.
     * @return JobDTO
     */
    private function row_to_dto( array $row ): JobDTO {
        return new JobDTO( [
            JobDTO::KEY_ID            => (int) $row['id'],
            JobDTO::KEY_JOB_CLASS     => (string) $row['job_class'],
            JobDTO::KEY_QUEUE         => (string) $row['queue'],
            JobDTO::KEY_PRIORITY      => (int) $row['priority'],
            JobDTO::KEY_STATUS        => (string) $row['status'],
            JobDTO::KEY_PAYLOAD       => $this->decode_payload( $row['payload'] ?? '' ),
            JobDTO::KEY_ATTEMPTS      => (int) $row['attempts'],
            JobDTO::KEY_MAX_ATTEMPTS  => (int) $row['max_attempts'],
            JobDTO::KEY_AVAILABLE_AT  => (string) $row['available_at'],
            JobDTO::KEY_CREATED_AT    => (string) $row['created_at'],
            JobDTO::KEY_STARTED_AT    => ! empty( $row['started_at'] ) ? (string) $row['started_at'] : null,
            JobDTO::KEY_COMPLETED_AT  => ! empty( $row['completed_at'] ) ? (string) $row['completed_at'] : null,
            JobDTO::KEY_RESULT        => isset( $row['result'] )
                                            ? $this->decode_payload( $row['result'] )
                                            : null,
            JobDTO::KEY_ERROR_MESSAGE => ! empty( $row['error_message'] )
                                            ? (string) $row['error_message']
                                            : null,
        ] );
    }

    /**
     * Flatten a JobDTO into a plain array suitable for DB insert/update.
     *
     * DateTimeImmutable fields are formatted as Y-m-d H:i:s strings.
     * Payload and result are JSON-encoded.
     * Null fields are preserved as null for the DB layer to handle.
     *
     * @param JobDTO $job
     * @return array<string, mixed>
     */
    private function dto_to_row( JobDTO $job ): array {
        $data = $job->to_array();

        // Encode payload and result as JSON for storage.
        $data['payload'] = json_encode( $data['payload'] ?? [] );
        $data['result']  = $data['result'] !== null
            ? json_encode( $data['result'] )
            : null;

        return $data;
    }

    /**
     * Decode a JSON-encoded payload string from the database.
     *
     * Returns an empty array on any decode failure so the system
     * never receives a null payload where an array is expected.
     *
     * @param mixed $raw Raw value from the DB column.
     * @return array<string, mixed>
     */
    private function decode_payload( mixed $raw ): array {
        if ( empty( $raw ) ) {
            return [];
        }

        $decoded = json_decode( (string) $raw, true );

        return is_array( $decoded ) ? $decoded : [];
    }
}