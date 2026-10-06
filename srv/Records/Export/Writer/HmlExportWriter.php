<?php
/**
* HmlExportWriter.php - XML export: flat HML that the HML import reads
*
* Port of the core of export/xml/flathml.php: the <hml> document with database,
* query, date stamp and counts; one <record> per exported record (id, type with
* concept code, url, notes, citeAs, title, added, modified, workgroup) with its
* details, reverse pointers and relationships among the exported records.
* Relationship records between exported records are exported as records too
* (ExportService adds them to the ids), so the HML import recreates them.
* Definitions are identified by concept codes (conceptID, termConceptID), as the
* default flathml output; with "names" (ExportRequest::$names) also by local ids and
* names (id, name, basename, termID, term labels), as flathml human_readable_names.
* importHeurist::hmlToJson reads both (field: id, else conceptID; term: termID, else
* the label). Dates are written as stored (simple dates, temporal JSON objects).
* The file is written with XMLWriter (XmlStreamWriter), flushed after every batch.
*
* Not ported: stubs, xinclude, file content (fc), rectype templates, HuNI mode, and the
* outdated "|VER=..|TYP=.." temporal format (<raw>, <temporal>, <year>... elements).
*
* @project     Heurist academic knowledge management system
* @package     Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @author      Kim Jackson
* @author      Stephen White
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Export\Writer;

use Heurist\Records\Export\ExportDefinitions;
use Heurist\Records\Export\ValueFormatter;

/** Writes one HML document. */
final class HmlExportWriter implements ExportWriterInterface
{
    private const GEO_TYPES = array('r' => 'bounds', 'c' => 'circle', 'pl' => 'polygon', 'l' => 'path', 'p' => 'point', 'm' => 'multi');

    private ExportDefinitions $definitions;
    private ValueFormatter $formatter;
    private string $path;
    private int $relationTypeField;
    /** Local ids and names besides the concept codes. */
    private bool $names;
    private ?XmlStreamWriter $xml = null;
    private int $written = 0;
    /** @var array<int,true> Records of the result (depth 0). */
    private array $seeds = array();
    /** @var array<int,true> Selected records. */
    private array $selected = array();
    /** @var array<int,array> Links by source record. */
    private array $outgoing = array();
    /** @var array<int,array> Links by target record. */
    private array $incoming = array();

    /**
     * @param ExportDefinitions $definitions Names and concept codes.
     * @param ValueFormatter $formatter File URLs, record URLs.
     * @param string $workDir Folder for the file.
     * @param int $relationTypeField DT_RELATION_TYPE (inverse term attributes).
     * @param bool $names Local ids and names besides the concept codes.
     */
    public function __construct(ExportDefinitions $definitions, ValueFormatter $formatter, string $workDir,
        int $relationTypeField, bool $names = false)
    {
        $this->names = $names;
        $this->definitions = $definitions;
        $this->formatter = $formatter;
        $this->path = rtrim($workDir, '/\\').'/export.xml';
        $this->relationTypeField = $relationTypeField;
    }

    /**
     * Records of the result, the selection and the links among the exported records.
     *
     * @param int[] $seeds Result records (depth 0); the others are expanded (depth 1).
     * @param int[] $selected Selected records.
     * @param array $links ExportPlanner::linksAmong().
     */
    public function setContext(array $seeds, array $selected, array $links): void
    {
        $this->seeds = array_fill_keys($seeds, true);
        $this->selected = array_fill_keys($selected, true);
        foreach($links as $link){
            $this->outgoing[$link['source']][] = $link;
            $this->incoming[$link['target']][] = $link;
        }
    }

    /** @inheritDoc */
    public function fieldCodes(): array
    {
        return array('_all');
    }

    /** @inheritDoc */
    public function begin(array $meta): void
    {
        $this->xml = new XmlStreamWriter($this->path);
        $this->xml->start('hml', array(
            'xmlns' => 'https://heuristnetwork.org',
            'xmlns:xsi' => 'https://www.w3.org/2001/XMLSchema-instance',
            'xsi:schemaLocation' => 'https://heuristref.net/scheme_hml.xsd'
        ));
        $this->xml->element('database', array('id' => (string)$this->definitions->registeredId()), (string)($meta['database'] ?? ''));
        $query = $meta['query'] ?? '';
        $this->xml->element('query', array(
            'q' => is_string($query) ? $query : (json_encode($query, JSON_UNESCAPED_UNICODE) ?: ''),
            'title' => (string)($meta['title'] ?? '')
        ));
        if(!empty($this->selected)){
            $this->xml->element('selectedIDs', array(), implode(',', array_keys($this->selected)));
        }
        $this->xml->element('dateStamp', array(), date('c'));
        $this->xml->element('resultCount', array(), (string)count($this->seeds));
        $this->xml->start('records');
    }

