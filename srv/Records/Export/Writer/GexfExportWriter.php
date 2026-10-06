<?php
/**
* GexfExportWriter.php - Gephi export (GEXF 1.2)
*
* Port of hserv/records/export/ExportRecordsGEPHI.php with the same attribute
* layout (the Graph module's client GEXF uses it too):
*   node attributes  0 name, 1 image, 2 rectype, 3 count, 4 url, 5.. requested "*" columns
*   edge attributes  0 relation-id, 1 relation-name, 2 relation-image, 3 relation-count,
*                    4 relation-start, 5 relation-end
* Nodes are the exported records. Edges are the pointers and relationships whose
* both ends are exported (ExportPlanner::linksAmong), one per source-target pair;
* relation-id is the pointer field id or the relationship type term id. Nodes are
* written while records arrive; edges are written at the end, after the nodes.
* The file is written with XMLWriter (XmlStreamWriter), flushed after every batch.
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

namespace Heurist\Records\Export\Writer;

use Heurist\Records\Export\ExportColumns;
use Heurist\Records\Export\ExportDefinitions;
use Heurist\Records\Export\ExportRequest;
use Heurist\Records\Export\ValueFormatter;

/** Writes one GEXF document. */
final class GexfExportWriter implements ExportWriterInterface
{
    /** Node attributes 0..4 (id => [title, type]). */
    private const NODE_ATTRIBUTES = array(
        0 => array('name', 'string'), 1 => array('image', 'string'), 2 => array('rectype', 'string'),
        3 => array('count', 'float'), 4 => array('url', 'string')
    );

    /** Edge attributes 0..5 (id => [title, type]). */
    private const EDGE_ATTRIBUTES = array(
        0 => array('relation-id', 'float'), 1 => array('relation-name', 'string'), 2 => array('relation-image', 'string'),
        3 => array('relation-count', 'float'), 4 => array('relation-start', 'string'), 5 => array('relation-end', 'string')
    );

    private ExportRequest $request;
    private ExportColumns $columns;
    private ExportDefinitions $definitions;
    private ValueFormatter $formatter;
    private string $path;
    private ?XmlStreamWriter $xml = null;
    /** @var array Expanded "*" columns (node attributes 5..). */
    private array $expanded = array();
    private array $links = array();
    /** @var callable|null fn(int[] $relationIds): array<int,array{0:string,1:string}> start/end of relationships */
    private $relationDates = null;
    private int $edges = 0;

    /**
     * @param ExportRequest $request "*" columns.
     * @param ExportColumns $columns Column expansion and cells.
     * @param ExportDefinitions $definitions Field and term names.
     * @param ValueFormatter $formatter Icon and record URLs.
     * @param string $workDir Folder for the file.
     */
    public function __construct(ExportRequest $request, ExportColumns $columns, ExportDefinitions $definitions,
        ValueFormatter $formatter, string $workDir)
    {
        $this->request = $request;
        $this->columns = $columns;
        $this->definitions = $definitions;
        $this->formatter = $formatter;
        $this->path = rtrim($workDir, '/\\').'/export.gexf';
        $this->expanded = $columns->expand(0, $request->columns['*'] ?? array(), false);
    }

    /**
     * Links among the exported records and the reader of relationship dates.
     *
     * @param array $links ExportPlanner::linksAmong().
     * @param callable|null $relationDates fn(int[] $relationIds): array<int,array{0:string,1:string}>
     */
    public function setLinks(array $links, ?callable $relationDates = null): void
    {
        $this->links = $links;
        $this->relationDates = $relationDates;
    }

    /** @inheritDoc */
    public function fieldCodes(): array
    {
        return ExportColumns::codes($this->request->columns['*'] ?? array());
    }

