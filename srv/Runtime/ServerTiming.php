<?php
/**
* ServerTiming.php - Request phase timings for the Server-Timing response header
*
* Named marks taken during one request (legacy start-up, session, login, srv
* services) are sent as `Server-Timing: db;dur=3.1, session;dur=0.4, ...`, so the
* browser's network panel (Timing tab) shows where the server time goes. Each
* duration is the time since the previous mark, the first one since the start of
* the request. Cheap and always on; no request data is included.
*
* @project     Heurist academic knowledge management system
* @package     Runtime
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Runtime;

/** Collects the phase marks of the current request. */
final class ServerTiming
{
    /** @var array<int, array{0: string, 1: float}> Marks in order: name, microtime. */
    private static array $marks = array();

    /** Mark the end of a phase called `$name` (letters, digits, `_`, `-`). */
    public static function mark(string $name): void
    {
        self::$marks[] = array(preg_replace('/[^A-Za-z0-9_\-]/', '', $name), microtime(true));
    }

    /**
     * Send the header with all marks and a final `$last` phase (once; nothing when
     * the headers are already sent).
     */
    public static function send(string $last = 'app'): void
    {
        if(headers_sent()){ return; }
        $start = isset($_SERVER['REQUEST_TIME_FLOAT']) ? floatval($_SERVER['REQUEST_TIME_FLOAT']) : null;
        self::mark($last);
        $previous = $start ?? self::$marks[0][1];
        $parts = array();
        foreach(self::$marks as $mark){
            $parts[] = sprintf('%s;dur=%.1f', $mark[0], max(0, ($mark[1] - $previous) * 1000));
            $previous = $mark[1];
        }
        if($start !== null){
            $parts[] = sprintf('total;dur=%.1f', ($previous - $start) * 1000);
        }
        self::$marks = array();
        header('Server-Timing: '.implode(', ', $parts));
    }
}
