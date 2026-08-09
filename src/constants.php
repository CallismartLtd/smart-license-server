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


/*
|--------------------------
| DATABASE TABLE CONSTANTS
|--------------------------
*/

/**
 * Licenses database table name.
 *
 * Dynamically generated using the configured database prefix.
 *
 * @var string `smliser_licenses.`
 */
define( 'SMLISER_LICENSE_TABLE', $smliser_runtime->db_table_prefix . 'licenses' );

/**
 * License metadata database table name.
 *
 * @var string `smliser_license_meta.`
 */
define( 'SMLISER_LICENSE_META_TABLE', $smliser_runtime->db_table_prefix . 'license_meta' );

/**
 * Plugins database table name.
 *
 * @var string `smliser_plugins.`
 */
define( 'SMLISER_PLUGINS_TABLE', $smliser_runtime->db_table_prefix . 'plugins' );

/**
 * Plugin metadata database table name.
 *
 * @var string `smliser_plugin_meta.`
 */
define( 'SMLISER_PLUGINS_META_TABLE', $smliser_runtime->db_table_prefix . 'plugin_meta' );

/**
 * Themes database table name.
 *
 * @var string `smliser_themes.`
 */
define( 'SMLISER_THEMES_TABLE', $smliser_runtime->db_table_prefix . 'themes' );

/**
 * Theme metadata database table name.
 *
 * @var string `smliser_theme_meta.`
 */
define( 'SMLISER_THEMES_META_TABLE', $smliser_runtime->db_table_prefix . 'theme_meta' );

/**
 * Software database table name.
 *
 * @var string `smliser_software.`
 */
define( 'SMLISER_SOFTWARE_TABLE', $smliser_runtime->db_table_prefix . 'software' );

/**
 * Software metadata database table name.
 *
 * @var string `smliser_software_meta.`
 */
define( 'SMLISER_SOFTWARE_META_TABLE', $smliser_runtime->db_table_prefix . 'software_meta' );

/**
 * Item download token database table name.
 *
 * @var string `smliser_item_download_token.`
 */
define( 'SMLISER_DOWNLOAD_TOKEN_TABLE', $smliser_runtime->db_table_prefix . 'item_download_token' );

/**
 * Application download token database table name.
 *
 * @var string `smliser_app_download_tokens.`
 */
define( 'SMLISER_APP_DOWNLOAD_TOKEN_TABLE', $smliser_runtime->db_table_prefix . 'app_download_tokens' );

/**
 * Monetization records database table name.
 *
 * @var string `smliser_monetization.`
 */
define( 'SMLISER_MONETIZATION_TABLE', $smliser_runtime->db_table_prefix . 'monetization' );

/**
 * Pricing tiers database table name.
 *
 * @var string `smliser_pricing_tiers.`
 */
define( 'SMLISER_PRICING_TIER_TABLE', $smliser_runtime->db_table_prefix . 'pricing_tiers' );

/**
 * Bulk messages database table name.
 *
 * @var string `smliser_bulk_messages.`
 */
define( 'SMLISER_BULK_MESSAGES_TABLE', $smliser_runtime->db_table_prefix . 'bulk_messages' );

/**
 * Bulk message to application mapping database table name.
 *
 * @var string `smliser_bulk_messages_apps.`
 */
define( 'SMLISER_BULK_MESSAGES_APPS_TABLE', $smliser_runtime->db_table_prefix . 'bulk_messages_apps' );

/**
 * Plugin options database table name.
 *
 * @var string `smliser_options.`
 */
define( 'SMLISER_OPTIONS_TABLE', $smliser_runtime->db_table_prefix . 'options' );

/**
 * Analytics event logs database table name.
 *
 * @var string `smliser_analytics_log.`
 */
define( 'SMLISER_ANALYTICS_LOGS_TABLE', $smliser_runtime->db_table_prefix . 'analytics_log' );

/**
 * Daily analytics aggregation database table name.
 *
 * @var string `smliser_analytics_daily.`
 */
define( 'SMLISER_ANALYTICS_DAILY_TABLE', $smliser_runtime->db_table_prefix . 'analytics_daily' );

/**
 * Resource owners database table name.
 *
 * @var string `smliser_resource_owners.`
 */
define( 'SMLISER_OWNERS_TABLE', $smliser_runtime->db_table_prefix . 'resource_owners' );

/**
 * Internal users database table name.
 *
 * @var string `smliser_users.`
 */
define( 'SMLISER_USERS_TABLE', $smliser_runtime->db_table_prefix . 'users' );

/**
 * Users options database table name.
 * 
 * @var string `smliser_user_options.`
 */
define( 'SMLISER_USER_OPTIONS_TABLE', $smliser_runtime->db_table_prefix . 'user_options' );

/**
 * Service accounts database table name.
 *
 * @var string `smliser_service_accounts.`
 */
define( 'SMLISER_SERVICE_ACCOUNTS_TABLE', $smliser_runtime->db_table_prefix . 'service_accounts' );

/**
 * Roles database table name.
 *
 * @var string `smliser_roles.`
 */
define( 'SMLISER_ROLES_TABLE', $smliser_runtime->db_table_prefix . 'roles' );

/**
 * Roles database table name.
 *
 * @var string `smliser_role_caps.`
 */
define( 'SMLISER_ROLE_CAPABILITIES_TABLE', $smliser_runtime->db_table_prefix . 'role_caps' );

/**
 * Roles to principals database table name.
 *
 * @var string `smliser_principal_roles.`
 */
define( 'SMLISER_ROLE_ASSIGNMENT_TABLE', $smliser_runtime->db_table_prefix . 'principal_roles' );

/**
 * Organizations database table name.
 *
 * @var string `smliser_organizations.`
 */
define( 'SMLISER_ORGANIZATIONS_TABLE', $smliser_runtime->db_table_prefix . 'organizations' );

/**
 * Organization members database table name.
 *
 * @var string `smliser_organization_members.`
 */
define( 'SMLISER_ORGANIZATION_MEMBERS_TABLE', $smliser_runtime->db_table_prefix . 'organization_members' );

/**
 * Identity provider map database table name.
 *
 * @var string `smliser_identity_provider_lookup.`
 */
define( 'SMLISER_IDENTITY_FEDERATION_TABLE', $smliser_runtime->db_table_prefix . 'identity_provider_lookup' );

/**
 * Background jobs queue table name.
 *
 * @var string `smliser_background_jobs`
 */
define( 'SMLISER_BACKGROUND_JOBS_TABLE', $smliser_runtime->db_table_prefix . 'background_jobs' );

/**
 * Failed jobs archive table name.
 *
 * @var string `smliser_failed_jobs`
 */
define( 'SMLISER_FAILED_JOBS_TABLE', $smliser_runtime->db_table_prefix . 'failed_jobs' );