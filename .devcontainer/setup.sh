#!/usr/bin/env bash
set -Eeuo pipefail

export DEBIAN_FRONTEND=noninteractive

printf '%s\n' '=== Checking workspace environment components ==='

# Privilege helper: root in containers/Codespaces hooks, sudo elsewhere.
if (( EUID == 0 )); then
    SUDO=()
elif command -v sudo >/dev/null 2>&1; then
    SUDO=(sudo)
else
    printf '%s\n' 'Error: not root and sudo is unavailable.' >&2
    exit 1
fi

APT_UPDATED=0

apt_update_once() {
    if (( APT_UPDATED == 0 )); then
        "${SUDO[@]}" apt-get update
        APT_UPDATED=1
    fi
}

# Dev-container images may have empty apt lists.
apt_install() {
    if ! compgen -G '/var/lib/apt/lists/*_Packages*' >/dev/null; then
        APT_UPDATED=0
        apt_update_once
    fi

    "${SUDO[@]}" apt-get install -y "$@"
}

# Locate extensions.txt: explicit override, ~/.phpmgr, then beside this script.
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
EXT_FILE=''

for candidate in "${PHPMGR_EXT_FILE:-}" "$HOME/.phpmgr/extensions.txt" "$SCRIPT_DIR/extensions.txt"; do
    if [[ -n "$candidate" && -f "$candidate" ]]; then
        EXT_FILE="$candidate"
        break
    fi
done

if [[ -z "$EXT_FILE" ]]; then
    printf '%s\n' 'Error: no extensions.txt found (set PHPMGR_EXT_FILE, or place it in ~/.phpmgr/ or beside this script).' >&2
    exit 1
fi

# 1. Add the PHP repository if missing (Ubuntu PPA or Debian Sury).
# shellcheck disable=SC1091
. /etc/os-release

if ! grep -rqsE 'ondrej/php|packages\.sury\.org/php' /etc/apt/sources.list /etc/apt/sources.list.d/ 2>/dev/null; then
    printf -- '%s\n' "-> PHP repository not found. Adding for ${ID} ${VERSION_CODENAME}..."

    case "$ID" in
        ubuntu)
            apt_install software-properties-common
            "${SUDO[@]}" add-apt-repository -y ppa:ondrej/php
            ;;

        debian)
            apt_install curl ca-certificates

            KEYRING_DEB="$(mktemp --suffix=.deb)"
            trap 'rm -f -- "${KEYRING_DEB:-}"' EXIT

            curl -fsSL https://packages.sury.org/debsuryorg-archive-keyring.deb -o "$KEYRING_DEB"
            "${SUDO[@]}" dpkg -i "$KEYRING_DEB"

            rm -f -- "$KEYRING_DEB"
            trap - EXIT

            printf -- 'deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ %s main\n' "$VERSION_CODENAME" \
                | "${SUDO[@]}" tee /etc/apt/sources.list.d/php.list >/dev/null
            ;;

        *)
            printf -- 'Error: unsupported distro %q.\n' "$ID" >&2
            exit 1
            ;;
    esac

    APT_UPDATED=0
    apt_update_once
else
    printf -- '%s\n' '-> PHP repository is already configured.'
fi

# 2. Install PHP 8.4 CLI/common and Redis before inspecting PHP extensions.
CORE_PKGS=()

for pkg in php8.4-cli php8.4-common redis-server; do
    if ! dpkg-query -W -f='${Status}' "$pkg" 2>/dev/null | grep -q 'install ok installed'; then
        CORE_PKGS+=("$pkg")
    fi
done

