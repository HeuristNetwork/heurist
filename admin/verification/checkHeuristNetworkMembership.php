<?php
declare(strict_types=1);

/**
* checkHeuristNetworkMembership.php - Checks to see if the user or database (eventually group) is a member of the association
*
* @fileOverview This is an independent function which compares a user email address and/or database name against a text file
*               list of members (and databses owned by members) and returns whether the person is a memvber of the Heurist 
*               Network association, either individually or because the database is authorised as belonging to a group which is a member.
*               It also logs non-member requests except in specific situations (notably program startup or independent enquiry).
*               This function is unique to the HeuristRef.net server which contains the membership list updated daily.
*
* @project     Heurist academic knowledge management system
* @package     Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>  specification
* @author      ChatGPT 5 draft
* @author      Artem Osmakov <osmakov@gmail.com> corrections
* @since       7.0
*/

/**
* Heurist Network — Membership Check
* ----------------------------------
*
* Usage (include in any PHP page or API):
*   require_once 'check_membership.php';
*   $status = check_membership($email, $name, $database, $context);
*   // $status is one of: "nonmember" or a pipe-joined list of: database|individual|group
*
* Behavior:
*   - Parses /var/www/html/HEURIST/association_members.txt (cached in PHP session).
*   - Recognizes three line formats:
*       1) email, family name, first name      => individual member
*       2) email, database name                => database-level membership
*       3) email, project/lab/institution name => group (heuristic; not fully used yet)
*   - Returns membership type(s). If none => 'nonmember'.
*   - If 'nonmember', shows a modal popup (web SAPI only) with a 5s delayed Close button.
*   - Logs nonmember checks (except when context === 'Initial sign-in') to membership_checkpoint.log
*/

// --- configuration ---
const HN_MEMBERS_FILE = '/var/www/html/HEURIST/association_members.txt';
const HN_LOG_FILE     = '/var/www/html/HEURIST/HEURIST_FILESTORE/_HEURISTNETWORK_membership_checkpoint.log';
const HN_TIMEZONE     = 'Australia/Sydney';



// --- lightweight session helpers for last-result caching ---
function hn_session_start_if_needed(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (PHP_SAPI !== 'cli' && headers_sent()) { return; } // avoid warnings in web if headers sent
        @session_start();
    }
}

/**
 * Store last successful (non-'nonmember') check in session so we can skip re-reading the file
 * structure: ['email' => string, 'db' => string, 'result' => string]
 */
function hn_set_last_membership_check(string $email, string $db, string $result): void {
    hn_session_start_if_needed();
    $_SESSION['hn_last_membership_check'] = ['email' => $email, 'db' => $db, 'result' => $result];
}

/** Get last membership check from session or null */
function hn_get_last_membership_check(): ?array {
    hn_session_start_if_needed();
    $v = $_SESSION['hn_last_membership_check'] ?? null;
    return (is_array($v)) ? $v : null;
}

