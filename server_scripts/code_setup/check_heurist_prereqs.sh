#!/usr/bin/env bash
#
# check_heurist_prereqs.sh
#
# Purpose:
#   Verify that the host system meets Heurist’s runtime prerequisites.
#   By default this script is *non-destructive* — it checks and prints
#   distro-specific hints to install what’s missing, then exits non-zero.
#
#   If invoked with --auto-install (or --yes), it will attempt to install
#   ONLY missing PHP extensions via the OS package manager, enable them,
#   and restart the relevant web/PHP services. It will NOT install PHP,
#   Apache/httpd, MySQL/MariaDB, or other system packages.
#
# Usage:
#   ./check_heurist_prereqs.sh
#   ./check_heurist_prereqs.sh --auto-install
#   ./check_heurist_prereqs.sh --auto-install --dry-run
#
# Exit codes:
#   0  All checks passed (and any requested auto-installs succeeded)
#   1  One or more prerequisites are missing (and were not installed)
#   2  Hard failure (e.g., PHP not found when --auto-install requested)
#
# Notes on philosophy:
#   - We avoid editing php.ini directly. Enabling extensions is done via
#     distro tooling (phpenmod on Debian/Ubuntu) or conf.d drop-ins.
#   - We target PHP CLI for detection (php -m). For web SAPI, we restart
#     Apache/httpd and/or PHP-FPM after enabling modules so both CLl and
#     web contexts see the extension.
#   - The script strives to be idempotent and chatty: every action is
#     echoed; --dry-run prints commands without executing them.
#

set -o errexit
set -o nounset
set -o pipefail

########################################
# Flags & ANSI formatting
########################################

AUTO_INSTALL=0
DRY_RUN=0

for arg in "$@"; do
  case "$arg" in
    --auto-install|--yes) AUTO_INSTALL=1 ;;
    --dry-run) DRY_RUN=1 ;;
    -h|--help)
      sed -n '1,90p' "$0"
      exit 0
      ;;
    *) ;;
  esac
done

# Basic styling (silently degrade if not a TTY)
if [ -t 1 ]; then
  BOLD="$(printf '\033[1m')"; GREEN="$(printf '\033[32m')"
  YELLOW="$(printf '\033[33m')"; RED="$(printf '\033[31m')"
  DIM="$(printf '\033[2m')"; RESET="$(printf '\033[0m')"
else
  BOLD=""; GREEN=""; YELLOW=""; RED=""; DIM=""; RESET=""
fi

log()   { printf "%b%s%b\n" "$DIM" "$*" "$RESET"; }
info()  { printf "%b%s%b\n" "$BOLD" "$*" "$RESET"; }
ok()    { printf "%b✔ %s%b\n" "$GREEN" "$*" "$RESET"; }
warn()  { printf "%b! %s%b\n" "$YELLOW" "$*" "$RESET"; }
fail()  { printf "%b✖ %s%b\n" "$RED" "$*" "$RESET"; }

########################################
# Utilities
########################################

have() { command -v "$1" >/dev/null 2>&1; }

# run: echo and execute a command, unless --dry-run is set
run() {
  echo "+ $*"
  if [ "$DRY_RUN" = "1" ]; then return 0; fi
  # shellcheck disable=SC2068
  eval $@
}

# Simple semver-ish compare for major.minor[.patch]
# Returns 0 if $1 >= $2
ver_ge() {
  # normalize to x.y.z
  local a b IFS=.
  read -r a1 a2 a3 <<<"${1//[!0-9.]/}"
  read -r b1 b2 b3 <<<"${2//[!0-9.]/}"
  a1=${a1:-0}; a2=${a2:-0}; a3=${a3:-0}
  b1=${b1:-0}; b2=${b2:-0}; b3=${b3:-0}
  if   [ "$a1" -gt "$b1" ]; then return 0
  elif [ "$a1" -lt "$b1" ]; then return 1
  fi
  if   [ "$a2" -gt "$b2" ]; then return 0
  elif [ "$a2" -lt "$b2" ]; then return 1
  fi
  [ "$a3" -ge "$b3" ]
}

