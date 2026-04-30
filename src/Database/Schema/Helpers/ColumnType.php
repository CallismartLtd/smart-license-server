<?php
/**
 * Canonical database column type registry class file.
 *
 * Defines engine-neutral column type intents that can be translated
 * into native SQL column types per database driver.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema\Helpers;

use InvalidArgumentException;

final class ColumnType {

	public const BOOLEAN		= 1;
	public const SMALL_INT		= 2;
	public const INTEGER		= 3;
	public const BIG_INT		= 4;
	public const DECIMAL		= 5;
	public const FLOAT			= 6;
	public const DOUBLE			= 7;

	public const CHAR			= 10;
	public const STRING			= 11;
	public const VARCHAR		= 11;
	public const TEXT			= 12;
	public const LONG_TEXT		= 13;

	public const BINARY			= 20;
	public const BLOB			= 21;

	public const DATE			= 30;
	public const TIME			= 31;
	public const DATETIME		= 32;
	public const TIMESTAMP		= 33;

	public const JSON			= 40;
	public const UUID			= 41;

	public const MYSQL			= 'mysql';
	public const POSTGRES		= 'pgsql';
	public const SQLITE			= 'sqlite';

	/**
	 * Resolve a canonical column type into a native engine type.
	 *
	 * @param int    $type   Canonical type constant.
	 * @param string $engine Database engine identifier.
	 * @param array  $args   Optional modifiers.
	 *
	 * @return string
	 */
	public static function resolve( int $type, string $engine, array $args = array() ): string {
		$map = self::map( $engine );

		if ( ! isset( $map[ $type ] ) ) {
			throw new InvalidArgumentException(
				"Unsupported column type '{$type}'."
			);
		}

		return self::apply_modifiers(
			$map[ $type ],
			$type,
			$args
		);
	}

	/**
	 * Get engine-specific type mappings.
	 *
	 * @param string $engine Database engine.
	 *
	 * @return array
	 */
	public static function map( string $engine ): array {
		return match ( $engine ) {
			self::MYSQL		=> array(
				self::BOOLEAN		=> 'TINYINT',
				self::SMALL_INT		=> 'SMALLINT',
				self::INTEGER		=> 'INT',
				self::BIG_INT		=> 'BIGINT',
				self::DECIMAL		=> 'DECIMAL',
				self::FLOAT			=> 'FLOAT',
				self::DOUBLE		=> 'DOUBLE',

				self::CHAR			=> 'CHAR',
				self::STRING		=> 'VARCHAR',
				self::TEXT			=> 'TEXT',
				self::LONG_TEXT		=> 'LONGTEXT',

				self::BINARY		=> 'VARBINARY',
				self::BLOB			=> 'BLOB',

				self::DATE			=> 'DATE',
				self::TIME			=> 'TIME',
				self::DATETIME		=> 'DATETIME',
				self::TIMESTAMP		=> 'TIMESTAMP',

				self::JSON			=> 'JSON',
				self::UUID			=> 'CHAR',
			),

			self::POSTGRES	=> array(
				self::BOOLEAN		=> 'BOOLEAN',
				self::SMALL_INT		=> 'SMALLINT',
				self::INTEGER		=> 'INTEGER',
				self::BIG_INT		=> 'BIGINT',
				self::DECIMAL		=> 'NUMERIC',
				self::FLOAT			=> 'REAL',
				self::DOUBLE		=> 'DOUBLE PRECISION',

				self::CHAR			=> 'CHAR',
				self::STRING		=> 'VARCHAR',
				self::TEXT			=> 'TEXT',
				self::LONG_TEXT		=> 'TEXT',

				self::BINARY		=> 'BYTEA',
				self::BLOB			=> 'BYTEA',

				self::DATE			=> 'DATE',
				self::TIME			=> 'TIME',
				self::DATETIME		=> 'TIMESTAMP',
				self::TIMESTAMP		=> 'TIMESTAMP',

				self::JSON			=> 'JSONB',
				self::UUID			=> 'UUID',
			),

			self::SQLITE	=> array(
				self::BOOLEAN		=> 'INTEGER',
				self::SMALL_INT		=> 'INTEGER',
				self::INTEGER		=> 'INTEGER',
				self::BIG_INT		=> 'INTEGER',
				self::DECIMAL		=> 'NUMERIC',
				self::FLOAT			=> 'REAL',
				self::DOUBLE		=> 'REAL',

				self::CHAR			=> 'TEXT',
				self::STRING		=> 'TEXT',
				self::TEXT			=> 'TEXT',
				self::LONG_TEXT		=> 'TEXT',

				self::BINARY		=> 'BLOB',
				self::BLOB			=> 'BLOB',

				self::DATE			=> 'TEXT',
				self::TIME			=> 'TEXT',
				self::DATETIME		=> 'TEXT',
				self::TIMESTAMP		=> 'TEXT',

				self::JSON			=> 'TEXT',
				self::UUID			=> 'TEXT',
			),

			default => throw new InvalidArgumentException(
				"Unsupported database engine '{$engine}'."
			),
		};
	}

	/**
	 * Apply type modifiers such as length or precision.
	 *
	 * @param string $native Native SQL type.
	 * @param int    $type   Canonical type.
	 * @param array  $args   Modifier arguments.
	 *
	 * @return string
	 */
	private static function apply_modifiers( string $native, int $type, array $args ): string {
		return match ( $type ) {
			self::CHAR => sprintf(
				'%s(%d)',
				$native,
				(int) ( $args['length'] ?? 1 )
			),

			self::STRING => sprintf(
				'%s(%d)',
				$native,
				(int) ( $args['length'] ?? 255 )
			),

			self::DECIMAL => sprintf(
				'%s(%d,%d)',
				$native,
				(int) ( $args['precision'] ?? 10 ),
				(int) ( $args['scale'] ?? 2 )
			),

			self::UUID => 'CHAR' === $native
				? 'CHAR(36)'
				: $native,

			default => $native,
		};
	}
}