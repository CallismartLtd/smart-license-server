<?php
/**
 * Table name enum file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Schema
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Schema;

/**
 * The application's database tables, by logical (unprefixed) name.
 *
 * The value is the table name without the database prefix. The real name is
 * resolved through SchemaRegistry, which holds the active prefix:
 *
 *     TableName::USERS->table();                                  // "smliser_users"
 *     SchemaRegistry::instance()->table_name( TableName::USERS ); // same
 *
 * Because the prefix is read when the name is resolved, not when the
 * application starts, a prefix changed during installation applies at once.
 */
enum TableName : string {
	case LICENSES                 = 'licenses';
	case LICENSE_META             = 'license_meta';
	case PLUGINS                  = 'plugins';
	case PLUGIN_META              = 'plugin_meta';
	case THEMES                   = 'themes';
	case THEME_META               = 'theme_meta';
	case SOFTWARE                 = 'software';
	case SOFTWARE_META            = 'software_meta';
	case ITEM_DOWNLOAD_TOKEN      = 'item_download_token';
	case APP_DOWNLOAD_TOKENS      = 'app_download_tokens';
	case MONETIZATION             = 'monetization';
	case PRICING_TIERS            = 'pricing_tiers';
	case BULK_MESSAGES            = 'bulk_messages';
	case BULK_MESSAGES_APPS       = 'bulk_messages_apps';
	case OPTIONS                  = 'options';
	case ANALYTICS_LOG            = 'analytics_log';
	case ANALYTICS_DAILY          = 'analytics_daily';
	case RESOURCE_OWNERS          = 'resource_owners';
	case USERS                    = 'users';
	case USER_OPTIONS             = 'user_options';
	case SERVICE_ACCOUNTS         = 'service_accounts';
	case ROLES                    = 'roles';
	case ROLE_CAPS                = 'role_caps';
	case PRINCIPAL_ROLES          = 'principal_roles';
	case ORGANIZATIONS            = 'organizations';
	case ORGANIZATION_MEMBERS     = 'organization_members';
	case IDENTITY_PROVIDER_LOOKUP = 'identity_provider_lookup';
	case BACKGROUND_JOBS          = 'background_jobs';
	case FAILED_JOBS              = 'failed_jobs';

	/**
	 * The real table name, with the active database prefix.
	 *
	 * @return string E.g. "smliser_users".
	 * @throws \LogicException When the prefix has not been set (see SchemaRegistry::set_prefix()).
	 */
	public function table() : string {
		return SchemaRegistry::instance()->table_name( $this );
	}
}