if ((${#CORE_PKGS[@]} > 0)); then
    printf -- '-> Installing core packages: %s\n' "${CORE_PKGS[*]}"
    apt_install "${CORE_PKGS[@]}"
else
    printf -- '%s\n' '-> Core packages are already installed.'
fi

if ! command -v php8.4 >/dev/null 2>&1; then
    printf -- '%s\n' 'Error: php8.4 is not available after package installation.' >&2
    exit 1
fi

LOADED_EXTS="$(php8.4 -m 2>/dev/null | tr '[:upper:]' '[:lower:]' || true)"

TO_INSTALL=()
SKIPPED=()

add_pkg() {
    local pkg="$1" existing

    for existing in "${TO_INSTALL[@]}"; do
        [[ "$existing" == "$pkg" ]] && return 0
    done

    TO_INSTALL+=("$pkg")
}

printf -- '-> Checking PHP extensions from %s...\n' "$EXT_FILE"

while IFS= read -r ext || [[ -n "$ext" ]]; do
    ext="${ext%%#*}"           # Strip inline comments.
    ext="${ext//[[:space:]]/}" # Strip CR, spaces and tabs.
    ext="${ext,,}"             # Normalize to lowercase.

    [[ -z "$ext" ]] && continue

    # Already compiled in or provided by an installed/enabled package.
    if grep -qxF -- "$ext" <<<"$LOADED_EXTS"; then
        continue
    fi

    case "$ext" in
        mysqli|pdo_mysql)                  pkg_name='php8.4-mysql' ;;
        pdo_pgsql)                         pkg_name='php8.4-pgsql' ;;
        pdo_sqlite)                        pkg_name='php8.4-sqlite3' ;;
        dom|simplexml|xmlreader|xmlwriter) pkg_name='php8.4-xml' ;;
        *)                                 pkg_name="php8.4-${ext//_/-}" ;;
    esac

    if dpkg-query -W -f='${Status}' "$pkg_name" 2>/dev/null | grep -q 'install ok installed'; then
        continue
    fi

    if ! apt-cache show "$pkg_name" >/dev/null 2>&1; then
        printf -- "   ! '%s' is not loaded and package '%s' does not exist in the configured repositories. Skipping.\n" "$ext" "$pkg_name"
        SKIPPED+=("$ext")
        continue
    fi

    add_pkg "$pkg_name"

done < "$EXT_FILE"

