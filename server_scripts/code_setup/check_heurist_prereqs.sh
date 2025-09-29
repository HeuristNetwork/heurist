#!/usr/bin/env bash
# check_heurist_prereqs.sh
# Verifies: Apache, PHP>=8, MySQL 8 or MariaDB 11, required utilities, and required PHP extensions.
# Prints install hints for popular Linux distributions when something is missing.
#
# Usage:
#   ./check_heurist_prereqs.sh
#
set -Eeuo pipefail

# -------------------------- helpers --------------------------
have() { command -v "$1" >/dev/null 2>&1; }
fail() { echo "ERROR: $*" >&2; exit 1; }
note() { printf "%b\n" "$*"; }
semver_ge() { [[ "$(printf '%s\n' "$2" "$1" | sort -V | head -n1)" == "$2" ]]; }

# Detect package manager (for hints only)
if have apt-get; then PKG=apt
elif have dnf; then PKG=dnf
elif have yum; then PKG=yum
elif have zypper; then PKG=zypper
elif have pacman; then PKG=pacman
elif have apk; then PKG=apk
else PKG=unknown
fi

print_hint_header() {
  echo ""
  echo "---- Installation hints ------------------------------------------------"
}

hint_apache() {
  case "$PKG" in
    apt)   echo "Debian/Ubuntu: sudo apt-get update && sudo apt-get install -y apache2" ;;
    dnf)   echo "RHEL/CentOS/Fedora: sudo dnf install -y httpd && sudo systemctl enable --now httpd" ;;
    yum)   echo "RHEL/CentOS (legacy): sudo yum install -y httpd && sudo systemctl enable --now httpd" ;;
    zypper)echo "openSUSE/SLES: sudo zypper install -y apache2 && sudo systemctl enable --now apache2" ;;
    pacman)echo "Arch: sudo pacman -S --needed apache && sudo systemctl enable --now httpd" ;;
    apk)   echo "Alpine: sudo apk add apache2 && sudo rc-update add apache2 default && sudo rc-service apache2 start" ;;
    *)     echo "Install Apache (package name usually 'apache2' or 'httpd')." ;;
  esac
}

hint_php_core() {
  case "$PKG" in
    apt)   echo "Debian/Ubuntu: sudo apt-get install -y php8.2 php8.2-cli libapache2-mod-php8.2" ;;
    dnf)   echo "RHEL/CentOS/Fedora: sudo dnf install -y php php-cli php-fpm (or mod_php for httpd setups)" ;;
    yum)   echo "RHEL/CentOS (legacy): sudo yum install -y php php-cli php-fpm (or mod_php for httpd setups)" ;;
    zypper)echo "openSUSE/SLES: sudo zypper install -y php8 php8-cli apache2-mod_php8" ;;
    pacman)echo "Arch: sudo pacman -S --needed php php-apache" ;;
    apk)   echo "Alpine: sudo apk add php php-cli php-apache2" ;;
    *)     echo "Install PHP 8.x (CLI and web SAPI)." ;;
  esac
}

hint_php_exts() {
  # Expect: mysqli, pdo, curl, gd, mbstring, json, session, dom, simplexml, xml, pcre, filter, zip
  case "$PKG" in
    apt)   echo "Debian/Ubuntu: sudo apt-get install -y php8.2-mysql php8.2-curl php8.2-gd php8.2-mbstring php8.2-xml php8.2-zip" ;;
    dnf)   echo "RHEL/CentOS/Fedora: sudo dnf install -y php-mysqlnd php-curl php-gd php-mbstring php-xml php-zip" ;;
    yum)   echo "RHEL/CentOS (legacy): sudo yum install -y php-mysqlnd php-curl php-gd php-mbstring php-xml php-zip" ;;
    zypper)echo "openSUSE/SLES: sudo zypper install -y php8-mysql php8-curl php8-gd php8-mbstring php8-xml php8-zip" ;;
    pacman)echo "Arch: sudo pacman -S --needed php php-gd (enable needed extensions in /etc/php/php.ini)" ;;
    apk)   echo "Alpine: sudo apk add php-mysqli php-curl php-gd php-mbstring php-xml php-simplexml php-dom php-zip" ;;
    *)     echo "Install required PHP extensions (mysqli, pdo/pdo_mysql, curl, gd, mbstring, xml/dom/simplexml, zip). Many others are built into core." ;;
  esac
}

hint_mysql_or_mariadb() {
  case "$PKG" in
    apt)   echo "Debian/Ubuntu: EITHER sudo apt-get install -y mysql-server  (MySQL 8)  OR  sudo apt-get install -y mariadb-server (MariaDB 11 if available)" ;;
    dnf)   echo "RHEL/CentOS/Fedora: sudo dnf install -y mysql-server  OR  sudo dnf install -y mariadb-server" ;;
    yum)   echo "RHEL/CentOS (legacy): sudo yum install -y mysql-server  OR  sudo yum install -y mariadb-server" ;;
    zypper)echo "openSUSE/SLES: sudo zypper install -y mysql-community-server  OR  sudo zypper install -y mariadb" ;;
    pacman)echo "Arch: sudo pacman -S --needed mariadb  (or mysql from AUR/community if required)" ;;
    apk)   echo "Alpine: sudo apk add mariadb mariadb-client  (or mysql-community)" ;;
    *)     echo "Install MySQL 8.x or MariaDB 11.x (server + client)." ;;
  esac
}

