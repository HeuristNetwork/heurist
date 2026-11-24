<?php
/**
* PresentationEntityHandler.php - Hooks of the entity presentation (Presentation set 2-1112, plan 27q1 §3, 27q3 §5)
*
* A Presentation set stores the settings of a story map, crosstabs, an export or a report: Presentation Type 2-1203
* (the kind), Configuration 2-1187 (JSON), Heurist Query String 2-12 (the source query), Short summary 2-3; a report
* (kind Card Report or List Report, plan 27q3: was Custom Report 2-1104) also its template file 2-62 and output
* format 2-29. No own endpoint: the generic routes /presentation/{id}, /presentation/{id}/duplicate and
* /sys?q=[{"t":"presentation"}] of plan 28. The hooks and actions of a report (asset, execute, export, import, setup)
* are those of ReportEntityHandler (kind "report"); another kind answers 400 to them.
*
* Before a save the configuration must be a JSON object; the settings of a story map are checked by
* StoryMapSettings, those of crosstabs by CrosstabSettings (plan 27q2). A Presentation set used by website pages (element `storymap`, plan 27q1 §8) is not deleted (409).
* The pages are those the user can read.
*
* @project     Heurist academic knowledge management system
* @package     Presentations
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Presentations;

use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Entity\RecordEntityHandler;
use Heurist\Runtime\SystemCode;

/** Hooks of the entity presentation. */
final class PresentationEntityHandler extends RecordEntityHandler
{
    /** Kind => term constant of Presentation Type 2-1203. */
    public const KINDS = array(
        'storymap' => 'TRM_PRESENTATION_STORYMAP',
        'crosstabs' => 'TRM_PRESENTATION_CROSSTABS',
        'export' => 'TRM_PRESENTATION_EXPORT',
        'cardreport' => 'TRM_PRESENTATION_CARD_REPORT',
        'listreport' => 'TRM_PRESENTATION_LIST_REPORT'
    );

    /** Kinds that are reports (a template file). */
    public const REPORT_KINDS = array('cardreport', 'listreport');

    /** Actions of a report record (with an id), and of the whole entity. */
    private const REPORT_ACTIONS = array('asset', 'execute', 'export');
    private const REPORT_ENTITY_ACTIONS = array('import', 'setup');

    private SystemCode $codes;
    /** @var callable(int,array<int,int>):array<int,array<int,string>> stored values: (record id, field ids) => field => values */
    private $stored;
    /** @var callable(int):int|null number of website pages that use a Presentation set */
    private $pagesUsing;
    /** @var callable():RecordEntityHandler|null the hooks and actions of a report (ReportEntityHandler "report") */
    private $reportFactory;
    private ?RecordEntityHandler $reports = null;
    /** Whether the record of the current save is a report. */
    private bool $savingReport = false;

    /**
     * @param SystemCode $codes Local ids of the constants.
     * @param callable(int,array<int,int>):array<int,array<int,string>> $stored Stored values of a record (PATCH).
     * @param callable(int):int|null $pagesUsing Website pages that use a Presentation set (null: not checked).
     * @param callable():RecordEntityHandler|null $reports The report handler (null: reports do not run here).
     */
    public function __construct(SystemCode $codes, callable $stored, ?callable $pagesUsing = null, ?callable $reports = null)
    {
        $this->codes = $codes;
        $this->stored = $stored;
        $this->pagesUsing = $pagesUsing;
        $this->reportFactory = $reports;
    }

    public function beforeDelete(int $id, array $params): array
    {
        $count = $this->pagesUsing !== null ? intval(($this->pagesUsing)($id)) : 0;
        if($count > 0){
            throw new RecordEditException(409, 'in_use', 'This presentation set is used by '.$count
                .' website page(s): remove it from the pages first', array(), array('pages' => $count));
        }
        // a report with generated reports is not deleted; its template file goes with it
        return $this->isReport($id) ? array('report' => $this->reports()->beforeDelete($id, $params)) : array();
    }

    public function afterDelete(int $id, array $params, array $state, array $result): array
    {
        return isset($state['report']) ? $this->reports()->afterDelete($id, $params, $state['report'], $result) : $result;
    }

    public function afterSave(string $method, int $id, array $body, array $params): array
    {
        $saving = $this->savingReport;
        $this->savingReport = false;
        return $saving ? $this->reports()->afterSave($method, $id, $body, $params) : array();
    }

    public function afterDuplicate(int $sourceId, int $newId, array $body, array $params): array
    {
        // a copy of a report gets its own template file
        return $this->isReport($sourceId) ? $this->reports()->afterDuplicate($sourceId, $newId, $body, $params) : array();
    }

    public function describe(int $id): array
    {
        return $this->isReport($id) ? $this->reports()->describe($id) : array();
    }

    public function describeMany(array $ids): array
    {
        return $this->reportFactory !== null ? $this->reports()->describeMany($ids) : array();
    }

