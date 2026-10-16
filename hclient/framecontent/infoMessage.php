<?php
if(!isset($globalMessage)){
    $globalMessage = '<p>Unknown error</p>';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>HEURIST — Message</title>

    <link rel="stylesheet" href="<?php echo HEURIST_BASE_URL;?>hclient/assets/css/heurist.css">
    <link rel="icon" href="<?php echo HEURIST_BASE_URL;?>favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo HEURIST_BASE_URL;?>favicon.ico" type="image/x-icon">

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
    <div class="header">
        <div class="header-inner" style="max-width:800px">
            <div class="left">
                <img class="logo" src="<?php echo HEURIST_BASE_URL;?>hclient/assets/v6/h6logo_inv.png" alt="HEURIST">
                <a class="title" href="/"></a>
            </div>
            <div class="right"></div>
        </div>
    </div>

    <div class="container-full" style="max-width:800px">
        <div class="main" style="margin:12px 0">

            <div class="section-title">
                <div>Information <!-- <span class="badge fail">MSG</span> --></div>
            </div>

            <div class="section-sub">

                <!--
                <h2 class="dont-panic">Don't Panic !</h2>
                -->
                <!-- Dynamic message -->
                <div class="message-content">
                    <?php echo $globalMessage; ?>
                </div>

                <hr>

                <!-- Optional fallback guidance (can be hidden if not needed) -->
                <div class="fallback">

                    <p>
                        If you are unable to resolve the issue, please contact us at
                        <a href="mailto:support@heuristnetwork.org">support@heuristnetwork.org</a>
                    </p>
                </div>
<!--
                <hr>

                <p>
                    <a href="/">Go to startup page</a>
                    or try refreshing the page or checking the URL.
                </p>
-->
            </div>
        </div>
    </div>
</body>
</html>