hint_utils() {
  local missing_list="$1"
  case "$PKG" in
    apt)   echo "Debian/Ubuntu: sudo apt-get install -y $missing_list" ;;
    dnf)   echo "RHEL/CentOS/Fedora: sudo dnf install -y $missing_list" ;;
    yum)   echo "RHEL/CentOS (legacy): sudo yum install -y $missing_list" ;;
    zypper)echo "openSUSE/SLES: sudo zypper install -y $missing_list" ;;
    pacman)echo "Arch: sudo pacman -S --needed $missing_list" ;;
    apk)   echo "Alpine: sudo apk add $missing_list" ;;
    *)     echo "Install utilities: $missing_list" ;;
  esac
}

# ---------------------- checks start here ----------------------
echo "== Heurist prerequisite check =="

# Apache check
apache_bin=""
if have apache2ctl; then apache_bin="apache2ctl"
elif have apachectl; then apache_bin="apachectl"
elif have httpd; then apache_bin="httpd"
fi

if [[ -z "$apache_bin" ]]; then
  echo "✖ Apache web server: NOT FOUND"
  print_hint_header; hint_apache
  exit 1
else
  echo "✔ Apache web server: found ($apache_bin)"
fi

# PHP check (>= 8.0)
if ! have php; then
  echo "✖ PHP: NOT FOUND"
  print_hint_header; hint_php_core
  exit 1
fi

php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION.".".PHP_RELEASE_VERSION;')"
if semver_ge "$php_version" "8.0.0"; then
  echo "✔ PHP version: $php_version"
else
  echo "✖ PHP version: $php_version (need >= 8.0.0)"
  print_hint_header; hint_php_core
  exit 1
fi

# MySQL 8 or MariaDB 11 check (client/server version)
db_ok=false
db_desc=""
if have mysql; then
  # mysql --version -> e.g., mysql  Ver 8.0.36 for Linux on x86_64 (MySQL Community Server - GPL)
  mysql_version="$(mysql --version | sed -E 's/.*Distrib ([0-9]+\.[0-9]+\.[0-9]+).*/\1/;t; s/.* ([0-9]+\.[0-9]+\.[0-9]+).*/\1/')"
  if [[ "$mysql_version" =~ ^8\. ]]; then db_ok=true; db_desc="MySQL $mysql_version (client)"; fi
fi
if ! $db_ok && have mariadb; then
  mariadb_version="$(mariadb --version | sed -E 's/.*Distrib ([0-9]+\.[0-9]+\.[0-9]+).*/\1/;t; s/.* ([0-9]+\.[0-9]+\.[0-9]+).*/\1/')"
  if [[ "$mariadb_version" =~ ^11\. ]]; then db_ok=true; db_desc="MariaDB $mariadb_version (client)"; fi
fi
# Also try server binaries
if ! $db_ok && have mysqld; then
  mysqld_version="$(mysqld --version | sed -E 's/.*Ver ([0-9]+\.[0-9]+\.[0-9]+).*/\1/')"
  if [[ "$mysqld_version" =~ ^8\. ]]; then db_ok=true; db_desc="MySQL $mysqld_version (server)"; fi
fi
if ! $db_ok && have mariadbd; then
  mariadbd_version="$(mariadbd --version | sed -E 's/.*Ver ([0-9]+\.[0-9]+\.[0-9]+).*/\1/')"
  if [[ "$mariadbd_version" =~ ^11\. ]]; then db_ok=true; db_desc="MariaDB $mariadbd_version (server)"; fi
fi

if ! $db_ok; then
  echo "✖ Database: neither MySQL 8.x nor MariaDB 11.x detected"
  print_hint_header; hint_mysql_or_mariadb
  exit 1
else
  echo "✔ Database detected: $db_desc"
fi

# Required utilities
echo "== Checking required utilities =="
required_utils=(curl wget tar bzip2 gzip bunzip2 unzip sed awk grep cut ln mkdir rm chmod chown)
missing=()
for u in "${required_utils[@]}"; do have "$u" || missing+=("$u"); done

if (( ${#missing[@]} > 0 )); then
  echo "✖ Missing utilities: ${missing[*]}"
  print_hint_header; hint_utils "${missing[*]}"
  exit 1
else
  echo "✔ All required utilities are present"
fi

# Required PHP extensions
echo "== Checking PHP extensions =="
# normalize php -m output to lowercase for quick membership
mapfile -t phpmods < <(php -m 2>/dev/null | tr '[:upper:]' '[:lower:]')
php_mods_joined=" ${phpmods[*]} "

need_exts=(mysqli pdo curl gd mbstring json session dom simplexml xml pcre filter zip)
missing_exts=()

is_loaded() {
  local ext="$1"
  # Try extension_loaded first (more accurate for modules that are part of core or compiled-in)
  php -r "exit(extension_loaded('$ext')?0:1);" 2>/dev/null && return 0
  # fallback: look at php -m list
  [[ "$php_mods_joined" == *" $ext "* ]]
}

for e in "${need_exts[@]}"; do
  if ! is_loaded "$e"; then missing_exts+=("$e"); fi
done

if (( ${#missing_exts[@]} > 0 )); then
  echo "✖ Missing PHP extensions: ${missing_exts[*]}"
  print_hint_header
  hint_php_core
  hint_php_exts
  echo "Note: Some extensions (json, session, pcre, filter) are bundled with PHP core on many distros."
  echo "      After installing the packages, restart Apache: sudo systemctl restart apache2|httpd"
  exit 1
else
  echo "✔ All required PHP extensions are loaded"
fi

echo ""
echo "All prerequisite checks PASSED. You're good to proceed."