    /** @inheritDoc */
    public function begin(array $meta): void
    {
        $this->xml = new XmlStreamWriter($this->path);
        $this->xml->start('gexf', array(
            'xmlns' => 'http://www.gexf.net/1.2draft',
            'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            'xsi:schemaLocation' => 'http://www.gexf.net/1.2draft http://www.gexf.net/1.2draft/gexf.xsd',
            'version' => '1.2'
        ));
        $this->xml->start('meta', array('lastmodifieddate' => date('Y-m-d')));
        $this->xml->element('creator', array(), 'HeuristNetwork.org');
        $this->xml->element('description', array(), 'Visualisation export '.(string)($meta['database'] ?? '')
            .(empty($meta['title']) ? '' : ': '.$meta['title']));
        $this->xml->end(); // meta
        $this->xml->start('graph', array('mode' => 'static', 'defaultedgetype' => 'directed'));

        $this->xml->start('attributes', array('class' => 'node'));
        foreach(self::NODE_ATTRIBUTES as $id => list($title, $type)){
            $this->xml->element('attribute', array('id' => (string)$id, 'title' => $title, 'type' => $type));
        }
        foreach($this->expanded as $index => $column){
            $this->xml->element('attribute', array('id' => (string)(5 + $index), 'title' => $column['header'], 'type' => 'string'));
        }
        $this->xml->end(); // attributes
        $this->xml->start('attributes', array('class' => 'edge'));
        foreach(self::EDGE_ATTRIBUTES as $id => list($title, $type)){
            $this->xml->element('attribute', array('id' => (string)$id, 'title' => $title, 'type' => $type));
        }
        $this->xml->end(); // attributes
        $this->xml->start('nodes');
    }

    /** @inheritDoc */
    public function write(array $records): void
    {
        foreach($records as $record){
            $recordId = intval($record['rec_ID']);
            $rectypeId = intval($record['rec_RecTypeID'] ?? 0);
            $name = (string)($record['rec_Title'] ?? '');
            $this->xml->start('node', array('id' => (string)$recordId, 'label' => $name));
            $this->xml->start('attvalues');
            $this->attvalue(0, $name);
            $this->attvalue(1, $this->formatter->iconUrl($rectypeId));
            $this->attvalue(2, (string)$rectypeId);
            $this->attvalue(3, '0');
            $this->attvalue(4, $this->formatter->recordUrl($recordId));
            if(!empty($this->expanded)){
                $cells = $this->columns->cells($record, $this->expanded, '|');
                foreach($cells as $index => $cell){
                    if($cell !== ''){ $this->attvalue(5 + $index, $cell); }
                }
            }
            $this->xml->end(); // attvalues
            $this->xml->end(); // node
        }
        $this->xml->flush();
    }

    /** @inheritDoc */
    public function end(): void
    {
        if($this->xml === null){ return; }
        $this->xml->end(); // nodes
        $this->xml->start('edges');
        $relationIds = array();
        foreach($this->links as $link){
            if($link['relation'] > 0){ $relationIds[] = $link['relation']; }
        }
        $dates = $this->relationDates !== null && !empty($relationIds)
            ? (array)call_user_func($this->relationDates, $relationIds) : array();
        $printed = array();
        foreach($this->links as $link){
            $pair = $link['source'].':'.$link['target'];
            if(isset($printed[$pair])){ continue; }
            $printed[$pair] = true;
            if($link['relType'] > 0){
                $relationId = $link['relType'];
                $relationName = $this->definitions->term($relationId)['label'] ?? 'Floating relationship';
            }elseif($link['field'] > 0){
                $relationId = $link['field'];
                $relationName = $this->definitions->field($relationId)['name'] ?? (string)$relationId;
            }else{
                $relationId = 0;
                $relationName = 'Floating relationship';
            }
            $this->edges++;
            $this->xml->start('edge', array(
                'id' => (string)$this->edges,
                'source' => (string)$link['source'],
                'target' => (string)$link['target'],
                'weight' => '1',
                'label' => $relationName
            ));
            $this->xml->start('attvalues');
            $this->attvalue(0, (string)$relationId);
            $this->attvalue(1, $relationName);
            $this->attvalue(3, '1');
            $start = (string)($dates[$link['relation']][0] ?? '');
            $end = (string)($dates[$link['relation']][1] ?? '');
            if($start !== ''){ $this->attvalue(4, $start); }
            if($end !== ''){ $this->attvalue(5, $end); }
            $this->xml->end(); // attvalues
            $this->xml->end(); // edge
            if($this->edges % 1000 === 0){ $this->xml->flush(); }
        }
        $this->xml->close(); // edges, graph, gexf
        $this->xml = null;
    }

    /** @inheritDoc */
    public function files(): array
    {
        return array(array('path' => $this->path, 'name' => 'export.gexf'));
    }

    /** Number of edges written. */
    public function edgeCount(): int
    {
        return $this->edges;
    }

    private function attvalue(int $for, string $value): void
    {
        $this->xml->element('attvalue', array('for' => (string)$for, 'value' => $value));
    }
}
