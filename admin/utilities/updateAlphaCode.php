<?php
/**
* updateAlphaCode.php 
*
* Runs the alpha code-only update script from the Heurist reference server.
* Access is already restricted by initPageMin.php via ADMIN_PWD_REQUIRED and MANAGER_REQUIRED.
*
* @project     Heurist academic knowledge management system
* @package Admin 
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/
define('ADMIN_PWD_REQUIRED', 1);
define('MANAGER_REQUIRED', 1);
define('PDIR', '../../');//need for proper path to js and css

global $version;
global $sysadmin_pwd;

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

$heurist_base_dir = '/var/www/html/HEURIST';

$status = null;
$message = '';
$last_output = '';
$db = $_REQUEST['db'] ?? '';
$has_executed = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_update']));

function hsc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function get_alpha_codebase_from_version($version){
    $version = trim((string)$version);

    if(!preg_match('/^(\d+)(?:\.|$)/', $version, $matches)){
        return null;
    }

    return 'h'.$matches[1].'-alpha';
}

function read_log_tail($file, $lines = 80){
    if(!is_readable($file)){
        return '';
    }

    $data = @file($file, FILE_IGNORE_NEW_LINES);
    if(!is_array($data)){
        return '';
    }

    return implode("\n", array_slice($data, -$lines));
}

function get_current_host(){
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $host = strtolower(trim($host));

    // Strip port if present, for example heuristref.net:443
    if(strpos($host, ':') !== false){
        $host = preg_replace('/:\\d+$/', '', $host);
    }

    return $host;
}

$current_host = get_current_host();
$is_heurist_ref = ($current_host === 'heuristref.net'); // || str_ends_with($current_host, 'heuristref.net')

if($is_heurist_ref){
    $status = 'error';
    $message = 'This is meaningless, you are on HeuristRef.net';
}

$alpha_codebase = get_alpha_codebase_from_version($version ?? '');

$target_dir = $alpha_codebase !== null ? $heurist_base_dir.'/'.$alpha_codebase : '';
$log_file = $alpha_codebase !== null ? $heurist_base_dir.'/'.$alpha_codebase.'_install.log' : $heurist_base_dir.'/alpha_install.log';
$lock_file = $alpha_codebase !== null ? '/tmp/heurist_update_'.$alpha_codebase.'_code.lock' : '/tmp/heurist_update_alpha_code.lock';

if($alpha_codebase === null){
    $status = 'error';
    $message = 'Unable to determine alpha codebase from current Heurist version: '.($version ?? '(not set)');
}

if($has_executed && $status === null){

    if(!is_dir($target_dir)){
        $status = 'error';
        $message = 'Target alpha folder does not exist: '.$target_dir.'. Update was not started.';
        $last_output = read_log_tail($log_file);
    }else{

        $lock_handle = @fopen($lock_file, 'c');

        if(!$lock_handle){
            $status = 'error';
            $message = 'Unable to create/open lock file: '.$lock_file;
        }elseif(!flock($lock_handle, LOCK_EX | LOCK_NB)){
            $status = 'error';
            $message = 'Another alpha code update appears to be running. Please wait for it to finish.';
        }else{

            @set_time_limit(0);
            @ignore_user_abort(true);

            $started = date('Y-m-d H:i:s');

            if(is_file($log_file) && filesize($log_file) > 51200){
                @file_put_contents(
                    $log_file,
                    "================ updateAlphaCode.php log reset {$started}; previous size exceeded 50 KB ================\n",
                    LOCK_EX
                );
            }
                        
            @file_put_contents(
                $log_file,
                "\n\n================ updateAlphaCode.php started {$started} for {$alpha_codebase} ================\n",
                FILE_APPEND | LOCK_EX
            );

            /*
             * Keep the command fixed apart from the server-side alpha codebase derived from $version.
             * pipefail makes PHP receive a failure if either curl or the update script fails.
             * The braces ensure both curl and bash output are appended to the same log.
             */
            $inner_command = sprintf(
                '{ curl -l https://heuristref.net/HEURIST/DISTRIBUTION/update_heurist.sh | bash -s %s dummy codeonly; } >> %s 2>&1',
                escapeshellarg($alpha_codebase),
                escapeshellarg($log_file)
            );

            $command = '/bin/bash -o pipefail -c ' . escapeshellarg($inner_command);

            exec($command, $output, $exit_code);

            $finished = date('Y-m-d H:i:s');

            @file_put_contents(
                $log_file,
                "================ updateAlphaCode.php finished {$finished}; exit code {$exit_code} ================\n",
                FILE_APPEND | LOCK_EX
            );

            if($exit_code === 0){
                $status = 'success';
                $message = 'Alpha code update completed successfully for '.$alpha_codebase.'.';
            }else{
                $status = 'error';
                $message = 'Alpha code update failed for '.$alpha_codebase.'. Exit code: '.$exit_code.'. Please check the log below.';
            }

            flock($lock_handle, LOCK_UN);
            fclose($lock_handle);
        }

        $last_output = read_log_tail($log_file);
    }

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Update Alpha Code</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            max-width: 860px;
            margin: 32px auto;
            padding: 0 16px;
            line-height: 1.45;
            color: #222;
        }
        .panel {
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 18px;
            background: #fafafa;
        }
        .status {
            margin: 18px 0;
            padding: 12px;
            border-radius: 4px;
        }
        .success {
            border: 1px solid #7ab97a;
            background: #edf8ed;
            color: #245724;
        }
        .error {
            border: 1px solid #d08a8a;
            background: #fff0f0;
            color: #8a1f1f;
        }
        button {
            padding: 8px 18px;
            font-size: 14px;
            cursor: pointer;
        }
        button:disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }
        pre {
            margin-top: 18px;
            padding: 12px;
            overflow: auto;
            max-height: 420px;
            background: #111;
            color: #eee;
            border-radius: 4px;
            white-space: pre-wrap;
        }
        .small {
            color: #666;
            font-size: 13px;
        }
        dl {
            display: grid;
            grid-template-columns: 170px 1fr;
            gap: 6px 12px;
        }
        dt {
            font-weight: bold;
        }
        dd {
            margin: 0;
        }
    </style>
