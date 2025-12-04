=== Smart License Server ===
Contributors: callismart
Tags: license management, update server, plugin updates, license keys, monetization
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A professional License Key and Update Server that empowers developers to securely manage licenses and deliver updates for WordPress plugins, themes, and custom applications.

== Description ==

**Smart License Server** is a professional License Key and Update Server that empowers developers to securely manage licenses and deliver updates for WordPress plugins, themes, custom applications, or any other digital products. With Smart License Server, you can control access, enforce license rules, and monetize your products without relying on third-party marketplaces.

Whether you distribute plugins, themes, standalone software, or custom applications, Smart License Server provides a **centralized, secure, and extensible platform** to manage licenses, updates, and pricing tiers for all your products.

= Key Features =

Smart License Server works on any hosting environment and supports plugins, themes, and custom applications—without requiring SVN or external servers.

* **License Key Management** - Easily create, manage, and validate license keys for any product—plugins, themes, or custom applications. Supports single-site, multi-site, and lifetime licenses with full control over activations and expirations.

* **Universal Update Server** - Turn any WordPress site into a fully-featured update server via REST API. Works on any hosting environment (shared hosting, VPS, or dedicated servers) without requiring SVN or external services. Updates are securely stored on the database and hosting filesystem, safely away from public directories, ensuring reliable delivery.

* **Robust Filesystem Handling** - Overcome hosting limitations with robust, sandboxed filesystem APIs. Manage hosted applications, updates, and assets reliably across environments.

* **Monetization Support** - Define flexible pricing tiers and integrate multiple providers (e.g., WooCommerce, EDD). Control billing cycles, features, and maximum site activations per license to maximize revenue and control access.

* **Secure Access** - Only verified license holders can access premium products or updates. Includes OAuth support for authorized clients and programmatic API access.

* **Developer-Friendly API** - Provides detailed REST endpoints for license management, repository operations, update handling, and monetization. Seamlessly integrate with your existing workflows or custom applications.

* **Works with Free and Premium Products** - Manage both free and paid products in a single, unified platform.

* **Extensible** - Easily add custom providers, pricing tiers, integrations, or extend APIs to suit your unique business model.

= Why Purchase Smart License Server =

* **Complete Functionality** – Every feature, from license management to monetization APIs, is fully available. No hidden limitations or freemium tiers—what you purchase is what you get.
* **Automatic Updates** – Users automatically receive updates for all hosted applications and products through secure REST API endpoints.
* **Full Support & Maintenance** – We maintain, support, and enhance the plugin continuously, so you don't have to worry about broken updates or outdated functionality.

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or greater
* PHP version 8.0 or greater
* Web hosting: Shared, VPS, or any standard hosting environment

= Installation Steps =