########################################
# Package manager & distro characteristics
########################################

PKG=""
if have apt-get; then PKG="apt"; fi
if have dnf; then PKG="dnf"; fi
if [ -z "$PKG" ] && have yum; then PKG="yum"; fi

is_debian() { [ "$PKG" = "apt" ]; }
is_rhel()   { [ "$PKG" = "dnf" ] || [ "$PKG" = "yum" ]; }

########################################
# Determine PHP version, SAPI, services
########################################

PHP_FOUND=0
PHP_MM=""
PHP_FULL=""
PHP_INI_SCAN_DIRS=""
if have php; then
  PHP_FOUND=1
  PHP_MM="$(php -r 'printf("%d.%d", PHP_MAJOR_VERSION, PHP_MINOR_VERSION);' 2>/dev/null || true)"
  PHP_FULL="$(php -r 'echo PHP_VERSION;' 2>/dev/null || true)"
  PHP_INI_SCAN_DIRS="$(php --ini 2>/dev/null | awk -F': ' '/Scan for additional .ini files in:/{print $2}')"
fi

HAS_APACHE=0
APACHE_SVC=""
if have apache2ctl; then HAS_APACHE=1; APACHE_SVC="apache2"; fi
if have httpd; then HAS_APACHE=1; APACHE_SVC="httpd"; fi

HAS_PHPFPM=0
PHPFPM_SVC=""
if systemctl list-unit-files --type=service 2>/dev/null | grep -q "php${PHP_MM}-fpm\.service"; then
  HAS_PHPFPM=1; PHPFPM_SVC="php${PHP_MM}-fpm"
elif systemctl list-unit-files --type=service 2>/dev/null | grep -qi 'php-fpm\.service'; then
  HAS_PHPFPM=1; PHPFPM_SVC="php-fpm"
fi

restart_web_stack() {
  # Restart Apache and/or PHP-FPM if present
  if [ "$HAS_APACHE" -eq 1 ]; then
    run sudo systemctl restart "$APACHE_SVC" || warn "Failed to restart $APACHE_SVC"
  fi
  if [ "$HAS_PHPFPM" -eq 1 ]; then
    run sudo systemctl restart "$PHPFPM_SVC" || warn "Failed to restart $PHPFPM_SVC"
  fi
}

########################################
# PHP extension helpers
########################################

# Is an extension currently loaded (case-insensitive) for CLI?
ext_loaded() {
  local ext="$1"
  php -m 2>/dev/null | awk '{print tolower($0)}' | grep -qx "^[[:space:]]*${ext}[[:space:]]*$"
}

# Map "ext" -> OS package name for this distro
pkg_for_ext() {
  local ext="$1"
  if is_debian; then
    # Debian/Ubuntu packages are version-suffixed: php8.2-<ext>
    echo "php${PHP_MM}-${ext}"
  else
    # RHEL/Fedora packages are unversioned: php-<ext>
    echo "php-${ext}"
  fi
}

# Enable extension on Debian/Ubuntu.
# Prefer phpenmod; otherwise create a conf.d drop-in.
enable_ext_debian() {
  local ext="$1"
  if have phpenmod; then
    run sudo phpenmod "$ext"
    return
  fi
  # Fallback: drop an INI file into additional scan dirs
  if [ -n "$PHP_INI_SCAN_DIRS" ]; then
    for d in $PHP_INI_SCAN_DIRS; do
      [ -d "$d" ] || continue
      local ini="$d/20-${ext}.ini"
      if [ ! -f "$ini" ]; then
        run "echo 'extension=${ext}' | sudo tee '$ini' >/dev/null"
      fi
    done
  else
    warn "Could not determine conf.d directories; extension '${ext}' may already be auto-enabled by package."
  fi
}

