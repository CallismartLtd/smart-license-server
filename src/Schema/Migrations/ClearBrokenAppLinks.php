<?php
/**
 * Clear broken app links migration class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Schema\Migrations
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Migrations;

use Callismart\DBPrism\Migrations\AbstractMigration;
use Callismart\DBPrism\Migrations\Contracts\ReversibleMigrationInterface;
use SmartLicenseServer\Schema\TableName;

/** Clear broken links while retaining before/after values for an exact rollback. */
final class ClearBrokenAppLinks extends AbstractMigration implements ReversibleMigrationInterface {
	public static function get_description() : string {
		return 'Clear app download links saved without the app slug, and empty author profile URLs';
	}

	public function up() : void {
		$table = TableName::APPS->table();
		$snapshots = new MigrationSnapshotStore( $this->database() );

		$links = $this->query()->select( 'id', 'download_link' )->from( $table )->where_ends_with( 'download_link', '/.zip' );
		foreach ( $this->database()->get_results( $links->build(), $links->get_bindings() ) ?: [] as $row ) {
			$id = (int) $row['id'];
			$snapshots->capture( self::class, $id . ':download_link',
				[ 'id' => $id, 'field' => 'download_link', 'value' => $row['download_link'] ],
				[ 'id' => $id, 'field' => 'download_link', 'value' => null ]
			);
			$update = $this->query()->update( $table )->set( [ 'download_link' => null ] )->where( 'id', '=', $id );
			if ( false === $this->database()->execute( $update->build(), $update->get_bindings() ) ) {
				throw new \RuntimeException( sprintf( 'Could not clear the broken download link for app %d.', $id ) );
			}
		}

		$profiles = $this->query()->select( 'id', 'author_profile' )->from( $table )->where( 'author_profile', '=', '/' );
		foreach ( $this->database()->get_results( $profiles->build(), $profiles->get_bindings() ) ?: [] as $row ) {
			$id = (int) $row['id'];
			$snapshots->capture( self::class, $id . ':author_profile',
				[ 'id' => $id, 'field' => 'author_profile', 'value' => $row['author_profile'] ],
				[ 'id' => $id, 'field' => 'author_profile', 'value' => null ]
			);
			$update = $this->query()->update( $table )->set( [ 'author_profile' => null ] )->where( 'id', '=', $id );
			if ( false === $this->database()->execute( $update->build(), $update->get_bindings() ) ) {
				throw new \RuntimeException( sprintf( 'Could not clear the broken author profile for app %d.', $id ) );
			}
		}

		$snapshots->complete( self::class );
	}

	/** Restore only rows still in the post-migration state; retain later edits. */
	public function down() : void {
		$snapshots = new MigrationSnapshotStore( $this->database() );
		$table = TableName::APPS->table();
		foreach ( $snapshots->rows( self::class ) as $snapshot ) {
			$id = (int) ( $snapshot['before']['id'] ?? 0 );
			$field = (string) ( $snapshot['before']['field'] ?? '' );
			if ( $id < 1 || ! in_array( $field, [ 'download_link', 'author_profile' ], true ) ) {
				continue;
			}
			$select  = $this->query()->select( $field )->from( $table )->where( 'id', '=', $id )->limit( 1 );
			$current = $this->database()->get_row( $select->build(), $select->get_bindings() );
			if ( ! $current ) {
				throw new \RuntimeException( sprintf( 'Cannot restore %s for app %d because the app no longer exists.', $field, $id ) );
			}
			$original = $snapshot['before']['value'] ?? null;
			if ( $current[ $field ] === $original ) {
				continue;
			}
			if ( null !== $current[ $field ] ) {
				throw new \RuntimeException( sprintf( 'Cannot restore %s for app %d because it changed after this migration.', $field, $id ) );
			}
			$update = $this->query()->update( $table )
				->set( [ $field => $original ] )
				->where( 'id', '=', $id )
				->where_null( $field );
			if ( false === $this->database()->execute( $update->build(), $update->get_bindings() ) ) {
				throw new \RuntimeException( sprintf( 'Could not restore %s for app %d.', $field, $id ) );
			}
		}
		$snapshots->clear( self::class );
	}
}
