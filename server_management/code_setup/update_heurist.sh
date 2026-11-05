#! /bin/sh

# update_heurist.sh: update script for Heurist, creates new copy. codeonly does not update the support packages.

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2023 University of Sydney  2024 onwards Heurist Network
# @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     7

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

# Download and check everything before changing the installed code. Keep this
# block self-contained: these scripts are also run directly through curl | sh.
set -eu
ref_server=https://heuristref.net
base_dir="/var/www/html"
version=${1:-}
privilege=
codeonly=false
for argument in "${2:-}" "${3:-}"; do
    case "$argument" in
        sudo) privilege=sudo ;;
        codeonly) codeonly=true ;;
        "") ;;
        *) echo "Unknown argument: $argument" >&2; exit 2 ;;
    esac
done
case "$version" in
    h[0-9]*) ;;
    *) echo "Usage: $0 <version, e.g. h7-alpha> [sudo] [codeonly]" >&2; exit 2 ;;
esac
case "$version" in
    *[!a-zA-Z0-9._-]*|*..*) echo "Invalid version: $version" >&2; exit 2 ;;
esac
run() { $privilege "$@"; }
fail() { echo "ERROR: $*" >&2; exit 1; }
install_root="$base_dir/HEURIST"
run mkdir -p "$install_root"
# Serialize installation and updates, including support packages shared by versions.
lock="$install_root/.heurist-install.lock"
run mkdir "$lock" 2>/dev/null || fail "Another installation may be running ($lock)."
stage=
backup=
published=
committed=false
cleanup() {
    status=$?
    trap - EXIT HUP INT TERM
    if [ "$committed" = false ]; then
        for relative in $published; do
            run rm -rf "$install_root/$relative"
            if [ -e "$backup/$relative" ] || [ -L "$backup/$relative" ]; then
                run mv "$backup/$relative" "$install_root/$relative" ||
                    echo "Restore $backup/$relative manually" >&2
            fi
        done
    fi
    [ -z "$stage" ] || run rm -rf "$stage"
    run rmdir "$lock" || true
    exit "$status"
}
trap cleanup EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM
stage=$(run mktemp -d "$install_root/.heurist-stage.XXXXXXXX")
run chmod 755 "$stage"
fetch() {
    package=$1
    destination=$2
    archive="$stage/$package.tar.bz2"
    run curl --fail --location --show-error --silent \
        --connect-timeout 30 --max-time 1800 \
        "$ref_server/HEURIST/DISTRIBUTION/$destination$package.tar.bz2" \
        --output "$archive" || fail "Download failed: $package"
    # Listing checks the complete compressed archive; reject unexpected roots
    # and traversal before extraction, including accidentally returned HTML.
    members=$(run tar -tjf "$archive") || fail "Invalid archive: $package"
    printf '%s\n' "$members" | awk -v root="$package" '
        { p=$0; sub(/^\.\//,"",p)
          if (p !~ ("^" root "(/|$)") || p ~ /(^|\/)\.\.(\/|$)/) bad=1; n++ }
        END { exit (bad || !n) }
    ' || fail "Unexpected archive contents: $package"
    run mkdir -p "$stage/unpacked"
    run tar -xjf "$archive" -C "$stage/unpacked" || fail "Extraction failed: $package"
    [ -d "$stage/unpacked/$package" ] && [ ! -L "$stage/unpacked/$package" ] ||
        fail "Missing package directory: $package"
}
fetch "$version" ""
# Reject empty/partial builds, even when they are valid tarballs.
for required in index.php configIni.php autoload.php hserv/controller/index.php; do
    [ -s "$stage/unpacked/$version/$required" ] || fail "Incomplete build: missing $required"
done
for required in admin hclient hserv; do
    [ -d "$stage/unpacked/$version/$required" ] || fail "Incomplete build: missing $required"
done
if [ "$codeonly" = false ]; then
    for support in external_h5 vendor help; do
        fetch "$support" "HEURIST_SUPPORT/"
        [ -n "$(run ls -A "$stage/unpacked/$support")" ] || fail "Empty support package: $support"
    done
    [ -s "$stage/unpacked/vendor/autoload.php" ] || fail "Missing vendor/autoload.php"
fi
for support in external_h5 vendor help; do
    case "$support" in external_h5) link=external ;; *) link=$support ;; esac
    run rm -rf "$stage/unpacked/$version/$link"
    run ln -s "../HEURIST_SUPPORT/$support" "$stage/unpacked/$version/$link"
done
# Finish permissions before publishing. Missing platform-specific accounts are OK.
for package in "$version" external_h5 vendor help; do
    [ -d "$stage/unpacked/$package" ] || continue
    for account in apache nginx www-data; do
        if id "$account" >/dev/null 2>&1; then
            run chown -R "$account" "$stage/unpacked/$package"
            break
        fi
    done
    if getent group heurist >/dev/null 2>&1; then
        run chgrp -R heurist "$stage/unpacked/$package"
    fi
    run chmod -R ug+rwX "$stage/unpacked/$package"
done
backup=$(run mktemp -d "$install_root/.heurist-backup.XXXXXXXX")
run chmod 755 "$backup"
publish() {
    relative=$1
    source=$2
    run mkdir -p "$(dirname "$backup/$relative")"
    if [ -e "$install_root/$relative" ] || [ -L "$install_root/$relative" ]; then
        run mv "$install_root/$relative" "$backup/$relative"
    fi
    published="$relative $published"
    run mv "$source" "$install_root/$relative"
}
run mkdir -p "$install_root/HEURIST_SUPPORT"
if [ "$codeonly" = false ]; then
    for support in external_h5 vendor help; do
        publish "HEURIST_SUPPORT/$support" "$stage/unpacked/$support"
    done
fi
publish "$version" "$stage/unpacked/$version"
committed=true
echo "Installed $install_root/$version; previous files retained in $backup"
