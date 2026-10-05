<?php
/**
 * @var \SmartLicenseServer\RuntimeConfig $smliser_runtime 
 */

/**
 * Human-readable application name.
 *
 * @var string
 */
define( 'SMLISER_APP_NAME', 'Smart License Server' );

/**
 * Current application version.
 *
 * @var string
 */
define( 'SMLISER_VER', '0.2.0' );

/**
 * Current database schema version.
 *
 * Used to determine whether database migrations or updates are required.
 *
 * @var string
 */
define( 'SMLISER_DB_VER', '0.2.0' );

/*
|---------------------------------------
| CORE BOOTSTRAP CONSTANTS
|---------------------------------------
*/

/**
 * Absolute path to the root directory.
 *
 * @var string
 */
define( 'SMLISER_ROOT',  rtrim( $smliser_runtime->app_root, '/' ) . '/' );

/**
 * Absolute path to the runtime directory.
 *
 * @var string
 */
define( 'SMLISER_RUNTIME_DIR', rtrim( $smliser_runtime->runtime_dir, '/' ) . '/' );

/**
 * Absolute path to the storage directory.
 * 
 * @var string
 */
define( 'SMLISER_STORAGE_DIR', rtrim( $smliser_runtime->storage_dir,  '/' ) . '/' );

/**
 * Absolute path to the Smart License Server repository root directory.
 *
 * This is the base directory where all hosted application files are stored.
 *
 * @var string
 */
define( 'SMLISER_REPO_DIR', SMLISER_STORAGE_DIR . 'app-repository/' );

/**
 * Absolute path to the plugin repository directory.
 *
 * @var string
 */
define( 'SMLISER_PLUGINS_REPO_DIR', SMLISER_REPO_DIR . 'plugins/' );

/**
 * Absolute path to the theme repository directory.
 *
 * @var string
 */
define( 'SMLISER_THEMES_REPO_DIR', SMLISER_REPO_DIR . 'themes/' );

/**
 * Absolute path to the software repository directory.
 *
 * @var string
 */
define( 'SMLISER_SOFTWARE_REPO_DIR', SMLISER_REPO_DIR . 'software/' );

/**
 * Absolute path to the cache directory.
 * 
 * @var string
 */
define( 'SMLISER_CACHE_DIR', SMLISER_STORAGE_DIR . '.cache/' );

/**
 * Absolute path to the trash directory.
 * 
 * @var string
 */
define( 'SMLISER_TRASH_DIR', SMLISER_STORAGE_DIR . '.trash/' );

/**
 * Absolute path to the tmp directory.
 * 
 * @var string
 */
define( 'SMLISER_TMP_DIR', SMLISER_STORAGE_DIR . '.tmp/' );

/**
 * Absolute path to the uploads directory.
 * 
 * @var string
 */
define( 'SMLISER_UPLOADS_DIR', SMLISER_STORAGE_DIR . 'uploads/' );

/**
 * Absolute path to the logs directory.
 * 
 * @var string
 */
define( 'SMLISER_LOGS_DIR', SMLISER_STORAGE_DIR . 'logs/' );

/**
 * Absolute path to the application entry point file.
 *
 * @var string
 */
define( 'SMLISER_FILE', $smliser_runtime->index_file );

/**
 * The secret key used for encryption.
 * 
 * @var string
 */
define( 'SMLISER_SECRET', $smliser_runtime->secret );

/**
 * The salt used for encryption.
 * 
 * @var string
 */
define( 'SMLISER_SALT', $smliser_runtime->salt );

/**
 * Debug mode flag.
 * 
 * @var bool
 */
define( 'APP_DEBUG', (bool) $smliser_runtime->debug_mode );

/**
 * Temporary file prefix
 */
define( 'SMLISER_UPLOAD_TMP_PREFIX', 'smliser_tmp_' );

/**
 * Default file permission.
 * 
 * @var int
 */
define( 'SMLISER_FILE_PERMISSION', ( fileperms( SMLISER_FILE  ) & 0777 | 0644 ) );

/**
 * Default directory permission.
 * 
 * @var int
 */
define( 'SMLISER_DIR_PERMISSION', ( fileperms( SMLISER_ROOT ) & 0777 | 0755 ) );