</head>
<body>

<div class="panel">
    <h2>Update Alpha Code</h2>

    <p>
        This operation updates the alpha code on this server by downloading and running the
        Heurist update script from the reference server. It performs a <strong>code-only</strong> update.
    </p>

    <dl>
        <dt>Current version</dt>
        <dd><code><?php echo hsc($version ?? '(not set)'); ?></code></dd>

        <dt>Alpha codebase</dt>
        <dd><code><?php echo hsc($alpha_codebase ?? '(unable to determine)'); ?></code></dd>

        <dt>Target folder</dt>
        <dd><code><?php echo hsc($target_dir ?: '(unable to determine)'); ?></code></dd>

        <dt>Log file</dt>
        <dd><code><?php echo hsc($log_file); ?></code></dd>
    </dl>

    <p class="small">
        Before starting, this page checks that the target alpha folder exists. The update can take some time.
        Do not start it again while another update is running.
    </p>

    <?php if(!$has_executed && !$is_heurist_ref): ?>
        <form method="post">
            <input type="hidden" name="pwd" value="<?php echo hsc($sysadmin_pwd); ?>">
            <input type="hidden" name="db" value="<?php echo hsc($db); ?>">
            <input type="hidden" name="start_update" value="1">
            <button type="submit"
                    <?php echo $alpha_codebase === null ? 'disabled' : ''; ?>
                    onclick="this.disabled=true; this.form.submit();">
                Start update
            </button>
        </form>
    <?php endif; ?>

    <?php if($status !== null): ?>
        <div class="status <?php echo hsc($status); ?>">
            <?php echo hsc($message); ?>
        </div>
    <?php endif; ?>

    <?php if($last_output !== ''): ?>
        <h3>Latest log output</h3>
        <pre><?php echo hsc($last_output); ?></pre>
    <?php endif; ?>
</div>

</body>
</html>