# Ensure one extension is present; optionally install/enable it.
ensure_one_ext() {
  local ext="$1"

  if ext_loaded "$ext"; then
    ok "PHP extension '${ext}' already enabled"
    return 0
  fi

  # Not loaded
  if [ "$AUTO_INSTALL" -ne 1 ]; then
    fail "Missing PHP extension '${ext}'"
    # Print distro-specific hints
    if [ -n "$PKG" ]; then
      local pkg="$(pkg_for_ext "$ext")"
      if is_debian; then
        printf "  Hint (Debian/Ubuntu): sudo apt-get update && sudo apt-get install -y %s\n" "$pkg"
        printf "                        sudo phpenmod %s && sudo systemctl restart apache2 || sudo systemctl restart php%s-fpm\n" "$ext" "$PHP_MM"
      else
        printf "  Hint (RHEL/Fedora):   sudo %s install -y %s\n" "$PKG" "$pkg"
        printf "                        sudo systemctl restart %s || sudo systemctl restart php-fpm\n" "${APACHE_SVC:-httpd}"
      fi
    else
      warn "No supported package manager detected; install '${ext}' using your distro’s tooling."
    fi
    return 1
  fi

  # Auto-install path requires PHP to be present (we only add extensions)
  if [ "$PHP_FOUND" -ne 1 ] || [ -z "$PHP_MM" ]; then
    fail "PHP not found; cannot auto-install extension '${ext}'. Install PHP >= 8 first."
    return 2
  fi

  local pkg; pkg="$(pkg_for_ext "$ext")"
  info "Installing PHP extension '${ext}' via package '${pkg}'"

  if is_debian; then
    run sudo apt-get update
    run sudo apt-get install -y "$pkg"
    # Many Debian packages auto-enable; ensure with phpenmod or conf.d
    enable_ext_debian "$ext"
  elif is_rhel; then
    run sudo "$PKG" install -y "$pkg"
    # RHEL/Fedora typically auto-enable on install
  else
    fail "Unsupported distro for auto-install"
    return 2
  fi

  # Restart web stack so Apache/FPM pick up changes
  restart_web_stack

  # Verify
  if ext_loaded "$ext"; then
    ok "Enabled '${ext}'"
    return 0
  else
    fail "Extension '${ext}' still not detected by 'php -m' after install"
    return 1
  fi
}

ensure_php_extensions() {
  local missing=0 rc=0
  for ext in "$@"; do
    set +e
    ensure_one_ext "$ext"
    rc=$?
    set -e
    if [ $rc -ne 0 ]; then missing=1; fi
  done
  return $missing
}

########################################
# Checks: Apache, PHP, DB server, utilities
########################################

errors=0

info "=== Heurist prerequisite checks ==="

# Apache/httpd present
if [ "$HAS_APACHE" -eq 1 ]; then
  ok "Web server detected: $APACHE_SVC"
else
  fail "Web server not found (apache2/httpd)"
  if is_debian; then
    echo "  Hint: sudo apt-get update && sudo apt-get install -y apache2 libapache2-mod-php"
  elif is_rhel; then
    echo "  Hint: sudo $PKG install -y httpd php"
  fi
  errors=1
fi

# PHP >= 8.0
if [ "$PHP_FOUND" -eq 1 ] && [ -n "$PHP_FULL" ]; then
  if ver_ge "$PHP_FULL" "8.0.0"; then
    ok "PHP detected: $PHP_FULL"
  else
    fail "PHP >= 8.0 required, found $PHP_FULL"
    if is_debian; then
      echo "  Hint: sudo apt-get install -y php${PHP_MM} (or a newer supported PHP release)"
    elif is_rhel; then
      echo "  Hint: sudo $PKG install -y php"
    fi
    errors=1
  fi
else
  fail "PHP not found"
  if is_debian; then
    echo "  Hint: sudo apt-get update && sudo apt-get install -y php"
  elif is_rhel; then
    echo "  Hint: sudo $PKG install -y php"
  fi
  errors=1
fi

