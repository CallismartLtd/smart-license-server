<?php
/**
 * Database job storage adapter class file.
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
use Callismart\DBPrism\Database;
use Callismart\DBPrism\Utils\CaseExpression;
use DateTimeImmutable;
use RuntimeException;

/**
 * Job storage adapter backed by the application Database abstraction.
 */
class DatabaseJobStorageAdapter implements JobStorageAdapterInterface {

    /**
     * Constructor.
     *
     * @param Database $db Database abstraction instance.
     * @param string $jobs_table The database table name where jobs are stored.
     */
    public function __construct( 
        private Database $db, 
        private string $jobs_table = \SMLISER_BACKGROUND_JOBS_TABLE 
    ) {}

    /*
    |-------------------------------------------
    | JobStorageAdapterInterface implementation
    |-------------------------------------------
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

        $id = $this->db->insert( $this->jobs_table, $data );

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
            $this->db->begin_transaction();

            // Build queue filter.
            $queue_sql    = \smliserQueryBuilder()
                ->select( '*' )->from( $this->jobs_table )
                ->where_in( 'status', [ JobDTO::STATUS_PENDING, JobDTO::STATUS_RETRYING] )
                ->where( 'available_at', '<=', $now );

            if ( null !== $queue ) {
                $queue_sql->where( 'queue', '=', $queue );
            }

            // Select the next claimable job, ordered by queue priority
            // then numeric priority then availability time.
            $queue_sql->order_by_case( 
                fn( CaseExpression $case ) => $case
                    ->as( 'priority')
                    ->when( fn( CaseExpression $q ) => $q->where( 'queue', '=', 'critical' ), '1' )
                    ->when( fn( CaseExpression $q ) => $q->where( 'queue', '=', 'default' ), '2' )
                    ->when( fn( CaseExpression $q ) => $q->where( 'queue', '=', 'low' ), '3' )
                    ->else( 4 ),
                'ASC'
            )
            ->order_by( 'priority', 'ASC' )
            ->order_by( 'available_at', 'ASC' )
            ->limit( 1 )
            ->lock_for_update();

            $row = $this->db->get_row( $queue_sql->build(), $queue_sql->get_bindings() );

            if ( ! $row ) {
                return null;
            }

            // Atomically mark as running.
            $affected = $this->db->update(
                $this->jobs_table,
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

            $this->db->commit();
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
            $this->jobs_table,
            $data,
            [ 'id' => $id ]
        );

        return $job;
    }

    /**
     * {@inheritdoc}
     */
    public function get_job_by_id( int $id ): ?JobDTO {
        $sql    = \smliserQueryBuilder()
            ->select( '*' )->from( $this->jobs_table )
            ->where( 'id', '=', $id )
            ->limit( 1 );
        $row = $this->db->get_row( $sql->build(), $sql->get_bindings() );

        return $row ? $this->row_to_dto( $row ) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function get_jobs_by_status( string $status, ?string $queue = null, int $limit = 50, int $offset = 0 ): array {
        $sql    = \smliserQueryBuilder()
            ->select( '*' )->from( $this->jobs_table )
            ->where( 'status', '=', $status );

        if ( $queue !== null ) {
            $sql->where( 'queue', '=', $queue );
        }

        $sql->order_by( 'created_at', 'ASC' )
        ->limit( $limit )
        ->offset( $offset );

        $rows = $this->db->get_results( $sql->build(), $sql->get_bindings() );

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
            $this->db->begin_transaction();

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

            $removed = $this->db->delete( $this->jobs_table, [ 'id' => $id ] );

            if ( $removed === false ) {
                $this->db->rollback();
                return false;
            }

            $this->db->commit();
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

        $affected = $this->db->delete( $this->jobs_table, [ 'id' => $id ] );

        return (bool) $affected;
    }

    /**
     * {@inheritdoc}
     */
    public function count_jobs_by_status( string $status, ?string $queue = null ): int {
        $queue_sql = smliserQueryBuilder()
            ->select( 'COUNT(*)' )->from( $this->jobs_table )
            ->where( 'status', '=', $status );


        if ( $queue !== null ) {
            $queue_sql->where( 'queue', '=', $queue );
        }

        return (int) $this->db->get_var( $queue_sql->build(), $queue_sql->get_bindings() );
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

            $sql    = \smliserQueryBuilder()
                ->select( '*' )->from( $this->jobs_table )
                ->where( 'status', '=', JobDTO::STATUS_RUNNING )
                ->where( 'started_at', '<=', $cutoff );

        $stale_jobs = $this->db->get_results( $sql->build(), $sql->get_bindings() );

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
                    $this->jobs_table,
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

        $sql    = smliserQueryBuilder()
            ->delete( $this->jobs_table )
            ->where( 'status', '=', JobDTO::STATUS_COMPLETED )
            ->where( 'completed_at', '<=', $cutoff );
        $deleted = $this->db->execute( $sql->build(), $sql->get_bindings() );

        return $deleted;
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