<?php
/**
* PresentationEntityHandler.php - Hooks of the entity presentation (Presentation set 2-1112, plan 27q1 §3)
*
* A Presentation set stores the settings of a story map, crosstabs or an export: Presentation Type 2-1203 (the kind),
* Configuration 2-1187 (JSON) and Heurist Query String 2-12 (the source query). No own endpoint: the generic routes
* /presentation/{id}, /presentation/{id}/duplicate and /sys?q=[{"t":"presentation"}] of plan 28.
*
* Before a save the configuration must be a JSON object; the settings of a story map are checked by
* StoryMapSettings. A Presentation set used by website pages (element `storymap`, plan 27q1 §8) is not deleted (409).
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
        'export' => 'TRM_PRESENTATION_EXPORT'
    );

    private SystemCode $codes;
    /** @var callable(int,array<int,int>):array<int,array<int,string>> stored values: (record id, field ids) => field => values */
    private $stored;
    /** @var callable(int):int|null number of website pages that use a Presentation set */
    private $pagesUsing;

    /**
     * @param SystemCode $codes Local ids of the constants.
     * @param callable(int,array<int,int>):array<int,array<int,string>> $stored Stored values of a record (PATCH).
     * @param callable(int):int|null $pagesUsing Website pages that use a Presentation set (null: not checked).
     */
    public function __construct(SystemCode $codes, callable $stored, ?callable $pagesUsing = null)
    {
        $this->codes = $codes;
        $this->stored = $stored;
        $this->pagesUsing = $pagesUsing;
    }

    public function beforeDelete(int $id, array $params): array
    {
        $count = $this->pagesUsing !== null ? intval(($this->pagesUsing)($id)) : 0;
        if($count > 0){
            throw new RecordEditException(409, 'in_use', 'This presentation set is used by '.$count
                .' website page(s): remove it from the pages first', array(), array('pages' => $count));
        }
        return array();
    }

    /**
     * Whether a page configuration (JSON of 2-1187) has a story map element of a Presentation set.
     *
     * @param string $configuration
     * @param int $presetId
     */
    public static function configurationUses(string $configuration, int $presetId): bool
    {
        $config = json_decode($configuration, true);
        $instances = is_array($config['instances'] ?? null) ? $config['instances'] : array();
        foreach($instances as $instance){
            if(is_array($instance) && ($instance['type'] ?? '') === 'storymap'
                && intval($instance['settings']['preset'] ?? 0) === $presetId){
                return true;
            }
        }
        return false;
    }

    public function beforeSave(string $method, int $id, array $body, array $params, bool $dryRun = false): array
    {
        $record = isset($body['records'][0]) && is_array($body['records'][0]) ? $body['records'][0] : $body;
        $details = is_array($record['details'] ?? null) ? $record['details'] : array();
        $configField = $this->codes->id('DT_CONFIGURATION');
        $typeField = $this->codes->id('DT_PRESENTATION_TYPE');
        $hasConfig = array_key_exists($configField, $details);
        $hasType = array_key_exists($typeField, $details);
        if(!$hasConfig && !$hasType){ return $body; }
        // a PATCH of one of them is checked with the stored other
        $stored = $id > 0 && (!$hasConfig || !$hasType) ? ($this->stored)($id, array($configField, $typeField)) : array();
        $config = self::text($hasConfig ? $details[$configField] : ($stored[$configField] ?? null));
        $kind = $this->kind(self::text($hasType ? $details[$typeField] : ($stored[$typeField] ?? null)));
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
        }
        return $body;
    }

    /**
     * Kind of a term id of Presentation Type.
     *
     * @return string|null storymap, crosstabs, export, or null.
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
