#!/usr/bin/env bash
#
# install_heurist.sh — robust installer for Heurist
# Adds: environment checks, external utilities check, writable destination check,
# and graceful termination with cleanup.
#
# Usage:
#   ./install_heurist.sh <heurist_version> [sudo]
# Example:
#   ./install_heurist.sh h6.1.1 sudo
#
set -Eeuo pipefail

# ----------------------------- config ---------------------------------
ref_server="http://heuristref.net"
base_dir="/var/www/html"
# ----------------------------------------------------------------------

# --------------------------- graceful exit ----------------------------
cleanup() {
  local ec=$?
  if [[ -n "${TMP_WORKDIR:-}" && -d "$TMP_WORKDIR" ]]; then
    rm -rf "$TMP_WORKDIR" || true
  fi
  if [[ $ec -ne 0 ]]; then
    echo ""
    echo "Installation failed (exit code $ec). Cleaned up temporary files."
    echo "Nothing permanent was changed beyond files created before the error."
  fi
  exit $ec
}
trap cleanup EXIT
trap 'echo "Interrupted."; exit 130' INT
trap 'echo "Terminated."; exit 143' TERM

# ----------------------------- helpers --------------------------------
have() { command -v "$1" >/dev/null 2>&1; }

semver_ge() { # semver_ge A B  => returns 0 if A >= B
  # Uses sort -V to compare version-like strings
  [[ "$(printf '%s\n' "$2" "$1" | sort -V | head -n1)" == "$2" ]]
}

fail() { echo "ERROR: $*" >&2; exit 1; }

sudo_cmd="${2:-}"

