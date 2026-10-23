<?php
/**
* RecordDuplicateService.php - Copy of one record (POST /records/{id}/duplicate)
*
* Port of recordDuplicate() of hserv/records/edit/recordModify.php for every
* record type (plan 21): the header, detail values and edit/view groups
* are copied; the copy belongs to the current user (website pages: group 1,
* public, as on insert) and its name values without language prefix get
* "Copy of ". Relationship records and linked records are not copied.
*
* @project     Heurist academic knowledge management system
* @package     Records\Edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Edit;

use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Store\RecordStore;
use Heurist\Runtime\RuntimeContext;
use Heurist\Utilities\LanguageCodes;
use Throwable;

/** Duplicates one record. */
final class RecordDuplicateService
{
    /** Prefix of the name of the copy. */
    public const NAME_PREFIX = 'Copy of ';

    private RecordStore $store;
    private RecordPermissionService $permissions;
    private DefinitionLookup $definitions;
    private RuntimeContext $runtime;
    private RecordTitle $title;
    private LanguageCodes $languages;
    /** @var array<string,int> name (DT_NAME), cmsPage (RT_CMS_PAGE) */
    private array $codes;
    private ?TitleMask $titleMask;
    private ?RecordValidator $validator;

    /**
     * @param array<string,int> $codes Local ids: name (DT_NAME), cmsPage (RT_CMS_PAGE).
     * @param TitleMask|null $titleMask Title from the mask of the record type (null: DT_NAME only).
     */
    public function __construct(
        RecordStore $store,
        RecordPermissionService $permissions,
        DefinitionLookup $definitions,
        RuntimeContext $runtime,
        RecordTitle $title,
        LanguageCodes $languages,
        array $codes = array(),
        ?TitleMask $titleMask = null,
        ?RecordValidator $validator = null
    ) {
        $this->store = $store;
        $this->permissions = $permissions;
        $this->definitions = $definitions;
        $this->runtime = $runtime;
        $this->title = $title;
        $this->languages = $languages;
        $this->codes = array_map('intval', $codes);
        $this->titleMask = $titleMask;
        $this->validator = $validator;
    }

    /**
     * Duplicate a record.
     *
     * @return int Id of the new record.
     * @throws RecordEditException 403, 404
     */
    public function duplicate(int $recordId): int
    {
        $this->permissions->assertAccount('add');
        $header = $recordId > 0 ? $this->store->records()->header($recordId) : null;
        if($header === null || !$this->canView($recordId, $header)){
            throw RecordEditException::notFound('Record '.$recordId.' does not exist');
        }
        $rectype = intval($header['rec_RecTypeID']);
        $owner = $this->runtime->userId;
        $visibility = (string)$header['rec_NonOwnerVisibility'];
        if($rectype === ($this->codes['cmsPage'] ?? -1)){
            $owner = 1;
            $visibility = 'public';
        }
        $this->permissions->assertNewOwners($owner, array());

        $nameField = $this->codes['name'] ?? 0;
        $database = $this->store->database();
        $database->beginTransaction();
        try{
            $this->store->setUser($this->runtime->userId);
            $newId = $this->store->insertRecord(array(
                'rectype' => $rectype,
                'owner' => $owner,
                'visibility' => $visibility,
                'url' => $header['rec_URL'],
                'scratchPad' => \Heurist\Utilities\StoredText::clean((string)$header['rec_ScratchPad'], 'blocktext'),
                'addedBy' => $this->runtime->userId
            ));
            $this->store->copyContent($recordId, $newId);
            foreach($this->store->records()->editValues($newId) as $fieldId=>$values){
                $type = $this->definitions->fieldType((int)$fieldId);
                if(!in_array($type, array('freetext', 'blocktext'), true)){ continue; }
                foreach($values as $item){
                    $old = is_array($item) ? (string)($item['value'] ?? '') : (string)$item;
                    $clean = $this->validator !== null ? $this->validator->cleanText($rectype, (int)$fieldId, $type, $old)
                        : \Heurist\Utilities\StoredText::clean($old, $type);
                    if($old !== $clean){ $this->store->updateValue($newId, (int)$fieldId, $old, $clean); }
                }
            }
            $names = array();
            if($nameField > 0){
                foreach($this->store->records()->editValues($newId, array($nameField))[$nameField] ?? array() as $name){
                    $name = is_array($name) ? (string)($name['value'] ?? '') : (string)$name;
                    list($language) = $this->languages->splitPrefix($name);
                    if($language === null && $name !== '' && strpos($name, self::NAME_PREFIX) !== 0){
                        $this->store->updateValue($newId, $nameField, $name, self::NAME_PREFIX.$name);
                        $name = self::NAME_PREFIX.$name;
                    }
                    $names[] = $name;
                }
            }
            $title = $this->titleMask !== null ? $this->titleMask->fill($newId) : null;
            $this->store->setTitle($newId, $title ?? $this->title->fromNames($names, $newId));
            $database->commit();
        }catch(Throwable $exception){
            $database->rollback();
            throw $exception;
        }
        return $newId;
    }

    /** The user may see the record (owner, edit group, administrator or non-owner visibility). */
    private function canView(int $recordId, array $header): bool
    {
        $owners = $this->permissions->owners($recordId);
        if($owners !== null && $this->permissions->canEditOwned($owners['owner'], $owners['editGroups'])){
            return true;
        }
        $visibility = (string)$header['rec_NonOwnerVisibility'];
        if($visibility === 'public' || $visibility === 'pending'){ return true; }
        if($visibility !== 'viewable'){ return false; }
        $groups = $this->store->records()->owners(array($recordId))[$recordId]['viewGroups'] ?? array();
        return empty($groups) || !empty(array_intersect($groups, $this->permissions->memberOf()));
    }
}
