<?php
/**
* checkMembershipForm.php - Form to validate Association membership
* 
* @project     Heurist academic knowledge management system
* @package     Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>  specification
* @author      Artem Osmakov <osmakov@gmail.com> corrections
* @since       7.0
*/
require_once 'checkMembershipLib.php';
$ENDPOINT = 'https://heuristref.net/h7-alpha/admin/utilities/checkMembershipApi.php';

// ---------- helpers ----------
function postToEndpoint(array $payload, string $endpoint): string
{
    $isMainServer = (@$_SERVER["SERVER_NAME"]=='heuristref.net');

    if($isMainServer){
        return checkMembershipInFile('', $payload['email'], $payload['host'], $payload['db'], '', $payload['firstName'], $payload['lastName']);
    }
    
    $postFields = http_build_query($payload, '', '&');

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, array(
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $postFields,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_TIMEOUT         => 10,
            CURLOPT_CONNECTTIMEOUT  => 5,
            CURLOPT_HTTPHEADER      => array('Content-Type: application/x-www-form-urlencoded'),
            CURLOPT_USERAGENT       => 'HN-Membership-Form/1.0',
        ));
        $resp = curl_exec($ch);
        if ($resp === false) {
            $resp = 'Request error: ' . curl_error($ch);
        }
        curl_close($ch);
        return (string)$resp;
    }

    $ctx = stream_context_create(array(
        'http' => array(
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                       . "User-Agent: HN-Membership-Form/1.0\r\n",
            'content' => $postFields,
            'timeout' => 10,
        )
    ));
    $resp = @file_get_contents($endpoint, false, $ctx);
    return $resp !== false ? (string)$resp : 'Request error (no cURL and fopen failed).';
}

