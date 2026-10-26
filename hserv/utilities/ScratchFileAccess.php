<?php
/**
* ScratchFileAccess.php - protection for scratch import files.
*
* Session ownership and private download copies for scratch import files
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.3
*/
namespace hserv\utilities;

/** Session ownership and private download copies for scratch import files. */
class ScratchFileAccess
{
    private const SESSION_KEY = 'scratch_import_files';

    public static function register($system, string $filename): bool
    {
        if (!$system->hasAccess() || $system->getUserId() < 1 || basename($filename) !== $filename) {
            return false;
        }
        $path = $system->getSysDir('scratch').$filename;
        if (!is_file($path) || is_link($path)) { return false; }
        $hash = hash_file('sha256', $path);
        if ($hash === false) { return false; }
        return $system->session()->updateDb($system->dbnameFull(), function (&$session) use ($system, $filename, $hash) {
            $session[self::SESSION_KEY][$filename] = ['user'=>$system->getUserId(), 'hash'=>$hash];
            return true;
        }) === true;
    }

    /** Return a private stream; callers must close it after sending the response. */
    public static function downloadCopy($system, string $filename, ?string $encoding = null)
    {
        if ($system->getUserId() < 1 || !$system->hasAccess()) {
            throw new \RuntimeException('Authentication required', 401);
        }
        $files = $system->session()->getDbValue($system->dbnameFull(), self::SESSION_KEY, []);
        $owner = $files[$filename] ?? null;
        if (!is_array($owner) || ($owner['user'] ?? null) !== $system->getUserId()
            || basename($filename) !== $filename) {
            throw new \RuntimeException('Temporary file access denied', 403);
        }
        $path = $system->getSysDir('scratch').$filename;
        $root = realpath($system->getSysDir('scratch'));
        $resolved = realpath($path);
        if (!$root || !$resolved || dirname($resolved) !== $root || !is_file($resolved) || is_link($path)) {
            throw new \RuntimeException('Temporary file not found', 404);
        }
        $source = fopen($resolved, 'rb');
        $copy = tmpfile();
        if (!$source || !$copy) {
            if (is_resource($source)) { fclose($source); }
            if (is_resource($copy)) { fclose($copy); }
            throw new \RuntimeException('Cannot read temporary file', 500);
        }
        try {
            if (stream_copy_to_stream($source, $copy) === false) {
                throw new \RuntimeException('Cannot copy temporary file', 500);
            }
            rewind($copy);
            $hash = hash_init('sha256');
            hash_update_stream($hash, $copy);
            if (!hash_equals($owner['hash'], hash_final($hash))) {
                throw new \RuntimeException('Temporary file access denied', 403);
            }
            rewind($copy);
            if ($encoding && strcasecmp($encoding, 'UTF-8') !== 0) {
                try {
                    $content = mb_convert_encoding(stream_get_contents($copy), 'UTF-8', $encoding);
                } catch (\ValueError $error) {
                    throw new \RuntimeException('Invalid source encoding', 400);
                }
                rewind($copy);
                if (!ftruncate($copy, 0) || fwrite($copy, $content) !== strlen($content)) {
                    throw new \RuntimeException('Cannot convert temporary file', 500);
                }
                rewind($copy);
            }
            return $copy;
        } catch (\Throwable $error) {
            fclose($copy);
            throw $error;
        } finally {
            fclose($source);
        }
    }
}