    /** @inheritDoc */
    public function write(array $records): void
    {
        foreach($records as $record){
            $this->record($record);
            $this->written++;
        }
        $this->xml->flush();
    }

    /** @inheritDoc */
    public function end(): void
    {
        if($this->xml === null){ return; }
        $this->xml->end(); // records
        $this->xml->element('recordCount', array(), (string)$this->written);
        $this->xml->close(); // hml
        $this->xml = null;
    }

    /** @inheritDoc */
    public function files(): array
    {
        return array(array('path' => $this->path, 'name' => 'export.xml'));
    }

    private function record(array $record): void
    {
        $recordId = intval($record['rec_ID']);
        $rectypeId = intval($record['rec_RecTypeID'] ?? 0);
        $visibility = (string)($record['rec_NonOwnerVisibility'] ?? '');
        $visibility = $visibility !== '' ? $visibility : 'viewable';
        $this->xml->start('record', array(
            'visibility' => $visibility,
            'visnote' => $visibility === 'hidden' ? 'owner group only'
                : ($visibility === 'public' ? 'no login required' : 'logged in users'),
            'selected' => isset($this->selected[$recordId]) ? 'yes' : 'no',
            'depth' => isset($this->seeds[$recordId]) ? '0' : '1'
        ));
        $this->xml->element('id', array(), (string)$recordId);
        $this->xml->element('type', array('id' => $this->names ? (string)$rectypeId : null,
            'conceptID' => $this->definitions->rectypeConcept($rectypeId)),
            $this->definitions->rectypeName($rectypeId));
        if(!empty($record['rec_URL'])){ $this->xml->element('url', array(), (string)$record['rec_URL']); }
        if(!empty($record['rec_ScratchPad'])){ $this->xml->element('notes', array(), (string)$record['rec_ScratchPad']); }
        $this->xml->element('citeAs', array(), $this->formatter->recordUrl($recordId));
        $this->xml->element('title', array(), (string)($record['rec_Title'] ?? ''));
        if(!empty($record['rec_Added'])){ $this->xml->element('added', array(), (string)$record['rec_Added']); }
        if(!empty($record['rec_Modified'])){ $this->xml->element('modified', array(), (string)$record['rec_Modified']); }
        if(array_key_exists('rec_OwnerUGrpID', $record)){
            $owner = intval($record['rec_OwnerUGrpID']);
            $this->xml->element('workgroup', array('id' => (string)$owner), $owner > 0 ? $this->definitions->groupName($owner) : 'public');
        }

        foreach($record['details'] ?? array() as $fieldId => $values){
            foreach($values as $value){
                $this->detail(intval($fieldId), $rectypeId, $value);
            }
        }

        // pointers from other exported records to this one
        foreach($this->incoming[$recordId] ?? array() as $link){
            if($link['relation'] > 0 || $link['field'] < 1){ continue; }
            $field = $this->definitions->field($link['field']);
            $this->xml->element('reversePointer', array('id' => $this->names ? (string)$link['field'] : null,
                'conceptID' => $field['concept'] ?? ''),
                (string)$link['source']);
        }
        // relationships with other exported records, in both directions
        foreach(array('outgoing' => false, 'incoming' => true) as $side => $inverse){
            foreach($this->{$side}[$recordId] ?? array() as $link){
                if($link['relation'] < 1){ continue; }
                $this->relationship($link, $inverse);
            }
        }
        $this->xml->end(); // record
    }

