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

# Dev container images ship with empty apt lists, so an install before the first
# update fails with "Unable to locate package". Every install goes through here.
apt_install() {
	if ! compgen -G "/var/lib/apt/lists/*_Packages*" > /dev/null; then
		APT_UPDATED=0
		apt_update_once
	fi
	$SUDO apt-get install -y "$@"
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
			apt_install software-properties-common
			$SUDO add-apt-repository -y ppa:ondrej/php
			;;
		debian)
			apt_install curl ca-certificates
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

# 2. Core packages first, so extension detection can ask the real PHP binary what it already ships.
CORE_PKGS=()
for pkg in php8.4-cli php8.4-common redis-server; do
	dpkg -s "$pkg" &> /dev/null || CORE_PKGS+=("$pkg")
done
if [ "${#CORE_PKGS[@]}" -gt 0 ]; then
	echo "-> Installing core packages: ${CORE_PKGS[*]}"
	apt_install "${CORE_PKGS[@]}"
else
	echo "-> Core packages are already installed."
fi

LOADED_EXTS="$(php8.4 -m 2>/dev/null | tr '[:upper:]' '[:lower:]' || true)"

TO_INSTALL=()
SKIPPED=()
add_pkg() {
	local p="$1" existing
	for existing in "${TO_INSTALL[@]:-}"; do
		[ "$existing" = "$p" ] && return 0
	done
	TO_INSTALL+=("$p")
}

echo "-> Checking PHP extensions from $EXT_FILE..."
while IFS= read -r ext || [ -n "$ext" ]; do
	ext="${ext%%#*}"           # strip inline comments
	ext="${ext//[[:space:]]/}" # strip CR, spaces, tabs
	ext="${ext,,}"             # lowercase
	[ -z "$ext" ] && continue

	# Already compiled in or provided by an installed package.
	if grep -qxF "$ext" <<< "$LOADED_EXTS"; then
		continue
	fi

	case "$ext" in
		mysqli|pdo_mysql)                  pkg_name="php8.4-mysql" ;;
		pdo_pgsql)                         pkg_name="php8.4-pgsql" ;;
		pdo_sqlite)                        pkg_name="php8.4-sqlite3" ;;
		dom|simplexml|xmlreader|xmlwriter) pkg_name="php8.4-xml" ;;
		*)                                 pkg_name="php8.4-${ext//_/-}" ;;
	esac

	if dpkg -s "$pkg_name" &> /dev/null; then
		continue
	fi

	if ! apt-cache show "$pkg_name" &> /dev/null; then
		echo "   ! '$ext' is not loaded and package '$pkg_name' does not exist in the configured repos. Skipping."
		SKIPPED+=("$ext")
		continue
	fi

	add_pkg "$pkg_name"
done < "$EXT_FILE"

if [ "${#TO_INSTALL[@]}" -gt 0 ]; then
	echo "-> Installing: ${TO_INSTALL[*]}"
	apt_install "${TO_INSTALL[@]}"
else
	echo "-> All required PHP extension packages are already installed."
fi

if [ "${#SKIPPED[@]}" -gt 0 ]; then
	echo "-> WARNING: skipped extensions (fix extensions.txt or add a case mapping): ${SKIPPED[*]}"
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

# 5. Redis daemon, then wait until it actually answers.
# --dir /tmp keeps dump.rdb out of the repo (lifecycle hooks run in the workspace folder).
if ! redis-cli ping &> /dev/null; then
	echo "-> Starting Redis background daemon..."
	redis-server --daemonize yes --dir /tmp > /dev/null
	for _ in 1 2 3 4 5 6 7 8 9 10; do
		redis-cli ping &> /dev/null && break
		sleep 0.5
	done
	redis-cli ping &> /dev/null || { echo "Error: Redis did not come up."; exit 1; }
else
	echo "-> Redis server is already running."
fi

# 6. Cloudflare Tunnel: install the binary, run it from a token held in a Codespaces secret
if ! command -v cloudflared &> /dev/null; then
	echo "-> Installing cloudflared..."
	apt_install curl ca-certificates
	$SUDO mkdir -p --mode=0755 /usr/share/keyrings
	curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | $SUDO tee /usr/share/keyrings/cloudflare-main.gpg > /dev/null
	echo 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main' \
		| $SUDO tee /etc/apt/sources.list.d/cloudflared.list > /dev/null
	APT_UPDATED=0
	apt_update_once
	apt_install cloudflared
else
	echo "-> cloudflared is already installed."
fi

if [ -z "${CLOUDFLARE_TUNNEL_TOKEN:-}" ]; then
	echo "-> CLOUDFLARE_TUNNEL_TOKEN is not set (add it as a Codespaces secret). Skipping tunnel."
elif pgrep -x cloudflared > /dev/null; then
	echo "-> Cloudflare Tunnel is already running."
else
	echo "-> Starting Cloudflare Tunnel..."
	# Token goes via env var so it doesn't show in `ps`. Fully detached so the lifecycle hook can't hang on it.
	TUNNEL_TOKEN="$CLOUDFLARE_TUNNEL_TOKEN" setsid nohup cloudflared tunnel --no-autoupdate run > /tmp/cloudflared.log 2>&1 < /dev/null &
fi

echo "=== Environment check completed successfully! ==="