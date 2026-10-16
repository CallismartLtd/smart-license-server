<?php
/**
 * Migration registry class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Schema
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema;

/**
 * The application's database migrations, in the order they apply.
 *
 * SchemaRegistry describes the current schema; this list describes how a
 * database created at an older schema reaches it. A fresh installation
 * creates the current schema directly and records every migration listed
 * here as applied without running it (see AppInstaller::create_tables()).
 *
 * Schema 0.2.0 is the base: the first schema whose databases keep a
 * migration ledger. The list starts empty there, and every migration in it
 * changes a schema at 0.2.0 or later.
 *
 * Rules:
 *
 *  - A migration that changes the schema goes at the end of the list.
 *    Entries are never removed or reordered once released.
 *  - The release that adds a migration also changes SchemaRegistry, so a
 *    fresh installation gets the same schema the migration produces.
 *  - The release that adds a migration bumps SMLISER_DB_VER. The updater
 *    runs migrations only when that version changes.
 *  - Migration classes live in SmartLicenseServer\Schema\Migrations.
 */
final class MigrationRegistry {

	/**
	 * Migration class names, oldest first.
	 *
	 * @var class-string<\Callismart\DBPrism\Migrations\Contracts\MigrationInterface>[]
	 */
	private const MIGRATIONS = [];

	/**
	 * Every registered migration, oldest first.
	 *
	 * @return class-string<\Callismart\DBPrism\Migrations\Contracts\MigrationInterface>[]
	 */
	public static function all() : array {
		return self::MIGRATIONS;
	}
}