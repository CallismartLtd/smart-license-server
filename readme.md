# Smart License Server

## Description

**Smart License Server** is a professional License Key and Update Server that empowers developers to securely manage licenses and deliver updates for WordPress plugins, themes, custom applications, or any other digital products. With Smart License Server, you can control access, enforce license rules, and monetize your products without relying on third-party marketplaces.

Whether you distribute plugins, themes, standalone software, or custom applications, Smart License Server provides a **centralized, secure, and extensible platform** to manage licenses, updates, and pricing tiers for all your products.


---

## Features

*Smart License Server works on any hosting environment and supports plugins, themes, and custom applications—without requiring SVN or external servers.*

* **License Key Management**
  Easily create, manage, and validate license keys for any product—plugins, themes, or custom applications. Supports single-site, multi-site, and lifetime licenses with full control over activations and expirations.

* **Universal Update Server**
  Turn any WordPress site into a fully-featured update server via REST API. Works on any hosting environment (shared hosting, VPS, or dedicated servers) without requiring SVN or external services. Updates are securely stored on the database and hosting filesystem, safely away from public directories, ensuring reliable delivery.

* **Robust Filesystem Handling**
  Overcome hosting limitations with robust, sandboxed filesystem APIs. Manage hosted applications, updates, and assets reliably across environments.

* **Monetization Support**
  Define flexible pricing tiers and integrate multiple providers (e.g., WooCommerce, EDD). Control billing cycles, features, and maximum site activations per license to maximize revenue and control access.

* **Secure Access**
  Only verified license holders can access premium products or updates. Includes OAuth support for authorized clients and programmatic API access.

* **Developer-Friendly API**
  Provides detailed REST endpoints for license management, repository operations, update handling, and monetization. Seamlessly integrate with your existing workflows or custom applications.

* **Works with Free and Premium Products**
  Manage both free and paid products in a single, unified platform.

* **Extensible**
  Easily add custom providers, pricing tiers, integrations, or extend APIs to suit your unique business model.

---

## Installation & Requirements

**Minimum Requirements:**

- WordPress: 6.4+
- PHP: 8.0+
- Web hosting: Shared, VPS, or any standard hosting environment

**Installation:**

