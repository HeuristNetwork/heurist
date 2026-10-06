<?php
/**
* ExportPlanner.php - Which records an export writes, and the links between them
*
* The complete ordered id list is built before anything is written:
*   1. the result of the data source query (or the selection), filtered by record
*      type, cut to the request limit; read in pages of PAGE ids;
*   2. the records the expansion rules reach from it (ExpansionEngine), each
*      added once, after the result, in the order the levels reach them.
* Every record is therefore written exactly once. The whole list is refused when
* it is larger than the database maximum (ExportSettings::maxRecords).
*
* @project     Heurist academic knowledge management system
* @package     Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Export;

use DomainException;
use Heurist\Database\DatabaseInterface;
use Heurist\Records\Expansion\ExpansionEngine;
use Heurist\Records\Expansion\ExpansionRequest;
use Heurist\Records\Query\Compiler\QueryBuilder;
use Heurist\Records\Query\QueryExecutor;
use Heurist\Records\Query\RecordSearchService;
use Heurist\Records\Query\SearchRequest;
use Heurist\Runtime\RuntimeContext;
use InvalidArgumentException;

/** Resolves export ids and record links. */
final class ExportPlanner
{
    /** Ids read per search request. */
    private const PAGE = 100000;

    /** Ids per recLinks query. */
    private const LINK_BATCH = 1000;

    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private ExportSettings $settings;
    /** @var callable fn(array $ids): array visible ids, same order */
    private $visibleIds;

    /**
     * @param DatabaseInterface $database Database of the export.
     * @param RuntimeContext $runtime Current user (search access rules).
     * @param ExportSettings $settings Limits.
     * @param callable $visibleIds Filters ids by the record access rules of the user.
     */
    public function __construct(DatabaseInterface $database, RuntimeContext $runtime, ExportSettings $settings, callable $visibleIds)
    {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->settings = $settings;
        $this->visibleIds = $visibleIds;
    }

    /** Export limits. */
    public function settings(): ExportSettings
    {
        return $this->settings;
    }

    /**
     * Ids of the export: the result first, then the expanded records.
     *
     * @param ExportRequest $request Export parameters.
     * @param callable|null $check Called between steps (Stop and time limit).
     * @return array{ids:int[], seedCount:int}
     */
    public function resolve(ExportRequest $request, ?callable $check = null): array
    {
        $seeds = $this->searchIds($request);
        if($check !== null){ call_user_func($check); }
        $ids = $seeds;
        if($request->rules !== null && !empty($seeds)){
            $search = new RecordSearchService($this->database, $this->runtime, new QueryBuilder($this->database));
            $engine = new ExpansionEngine(new QueryExecutor($this->database), $search);
            $expansion = $engine->expand(new ExpansionRequest($seeds, $request->rules))->toArray();
            $seen = array_fill_keys($seeds, true);
            foreach($expansion['records'] as $record){
                $id = intval($record['rec_ID']);
                if($id > 0 && !isset($seen[$id])){
                    $seen[$id] = true;
                    $ids[] = $id;
                }
            }
            if($check !== null){ call_user_func($check); }
        }
        $cap = $request->recordCap();
        if($cap > 0 && count($ids) > $cap){
            // Gephi: the result first, then expanded records up to the cap
            $ids = array_slice($ids, 0, $cap);
        }
        $max = $this->settings->maxRecords();
        if(count($ids) > $max){
            throw new DomainException('The export has '.count($ids).' records (expanded records included); an export can have at most '
                .$max.' (database setting Export: maxRecords)');
        }
        return array('ids' => $ids, 'seedCount' => count($seeds));
    }

    /**
     * Pointer and relationship links whose both ends are in the set.
     *
     * @param int[] $ids Exported ids.
     * @return array<int,array{source:int,target:int,field:int,relType:int,relation:int}>
     */
    public function linksAmong(array $ids): array
    {
        $set = array_fill_keys($ids, true);
        $links = array();
        foreach(array_chunk($ids, self::LINK_BATCH) as $chunk){
            $rows = $this->database->fetchRows(
                'SELECT rl_SourceID,rl_TargetID,rl_DetailTypeID,rl_RelationTypeID,rl_RelationID FROM recLinks '
                .'WHERE rl_SourceID IN ('.implode(',', array_fill(0, count($chunk), '?')).') ORDER BY rl_ID',
                $chunk
            );
            foreach($rows as $row){
                $source = intval($row[0]);
                $target = intval($row[1]);
                if(!isset($set[$target]) || $source === $target){ continue; }
                $links[] = array(
                    'source' => $source,
                    'target' => $target,
                    'field' => intval($row[2]),
                    'relType' => intval($row[3]),
                    'relation' => intval($row[4])
                );
            }
        }
        // relationship records the user may not view are dropped with their link
        $relations = array();
        foreach($links as $link){
            if($link['relation'] > 0){ $relations[] = $link['relation']; }
        }
        if(!empty($relations)){
            $visible = array_fill_keys(call_user_func($this->visibleIds, $relations), true);
            $links = array_values(array_filter($links, static function(array $link) use ($visible): bool {
                return $link['relation'] < 1 || isset($visible[$link['relation']]);
            }));
        }
        return $links;
    }

    private function searchIds(ExportRequest $request): array
    {
        $builder = new QueryBuilder($this->database);
        if(!empty($request->ids)){
            $query = array(array('ids' => implode(',', $request->ids)));
        }else{
            $query = $request->query;
            if(is_string($query)){
                $decoded = json_decode($query, true);
                $query = is_array($decoded) ? $decoded : $query;
            }
            $query = $builder->normalize($query);
            if(!is_array($query)){
                throw new InvalidArgumentException('Invalid query');
            }
        }
        if(!empty($request->rectypes)){
            $query[] = array('t' => implode(',', $request->rectypes));
        }
        $service = new RecordSearchService($this->database, $this->runtime, $builder);
        $max = $this->settings->maxRecords();
        $wanted = $request->limit > 0 ? min($request->limit, $max + 1) : $max + 1;
        $ids = array();
        do{
            $page = min(self::PAGE, $wanted - count($ids));
            $result = $service->search(new SearchRequest($query, array(
                'limit' => $page,
                'offset' => count($ids),
                'total' => false
            )));
            $ids = array_merge($ids, array_map('intval', $result->ids));
        }while(count($result->ids) === $page && count($ids) < $wanted);
        if(count($ids) > $max){
            throw new DomainException('The query finds more than '.$max.' records; an export can have at most '
                .$max.' (database setting Export: maxRecords). Set a record limit or narrow the query.');
        }
        return $ids;
    }
}