if ((${#TO_INSTALL[@]} > 0)); then
    printf -- '-> Installing PHP extension packages: %s\n' "${TO_INSTALL[*]}"
    apt_install "${TO_INSTALL[@]}"
else
    printf -- '%s\n' '-> All required PHP extension packages are already installed.'
fi

if ((${#SKIPPED[@]} > 0)); then
    printf -- '-> WARNING: skipped extensions (check extensions.txt or add a package mapping): %s\n' "${SKIPPED[*]}" >&2
fi

# 3. Enable APCu for PHP CLI, when the APCu package is installed.
APCU_CLI_CONF='/etc/php/8.4/cli/conf.d/99-apcu-cli.ini'

if dpkg-query -W -f='${Status}' php8.4-apcu 2>/dev/null | grep -q 'install ok installed'; then
    if ! grep -qsF 'apc.enable_cli=1' "$APCU_CLI_CONF"; then
        printf -- '%s\n' '-> Enabling APCu caching for PHP CLI...'
        printf -- '%s\n' 'apc.enable_cli=1' | "${SUDO[@]}" tee "$APCU_CLI_CONF" >/dev/null
    else
        printf -- '%s\n' '-> APCu CLI caching is already enabled.'
    fi
fi

# 4. Set PHP 8.4 as the default CLI alternative.
if [[ ! -x /usr/bin/php8.4 ]]; then
    printf -- '%s\n' 'Error: /usr/bin/php8.4 does not exist; cannot select PHP 8.4.' >&2
    exit 1
fi

CURRENT_PHP="$(update-alternatives --query php 2>/dev/null | awk '/^Value:/ {print $2}' || true)"

if [[ "$CURRENT_PHP" != '/usr/bin/php8.4' ]]; then
    printf -- '%s\n' '-> Setting PHP 8.4 as the default CLI...'
    "${SUDO[@]}" update-alternatives --set php /usr/bin/php8.4
else
    printf -- '%s\n' '-> PHP 8.4 is already the active CLI.'
fi

# 5. Install Composer globally if it is not already available globally.
COMPOSER_BIN='/usr/local/bin/composer'

if [[ ! -x "$COMPOSER_BIN" ]]; then
    if [[ -x /usr/bin/composer ]]; then
        printf -- '%s\n' '-> Composer is already installed globally in /usr/bin.'
    else
        printf -- '%s\n' '-> Installing Composer globally...'

        apt_install curl ca-certificates

        COMPOSER_INSTALLER="$(mktemp)"
        trap 'rm -f -- "${COMPOSER_INSTALLER:-}"' EXIT

        EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig | tr -d '[:space:]')"

        curl -fsSL https://getcomposer.org/installer -o "$COMPOSER_INSTALLER"

        ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', \$argv[1]);" "$COMPOSER_INSTALLER")"

        if [[ -z "$EXPECTED_CHECKSUM" || "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]]; then
            printf -- '%s\n' 'Error: Composer installer checksum verification failed.' >&2
            exit 1
        fi

        "${SUDO[@]}" php "$COMPOSER_INSTALLER" \
            --install-dir=/usr/local/bin \
            --filename=composer \
            --quiet

        rm -f -- "$COMPOSER_INSTALLER"
        trap - EXIT
    fi
else
    printf -- '-> Composer is already installed globally: %s\n' "$COMPOSER_BIN"
fi

if ! command -v composer >/dev/null 2>&1; then
    printf -- '%s\n' 'Error: Composer installation completed, but composer is not available in PATH.' >&2
    exit 1
fi

printf -- '-> %s\n' "$(composer --version --no-ansi)"

# 6. Start Redis if it is not already responding.
# --dir /tmp keeps dump.rdb out of the repository/workspace.
if ! redis-cli ping >/dev/null 2>&1; then
    printf -- '%s\n' '-> Starting Redis background daemon...'

    redis-server --daemonize yes --dir /tmp >/dev/null

    for _ in {1..10}; do
        redis-cli ping >/dev/null 2>&1 && break
        sleep 0.5
    done

    if ! redis-cli ping >/dev/null 2>&1; then
        printf -- '%s\n' 'Error: Redis did not start successfully.' >&2
        exit 1
    fi
else
    printf -- '%s\n' '-> Redis server is already running.'
fi

# 7. Install cloudflared if missing, then install the token-backed service.
if ! command -v cloudflared >/dev/null 2>&1; then
    printf -- '%s\n' '-> Installing cloudflared...'

    apt_install curl ca-certificates

    "${SUDO[@]}" install -d -m 0755 /usr/share/keyrings

    curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg \
        | "${SUDO[@]}" tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null

    printf -- '%s\n' 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main' \
        | "${SUDO[@]}" tee /etc/apt/sources.list.d/cloudflared.list >/dev/null

    APT_UPDATED=0
    apt_update_once
    apt_install cloudflared
else
    printf -- '%s\n' '-> cloudflared is already installed.'
fi

if [[ -z "${CLOUDFLARE_TUNNEL_TOKEN:-}" ]]; then
    printf -- '%s\n' '-> CLOUDFLARE_TUNNEL_TOKEN is not set (add it as a Codespaces secret). Skipping tunnel setup.'
elif pgrep -x cloudflared >/dev/null 2>&1; then
    printf -- '%s\n' '-> A cloudflared process is already running.'
else
    printf -- '%s\n' '-> Installing Cloudflare Tunnel service...'

    # cloudflared's service-install command accepts the token as an argument.
    "${SUDO[@]}" cloudflared service install "$CLOUDFLARE_TUNNEL_TOKEN"
fi

printf -- '%s\n' '=== Environment check completed successfully! ==='