1. Download the plugin from [Smart License Server](https://apps.callismart.com.ng/plugin/smart-license-server) for $150.
2. Upload the plugin files to the `/wp-content/plugins/smart-license-server` directory, or install via the WordPress plugin screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Configure your license and update settings in the Smart License Server admin page.

== Usage ==

= Getting Started =

1. **Create a Product** - Define your plugin or theme in the hosted repository. Set the item type (plugin, theme, or other) and basic metadata.

2. **Add Monetization** - Create pricing tiers for your product (e.g., Single Site, Multi-Site, Lifetime). Assign a provider (e.g., WooCommerce or EDD) to handle payments.

3. **Generate License Keys** - Issue license keys manually or automatically when a product is purchased. Set activation limits, expiration, and feature restrictions per tier.

4. **Serve Updates** - Users with valid licenses can receive automatic updates. Smart License Server handles version checks and secure delivery of update packages.

5. **Integration with Plugins/Themes** - Use the included API to validate licenses, check update availability, and fetch product metadata.

== Developer REST API Guide ==

Base Namespace: `/wp-json/smliser/v1`

= 1. License Activation =

**Endpoint:** `/wp-json/smliser/v1/license-activation/`
**Method:** POST
**Description:** Activates a license key for a specific domain, returning a download token, license expiry, and optional site secret for new domains.

**Parameters:**

* `item_id` (int, required) - ID of the item associated with the license
* `service_id` (string, required) - Service ID associated with the license key
* `license_key` (string, required) - License key to activate
* `domain` (string, required) - Domain where the license will be activated

**Example Request:**

`curl -X POST https://example.com/wp-json/smliser/v1/license-activation/ \
-d "item_id=123&service_id=service_001&license_key=XXXX&domain=example.com"`

**Success Response:**

`{
  "success": true,
  "item_id": 123,
  "license_key": "XXXX",
  "service_id": "service_001",
  "download_token": "<generated_download_token>",
  "token_expiry": "2025-10-30",
  "license_expiry": "2026-10-27",
  "site_secret": "<generated_site_secret>"
}`

**Error Responses:**

Invalid License:
`{
  "success": false,
  "code": "license_error",
  "message": "Invalid license",
  "data": {
    "status": 404
  }
}`

Server Busy:
`{
  "success": false,
  "code": "license_server_busy",
  "message": "Server is currently busy please retry. Contact support if the issue persists."
}`

= 2. License Deactivation =

**Endpoint:** `/license-deactivation/`
**Method:** POST
**Description:** Deactivate a license key for a specific domain.

**Parameters:**

* `license_key` (string, required) - License key to deactivate
* `service_id` (string, required) - Service ID associated with the license key
* `domain` (string, required) - Domain where the license is currently activated

**Example Request:**

`curl -X POST https://example.com/wp-json/smliser/v1/license-deactivation/ \
-d "license_key=XXXX&service_id=service_001&domain=example.com"`

**Sample Response:**

`{
  "success": true,
  "message": "License has been deactivated",
  "data": {
    "license_status": "Deactivated",
    "date": "2025-10-28"
  }
}`

**Response Notes:**

* `success` is always a boolean indicating if the operation was successful
* `license_status` shows the current status after the operation
* `date` is the server date when the deactivation occurred

**Domain Verification:**

* The deactivation request requires the domain to be verified
* The domain must have a known site token previously generated during activation
* Authorization is passed via a base64-encoded token in the request headers
* Requests failing verification return a WP_Error with appropriate HTTP status codes such as 400, 401, or 404

= 3. License Uninstallation =

**Endpoint:** `/license-uninstall/`
**Method:** POST
**Description:** Uninstall or remove a domain from the list of activated domains for a specific license key.

**Parameters:**

* `license_key` (string, required) - License key associated with the activation
* `service_id` (string, required) - Service ID associated with the license key
* `domain` (string, required) - Domain to uninstall from the license activation list

**Example Request:**

`curl -X POST https://example.com/wp-json/smliser/v1/license-uninstall/ \
-d "service_id=service_001&license_key=XXXX&domain=example.com"`

**Sample Response (Success):**

`{
  "success": true,
  "message": "Your domain example.com has been uninstalled successfully.",
  "data": {
    "license_status": "Active",
    "activated_on": 2
  }
}`

**Sample Response (Failure):**

`{
  "success": false,
  "message": "Unable to uninstall example.com please try again later.",
  "data": {
    "license_status": "Active",
    "activated_on": 2
  }
}`

= 4. License Validity Test =

**Endpoint:** `/license-validity-test/`
**Method:** POST
**Description:** Test if a license key is valid for a specific domain and check the download token.

**Parameters:**

* `item_id` (int, required) - ID of the item associated with the license
* `service_id` (string, required) - Service ID associated with the license key
* `license_key` (string, required) - License key to test
* `domain` (string, required) - Domain where the license is used

**Headers:**

* `X-Download-Token` (required) - The download token to verify authorization for the license

**Example Request:**

`curl -X POST https://example.com/wp-json/smliser/v1/license-validity-test/ \
-H "X-Download-Token: TOKEN_VALUE" \
-d "item_id=123&service_id=service_001&license_key=XXXX&domain=example.com"`

**Sample Response:**

`{
  "success": true,
  "message": "License and token validated successfully",
  "data": {
    "license": {
      "status": "Active",
      "expiry_date": "2026-10-27"
    },
    "token_validity": "Valid"
  }
}`

**Error Responses:**

Invalid License:
`{
  "success": false,
  "code": "invalid_license",
  "message": "The provided license does not exist"
}`

Missing Download Token:
`{
  "success": false,
  "code": "missing_download_token",
  "message": "Please provide the X-Download-Token header value"
}`

Domain verification failure:
`{
  "success": false,
  "code": "authorization_failed",
  "message": "Invalid authorization token"
}`

= 5. Item Download Reauthentication =

**Endpoint:** `/item-download-reauth/`
**Method:** POST
**Description:** Re-issue or re-authenticate a download token for a licensed item hosted on the repository.

**Parameters:**

* `item_id` (int, required) - ID of the item for which the download token will be reissued
* `license_key` (string, required) - License key associated with the item
* `service_id` (string, required) - Service ID associated with the license key
* `domain` (string, required) - Domain from which the request is made
* `download_token` (string, required) - The currently active download token to be reauthenticated

**Example Request:**

`curl -X POST https://example.com/wp-json/smliser/v1/item-download-reauth/ \
-d "item_id=123&service_id=service_001&license_key=XXXX&domain=example.com&download_token=YYYY"`

**Sample Response:**

`{
  "success": true,
  "message": "Download token reissued successfully",
  "data": {
    "download_token": "NEW_TOKEN_ABC123",
    "token_expiry": "2025-11-11"
  }
}`

= 6. Plugin Info Endpoint =

**Endpoint:** `/plugin-info/`
**Method:** GET
**Description:** Retrieves detailed information about a hosted plugin in the repository.

**Parameters:**

* `item_id` (int, optional) - The unique ID of the plugin to fetch information for. Either item_id or slug is required
* `slug` (string, optional) - The plugin slug (e.g., plugin-slug/plugin-slug). Either item_id or slug is required

**Permission Callback:**

* Ensures that either item_id or slug is provided
* Attempts to retrieve the plugin object by item_id or slug
* Returns WP_Error if neither is provided or the plugin does not exist

**Example Request:**

`curl -X GET https://example.com/wp-json/smliser/v1/plugin-info/?item_id=123`

**Sample Response:**

`{
  "success": true,
  "data": {
    "name": "Sample Plugin",
    "type": "plugin",
    "slug": "sample-plugin",
    "version": "1.2.0",
    "author": "<a href=\"https://example.com/author\">John Doe</a>",
    "author_profile": "https://example.com/author",
    "homepage": "https://example.com/plugin-homepage",
    "package": "https://example.com/download/sample-plugin.zip",
    "banners": [],
    "screenshots": [],
    "icons": [],
    "requires": "5.0",
    "tested": "6.4",
    "requires_php": "7.4",
    "requires_plugins": [],
    "added": "2025-10-01",
    "last_updated": "2025-10-20",
    "short_description": "A sample plugin description.",
    "sections": [],
    "num_ratings": 5,
    "rating": 4.8,
    "ratings": [],
    "support_url": "https://example.com/support",
    "active_installs": 1000,
    "is_monetized": false,
    "monetization": []
  }
}`

= 7. Plugin Download Logic =

This section handles the download of hosted plugins from the repository, combining URL structure, logic flow, and response details.

**URL Format:**

Standard (Public) Plugin Download:
`https://example.com/downloads-page/plugins/{plugin_slug}.zip`

Licensed/Monetized Plugin Download (requires token):
Query Parameter: `?download_token={token}`
HTTP Authorization Header: `Authorization: Bearer {token}`

**Logic Flow:**

1. Validate the request is for the plugins category
2. Retrieve the file_slug from query variables
3. Fetch the plugin object using the slug
4. If the plugin is monetized (licensed):
   - Check for a download token in query parameters
   - If missing, fallback to the Authorization header
   - Validate the token using smliser_verify_item_token() against the plugin's ID
5. Retrieve the plugin file path from the repository
6. Check file existence and readability
7. Serve the file for download with proper headers
8. Fire download statistics using smliser_stats action hook

**Headers on Successful Download:**

* Content-Type: application/zip
* Content-Disposition: attachment; filename="{plugin_slug}.zip"
* x-content-type-options: nosniff
* x-Robots-tag: noindex, nofollow
* Content-Description: file transfer
* Cache-Control: must-revalidate
* Pragma: public
* Content-Length: {file_size}
* Content-Transfer-Encoding: binary

**Error Handling:**

* 400 Bad Request - Missing slug or extension, or missing download token for licensed plugin
* 401 Unauthorized - Invalid download token for licensed plugin
* 404 Not Found - Plugin not found or file missing in repository
* 500 Internal Server Error - File cannot be read

= 8. Repository REST API =

**Endpoint:** `/repository/`
**Method:** GET
**Description:** This REST API endpoint provides a paginated list of hosted apps in the repository. Currently, the response format fully supports plugins only. Support for themes and software hosting will be added in future releases.

**Parameters:**

* `search` (string, optional) - Search term to filter apps by name or slug
* `page` (int, optional) - Pagination page number (default: 1)
* `limit` (int, optional) - Number of results per page (default: 25)
* `status` (string, optional) - Status filter, e.g., 'active' or 'inactive' (default: 'active')
* `types` (array, optional) - Array of app types to fetch. Currently only 'plugin' is fully supported (default: ['plugin','theme','software'])

**Response Format (Plugin Example):**

`{
  "apps": [
    {
      "name": "Plugin Name",
      "type": "plugin",
      "slug": "plugin-slug",
      "version": "1.0.0",
      "author": "Author Name",
      "author_profile": "https://example.com/author",
      "homepage": "https://example.com/plugin-homepage",
      "package": "https://example.com/downloads/plugin-slug.zip",
      "banners": [],
      "screenshots": [],
      "icons": [],
      "requires": "5.0",
      "tested": "6.5",
      "requires_php": "7.4",
      "requires_plugins": [],
      "added": "2025-10-28",
      "last_updated": "2025-10-28",
      "short_description": "Short description of the plugin.",
      "sections": {},
      "num_ratings": 10,
      "rating": 4.5,
      "ratings": {},
      "support_url": "https://example.com/support",
      "active_installs": 120,
      "is_monetized": false,
      "monetization": []
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_items": 125
  }
}`

**Notes:**

* Only plugins are fully supported in the REST response at this time
* Themes and software app responses will follow the same structure once implemented
* The API is public (repository_access_permission returns true) but may include authentication for premium apps in the future

== Frequently Asked Questions ==

= Can I sell my plugins on multiple providers? =

Yes. Each pricing tier can use a different provider, including WooCommerce or EDD, for maximum flexibility.

= Can I control the number of sites per license? =

Absolutely. Each pricing tier lets you define activation limits for single-site, multi-site, or unlimited use.

= How secure is the update delivery? =

All updates are delivered via secure REST API, and only valid license holders can access them.

= Does this work with themes as well as plugins? =

Yes, Smart License Server supports plugins, themes, and custom applications.

= What hosting environment do I need? =

The plugin works on any standard WordPress hosting environment including shared hosting, VPS, or dedicated servers. No special requirements like SVN are needed.

= Can I integrate with my existing e-commerce setup? =

Yes, the plugin integrates with popular providers like WooCommerce and Easy Digital Downloads, allowing you to use your existing payment setup.

== Screenshots ==

1. License Key Management Dashboard
2. Product Repository Interface
3. Pricing Tier Configuration
4. API Documentation View
5. License Activation Screen

== Changelog ==

= 1.0.0 =
* Initial release
* License key management system
* Universal update server functionality
* REST API for license operations
* WooCommerce and EDD integration
* Plugin and theme hosting support
* Monetization and pricing tiers

== Upgrade Notice ==

= 1.0.0 =
Initial release of Smart License Server. Start managing your licenses and updates today!

== Support ==

For support, visit the [Smart License Server Support Portal](https://support.callismart.com.ng/support-portal/).

== Additional Information ==

For complete API documentation, developer guides, and advanced usage examples, please visit the official plugin website at [https://apps.callismart.com.ng/plugin/smart-license-server](https://apps.callismart.com.ng/plugin/smart-license-server).