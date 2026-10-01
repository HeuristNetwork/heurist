<?php
/**
* QueryTrace.php - Request-scoped SQL timing collector for debug responses
*
* Collects every SQL statement executed through AbstractDatabase while the
* request asked for debug output (debug=1|2), groups the timings into named
* phases (ids, count, details, ...) and keeps the main search query. The
* collected data is appended by ApiResponse as a "debug" section.
*
* @project     Heurist academic knowledge management system
* @package     Database
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Database;

/**
 * One trace per PHP request. Static access keeps services free of an extra
 * constructor dependency; when disabled every call is a cheap no-op.
 */
final class QueryTrace
{
    /** Maximum number of individual statements returned in the debug section. */
    private const MAX_STATEMENTS = 50;

    /** Maximum SQL text length per listed statement. */
    private const MAX_SQL_LENGTH = 4000;

    private static ?QueryTrace $current = null;

    private int $level = 0;
    private bool $allowSql = false;
    private string $requestId = '';
    private float $startedAt;
    private float $bootMs = 0.0;
    private array $phaseStack = array();
    private array $phases = array();
    private array $statements = array();
    private int $statementCount = 0;
    private float $sqlMs = 0.0;
    private ?array $main = null;
    private ?string $path = null;
    private array $notes = array();

    /** @var callable|null Runs EXPLAIN for debug=2: fn(string $sql, array $values): array */
    private $explainer = null;

    private function __construct()
    {
        $this->startedAt = isset($_SERVER['REQUEST_TIME_FLOAT'])
            ? floatval($_SERVER['REQUEST_TIME_FLOAT']) : microtime(true);
    }

    /** Return the trace of the current request. */
    public static function current(): self
    {
        if(self::$current === null){ self::$current = new self(); }
        return self::$current;
    }

    /**
     * Enable tracing from request parameters (debug=1 timings, debug=2 adds EXPLAIN).
     * The URL parameter also counts for POST requests with a JSON body.
     */
    public static function enableFromRequest(array $params): void
    {
        $level = intval($params['debug'] ?? ($_GET['debug'] ?? 0));
        if($level <= 0){ return; }
        $trace = self::current();
        $trace->level = min(2, $level);
        $requestId = (string)($_SERVER['HTTP_X_HEURIST_REQUEST_ID'] ?? ($params['rid'] ?? ''));
        $trace->requestId = substr(preg_replace('/[^A-Za-z0-9_\-]/', '', $requestId), 0, 64);
    }

    /** Mark the end of the legacy bootstrap (System init) before srv/ services start. */
    public static function markBoot(): void
    {
        if(self::enabled() && self::$current->bootMs === 0.0){
            self::$current->bootMs = (microtime(true) - self::$current->startedAt) * 1000;
        }
    }

    /** True when debug output was requested. */
    public static function enabled(): bool
    {
        return self::$current !== null && self::$current->level > 0;
    }

    /** Requested debug level: 0 off, 1 timings, 2 timings and EXPLAIN. */
    public static function level(): int
    {
        return self::$current === null ? 0 : self::$current->level;
    }

    /** SQL text and EXPLAIN are returned only to logged-in users. */
    public static function allowSql(bool $allow): void
    {
        if(self::enabled()){ self::$current->allowSql = $allow; }
    }

    /** Register the EXPLAIN runner used for debug=2 (set by ServiceFactory). */
    public static function setExplainer(callable $explainer): void
    {
        if(self::enabled()){ self::$current->explainer = $explainer; }
    }

    /** Start a named phase; nested phases are reported with a dotted name. */
    public static function begin(string $name): void
    {
        if(!self::enabled()){ return; }
        $trace = self::$current;
        $parent = end($trace->phaseStack);
        $trace->phaseStack[] = array(
            'name'=>$parent ? $parent['name'].'.'.$name : $name,
            'start'=>hrtime(true),
            'statements'=>$trace->statementCount
        );
    }

    /** Finish the innermost phase. Repeated phases are summed. */
    public static function end(?int $rows = null): void
    {
        if(!self::enabled() || empty(self::$current->phaseStack)){ return; }
        $trace = self::$current;
        $phase = array_pop($trace->phaseStack);
        $ms = (hrtime(true) - $phase['start']) / 1e6;
        $name = $phase['name'];
        if(!isset($trace->phases[$name])){
            $trace->phases[$name] = array('name'=>$name, 'ms'=>0.0, 'calls'=>0, 'statements'=>0);
        }
        $trace->phases[$name]['ms'] += $ms;
        $trace->phases[$name]['calls']++;
        $trace->phases[$name]['statements'] += $trace->statementCount - $phase['statements'];
        if($rows !== null){
            $trace->phases[$name]['rows'] = ($trace->phases[$name]['rows'] ?? 0) + $rows;
        }
    }

