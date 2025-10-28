# Smart License Server

## Description

**Smart License Server** is a professional License Key and Update Server designed for WordPress developers. It allows you to securely deliver updates and manage license keys for your premium and free plugins, themes, or other digital products. With Smart License Server, you can control access, enforce license rules, and monetize your digital products without relying on third-party marketplaces.

Whether you sell plugins, themes, or software extensions, Smart License Server gives you a **centralized, secure, and extensible platform** to manage licenses, updates, and pricing tiers.

---

## Features

* **License Key Management**
  Create, manage, and validate license keys for individual products. Support single-site, multi-site, and lifetime licenses.

* **Update Server**
  Deliver plugin and theme updates automatically to authorized users via REST API. Control versions and update availability.

* **Monetization Support**
  Define pricing tiers and integrate multiple providers (e.g., WooCommerce, EDD). Control billing cycles, features, and maximum site activations per license.

* **Secure Access**
  Only verified license key holders can access premium plugins, themes, or updates. OAuth support for authorized clients.

* **Developer-Friendly API**
  REST endpoints and programmatic integration for seamless licensing, validation, and update workflows.

* **Works with Free and Premium Products**
  Manage both free and paid products in the same platform.

* **Extensible**
  Add custom providers, pricing tiers, and integrations to match your business model.

---

## Installation

1. Upload the plugin files to the `/wp-content/plugins/smart-license-server` directory, or install via the WordPress plugin screen.
2. Activate the plugin through the 'Plugins' screen.
3. Configure your license and update settings in the Smart License Server admin page.

---

## Usage

1. **Create a Product**

   * Define your plugin or theme in the hosted repository.
   * Set the item type (plugin, theme, or other) and basic metadata.

2. **Add Monetization**

   * Create pricing tiers for your product (e.g., Single Site, Multi-Site, Lifetime).
   * Assign a provider (e.g., WooCommerce or EDD) to handle payments.

3. **Generate License Keys**

   * Issue license keys manually or automatically when a product is purchased.
   * Set activation limits, expiration, and feature restrictions per tier.

4. **Serve Updates**

   * Users with valid licenses can receive automatic updates.
   * Smart License Server handles version checks and secure delivery of update packages.

5. **Integration with Plugins/Themes**

   * Use the included API to validate licenses, check update availability, and fetch product metadata.

---

## REST API Endpoints

**Base Namespace:** `/wp-json/smliser/v1`

| Endpoint                            | Method | Description                                                                                                         |
| ----------------------------------- | ------ | ------------------------------------------------------------------------------------------------------------------- |
| `/license-activation/`              | POST   | Activate a license key. Required params: `item_id`, `service_id`, `license_key`, `domain`.                          |
| `/license-deactivation/`            | POST   | Deactivate a license key. Required params: `license_key`, `service_id`, `domain`.                                   |
| `/license-uninstallation/`          | POST   | Uninstall a license key. Required params: `license_key`, `service_id`, `domain`.                                    |
| `/license-validity-test/`           | POST   | Check license validity. Required params: `license_key`, `service_id`, `domain`, `item_id`.                          |
| `/plugin-info/`                     | GET    | Retrieve plugin info. Optional params: `item_id`, `slug`.                                                           |
| `/repository/`                      | GET    | Query the full repository. Optional param: `search`.                                                                |
| `/client-auth/`                     | GET    | OAuth client authentication (token regeneration).                                                                   |
| `/download-token-reauthentication/` | POST   | Reauthenticate download token. Required params: `domain`, `license_key`, `item_id`, `download_token`, `service_id`. |
| `/bulk-messages/`                   | GET    | Fetch bulk messages for apps. Optional params: `page`, `limit`, `app_slugs`, `app_types`.                           |
| `/mock-inbox/`                      | GET    | Test endpoint for inbox messages. Optional param: `since`.                                                          |

> Example:
>
> ```bash
> curl -X POST https://example.com/wp-json/smliser/v1/license-activation/ \
> -d "license_key=XXXX&service_id=service_001&item_id=123&domain=example.com"
> ```

---

## Example Workflow

```php
// Validate a license key
$license = Smliser_License::get_by_key( $key );
if ( $license && $license->is_active() ) {
    // License is valid, allow update
    $updates = $license->get_available_updates();
} else {
    // License invalid or expired
    wp_die('Invalid license');
}
```

---

## Frequently Asked Questions

**Q: Can I sell my plugins on multiple providers?**
A: Yes. Each pricing tier can use a different provider, including WooCommerce or EDD, for maximum flexibility.

**Q: Can I control the number of sites per license?**
A: Absolutely. Each pricing tier lets you define activation limits for single-site, multi-site, or unlimited use.

**Q: How secure is the update delivery?**
A: All updates are delivered via secure REST API, and only valid license holders can access them.

---

## Changelog

See the [plugin changelog](changelog.md) for the latest updates and improvements.

---

## Support

For support, visit the [Smart License Server Support Portal](https://support.callismart.com.ng/support-portal/).

---

## License

This plugin is licensed under [GPLv3 or later](http://www.gnu.org/licenses/gpl-3.0.html).
