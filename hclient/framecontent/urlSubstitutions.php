<?php
/**
 * JT#3294 - Plain-text editor for a database's URL substitution rules.
 *
 * The routing implementation already reads settings/URLSubstitutions.txt.
 * This page only creates and edits that file; it does not interpret its rules.
 */

define('MANAGER_REQUIRED', 1);
require_once __DIR__ . '/initPageMin.php';

const URL_SUBSTITUTIONS_HELP = <<<'TXT'
# This file allows the substitution of a user-defined term in a URL with an arbitrary string
# such as the CMS Home Page/CMS Menu Page IDs or detailed search parameters.
# For example, "idenk.net/3/37461" loads the Tentang page (the Indonesian About page).
# By specifying "Tentang 3/37461" the page can be accessed as "idenk.net/Tentang"

# The terms are separated from the substitution by one or more spaces, so they must
# be single words (use dashes or underscores if needed to link multiple words).
# They must be at least 4 letters; do not use "view" which has pre-defined meaning.
# The term MUST immediately follow the domain name (if a specific database is implied)
# or the database name (if this is specified in the URL) to avoid accidental replacement
# of later text.

TXT;

global $experimental;

$error = '';
$saved = false;
$content = '';

if (!isset($experimental) || $experimental !== true) {
    $error = 'Sorry, experimental function not available on this server';
} else {
    $settingsDir = rtrim(HEURIST_FILESTORE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings';
    $filePath = $settingsDir . DIRECTORY_SEPARATOR . 'URLSubstitutions.txt';

    if (!is_dir($settingsDir) && !folderCreate($settingsDir, true)) {
        $error = 'Unable to create the database settings directory.';
    } elseif (!is_file($filePath)) {
        if (file_put_contents($filePath, URL_SUBSTITUTIONS_HELP, LOCK_EX) === false) {
            $error = 'Unable to create URLSubstitutions.txt.';
        }
    }

    if ($error === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $content = isset($_POST['content']) && is_string($_POST['content']) ? $_POST['content'] : '';
        if (strlen($content) > 262144) {
            $error = 'The substitutions file is unexpectedly large and was not saved.';
        } else {
            // Write beside the target and rename it, so an interrupted save cannot
            // leave a partially-written substitutions file for RequestRouter to read.
            $temporaryPath = tempnam($settingsDir, 'URLSubstitutions.');
            if ($temporaryPath === false
                || file_put_contents($temporaryPath, $content, LOCK_EX) === false
                || !rename($temporaryPath, $filePath)) {
                if ($temporaryPath !== false && is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
                $error = 'Unable to save URLSubstitutions.txt.';
            } else {
                $saved = true;
            }
        }
    }

    if ($error === '' && !$saved) {
        $loaded = file_get_contents($filePath);
        if ($loaded === false) {
            $error = 'Unable to read URLSubstitutions.txt.';
        } else {
            $content = $loaded;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>URL Substitutions</title>
    <style>
        body { box-sizing: border-box; margin: 0; padding: 18px; font: 14px Arial, sans-serif; }
        h1 { font-size: 18px; margin: 0 0 14px; }
        textarea { box-sizing: border-box; width: 100%; height: 390px; padding: 10px; resize: vertical;
            font: 13px/1.4 Consolas, "Courier New", monospace; }
        .actions { margin-top: 12px; text-align: right; }
        button { min-width: 90px; padding: 6px 14px; }
        .message { margin-bottom: 12px; padding: 9px; border-radius: 3px; }
        .error { background: #f8d7da; color: #842029; }
        .success { background: #d1e7dd; color: #0f5132; }
    </style>
</head>
<body>
    <h1>URL Substitutions</h1>
    <?php if ($error !== ''): ?>
        <div class="message error"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div>
    <?php else: ?>
        <?php if ($saved): ?><div class="message success">URL substitutions saved.</div><?php endif; ?>
        <form method="post">
            <textarea name="content" spellcheck="false" aria-label="URL substitutions"><?=htmlspecialchars($content, ENT_QUOTES, 'UTF-8')?></textarea>
            <div class="actions"><button type="submit">Save</button></div>
        </form>
    <?php endif; ?>
</body>
</html>