// ---------- handle submit ----------
$statusMsg = '';
$values = array(
    'email'         => '',
    'firstName'     => '',
    'lastName'      => '',
    'server_sel'    => 'heurist.huma-num.fr',
    'server_custom' => '',
    'db'            => '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['email']         = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $values['firstName']     = isset($_POST['firstName']) ? trim((string)$_POST['firstName']) : '';
    $values['lastName']      = isset($_POST['lastName']) ? trim((string)$_POST['lastName']) : '';
    $values['server_sel']    = isset($_POST['server_sel']) ? (string)$_POST['server_sel'] : 'huma-num.fr';
    $values['server_custom'] = isset($_POST['server_custom']) ? trim((string)$_POST['server_custom']) : '';
    $values['db']            = isset($_POST['db']) ? trim((string)$_POST['db']) : '';

    $host = $values['server_sel'] === 'other' && $values['server_custom'] !== ''
        ? $values['server_custom']
        : $values['server_sel'];

    // Validation: email OR (firstName+lastName) OR (host+database)
    $validCombo = false;
    if ($values['email'] !== '') {
        $validCombo = true;
    } elseif ($values['firstName'] !== '' && $values['lastName'] !== '') {
        $validCombo = true;
    } elseif ($host !== '' && $values['db'] !== '') {
        $validCombo = true;
    }

    if (!$validCombo) {
        $statusMsg = 'Please provide at least one valid combination: Email OR (First name + Last name) OR (Server + Database).';
    } else {
        // Ensure DB has hdb_ prefix
        $dbName = strtolower($values['db']);
        if (strpos($dbName, 'hdb_') !== 0) {
            //$dbName = 'hdb_' . $dbName;
        }

        $payload = array(
            'email'     => $values['email'],
            'host'      => $host,
            'db'        => $dbName,
            'firstName' => $values['firstName'],
            'lastName'  => $values['lastName'],
        );

        $statusMsg = [];
        $membership = postToEndpoint($payload, $ENDPOINT);
        if(strpos($membership, 'database')!==false){
          $statusMsg[] = 'project database';
        }
        if(strpos($membership, 'viaowner')!==false){
          $statusMsg[] = 'database owner';
        }
        if(strpos($membership, 'individual')!==false){
          $statusMsg[] = 'association member';
        }
        if(strpos($membership, 'nonmember')!==false){
          $statusMsg = 'NO - please contact <a href="mailto:support@heuristnetwork.org">support@heuristnetwork.org</a> if incorrect';
        }else{
          $statusMsg = 'YES, member - ' . implode(' & ', $statusMsg);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>HeuristNetwork Association — Membership Check</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    :root {
        --bg: #d4dbea;
        --panel:#ffffff;
        --text:#000000;
        --muted:#444;
        --accent:#3b82f6;
    }
    body { margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif; background:var(--bg); color:var(--text); }
    .wrap { max-width:780px; margin:40px auto; padding:0 16px; }
    .card { background:var(--panel); border-radius:10px; padding:20px; box-shadow:0 4px 10px rgba(0,0,0,.1); }
    h1 { font-size:22px; margin:0 0 8px; }
    .status { margin:0 0 18px; padding:12px 14px; border-radius:6px; background:#eef; }
    form { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    label { font-size:13px; color:var(--muted); display:block; margin-bottom:6px; }
    input, select, button {
        width:100%; box-sizing:border-box; padding:10px 12px;
        border-radius:6px; border:1px solid #ccc; background:#fff; color:var(--text);
    }
    .full { grid-column:1 / -1; }
    .hr { grid-column:1 / -1; height:1px; background:#ccc; margin:6px 0 4px; }
    .actions { display:flex; gap:10px; justify-content:flex-end; }
    button { cursor:pointer; font-weight:600; }
    button.primary { background:var(--accent); color:white; border:none; }
    button:disabled { opacity:0.6; cursor:not-allowed; }
</style>
<script>
function toggleCustomServer(selectEl){
    var isOther = selectEl.value === 'other';
    var custom = document.getElementById('server_custom');
    custom.disabled = !isOther;
    custom.style.opacity = isOther ? '1' : '0.6';
    if (!isOther) custom.value = '';
}
function disableButtonsOnSubmit(form){
    var checkBtn = document.getElementById('checkBtn');
    var clearBtn = document.getElementById('clearBtn');
    checkBtn.disabled = true;
    clearBtn.disabled = true;
    checkBtn.textContent = 'Checking...';
    return true; // allow form submit
}
function clearForm(){
  var form = document.forms[0];

  // Manually clear text inputs
  ['email','firstName','lastName','server_custom','db'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) el.value = '';
  });

  // Reset server selector to default preset
  var sel = document.getElementById('server_sel');
  if (sel){
    sel.value = 'huma-num.fr';
    toggleCustomServer(sel); // will disable and fade the custom field
  }

  // Optionally clear any server response/status message
  var status = document.querySelector('.status');
  if (status){ status.textContent = ''; }

  // Re-enable buttons and reset CHECK label if they were disabled
  var checkBtn = document.getElementById('checkBtn');
  var clearBtn = document.getElementById('clearBtn');
  if (checkBtn){ checkBtn.disabled = false; checkBtn.textContent = 'CHECK'; }
  if (clearBtn){ clearBtn.disabled = false; }
}
</script>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>HeuristNetwork Association — Membership Check</h1>

    <form method="post" action="" onsubmit="return disableButtonsOnSubmit(this);">
      <div class="full hr"></div>

      <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" placeholder=""
               value="<?php echo htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="full hr"></div>

      <div>  
        <label for="firstName"><b>OR</b><br>First name</label>
        <input id="firstName" name="firstName" type="text" placeholder=""
               value="<?php echo htmlspecialchars($values['firstName'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div> 
        <br>
        <label for="lastName">Last name</label>
        <input id="lastName" name="lastName" type="text" placeholder=""
               value="<?php echo htmlspecialchars($values['lastName'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="full hr"></div>

      <div>
        <label for="server_sel"><b>OR</b><br>Server name</label>
        <select id="server_sel" name="server_sel" onchange="toggleCustomServer(this)">
          <option value="heurist.huma-num.fr" <?php echo $values['server_sel']==='huma-num.fr'?'selected':''; ?>>heurist.huma-num.fr</option>
          <option value="heuristref.net" <?php echo $values['server_sel']==='heuristref.net'?'selected':''; ?>>heuristref.net</option>
          <option value="other" <?php echo $values['server_sel']==='other'?'selected':''; ?>>Other (specify on the right)</option>
        </select>
      </div>

      <div>
        <br>
        <label for="server_custom">Other server (name only, omit https://)</label>
        <input id="server_custom" name="server_custom" type="text" placeholder=""
               value="<?php echo htmlspecialchars($values['server_custom'], ENT_QUOTES, 'UTF-8'); ?>"
               <?php echo ($values['server_sel']==='other')?'':'disabled style="opacity:.6"'; ?>>
      </div>

      <div class="full">
        <label for="db">Database name</label>
        <input id="db" name="db" type="text" placeholder=""
               value="<?php echo htmlspecialchars($values['db'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="full hr"></div>

      <div class="full actions">
        <button type="submit" class="primary" id="checkBtn">CHECK</button>
        <button type="button" id="clearBtn" onclick="clearForm()">Reset Form</button>
      </div>
    </form>
    
<!-- Display the result -->
   <?php if ($statusMsg !== ''): ?>
      <div class="status">
        <?php echo $statusMsg; ?>
      </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
