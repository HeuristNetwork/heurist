<?php
/**
* KmlExportWriter.php - KML export: one Placemark per geographic value
*
* Port of the list mode of export/xml/kml.php: every geo value of an exported
* record becomes a Placemark with the record id and title, a TimeStamp/TimeSpan
* from the record's Date field or Start/End date pair, and the geometry (geoPHP
* WKT to KML, as the legacy script). With geo fields of the data source
* (ExportRequest::$geoFields, direct or linked paths) only those give geometry;
* with its time fields (ExportRequest::$timeFields) the time span covers their values. New: the requested columns are written as
* ExtendedData. Records without geo values are not written.
* The file is written with XMLWriter (XmlStreamWriter), flushed after every batch;
* only the geometry from geoPHP is inserted as ready-made KML.
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
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Export\ExportRequest;
use Heurist\Utilities\Temporal;

/** Writes one KML document. */
final class KmlExportWriter implements ExportWriterInterface
{
    private ExportRequest $request;
    private ExportColumns $columns;
    private DefinitionLookup $definitions;
    private string $path;
    /** @var array{date:int,start:int,end:int} Field ids of the time span. */
    private array $dateFields;
    private ?XmlStreamWriter $xml = null;
    private int $placemarks = 0;
    /** @var array<int,array> Expanded columns per record type. */
    private array $expanded = array();

    /**
     * @param ExportRequest $request Columns.
     * @param ExportColumns $columns Column expansion and cells.
     * @param DefinitionLookup $definitions Field types.
     * @param string $workDir Folder for the file.
     * @param array $dateFields date (DT_DATE), start (DT_START_DATE), end (DT_END_DATE).
     */
    public function __construct(ExportRequest $request, ExportColumns $columns, DefinitionLookup $definitions,
        string $workDir, array $dateFields)
    {
        $this->request = $request;
        $this->columns = $columns;
        $this->definitions = $definitions;
        $this->path = rtrim($workDir, '/\\').'/export.kml';
        $this->dateFields = array(
            'date' => intval($dateFields['date'] ?? 0),
            'start' => intval($dateFields['start'] ?? 0),
            'end' => intval($dateFields['end'] ?? 0)
        );
    }

    /** @inheritDoc */
    public function fieldCodes(): array
    {
        $codes = array('_all');
        foreach($this->request->columns as $list){
            foreach(ExportColumns::codes($list) as $code){ $codes[] = $code; }
        }
        foreach(array_merge($this->request->geoFields, $this->request->timeFields) as $code){ $codes[] = $code; }
        return array_values(array_unique($codes));
    }

    /** @inheritDoc */
    public function begin(array $meta): void
    {
        $title = trim((string)($meta['title'] ?? ''));
        $this->xml = new XmlStreamWriter($this->path);
        $this->xml->start('kml', array(
            'xmlns' => 'http://www.opengis.net/kml/2.2',
            'xmlns:gx' => 'http://www.google.com/kml/ext/2.2',
            'xmlns:kml' => 'http://www.opengis.net/kml/2.2',
            'xmlns:atom' => 'http://www.w3.org/2005/Atom'
        ));
        $this->xml->start('Document');
        $this->xml->element('name', array(), $title !== '' ? $title : 'Exported from Heurist');
    }

    /** @inheritDoc */
    public function write(array $records): void
    {
        foreach($records as $record){
            $geoValues = array();
            foreach($record['details'] ?? array() as $fieldId => $values){
                if(!empty($this->request->geoFields)){
                    if(!in_array((string)$fieldId, $this->request->geoFields, true)){ continue; }
                }elseif(!ctype_digit((string)$fieldId) || $this->definitions->fieldType(intval($fieldId)) !== 'geo'){
                    continue;
                }
                foreach($values as $value){
                    $wkt = is_array($value) ? (string)($value['geo']['wkt'] ?? '') : '';
                    if($wkt !== ''){ $geoValues[] = $wkt; }
                }
            }
            if(empty($geoValues)){ continue; }
            $time = $this->timeRange($record);
            foreach($geoValues as $wkt){
                $kml = self::kmlGeometry($wkt);
                if($kml === ''){ continue; }
                $this->placemark($record, $time, $kml);
                $this->placemarks++;
            }
        }
        $this->xml->flush();
    }

    /** @inheritDoc */
    public function end(): void
    {
        if($this->xml === null){ return; }
        $this->xml->close(); // Document, kml
        $this->xml = null;
    }

    /** @inheritDoc */
    public function files(): array
    {
        return array(array('path' => $this->path, 'name' => 'export.kml'));
    }

    /** Number of Placemarks written. */
    public function placemarkCount(): int
    {
        return $this->placemarks;
    }

