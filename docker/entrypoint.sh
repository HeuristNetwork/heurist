#!/bin/bash
# Entrypoint for the Heurist Docker image.
# Reproduces the directory layout created by install_heurist.sh at runtime,
# because the codebase is bind-mounted over the copy baked into the image.
set -euo pipefail

BASE=/var/www/html/HEURIST
APP="$BASE/heurist"

download_https() {
    curl -fsSL --proto '=https' --proto-redir '=https' --retry 3 "$@"
}

# 1. Filestore: create if missing and deny direct web access
mkdir -p "$BASE/HEURIST_FILESTORE"
if [[ ! -f "$BASE/HEURIST_FILESTORE/.htaccess" ]]; then
    printf '# Deny direct web access to filestore\nRequire all denied\n' \
        > "$BASE/HEURIST_FILESTORE/.htaccess"
fi

# 2. movetoparent files (switchboard index.html, favicon, ...).
#    heuristConfigIni.php is excluded: the Docker variant is mounted separately.
if [[ -d "$APP/movetoparent" ]]; then
    find "$APP/movetoparent" -maxdepth 1 -type f ! -name 'heuristConfigIni.php' \
        -exec cp -n {} "$BASE/" \; || true
fi

# 3. Support-bundle symlinks (skip if the repo already has real directories)
for pair in "external:external_h5" "help:help"; do
    link="${pair%%:*}"
    target="${pair##*:}"
    if [[ ! -e "$APP/$link" && -d "$BASE/HEURIST_SUPPORT/$target" ]]; then
        ln -s "../HEURIST_SUPPORT/$target" "$APP/$link"
    fi
done

# 4. Composer vendor directory for bind-mounted repos that don't have one
#    (baked into the image at build time as /opt/heurist_vendor)
if [[ ! -e "$APP/vendor/autoload.php" && -d /opt/heurist_vendor ]]; then
    cp -a /opt/heurist_vendor "$APP/vendor"
fi

# 4b. Fill gaps in the external_h5 support bundle: the distribution tarball
#     lacks local copies of the core JS libraries that hserv/consts.php
#     includeJQuery() loads when the server is accessed via localhost
#     (localhost mode uses local files; remote hosts get CDN links).
EXT="$BASE/HEURIST_SUPPORT/external_h5"
if [[ -d "$EXT" && ! -f "$EXT/jquery/jquery-3.7.1.js" ]]; then
    echo "==> Downloading missing external libraries (jquery, jquery-ui, bootstrap)..."
    mkdir -p "$EXT/jquery" "$EXT/bootstrap"
    download_https -o "$EXT/jquery/jquery-3.7.1.js" \
        https://code.jquery.com/jquery-3.7.1.js || true
    download_https -o "$EXT/jquery/jquery-ui.js" \
        https://code.jquery.com/ui/1.14.0/jquery-ui.js || true
    download_https -o "$EXT/jquery/jquery-ui.css" \
        https://code.jquery.com/ui/1.14.0/themes/base/jquery-ui.css || true
    download_https -o "$EXT/bootstrap/bootstrap.bundle.min.js" \
        https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js || true
    download_https -o "$EXT/bootstrap/bootstrap.min.css" \
        https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css || true
fi
if [[ -d "$EXT" && ! -f "$EXT/jquery.widgets/jquery.fancytree/jquery.fancytree-all.js" ]]; then
    download_https -o "$EXT/jquery.widgets/jquery.fancytree/jquery.fancytree-all.js" \
        https://cdnjs.cloudflare.com/ajax/libs/jquery.fancytree/2.38.4/jquery.fancytree-all.js || true
fi

# 4c. The distribution tarball also lacks the DataTables bundle loaded by the
#     main index page (external/js/datatable/datatables.min.js|.css). jQuery is
#     loaded separately by includeJQuery(), so the standalone build is enough.
if [[ -d "$EXT" && ! -f "$EXT/js/datatable/datatables.min.js" ]]; then
    mkdir -p "$EXT/js/datatable"
    download_https -o "$EXT/js/datatable/datatables.min.js" \
        https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js || true
    download_https -o "$EXT/js/datatable/datatables.min.css" \
        https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css || true
fi

# 5. Permissions: Apache (www-data) must be able to write the filestore.
#    NOTE: the codebase itself stays read-only (the bind mount keeps host
#    ownership) - Heurist writes uploads/databases to HEURIST_FILESTORE and MySQL.
chown -R www-data:www-data "$BASE/HEURIST_FILESTORE" 2>/dev/null || true

exec "$@"