    private function relationship(array $link, bool $useInverse): void
    {
        $termId = $link['relType'];
        $term = $this->definitions->term($termId);
        $attrs = array();
        if($useInverse){ $attrs['useInverse'] = 'true'; }
        if($this->names){ $attrs['termID'] = (string)$termId; }
        $attrs['relatedRecordID'] = (string)($useInverse ? $link['source'] : $link['target']);
        if($this->names){ $attrs['term'] = $term['label'] ?? ''; }
        $attrs['termConceptID'] = $term['concept'] ?? '';
        if(!empty($term['code'])){ $attrs['code'] = $term['code']; }
        $inverse = intval($term['inverse'] ?? 0);
        if($inverse > 0){
            $inverseTerm = $this->definitions->term($inverse);
            if($this->names){
                $attrs['inverse'] = $inverseTerm['label'] ?? '';
                $attrs['invTermID'] = (string)$inverse;
            }
            $attrs['invTermConceptID'] = $inverseTerm['concept'] ?? '';
        }
        $this->xml->element('relationship', $attrs, (string)$link['relation']);
    }

    private function detail(int $fieldId, int $rectypeId, $value): void
    {
        $field = $this->definitions->field($fieldId);
        $type = $field['type'] ?? '';
        $attrs = $this->names ? array(
            'id' => (string)$fieldId,
            'conceptID' => $field['concept'] ?? '',
            'name' => $this->definitions->fieldName($rectypeId, $fieldId),
            'basename' => $field['name'] ?? ''
        ) : array('conceptID' => $field['concept'] ?? (string)$fieldId);
        switch($type){
            case 'resource':
                $attrs['isRecordPointer'] = 'true';
                $this->xml->element('detail', $attrs, is_array($value) ? (string)($value['rec_ID'] ?? $value['value'] ?? '') : (string)$value);
                return;
            case 'enum':
            case 'relationtype':
                $termId = intval(is_array($value) ? ($value['trm_ID'] ?? $value['value'] ?? 0) : $value);
                $term = $this->definitions->term($termId);
                if($this->names){ $attrs['termID'] = (string)$termId; }
                if($term !== null){ $attrs['termConceptID'] = $term['concept']; }
                if($fieldId === $this->relationTypeField && !empty($term['inverse'])){
                    $inverse = $this->definitions->term(intval($term['inverse']));
                    if($this->names){ $attrs['inverse'] = $inverse['label'] ?? ''; }
                    $attrs['invTermConceptID'] = $inverse['concept'] ?? '';
                }
                $this->xml->element('detail', $attrs, $term['label'] ?? (string)$termId);
                return;
            case 'file':
                $file = is_array($value) && is_array($value['file'] ?? null) ? $value['file'] : array();
                $this->xml->start('detail', $attrs);
                $this->xml->start('file');
                $this->xml->element('id', array(), (string)($file['ulf_ID'] ?? ''));
                $this->xml->element('nonce', array(), (string)($file['ulf_ObfuscatedFileID'] ?? ''));
                $this->xml->element('origName', array(), (string)($file['ulf_OrigFileName'] ?? ''));
                if(!empty($file['fxm_MimeType'])){ $this->xml->element('mimeType', array(), (string)$file['fxm_MimeType']); }
                if(!empty($file['ulf_FileSizeKB'])){ $this->xml->element('fileSize', array('units' => 'kB'), (string)$file['ulf_FileSizeKB']); }
                if(!empty($file['ulf_Added'])){ $this->xml->element('date', array(), (string)$file['ulf_Added']); }
                if(!empty($file['ulf_Description'])){ $this->xml->element('description', array(), (string)$file['ulf_Description']); }
                $url = empty($file) ? '' : $this->formatter->fileUrl($file);
                if($url !== ''){ $this->xml->element('url', array(), $url); }
                $this->xml->end(); // file
                $this->xml->end(); // detail
                return;
            case 'geo':
                $geo = is_array($value) && is_array($value['geo'] ?? null) ? $value['geo'] : array();
                $this->xml->start('detail', $attrs);
                $this->xml->start('geo');
                $geoType = (string)($geo['type'] ?? '');
                $this->xml->element('type', array(), self::GEO_TYPES[$geoType] ?? $geoType);
                $this->xml->element('wkt', array(), (string)($geo['wkt'] ?? ''));
                $this->xml->end(); // geo
                $this->xml->end(); // detail
                return;
            case 'date':
                // as stored: a simple date or a temporal JSON object (no value formats in HML)
                $this->xml->element('detail', $attrs, $this->formatter->plain($value));
                return;
            default:
                $this->xml->element('detail', $attrs, $this->formatter->plain($value));
        }
    }
}
