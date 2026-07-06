# Smart License Server

**Smart License Server** is a PHP, database-agnostic application for developers, organizations, and freelancers who need to privately host custom software, applications, WordPress plugins, and themes — with full control over updates, distribution, and licensing.

It ships with a UI and tooling to monitor app usage and licensing, monetize your software through any payment platform, and integrate with up to **eight email service providers** and **five cache adapters** out of the box.

<!--
  NOTE: composer.json currently declares "license": "GPL-3.0-or-later" and
  "name": "callismartltd/smart-license-server". Update composer.json to
  "license": "MIT" and "name": "callismart/smart-license-server" so the
  package manifest matches this README before publishing.
-->

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.4-777bb4.svg)](https://www.php.net/)

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Supported Databases](#supported-databases)
- [Email Service Providers](#email-service-providers)
- [Cache Adapters](#cache-adapters)
- [Installation](#installation)
  - [As a WordPress Plugin](#as-a-wordpress-plugin)
  - [As a Standalone Application](#as-a-standalone-application)
- [Configuration](#configuration)
- [License](#license)

---

## Features

- **Database-agnostic** — runs on MySQL, MariaDB, PostgreSQL, or SQLite with no code changes.
- **Environment-agnostic** — deploy as a WordPress plugin, a standalone web app, or a CLI tool.
- **License management** — issue, validate, and revoke licenses for your software, plugins, and themes.
- **Update & distribution control** — privately host and serve updates for your own apps, WP plugins, and themes.
- **Usage monitoring** — a built-in UI to track app usage and licensing activity.
- **Monetization-ready** — licensing is decoupled from any specific payment platform, so you can monetize through whichever platform you already use.
- **Pluggable email delivery** — 8 supported providers out of the box.
- **Pluggable caching layer** — 5 supported cache adapters out of the box.
- **Modern PHP** — built for PHP 8.4+, including PHP 8.5.

## Requirements

| Requirement | Version |
|---|---|
| PHP | ^8.4 (including 8.5) |
| `ext-pdo_mysql` | Required for MySQL 8.0.45 support |
| `ext-pdo_pgsql` | Required for PostgreSQL support |
| `ext-sqlite3` | Required for SQLite support (v3.35+ recommended for expanded `ALTER TABLE`) |

Smart License Server also depends on the following Callismart Composer packages, which are installed automatically:

- [`callismart/dbprism`](https://packagist.org/packages/callismart/dbprism) — multi-adapter database abstraction layer
- [`callismart/dto`](https://packagist.org/packages/callismart/dto) — data transfer object library
- [`callismart/http`](https://packagist.org/packages/callismart/http) — HTTP abstraction layer
- [`league/commonmark`](https://packagist.org/packages/league/commonmark) — Markdown parsing

## Supported Databases

Smart License Server runs out of the box on:

- MySQL
- MariaDB
- PostgreSQL
- SQLite

No engine-specific code is required in your application — switch databases via configuration.

## Email Service Providers

Choose from any of the following, or fall back to PHP's native mail function:

| Provider | Identifier |
|---|---|
| PHP Mail | `php_mail` |
| SMTP | `smtp` |
| Brevo | `brevo` |
| SendGrid | `sendgrid` |
| Mailgun | `mailgun` |
| Postmark | `postmark` |
| Resend | `resend` |
| Amazon SES | `amazon_ses` |

## Cache Adapters

| Adapter | Identifier | Notes |
|---|---|---|
| Runtime (in-memory array) | `runtime` | **Default adapter** — no external dependency required |
| APCu | `apcu` | |
| Memcached | `memcached` | |
| Redis | `redis` | |
| SQLite Cache | `sqlitecache` | |

## Installation

Smart License Server can be installed as a WordPress plugin or as a standalone application.

### As a WordPress Plugin

**Option A — Standard WordPress install:**

1. Download the plugin zip: `https://apiv1.callismart.com.ng/downloads/plugin/smart-license-server.zip`
2. In your WordPress dashboard, go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip file and click **Install Now**.
4. Activate the plugin.

**Option B — WP-CLI (advanced):**

```bash
wp plugin install https://apiv1.callismart.com.ng/downloads/plugin/smart-license-server.zip --activate
```

### As a Standalone Application

1. Download the installer: `https://apiv1.callismart.com.ng/downloads/software/smliser-installer.zip`
2. Unzip the installer.
3. Run it through the web or via CLI.

> **Note:** Detailed standalone installer instructions (web and CLI flows) are coming in a future update to this document.

## Configuration

Configuration details (database connection, cache adapter selection, email provider setup) will be documented here once the configuration reference is finalized.

## License

Smart License Server is released under the **MIT License**.
See the [`LICENSE`](LICENSE) file for the full license text.

---

**Author:** Callistus Nwachukwu ([admin@callismart.com.ng](mailto:admin@callismart.com.ng))
**Repository:** [github.com/CallismartLtd/smart-license-server](https://github.com/CallismartLtd/smart-license-server)