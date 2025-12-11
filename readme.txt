=== Smart License Server ===
Contributors: callismartltd
Tags: rest-api, license, private-repository, plugin, theme, self-hosted
Requires PHP: 7.4
Requires at least: 6.2
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Smart License Server is a secure, self-hosted software repository application for PHP developers, individual or company teams. It allows private hosting of custom software, WordPress plugins, and themes, while providing licensing, update, and monetization capabilities. It is environment-agnostic, fast, efficient, and scalable, suitable for shared hosting and dedicated servers alike.

== Description ==

Smart License Server is designed for developers who need privacy and control over their software distribution. Instead of relying on crowded marketplaces like WordPress.org, ThemeForest, or AppSumo, you can host your apps privately while:

* Managing licenses for software and WordPress plugins/themes
* Serving automatic updates to client websites
* Providing secure downloads and version control
* Enabling bulk messages or notifications to clients
* Organizing and querying apps efficiently

It is environment-agnostic, meaning it can run on any platform that supports PHP. File system operations are securely handled, ensuring protection of your source code and user data. Smart License Server is fast, scalable, and resource-efficient, making it suitable for small teams or enterprise-level deployment.

== Features ==

* Fully environment-agnostic PHP application
* Private hosting for software, WordPress plugins, and themes
* Built-in licensing API with activation, deactivation, validity, and reauthentication
* App repository with CRUD operations for plugins and themes
* Automatic update server for clients
* Bulk messaging support
* Secure filesystem operations
* Fast, lightweight, and scalable
* Self-hosted on shared hosting or VPS
* Extendable monetization and licensing APIs

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory if using WordPress.
2. Activate the plugin through the 'Plugins' menu (if WordPress).
3. For self-hosted PHP environments, include the autoloader and initialize `SmartLicenseServer\RESTAPI\Versions\V1` to register routes.
4. Configure your server and database according to the plugin documentation.

== REST API ==

Smart License Server exposes a REST API (`smliser/v1`) that supports:

* License management (activation, deactivation, uninstallation, validity)
* Repository access (plugins/themes)
* App CRUD
* Client authentication
* Download token reauthentication
* Bulk messages and notifications

Developers can integrate the API with WordPress or other PHP platforms to automate updates, licensing checks, and private distribution.

== Use Cases ==

* Secure private repository for internal software
* Automatic plugin/theme updates for client websites
* Self-hosted licensing system for commercial products
* Controlled distribution for private or confidential software
* Developers seeking privacy and independence from public marketplaces

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. The API is available at the base namespace: `smliser/v1`.
4. Use the provided routes for REST requests.

== REST API Endpoints ==
This version (`v1`) is the foundation for all REST API requests to the Smart License Server plugin and is designed to be extensible.

=== Namespace ===
`smliser/v1`

=== License Management ===

*License Activation*

    POST /license-activation/{app_type}/{app_slug}

Required Parameters:

| Parameter   | Type   | Description                                |
|------------|--------|--------------------------------------------|
| service_id | string | The service id associated with the license key |
| license_key | string | The license key to verify                  |
| domain     | string | The ID of the item associated with the license |

*License Deactivation*

    PUT|POST|PATCH /license-deactivation/

Required Parameters:

| Parameter   | Type   | Description                               |
|------------|--------|-------------------------------------------|
| license_key | string | The license key to deactivate            |
| service_id  | string | The service ID associated with the license |
| domain      | string | The URL of the website where the license is currently activated |

*License Uninstallation*

    PUT|POST|PATCH /license-uninstallation/

Required Parameters:

| Parameter   | Type   | Description                               |
|------------|--------|-------------------------------------------|
| license_key | string | The license key to uninstall             |
| service_id  | string | The service ID associated with the license |
| domain      | string | The URL of the website where the license is currently activated |

*License Validity Test*

    POST /license-validity-test/{app_type}/{app_slug}

Required Parameters:

| Parameter   | Type   | Description                               |
|------------|--------|-------------------------------------------|
| license_key | string | The license key to validate              |
| service_id  | string | The service ID associated with the license |
| domain      | string | The URL of the website where the license is currently activated |

=== Repository ===

*Plugin Information*

    GET /plugin-info/

Optional Parameters:

| Parameter | Type    | Description                    |
|-----------|---------|--------------------------------|
| id        | integer | The plugin ID                  |
| slug      | string  | The plugin slug (eg. plugin-slug/plugin-slug) |

*Repository Query*

    GET /repository/

Optional Parameters:

| Parameter  | Type    | Description                       |
|----------- |---------|-----------------------------------|
| search     | string  | Search term                       |
| page       | integer | Page number (default 1)           |
| limit      | integer | Items per page (default 10)       |
| app_slugs  | array   | Filter by app slugs               |
| app_types  | array   | Filter by app types (plugin/theme) |

*Repository App CRUD*

    GET|POST|PUT|PATCH|DELETE /repository/{app_type}/{app_slug}

Optional Parameters:

| Parameter | Type   | Description                  |
|-----------|--------|-------------------------------|
| item_id   | integer| The app ID                    |
| slug      | string | The app slug (e.g., plugin-slug) |

=== Authentication ===

*OAuth Client Authentication*

    GET /client-auth/

No parameters required.

*Download Token Reauthentication*

    POST /download-token-reauthentication/{app_type}/{app_slug}

Required Parameters:

| Parameter       | Type   | Description                                                              |
|-----------------|--------|--------------------------------------------------------------------------|
| domain          | string | The domain where the plugin is installed                                 |
| license_key     | string | The license key to reauthenticate                                        |
| download_token  | string | Base64 encoded download token issued during activation or last reauth   |
| service_id      | string | The service ID associated with the license                                |

=== Bulk Messages ===

*Bulk Messages*

    GET /bulk-messages/

Optional Parameters:

| Parameter | Type    | Description               |
|-----------|---------|---------------------------|
| page      | integer | Page number (default 1)   |
| limit     | integer | Number of messages (default 10) |
| app_slugs | array   | Filter by app slugs       |
| app_types | array   | Filter by app types       |

== Changelog ==

= 1.0.0 =
* Initial release with full REST API endpoints for licenses, plugins, themes, repository, authentication, and bulk messages.

== Upgrade Notice ==

= 1.0.0 =
* First stable release.