# MySQL 8.x or MariaDB 11.x present (client/server acceptable for this check)
DB_OK=0
DB_VER=""
if have mysql; then
  DB_VER="$(mysql --version 2>/dev/null | sed 's/[,)]//g')"
elif have mariadb; then
  DB_VER="$(mariadb --version 2>/dev/null | sed 's/[,)]//g')"
elif have mysqld; then
  DB_VER="$(mysqld --version 2>/dev/null | sed 's/[,)]//g')"
elif have mariadbd; then
  DB_VER="$(mariadbd --version 2>/dev/null | sed 's/[,)]//g')"
fi

if [ -n "$DB_VER" ]; then
  # Try to infer major version
  if echo "$DB_VER" | grep -qi 'mariadb'; then
    # Extract number like 11.2.3
    dbnum=$(echo "$DB_VER" | grep -Eo '[0-9]+\.[0-9]+(\.[0-9]+)?' | head -n1 || true)
    if [ -n "$dbnum" ] && ver_ge "$dbnum" "11.0.0"; then
      DB_OK=1
    fi
  else
    dbnum=$(echo "$DB_VER" | grep -Eo 'Ver[[:space:]]+[0-9]+\.[0-9]+(\.[0-9]+)?' | awk '{print $2}' | head -n1 || true)
    if [ -n "$dbnum" ] && ver_ge "$dbnum" "8.0.0"; then
      DB_OK=1
    fi
  fi
fi

if [ "$DB_OK" -eq 1 ]; then
  ok "Database server/client detected: $DB_VER"
else
  fail "MySQL ≥ 8.0 or MariaDB ≥ 11.0 not detected"
  if is_debian; then
    echo "  Hint (client): sudo apt-get install -y default-mysql-client"
    echo "  Hint (server): use your preferred MySQL 8 / MariaDB 11 packages"
  elif is_rhel; then
    echo "  Hint (client): sudo $PKG install -y mysql"
    echo "  Hint (server): use your preferred MySQL 8 / MariaDB 11 packages"
  fi
  errors=1
fi

# CLI utilities Heurist installer typically relies on
REQUIRED_TOOLS=(curl wget tar unzip sed awk grep cut ln mkdir rm chmod chown)
missing_tools=()
for t in "${REQUIRED_TOOLS[@]}"; do
  if ! have "$t"; then missing_tools+=("$t"); fi
done
if [ "${#missing_tools[@]}" -eq 0 ]; then
  ok "Common CLI utilities present"
else
  fail "Missing CLI utilities: ${missing_tools[*]}"
  if is_debian; then
    echo "  Hint: sudo apt-get update && sudo apt-get install -y ${missing_tools[*]}"
  elif is_rhel; then
    echo "  Hint: sudo $PKG install -y ${missing_tools[*]}"
  fi
  errors=1
fi

########################################
# PHP extensions (auto-install capable)
########################################

# Tailor this list to Heurist 7 runtime needs.
# - 'pdo' is core; we check 'pdo_mysql' for DB connectivity.
# - 'json', 'session', 'xml' often ship by default but we verify anyway.
REQUIRED_EXTS=(mysqli pdo_mysql curl gd mbstring json session dom simplexml xml zip)

info "Checking PHP extensions: ${REQUIRED_EXTS[*]}"
set +e
ensure_php_extensions "${REQUIRED_EXTS[@]}"
ext_rc=$?
set -e
if [ $ext_rc -ne 0 ]; then
  errors=1
  if [ "$AUTO_INSTALL" -ne 1 ]; then
    warn "Some PHP extensions are missing. Re-run with --auto-install to attempt automatic installation."
  fi
fi

########################################
# Outcome
########################################

if [ "$errors" -eq 0 ]; then
  ok "All prerequisite checks PASSED"
  exit 0
else
  fail "Prerequisite checks did not pass"
  if [ "$AUTO_INSTALL" -eq 1 ]; then
    exit 1
  else
    echo "Run with --auto-install to attempt installing missing PHP extensions automatically."
    exit 1
  fi
fi