// --- session-backed cache loader ---
function hn_load_membership_cache(): array {
    // No longer stored in session; read fresh when invoked (only called when recompute is needed)
    $data = [
        'individual_emails' => [],    // email => ['last' => string, 'first' => string]
        'db_names'          => [],    // strtolower(dbname) => email
        'group_emails'      => [],    // email => group name (heuristic)
    ];

    if (is_file(HN_MEMBERS_FILE) && is_readable(HN_MEMBERS_FILE)) {
        $lines = file(HN_MEMBERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $firstLine = true;

        foreach ($lines as $ln) {
            $line = trim($ln);
            // Skip blank and commented lines (allow leading whitespace)
            if ($line === '' || preg_match('/^\s*#/', $line)) { continue; }

            // Strip UTF-8 BOM on the very first line if present
            if ($firstLine) {
                $line = ltrim($line, "\xEF\xBB\xBF");
                $firstLine = false;
            }

            // Robust CSV parsing with quotes and escapes
            $parts = str_getcsv($line, ',', '"', '\\');
            if (!$parts || count($parts) < 2) { continue; }

            $type = strtoupper(trim($parts[0]));

            if ($type === 'INDIVIDUAL') {
                // INDIVIDUAL,email,"last name","firstname"
                if (count($parts) < 4) { continue; }
                $email = strtolower(trim($parts[1]));
                $last  = trim($parts[2]);
                $first = trim($parts[3]);
                if ($email !== '') {
                    $data['individual_emails'][$email] = ['last' => $last, 'first' => $first];
                }
                continue;
            }

            if ($type === 'PROJECT') {
                // PROJECT,email,"server name",database
                if (count($parts) < 4) { continue; }
                $email  = strtolower(trim($parts[1]));
                $server = trim($parts[2]);
                $db     = strtolower(trim($parts[3]));
                if ($db !== '') {
                    // Treat as database-level membership for checking
                    $data['db_names'][$db]   = $email;
                    $data['db_servers'][$db] = $server; // optional meta
                }
                continue;
            }

            // Any other type is ignored
        }

    return $data;
}
    
// --- log helper ---
function hn_log_nonmember(string $result, string $database, string $name, string $email, string $context): void {
    if ($result !== 'nonmember') return;
    if (in_array($context, ['Initial sign-in', ''], true)) return;  // do not log sign-in message or a call with no context

    try {
        $tz = new DateTimeZone(HN_TIMEZONE);
        $now = (new DateTime('now', $tz))->format(DateTime::ATOM);
    } catch (Throwable) {
        $now = date('c');
    }

    $entry = json_encode([
        'ts'      => $now,
        'result'  => $result,
        'database'=> $database,
        'name'    => $name,
        'email'   => $email,
        'context' => $context,
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($entry !== false) {
        @file_put_contents(HN_LOG_FILE, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

// --- popup renderer (only in web SAPI) ---
function hn_render_nonmember_popup(string $context): void {
    if (PHP_SAPI === 'cli') return; // don't emit JS under CLI

    $title = 'Heurist Network Association';
    $firstSentence = 'The function you have requested was funded by the Heurist Network association and is only available to members (individuals, projects, research units or institutions).';

    // Body content (with optional removal of first sentence)
    $bodyIntro = ($context === 'Initial sign-in') ? '' : '<p style="margin-top:0">' . htmlspecialchars($firstSentence, ENT_QUOTES, 'UTF-8') . '</p>';

    $bodyMain = <<<HTML
    <p>Heurist Network is a non-profit association which supports the ongoing development and maintenance of Heurist and depends entirely on funding provided by membership subscriptions and consultancy related to Heurist. Please:</p>
    <ul style="margin:0 0 0.75em 1.25em;">
      <li>consider joining the association or ask your project, lab or institution to do so.</li>
      <li>include funding to support Heurist in grant applications and annual budgets.</li>
      <li>request a quote for any type of work associated with Heurist (database setup, website creation, new functionality),</li>
    </ul>
    <p>To discuss membership (including temporary membership while administrative wheels turn), special requirements or consultancy, please email <a href="mailto:support@heuristnetwork.org">support@heuristnetwork.org</a></p>
    <p>If you believe that you or your project, lab or institution is a member, but you have not been correctly identified, please email us specifying your database name, user name and institutional affiliation (this may happen because a new database has been created and not yet identified as belonging to a group membership).</p>
    <p style="margin-bottom:0.5em">Membership form: <a href="https://forms.gle/3nNQthZS4P9Ap1mg8" target="_blank" rel="noopener">https://forms.gle/3nNQthZS4P9Ap1mg8</a></p>
    HTML;

    $html = $bodyIntro . $bodyMain;

    // Emit a single modal if not already present
    echo '<script>(function(){
    if (document.getElementById("hn-membership-modal")) return;
    var overlay = document.createElement("div");
    overlay.id = "hn-membership-modal";
    overlay.setAttribute("role","dialog");
    overlay.setAttribute("aria-modal","true");
    overlay.style.cssText = "position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:2147483000;display:flex;align-items:center;justify-content:center;";

    var panel = document.createElement("div");
    panel.style.cssText = "background:#fff;width:500px;max-width:90vw;border-radius:10px;box-shadow:0 10px 35px rgba(0,0,0,.3);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,sans-serif;overflow:hidden;";

    var header = document.createElement("div");
    header.style.cssText = "padding:14px 18px;border-bottom:1px solid #eee;font-weight:600;font-size:16px;";
    header.textContent = " . json_encode($title) . ";

    var body = document.createElement("div");
    body.style.cssText = "padding:14px 18px;line-height:1.45;font-size:14px;max-height:70vh;overflow:auto;";
    body.innerHTML = " . json_encode($html) . ";

    var footer = document.createElement("div");
    footer.style.cssText = "display:flex;justify-content:flex-end;gap:8px;padding:12px 18px;border-top:1px solid #eee;";

    var btn = document.createElement("button");
    btn.type = "button";
    btn.disabled = true;
    btn.textContent = "Close (5)";
    btn.style.cssText = "padding:8px 14px;border-radius:8px;border:1px solid #ccc;background:#f3f3f3;cursor:not-allowed;font-size:14px;";

    var allowClose = false;
    var allow = function(){ allowClose = true; btn.disabled = false; btn.textContent = "Close"; btn.style.cursor = "pointer"; };
    var countdown = 5; var tick = function(){
    if (countdown <= 1) { allow(); return; }
    countdown--; btn.textContent = "Close ("+countdown+")"; setTimeout(tick, 1000);
    }; setTimeout(tick, 1000);

    btn.addEventListener("click", function(){ if(allowClose){ document.body.style.overflow = originalOverflow; overlay.remove(); }});

    var originalOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden"; // prevent background scroll

    footer.appendChild(btn);
    panel.appendChild(header);
    panel.appendChild(body);
    panel.appendChild(footer);
    overlay.appendChild(panel);
    document.body.appendChild(overlay);
    })();</script>';
}

/**
* Main API: check_membership
*
* @param string      $email   User email address
* @param string      $name    User display name (for logging)
* @param null|string $database Database name (if empty, only email is checked)
* @param string      $context Short string indicating where it is called from (e.g., 'LOD output', 'Initial sign-in')
*
* @return string One of: 'nonmember' OR a pipe-joined subset of {'database','individual','group'}
*/
function check_membership(string $email, string $name = '', ?string $database = null, string $context = ''): string {
    \1

    // Fast path: reuse prior session result if it matches and was a member (not 'nonmember')
    $last = hn_get_last_membership_check();
    if ($last && ($last['email'] ?? '') === $emailNorm && ($last['db'] ?? '') === $dbNorm && ($last['result'] ?? 'nonmember') !== 'nonmember') {
        // Keep session fresh for this pair
        hn_set_last_membership_check($emailNorm, $dbNorm, $last['result']);
        return $last['result'];
    }
$cache = hn_load_membership_cache();

    $hits = [];

    if ($dbNorm !== '' && isset($cache['db_names'][$dbNorm])) {
        $hits[] = 'database';
    }
    if ($emailNorm !== '' && isset($cache['individual_emails'][$emailNorm])) {
        $hits[] = 'individual';
    }
    if ($emailNorm !== '' && isset($cache['group_emails'][$emailNorm])) {
        $hits[] = 'group';
    }

    $result = empty($hits) ? 'nonmember' : implode('|', $hits);

    if ($result === 'nonmember') {
        // Show popup (web only)
        hn_render_nonmember_popup($context);
        // Log (except initial sign-in)
        hn_log_nonmember($result, $database ?? '', $name, $email, $context);
    }
    // Remember last check in session for fast path on subsequent calls
    hn_set_last_membership_check($emailNorm, $dbNorm, $result);

    return $result;

}