# ---------------------------- arguments --------------------------------
if [[ $# -lt 1 ]]; then
  cat >&2 <<USAGE
Usage: $0 <heurist_version> [sudo]

<heurist_version> must match a tarball on:
  $ref_server/HEURIST/DISTRIBUTION/<version>.tar.bz2

Example:
  $0 h6.1.1 sudo
USAGE
  exit 2
fi

heurist_version="$1"
archive_name="${heurist_version}.tar.bz2"
archive_url="${ref_server}/HEURIST/DISTRIBUTION/${archive_name}"

# --------------------- prerequisite checks ----------------------------
echo ""
echo "==> Checking required services and versions..."

# 1) Apache (apache2/httpd) installed
if have apache2ctl; then apache_bin="apache2ctl"
elif have apachectl; then apache_bin="apachectl"
elif have httpd; then apache_bin="httpd"
else
  fail "Apache is not installed. Please install Apache (apache2/httpd)."
fi
echo "  - Apache found: $apache_bin"

# 2) PHP >= 8.0
if ! have php; then
  fail "PHP is not installed. Please install PHP 8.x."
fi
php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION.".".PHP_RELEASE_VERSION;')"
if ! semver_ge "$php_version" "8.0.0"; then
  fail "PHP $php_version found, but >= 8.0.0 is required."
fi
echo "  - PHP $php_version"


# 3) MySQL 8.x OR MariaDB 11.x client/server (robust detection)
db_ok=false
db_desc=""

extract_version() {
  # Pull the first x.y or x.y.z occurrence
  echo "$1" | grep -oE '[0-9]+(\.[0-9]+){1,2}' | head -n1
}

check_mysql_like() {
  local bin="$1"
  if have "$bin"; then
    local out ver
    out="$("$bin" --version 2>/dev/null || true)"
    if [[ -n "$out" ]]; then
      # If the output mentions MariaDB, treat separately
      if [[ "$out" =~ [Mm]aria[Dd][Bb] ]]; then
        ver="$(extract_version "$out")"
        if [[ "$ver" =~ ^11\. ]]; then
          db_ok=true; db_desc="MariaDB $ver ($(basename "$bin"))"
          return 0
        fi
      else
        ver="$(extract_version "$out")"
        if [[ "$ver" =~ ^8\. ]]; then
          db_ok=true; db_desc="MySQL $ver ($(basename "$bin"))"
          return 0
        fi
      fi
    fi
  fi
  return 1
}

# Try common client/server binaries, considering distro variants
check_mysql_like mysql     || \
check_mysql_like mariadb   || \
check_mysql_like mysqld    || \
check_mysql_like mariadbd  || true

if ! $db_ok; then
  fail "Neither MySQL 8.x nor MariaDB 11.x detected (checked mysql/mariadb/mysqld/mariadbd). Please install one of them."
fi
echo "  - Database: $db_desc"


# 4) External utilities check (expandable)
echo "==> Checking external utilities..."
required_utils=(curl wget tar bzip2 gzip bunzip2 unzip sed awk grep cut ln mkdir rm chmod chown)
missing=()
for u in "${required_utils[@]}"; do
  have "$u" || missing+=("$u")
done
if (( ${#missing[@]} )); then
  printf 'Missing utilities: %s\n' "${missing[*]}" >&2
  fail "Install the missing utilities and re-run this script."
fi
echo "  - All required utilities present."

# 5) Destination dir writable
echo "==> Checking destination directory writable..."
if [[ ! -d "$base_dir" ]]; then
  fail "Destination base_dir '$base_dir' does not exist."
fi
if [[ ! -w "$base_dir" ]]; then
  echo "  - '$base_dir' not writable by current user."
  if [[ -n "$sudo_cmd" ]]; then
    echo "  - Will attempt to create/write using '$sudo_cmd' when needed."
  else
    fail "Provide 'sudo' as 2nd arg or run with sufficient permissions."
  fi
else
  echo "  - '$base_dir' is writable."
fi

# 6) Check the Heurist package exists remotely
echo "==> Validating package URL..."
if ! curl -fsI --range 0-100 "$archive_url" >/dev/null; then
  fail "Package not found at: $archive_url"
fi
echo "  - Package available: $archive_url"

# --------------------------- begin install ----------------------------
echo ""
echo "================ Installing Heurist $heurist_version ================"
echo ""

# Working dir for downloads
TMP_WORKDIR="$(mktemp -d -t heurist-install-XXXXXX)"
cd "$TMP_WORKDIR"

# Create HEURIST structure under base_dir
echo "==> Preparing directory structure..."
if [[ ! -d "${base_dir}/HEURIST" ]]; then
  ${sudo_cmd:+$sudo_cmd} mkdir -p "${base_dir}/HEURIST"
fi
${sudo_cmd:+$sudo_cmd} mkdir -p "${base_dir}/HEURIST/HEURIST_SUPPORT"

# Download and unpack main package
echo "==> Downloading $archive_name ..."
curl -fSL "$archive_url" -o "$archive_name"
echo "==> Extracting to ${base_dir}/HEURIST/${heurist_version} ..."
${sudo_cmd:+$sudo_cmd} mkdir -p "${base_dir}/HEURIST/${heurist_version}"
${sudo_cmd:+$sudo_cmd} tar -xjf "$archive_name" -C "${base_dir}/HEURIST/${heurist_version}" --strip-components=1

# Download HEURIST_SUPPORT bundles
echo "==> Installing HEURIST_SUPPORT bundles..."
cd "${base_dir}/HEURIST/HEURIST_SUPPORT"
${sudo_cmd:+$sudo_cmd} bash -c "set -euo pipefail; \
  wget -q ${ref_server}/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h5.tar.bz2 && \
  tar -xjf external_h5.tar.bz2 && rm -f external_h5.tar.bz2 && \
  wget -q ${ref_server}/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/vendor.tar.bz2 && \
  tar -xjf vendor.tar.bz2 && rm -f vendor.tar.bz2 && \
  wget -q ${ref_server}/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2 && \
  tar -xjf help.tar.bz2 && rm -f help.tar.bz2"

# Symlinks inside version dir
echo "==> Creating internal symlinks..."
cd "${base_dir}/HEURIST/${heurist_version}"
${sudo_cmd:+$sudo_cmd} ln -snf ../HEURIST_SUPPORT/external_h5 external
${sudo_cmd:+$sudo_cmd} ln -snf ../HEURIST_SUPPORT/help help
${sudo_cmd:+$sudo_cmd} ln -snf ../HEURIST_SUPPORT/vendor vendor

# Symlink version into web root
echo "==> Creating webroot symlinks..."
cd "${base_dir}"
${sudo_cmd:+$sudo_cmd} ln -snf "HEURIST/${heurist_version}" "${heurist_version}"
${sudo_cmd:+$sudo_cmd} ln -snf "HEURIST/${heurist_version}" "heurist"
${sudo_cmd:+$sudo_cmd} ln -snf "HEURIST/${heurist_version}" "heurist_switchboard"

cd "${base_dir}/HEURIST"
${sudo_cmd:+$sudo_cmd} ln -snf "${heurist_version}" "h"

# Create filestore and protect it
echo "==> Creating filestore and setting permissions..."
${sudo_cmd:+$sudo_cmd} mkdir -p "${base_dir}/HEURIST/HEURIST_FILESTORE"
if [[ ! -f "${base_dir}/HEURIST/HEURIST_FILESTORE/.htaccess" ]]; then
  ${sudo_cmd:+$sudo_cmd} bash -c "cat > '${base_dir}/HEURIST/HEURIST_FILESTORE/.htaccess' <<'HT'
# Deny direct web access to filestore
Require all denied
HT"
fi

# Done
echo ""
echo "---- Heurist installed in ${base_dir}/HEURIST/heurist ----------------"
echo ""
cat <<EOM
If your partition is tight on space, consider moving HEURIST_FILESTORE to a larger volume
(e.g., /srv or /data) and replace it with a symlink in ${base_dir}/HEURIST.

Access:
  - Program:      https://<server>/heurist
  - Switchboard:  https://<server>/HEURIST  or  http://<server>/heurist_switchboard

Configuration:
  Edit ${base_dir}/HEURIST/heuristConfigIni.php to set your database root password and other settings.
  For example:
      ${sudo_cmd:+$sudo_cmd }nano ${base_dir}/HEURIST/heuristConfigIni.php

All done!
EOM
