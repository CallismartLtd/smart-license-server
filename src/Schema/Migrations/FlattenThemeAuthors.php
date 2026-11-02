<?php
/**
 * Flatten theme author migration class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Schema\Migrations
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Migrations;

use Callismart\DBPrism\Migrations\AbstractMigration;
use Callismart\DBPrism\Migrations\Contracts\ReversibleMigrationInterface;
use SmartLicenseServer\Schema\TableName;

/**
 * Store theme authors the way every app type does while retaining exact values
 * so DBPrism can safely reverse the migration.
 */
final class FlattenThemeAuthors extends AbstractMigration implements ReversibleMigrationInterface {

	public static function get_description() : string {
		return 'Store theme authors as a name and a profile URL';
	}

	public function up() : void {
		$table     = TableName::APPS->table();
		$snapshots = new MigrationSnapshotStore( $this->database() );
		$select    = $this->query()
			->select( 'id', 'author', 'author_profile' )
			->from( $table )
			->where( 'app_type', '=', 'wptheme' )
			->where_starts_with( 'author', 'a:' );

		$rows = $this->database()->get_results( $select->build(), $select->get_bindings() ) ?: [];

		foreach ( $rows as $row ) {
			$serialized = (string) $row['author'];
			$author     = @\unserialize( $serialized, [ 'allowed_classes' => false ] );

			if ( ! is_array( $author ) ) {
				continue;
			}

			$name    = trim( (string) ( $author['author'] ?? '' ) ) ?: trim( (string) ( $author['display_name'] ?? '' ) );
			$profile = trim( (string) ( $row['author_profile'] ?? '' ) ) ?: trim( (string) ( $author['author_url'] ?? '' ) );
			$after   = [
				'author'         => '' !== $name ? $name : null,
				'author_profile' => '' !== $profile ? $profile : null,
			];
			$id      = (int) $row['id'];

			// Persist before values before touching the application row. Re-running
			// up() keeps the first snapshot and does not replace it with new state.
			$snapshots->capture( self::class, (string) $id, [
				'id'             => $id,
				'author'         => $serialized,
				'author_profile' => $row['author_profile'],
			], $after );

			$update = $this->query()
				->update( $table )
				->set( $after )
				->where( 'id', '=', $id );

			if ( false === $this->database()->execute( $update->build(), $update->get_bindings() ) ) {
				throw new \RuntimeException( sprintf( 'Could not flatten the author for theme %d.', $id ) );
			}
		}

		$snapshots->complete( self::class );
	}

	/** Restore each original value only when the row is still in our after-state. */
	public function down() : void {
		$snapshots = new MigrationSnapshotStore( $this->database() );
		$table     = TableName::APPS->table();

		foreach ( $snapshots->rows( self::class ) as $snapshot ) {
			$before = $snapshot['before'];
			$after  = $snapshot['after'];
			$id     = (int) ( $before['id'] ?? 0 );
			if ( $id < 1 || ! array_key_exists( 'author', $after ) || ! array_key_exists( 'author_profile', $after ) ) {
				continue;
			}
			$select  = $this->query()->select( 'author', 'author_profile' )->from( $table )->where( 'id', '=', $id )->limit( 1 );
			$current = $this->database()->get_row( $select->build(), $select->get_bindings() );
			if ( ! $current ) {
				throw new \RuntimeException( sprintf( 'Cannot restore the author for theme %d because the theme no longer exists.', $id ) );
			}
			$already_restored = $current['author'] === ( $before['author'] ?? null )
				&& $current['author_profile'] === ( $before['author_profile'] ?? null );
			if ( $already_restored ) {
				continue;
			}
			if ( $current['author'] !== $after['author'] || $current['author_profile'] !== $after['author_profile'] ) {
				throw new \RuntimeException( sprintf( 'Cannot restore the author for theme %d because it changed after this migration.', $id ) );
			}

			$update = $this->query()->update( $table )->set( [
				'author'         => $before['author'] ?? null,
				'author_profile' => $before['author_profile'] ?? null,
			] )->where( 'id', '=', $id );

			foreach ( [ 'author', 'author_profile' ] as $field ) {
				if ( null === $after[ $field ] ) {
					$update->where_null( $field );
				} else {
					$update->where( $field, '=', $after[ $field ] );
				}
			}

			if ( false === $this->database()->execute( $update->build(), $update->get_bindings() ) ) {
				throw new \RuntimeException( sprintf( 'Could not restore the author for theme %d.', $id ) );
			}
		}

		$snapshots->clear( self::class );
	}
}