    /** Record one executed statement (called by AbstractDatabase). */
    public static function record(string $sql, array $parameters, float $ms, ?int $rows): void
    {
        if(!self::enabled()){ return; }
        $trace = self::$current;
        $trace->statementCount++;
        $trace->sqlMs += $ms;
        if(count($trace->statements) < self::MAX_STATEMENTS){
            $phase = end($trace->phaseStack);
            $trace->statements[] = array(
                'phase'=>$phase ? $phase['name'] : '',
                'ms'=>round($ms, 2),
                'rows'=>$rows,
                'sql'=>self::shorten($sql),
                'params'=>count($parameters)
            );
        }
        if($trace->main !== null && $trace->main['sql'] === $sql && !isset($trace->main['ms'])){
            $trace->main['ms'] = $ms;
            $trace->main['rows'] = $rows;
        }
    }

    /**
     * Mark the main search query of the request. Only the first call counts,
     * so nested searches (expansion, linked fields) do not replace it.
     */
    public static function setMain(string $sql, array $values, string $kind = 'ids'): void
    {
        if(!self::enabled() || self::$current->main !== null){ return; }
        self::$current->main = array('sql'=>$sql, 'values'=>array_values($values), 'kind'=>$kind);
    }

    /** Record which execution path the main search used (sql|fallback). */
    public static function setPath(string $path): void
    {
        if(self::enabled() && self::$current->path === null){ self::$current->path = $path; }
    }

    /** Add a free-form diagnostic note (e.g. a limit that was applied). */
    public static function note(string $note): void
    {
        if(self::enabled() && count(self::$current->notes) < 20){ self::$current->notes[] = $note; }
    }

    /** Build the debug section; for debug=2 it also runs EXPLAIN on the main query. */
    public static function toArray(): array
    {
        if(!self::enabled()){ return array(); }
        $trace = self::$current;
        $explain = $trace->explainer;
        $debug = array(
            'requestId'=>$trace->requestId,
            'path'=>$trace->path,
            'mainMs'=>isset($trace->main['ms']) ? round($trace->main['ms'], 2) : null,
            'mainRows'=>$trace->main['rows'] ?? null,
            'sqlMs'=>round($trace->sqlMs, 2),
            'statements'=>$trace->statementCount,
            'totalMs'=>round((microtime(true) - $trace->startedAt) * 1000, 2),
            'bootMs'=>round($trace->bootMs, 2),
            'peakMemoryMb'=>round(memory_get_peak_usage(true) / 1048576, 1),
            'phases'=>array_map(static function(array $phase): array {
                $phase['ms'] = round($phase['ms'], 2);
                return $phase;
            }, array_values($trace->phases))
        );
        foreach(array('count'=>'countMs', 'details'=>'detailsMs') as $phase=>$key){
            if(isset($trace->phases[$phase])){ $debug[$key] = round($trace->phases[$phase]['ms'], 2); }
        }
        if($trace->main !== null){
            $debug['mainKind'] = $trace->main['kind'];
            $debug['shape'] = self::shape($trace->main['sql']);
        }
        $limit = StatementLimit::seconds();
        if($limit > 0){ $debug['statementLimitSec'] = $limit; }
        if(!empty($trace->notes)){ $debug['notes'] = $trace->notes; }
        if($trace->allowSql){
            if($trace->main !== null){
                $debug['mainSql'] = $trace->main['sql'];
                $debug['mainParams'] = $trace->main['values'];
            }
            $debug['sql'] = $trace->statements;
            if($trace->level >= 2 && $trace->main !== null && $explain !== null){
                try{
                    $debug['explain'] = $explain('EXPLAIN '.$trace->main['sql'], $trace->main['values']);
                }catch(\Throwable $exception){
                    $debug['explain'] = 'EXPLAIN failed: '.$exception->getMessage();
                }
            }
        }
        return $debug;
    }

    /** Count structural parts of the SQL that drive its cost. */
    private static function shape(string $sql): array
    {
        $upper = strtoupper($sql);
        return array(
            'length'=>strlen($sql),
            'exists'=>substr_count($upper, 'EXISTS ('),
            'joins'=>substr_count($upper, ' JOIN '),
            'recLinks'=>substr_count($sql, 'recLinks'),
            'or'=>substr_count($upper, ' OR ')
        );
    }

    private static function shorten(string $sql): string
    {
        return strlen($sql) > self::MAX_SQL_LENGTH ? substr($sql, 0, self::MAX_SQL_LENGTH).' …' : $sql;
    }
}
