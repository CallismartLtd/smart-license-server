#!/bin/bash
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

echo "=== Checking workspace environment components ==="

# Privilege helper: root in containers/Cloud Shell hooks, sudo elsewhere.
if [ "$(id -u)" -eq 0 ]; then
	SUDO=""
elif command -v sudo &> /dev/null; then
	SUDO="sudo"
else
	echo "Error: not root and sudo is unavailable."
	exit 1
fi

APT_UPDATED=0
apt_update_once() {
	if [ "$APT_UPDATED" -eq 0 ]; then
		$SUDO apt-get update -y
		APT_UPDATED=1
	fi
}

# Locate the extensions list: explicit override, ~/.phpmgr, then next to this script.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EXT_FILE=""
for candidate in "${PHPMGR_EXT_FILE:-}" "$HOME/.phpmgr/extensions.txt" "$SCRIPT_DIR/extensions.txt"; do
	if [ -n "$candidate" ] && [ -f "$candidate" ]; then
		EXT_FILE="$candidate"
		break
	fi
done
if [ -z "$EXT_FILE" ]; then
	echo "Error: no extensions.txt found (set PHPMGR_EXT_FILE, or place it in ~/.phpmgr/ or beside this script)."
	exit 1
fi

# 1. Add the PHP repository if missing (Ubuntu PPA or Debian sury.org)
. /etc/os-release
if ! grep -rqsE "ondrej/php|packages.sury.org/php" /etc/apt/sources.list /etc/apt/sources.list.d/; then
	echo "-> PHP repository not found. Adding for $ID $VERSION_CODENAME..."
	case "$ID" in
		ubuntu)
			$SUDO apt-get install -y software-properties-common
			$SUDO add-apt-repository -y ppa:ondrej/php
			;;
		debian)
			$SUDO apt-get install -y curl ca-certificates
			curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
			$SUDO dpkg -i /tmp/debsuryorg-archive-keyring.deb
			echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $VERSION_CODENAME main" \
				| $SUDO tee /etc/apt/sources.list.d/php.list > /dev/null
			;;
		*)
			echo "Error: unsupported distro '$ID'."
			exit 1
			;;
	esac
	APT_UPDATED=0
	apt_update_once
else
	echo "-> PHP repository is already configured."
fi

# 2. Resolve everything that needs installing, then do ONE apt call.
TO_INSTALL=()
add_pkg() {
	local p="$1" existing
	for existing in "${TO_INSTALL[@]:-}"; do
		[ "$existing" = "$p" ] && return 0
	done
	TO_INSTALL+=("$p")
}

dpkg -s php8.4-cli &> /dev/null    || add_pkg php8.4-cli
dpkg -s php8.4-common &> /dev/null || add_pkg php8.4-common
dpkg -s redis-server &> /dev/null  || add_pkg redis-server

echo "-> Checking PHP extensions from $EXT_FILE..."
while IFS= read -r ext || [ -n "$ext" ]; do
	ext="${ext%%#*}"        # strip inline comments
	ext="${ext//[$'\r ']/}" # strip CR and spaces
	[ -z "$ext" ] && continue

	case "$ext" in
		# Shipped inside php8.4-cli / php8.4-common
		fileinfo|exif|pdo|sodium|ctype|tokenizer|iconv|phar|posix|json|openssl|session|sockets|mysqlnd)
			continue
			;;
		mysqli|pdo_mysql)                  pkg_name="php8.4-mysql" ;;
		pdo_pgsql)                         pkg_name="php8.4-pgsql" ;;
		pdo_sqlite)                        pkg_name="php8.4-sqlite3" ;;
		dom|simplexml|xmlreader|xmlwriter) pkg_name="php8.4-xml" ;;
		*)                                 pkg_name="php8.4-${ext//_/-}" ;;
	esac

	dpkg -s "$pkg_name" &> /dev/null || add_pkg "$pkg_name"
done < "$EXT_FILE"

if [ "${#TO_INSTALL[@]}" -gt 0 ]; then
	echo "-> Installing: ${TO_INSTALL[*]}"
	apt_update_once
	$SUDO apt-get install -y "${TO_INSTALL[@]}"
else
	echo "-> All required packages are already installed."
fi

# 3. APCu for PHP CLI (required for cli-server)
APCU_CLI_CONF="/etc/php/8.4/cli/conf.d/99-apcu-cli.ini"
if dpkg -s php8.4-apcu &> /dev/null; then
	if ! grep -qs "apc.enable_cli=1" "$APCU_CLI_CONF"; then
		echo "-> Enabling APCu caching for PHP CLI..."
		echo "apc.enable_cli=1" | $SUDO tee "$APCU_CLI_CONF" > /dev/null
	else
		echo "-> APCu CLI caching is already enabled."
	fi
fi

# 4. Default CLI alternative
CURRENT_PHP="$(update-alternatives --query php 2>/dev/null | awk '/^Value:/ {print $2}' || true)"
if [ "$CURRENT_PHP" != "/usr/bin/php8.4" ]; then
	echo "-> Setting PHP 8.4 as default CLI..."
	$SUDO update-alternatives --set php /usr/bin/php8.4
else
	echo "-> PHP 8.4 is already the active default CLI."
fi

# 5. Redis daemon, then wait until it actually answers
if ! redis-cli ping &> /dev/null; then
	echo "-> Starting Redis background daemon..."
	redis-server --daemonize yes > /dev/null
	for _ in 1 2 3 4 5 6 7 8 9 10; do
		redis-cli ping &> /dev/null && break
		sleep 0.5
	done
	redis-cli ping &> /dev/null || { echo "Error: Redis did not come up."; exit 1; }
else
	echo "-> Redis server is already running."
fi

# 6. Cloudflare Tunnel configuration & startup
if ! command -v cloudflared &> /dev/null; then
    echo "-> Installing cloudflared..."
    $SUDO mkdir -p --mode=0755 /usr/share/keyrings
    curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | $SUDO tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
    echo 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main' | $SUDO tee /etc/apt/sources.list.d/cloudflared.list >/dev/null
    apt_update_once
    $SUDO apt-get install -y cloudflared
else
    echo "-> cloudflared is already installed."
fi

# Start the registered cloudflare service if configured
if service --status-all 2>&1 | grep -q "cloudflared"; then
    echo "-> Starting Cloudflare Tunnel service..."
    $SUDO service cloudflared start || true
else
    echo "-> Cloudflare service not yet installed with token. Run your 'sudo cloudflared service install <TOKEN>' command once."
fi

echo "=== Environment check completed successfully! ==="