    public function allowsGuest(string $action): bool
    {
        return $action === 'execute';
    }

    public function acceptsUpload(string $action): bool
    {
        return $action === 'import';
    }

    public function action(string $action, string $method, int $id, array $params, array $body): array
    {
        $entity = $id < 1 && in_array($action, self::REPORT_ENTITY_ACTIONS, true);
        if($entity || in_array($action, self::REPORT_ACTIONS, true)){
            if($this->reportFactory === null){
                throw new RecordEditException(405, 'method_not_allowed', 'Reports can not run here');
            }
            if(!$entity && !$this->isReport($id)){
                throw RecordEditException::invalid('Presentation set '.$id.' is not a report');
            }
            return $this->reports()->action($action, $method, $id, $params, $body);
        }
        return parent::action($action, $method, $id, $params, $body);
    }

    /** Whether a Presentation set is a report the user sees. */
    private function isReport(int $id): bool
    {
        if($this->reportFactory === null || $id < 1){ return false; }
        $reports = $this->reports();
        return method_exists($reports, 'isReport') && $reports->isReport($id);
    }

    private function reports(): RecordEntityHandler
    {
        if($this->reports === null){ $this->reports = ($this->reportFactory)(); }
        return $this->reports;
    }

    /**
     * Whether a page configuration (JSON of 2-1187) has a story map or crosstabs element of a Presentation set.
     *
     * @param string $configuration
     * @param int $presetId
     */
    public static function configurationUses(string $configuration, int $presetId): bool
    {
        $config = json_decode($configuration, true);
        $instances = is_array($config['instances'] ?? null) ? $config['instances'] : array();
        foreach($instances as $instance){
            if(is_array($instance) && in_array($instance['type'] ?? '', array('storymap', 'crosstabs'), true)
                && intval($instance['settings']['preset'] ?? 0) === $presetId){
                return true;
            }
        }
        return false;
    }

    public function beforeSave(string $method, int $id, array $body, array $params, bool $dryRun = false): array
    {
        $this->savingReport = false;
        $record = isset($body['records'][0]) && is_array($body['records'][0]) ? $body['records'][0] : $body;
        $details = is_array($record['details'] ?? null) ? $record['details'] : array();
        $configField = $this->codes->id('DT_CONFIGURATION');
        $typeField = $this->codes->id('DT_PRESENTATION_TYPE');
        $hasConfig = array_key_exists($configField, $details);
        $hasType = array_key_exists($typeField, $details);
        // the kind: given, or stored (a PATCH of other fields of a report must reach its file name rule)
        $stored = $id > 0 && (!$hasConfig || !$hasType) ? ($this->stored)($id, array($configField, $typeField)) : array();
        $kind = $this->kind(self::text($hasType ? $details[$typeField] : ($stored[$typeField] ?? null)));
        if(in_array($kind, self::REPORT_KINDS, true) && $this->reportFactory !== null){
            $body = $this->reports()->beforeSave($method, $id, $body, $params, $dryRun);
            $this->savingReport = !$dryRun;
        }
        if(!$hasConfig && !$hasType){ return $body; }
        $config = self::text($hasConfig ? $details[$configField] : ($stored[$configField] ?? null));
        if($config === ''){ return $body; }
        $settings = json_decode($config, true);
        if(json_last_error() !== JSON_ERROR_NONE || !is_array($settings) || ($settings !== array() && array_keys($settings) === range(0, count($settings) - 1))){
            throw self::refused($configField, 'Configuration must be a JSON object');
        }
        if($kind === 'storymap'){
            $problems = StoryMapSettings::problems($settings);
            if($problems){
                throw self::refused($configField, 'Story map: '.implode('; ', $problems));
            }
        }elseif($kind === 'crosstabs'){
            $problems = CrosstabSettings::problems($settings);
            if($problems){
                throw self::refused($configField, 'Crosstabs: '.implode('; ', $problems));
            }
        }
        return $body;
    }

    /**
     * Kind of a term id of Presentation Type.
     *
     * @return string|null storymap, crosstabs, export, cardreport, listreport, or null.
     */
    public function kind(string $termId): ?string
    {
        if($termId === ''){ return null; }
        foreach(self::KINDS as $kind => $constant){
            if($this->codes->id($constant) === intval($termId)){ return $kind; }
        }
        return null;
    }

    /** First value of a field of the request body (or of the stored values). */
    private static function text($values): string
    {
        $value = is_array($values) ? (array_values($values)[0] ?? '') : $values;
        if(is_array($value)){ $value = $value['value'] ?? ''; }
        return trim((string)$value);
    }

    /** The error of a field, in the format of the record validator. */
    private static function refused(int $field, string $message): RecordEditException
    {
        return new RecordEditException(422, 'validation', $message, array(
            array('field' => (string)$field, 'index' => 0, 'code' => 'invalid', 'severity' => 'error', 'message' => $message)
        ));
    }
}