    /**
     * One Placemark: time, id, name, link, ExtendedData and the geometry.
     *
     * @param array $record Record object.
     * @param array{0:string,1:string}|null $time Earliest and latest ISO date.
     * @param string $kml Geometry as KML (geoPHP).
     */
    private function placemark(array $record, ?array $time, string $kml): void
    {
        $this->xml->start('Placemark');
        if($time !== null){
            if($time[0] === $time[1]){
                $this->xml->start('TimeStamp');
                $this->xml->element('when', array(), $time[0]);
            }else{
                $this->xml->start('TimeSpan');
                $this->xml->element('begin', array(), $time[0]);
                $this->xml->element('end', array(), $time[1]);
            }
            $this->xml->end();
        }
        $this->xml->element('id', array(), (string)$record['rec_ID']);
        $this->xml->element('name', array(), (string)($record['rec_Title'] ?? ''));
        if(!empty($record['rec_URL'])){
            $this->xml->element('description', array(),
                '<a href="'.htmlspecialchars((string)$record['rec_URL'], ENT_QUOTES, 'UTF-8').'">link</a>');
        }
        $this->extendedData($record);
        $this->xml->raw($kml);
        $this->xml->end(); // Placemark
    }

    /**
     * Earliest and latest date of the record (kml.php: Date, else Start/End).
     *
     * @return array{0:string,1:string}|null
     */
    private function timeRange(array $record): ?array
    {
        if(!empty($this->request->timeFields)){
            return $this->timeFieldsRange($record);
        }
        $first = static function(array $record, int $fieldId){
            if($fieldId < 1){ return null; }
            $values = $record['details'][(string)$fieldId] ?? $record['details'][$fieldId] ?? array();
            $value = reset($values);
            return $value === false || is_array($value) ? null : (string)$value;
        };
        $date = $first($record, $this->dateFields['date']);
        $start = $first($record, $this->dateFields['start']);
        $end = $first($record, $this->dateFields['end']);
        try{
            if($start !== null || $end !== null){
                if($start === null){ $start = $end; }
                $temporal = $end !== null && $end !== $start ? Temporal::mergeTemporals($start, $end) : new Temporal($start);
            }elseif($date !== null){
                $temporal = new Temporal($date);
            }else{
                return null;
            }
            if(!($temporal instanceof Temporal) || !$temporal->isValid()){ return null; }
            $range = $temporal->calcMinMax();
            if(!is_array($range) || (string)$range[0] === ''){ return null; }
            return array((string)$range[0], (string)($range[1] ?? $range[0]));
        }catch(\Throwable $error){
            return null;
        }
    }

    /**
     * Earliest and latest date of the values of the data source time fields.
     *
     * @return array{0:string,1:string}|null
     */
    private function timeFieldsRange(array $record): ?array
    {
        $min = null;
        $max = null;
        foreach($this->request->timeFields as $code){
            foreach($record['details'][$code] ?? array() as $value){
                $text = is_array($value) ? ($value['value'] ?? '') : $value;
                if(!is_string($text) || $text === ''){ continue; }
                try{
                    $temporal = new Temporal($text);
                    if(!$temporal->isValid()){ continue; }
                    $range = $temporal->calcMinMax();
                }catch(\Throwable $error){
                    continue;
                }
                if(!is_array($range) || (string)$range[0] === ''){ continue; }
                // ISO dates (also negative years with the same length) compare as text within one era
                if($min === null || strcmp((string)$range[0], $min) < 0){ $min = (string)$range[0]; }
                $end = (string)($range[1] ?? $range[0]);
                if($max === null || strcmp($end, $max) > 0){ $max = $end; }
            }
        }
        return $min === null ? null : array($min, $max ?? $min);
    }

    private function extendedData(array $record): void
    {
        $rectypeId = intval($record['rec_RecTypeID'] ?? 0);
        $columns = $this->request->columnsFor($rectypeId);
        if(empty($columns)){ return; }
        if(!isset($this->expanded[$rectypeId])){
            $this->expanded[$rectypeId] = $this->columns->expand($rectypeId, $columns, false);
        }
        $cells = $this->columns->cells($record, $this->expanded[$rectypeId], $this->request->csv['mvsep']);
        $this->xml->start('ExtendedData');
        foreach($this->expanded[$rectypeId] as $index => $column){
            $this->xml->start('Data', array('name' => $column['header']));
            $this->xml->element('value', array(), $cells[$index]);
            $this->xml->end();
        }
        $this->xml->end(); // ExtendedData
    }

    private static function kmlGeometry(string $wkt): string
    {
        try{
            $geometry = \geoPHP::load($wkt, 'wkt');
            if(!$geometry || $geometry->isEmpty()){ return ''; }
            return (string)$geometry->out('kml');
        }catch(\Throwable $error){
            return '';
        }
    }
}
