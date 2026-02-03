<?php
// -------------------------------------------------
// Redirect fallback target
// -------------------------------------------------
$base = defined('HEURIST_BASE_URL')
    ? rtrim(HEURIST_BASE_URL, '/')
    : '';

// -------------------------------------------------
// Visibility conditions
// -------------------------------------------------

$allowedDomains = [
    'heuristref.net',
    'intersect.org.au',
    'heuristau.net',
    'heurist.huma-num.fr',
    'heurist.eu',
    'heuristeu.net'
    //,'127.0.0.1'
];

// If db not specified then redirect
if (!isset($_REQUEST['db'])) {
    header('Location: ' . $base . '/startup/?list=1');
    exit;
}

// Validate host
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$host = preg_replace('/:\d+$/', '', $host);

if (!in_array($host, $allowedDomains, true)) {
    header('Location: ' . $base . '/startup/?list=1');
    exit;
}

// -------------------------------------------------
// OK to show Database not found page
// -------------------------------------------------

$pathAndQuery = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>HEURIST — Database Not Found</title>
    <link rel="icon" href="<?php echo $base; ?>/hclient/assets/branding/favicon.ico">
    <link rel="shortcut icon" href="<?php echo $base; ?>/hclient/assets/branding/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo $base; ?>/hclient/assets/css/heurist.css">

    <style>
        body{
            font-size: 1.1em;
        }
        h2.dont-panic{
            color: green;
            font-style: italic;
            margin-top: 0;
        }
    </style>

</head>
<body>
    <div class="header"><div class="header-inner" style="max-width:800px">
            <div class="left">
                <img class="logo" src="<?php echo $base; ?>/hclient/assets/v6/h6logo_inv.png" alt="HEURIST">
                <a class="title" href="/"></a>
            </div>
            <div class="right"></div>
        </div>
    </div>
    <div class="container-full" style="max-width:800px">
        <div class="main" style="margin:12px 0">
            <div class="section-title"><div>Database not found <!-- <span class="badge fail">DB</span> --></div></div>
            <div class="section-sub">
                <h2 class="dont-panic">Don't Panic !</h2>
                If your database was/is on the Australian Heurist server (HeuristRef.net) up to mid Nov 2025,
                <br>you will now find it at <a id="auLink" href="https://heuristau.net<?php echo htmlspecialchars($pathAndQuery); ?>">HeuristAU.net</a>
                <br>(this simply points to the same server, nothing else has changed).<br>
                <br>
                If you do not find it there, please try <a id="humaLink" href="https://heurist.huma-num.fr<?php echo htmlspecialchars($pathAndQuery); ?>">Heurist.Huma-Num.fr</a><br>
                <br>
                If you are unable to find it, contact us at <a href="mailto:support@heuristnetwork.org">support@heuristnetwork.org</a>
                <br>(we have multiple backups of all servers and can restore them rapidly to any of these servers).

                <hr>

                <p>
                <a href="<?php echo $base; ?>/startup/?list=1">Go to startup page</a> Or try refreshing the page or checking the URL.
                </p>
            </div>

        </div>
    </div>
</body>
</html>