1. Download the plugin from [Smart License Server](https://apps.callismart.com.ng/plugin/smart-license-server) for $150.
2. Upload the plugin files to the `/wp-content/plugins/smart-license-server` directory, or install via the WordPress plugin screen.
3. Activate the plugin through the 'Plugins' screen.
4. Configure your license and update settings in the Smart License Server admin page.

**Why Purchase:**

- **Complete Functionality** – Every feature, from license management to monetization APIs, is fully available. No hidden limitations or freemium tiers—what you purchase is what you get.
- **Automatic Updates** – Users automatically receive updates for all hosted applications and products through secure REST API endpoints.
- **Full Support & Maintenance** – We maintain, support, and enhance the plugin continuously, so you don’t have to worry about broken updates or outdated functionality.

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

````markdown
# Smart License Server - Developer REST API Guide

**Base Namespace:** `/wp-json/smliser/v1`

---

### 1. License Activation

**Endpoint:** `/license-activation/`  
**Method:** `POST`  
**Description:** Activate a license key for a specific domain.

| Parameter     | Type    | Required | Description |
| ------------- | ------- | -------- | ----------- |
| item_id       | int     | Yes      | ID of the item associated with the license. |
| service_id    | string  | Yes      | Service ID associated with the license key. |
| license_key   | string  | Yes      | License key to activate. |
| domain        | string  | Yes      | Domain where the license will be activated. |

**Example Request:**
```bash
curl -X POST https://example.com/wp-json/smliser/v1/license-activation/ \
-d "item_id=123&service_id=service_001&license_key=XXXX&domain=example.com"
````

**Sample Response:**

```json
{
  "success": true,
  "message": "License activated successfully",
  "data": {
    "license_key": "XXXX",
    "domain": "example.com",
    "item_id": 123,
    "service_id": "service_001",
    "expires_at": "2026-10-27 23:59:59"
  }
}
```

---

### 2. License Deactivation

**Endpoint:** `/license-deactivation/`
**Method:** `POST`
**Description:** Deactivate a license key from a domain.

| Parameter   | Type   | Required | Description                                   |
| ----------- | ------ | -------- | --------------------------------------------- |
| license_key | string | Yes      | License key to deactivate.                    |
| service_id  | string | Yes      | Service ID associated with the license.       |
| domain      | string | Yes      | Domain where the license is currently active. |

**Example Request:**

```bash
curl -X POST https://example.com/wp-json/smliser/v1/license-deactivation/ \
-d "license_key=XXXX&service_id=service_001&domain=example.com"
```

**Sample Response:**

```json
{
  "success": true,
  "message": "License deactivated successfully"
}
```

---

### 3. License Uninstallation

**Endpoint:** `/license-uninstallation/`
**Method:** `POST`
**Description:** Uninstall a license key from a domain.

| Parameter   | Type   | Required | Description                                   |
| ----------- | ------ | -------- | --------------------------------------------- |
| license_key | string | Yes      | License key to uninstall.                     |
| service_id  | string | Yes      | Service ID associated with the license.       |
| domain      | string | Yes      | Domain where the license is currently active. |

**Example Request:**

```bash
curl -X POST https://example.com/wp-json/smliser/v1/license-uninstallation/ \
-d "license_key=XXXX&service_id=service_001&domain=example.com"
```

**Sample Response:**

```json
{
  "success": true,
  "message": "License uninstalled successfully"
}
```

---

### 4. License Validity Test

**Endpoint:** `/license-validity-test/`
**Method:** `POST`
**Description:** Check if a license key is valid for a specific domain.

| Parameter   | Type   | Required | Description                                 |
| ----------- | ------ | -------- | ------------------------------------------- |
| license_key | string | Yes      | License key to validate.                    |
| service_id  | string | Yes      | Service ID associated with the license.     |
| domain      | string | Yes      | Domain where the license is active.         |
| item_id     | int    | Yes      | ID of the software this license belongs to. |

**Example Request:**

```bash
curl -X POST https://example.com/wp-json/smliser/v1/license-validity-test/ \
-d "license_key=XXXX&service_id=service_001&domain=example.com&item_id=123"
```

**Sample Response:**

```json
{
  "valid": true,
  "message": "License is valid",
  "expires_at": "2026-10-27 23:59:59"
}
```

---

### 5. Plugin Info

**Endpoint:** `/plugin-info/`
**Method:** `GET`
**Description:** Retrieve information about a plugin.

| Parameter | Type   | Required | Description                                    |
| --------- | ------ | -------- | ---------------------------------------------- |
| item_id   | int    | No       | Plugin ID.                                     |
| slug      | string | No       | Plugin slug (e.g., `plugin-slug/plugin-slug`). |

**Example Request:**

```bash
curl https://example.com/wp-json/smliser/v1/plugin-info/?slug=smart-woo-pro
```

**Sample Response:**

```json
{
  "success": true,
  "data": {
    "name": "Smart Woo Pro",
    "version": "1.5.1",
    "description": "Advanced service invoicing for WooCommerce",
    "author": "Callismart",
    "download_url": "https://example.com/downloads/smart-woo-pro.zip"
  }
}
```

---

### 6. Repository Query

**Endpoint:** `/repository/`
**Method:** `GET`
**Description:** Query all hosted applications in the repository.

| Parameter | Type   | Required | Description                             |
| --------- | ------ | -------- | --------------------------------------- |
| search    | string | No       | Search term to filter repository items. |

**Example Request:**

```bash
curl https://example.com/wp-json/smliser/v1/repository/?search=woo
```

**Sample Response:**

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Smart Woo Pro", "type": "plugin" },
    { "id": 2, "name": "Smart Woo Lite", "type": "plugin" }
  ]
}
```

---

### 7. OAuth Client Authentication

**Endpoint:** `/client-auth/`
**Method:** `GET`
**Description:** OAuth client authentication for token regeneration.

| Parameter | Type | Required | Description |
| --------- | ---- | -------- | ----------- |
| None      | —    | —        | —           |

**Example Request:**

```bash
curl https://example.com/wp-json/smliser/v1/client-auth/
```

**Sample Response:**

```json
{
  "success": true,
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in": 3600
}
```

---

### 8. Download Token Reauthentication

**Endpoint:** `/download-token-reauthentication/`
**Method:** `POST`
**Description:** Reauthenticate a previously issued download token.

| Parameter      | Type   | Required | Description                                             |
| -------------- | ------ | -------- | ------------------------------------------------------- |
| domain         | string | Yes      | Domain where the plugin is installed.                   |
| license_key    | string | Yes      | License key to reauthenticate.                          |
| item_id        | int    | Yes      | ID of the item associated with the license.             |
| download_token | string | Yes      | Base64 encoded download token issued during activation. |
| service_id     | string | Yes      | Service ID associated with the license.                 |

**Example Request:**

```bash
curl -X POST https://example.com/wp-json/smliser/v1/download-token-reauthentication/ \
-d "domain=example.com&license_key=XXXX&item_id=123&download_token=abc123&service_id=service_001"
```

**Sample Response:**

```json
{
  "success": true,
  "message": "Download token reauthenticated successfully"
}
```

---

### 9. Bulk Messages

**Endpoint:** `/bulk-messages/`
**Method:** `GET`
**Description:** Fetch multiple messages for specific apps.

| Parameter | Type  | Required | Description                                            |
| --------- | ----- | -------- | ------------------------------------------------------ |
| page      | int   | No       | Page number for pagination (default 1).                |
| limit     | int   | No       | Number of messages per page (default 10).              |
| app_slugs | array | No       | Array of app slugs to filter by.                       |
| app_types | array | No       | Array of app types to filter by (plugin, theme, etc.). |

**Example Request:**

```bash
curl https://example.com/wp-json/smliser/v1/bulk-messages/?app_types[]=plugin&app_slugs[]=smart-woo-pro
```

**Sample Response:**

```json
{
  "success": true,
  "data": [
    { "id": "msg_001", "subject": "Welcome to Smart Woo", "body": "Hello!" },
    { "id": "msg_002", "subject": "New Feature", "body": "Check out auto billing." }
  ]
}
```

---

### 10. Mock Inbox (Testing)

**Endpoint:** `/mock-inbox/`
**Method:** `GET`
**Description:** Test endpoint simulating inbox messages for development.

| Parameter | Type   | Required | Description                                                          |
| --------- | ------ | -------- | -------------------------------------------------------------------- |
| since     | string | No       | Optional timestamp to filter messages created after a specific date. |

**Example Request:**

```bash
curl https://example.com/wp-json/smliser/v1/mock-inbox/
```

**Sample Response:**

```json
[
  { "id": "msg_001", "subject": "Welcome", "body": "Welcome message", "read": false },
  { "id": "msg_002", "subject": "Update", "body": "Update message", "read": false }
]
```

```
```


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
