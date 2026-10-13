<?php
/**
* RecordDuplicateService.php - Copy of one record (POST /records/{id}/duplicate)
*
* Port of recordDuplicate() of hserv/records/edit/recordModify.php for the
* writable system record types: the header, detail values and edit/view groups
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
    private WritableRecordTypes $writable;
    private RuntimeContext $runtime;
    private RecordTitle $title;
    private LanguageCodes $languages;
    /** @var array<string,int> name (DT_NAME), cmsPage (RT_CMS_PAGE) */
    private array $codes;

    /**
     * @param array<string,int> $codes Local ids: name (DT_NAME), cmsPage (RT_CMS_PAGE).
     */
    public function __construct(
        RecordStore $store,
        RecordPermissionService $permissions,
        DefinitionLookup $definitions,
        WritableRecordTypes $writable,
        RuntimeContext $runtime,
        RecordTitle $title,
        LanguageCodes $languages,
        array $codes = array()
    ) {
        $this->store = $store;
        $this->permissions = $permissions;
        $this->definitions = $definitions;
        $this->writable = $writable;
        $this->runtime = $runtime;
        $this->title = $title;
        $this->languages = $languages;
        $this->codes = array_map('intval', $codes);
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
        $header = $recordId > 0 ? $this->store->header($recordId) : null;
        if($header === null || !$this->canView($recordId, $header)){
            throw RecordEditException::notFound('Record '.$recordId.' does not exist');
        }
        $rectype = intval($header['rec_RecTypeID']);
        if(!$this->writable->contains($rectype)){
            throw RecordEditException::denied('Records of type "'.$this->definitions->rectypeName($rectype)
                .'" cannot be duplicated with this API yet');
        }
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
                'scratchPad' => $header['rec_ScratchPad'],
                'addedBy' => $this->runtime->userId
            ));
            $this->store->copyContent($recordId, $newId);
            $names = array();
            if($nameField > 0){
                foreach($this->store->details($newId, array($nameField))[$nameField] ?? array() as $name){
                    $name = is_array($name) ? (string)($name['value'] ?? '') : (string)$name;
                    list($language) = $this->languages->splitPrefix($name);
                    if($language === null && $name !== '' && strpos($name, self::NAME_PREFIX) !== 0){
                        $this->store->updateValue($newId, $nameField, $name, self::NAME_PREFIX.$name);
                        $name = self::NAME_PREFIX.$name;
                    }
                    $names[] = $name;
                }
            }
            $this->store->setTitle($newId, $this->title->fromNames($names, $newId));
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
        $groups = $this->store->viewGroups($recordId);
        return empty($groups) || !empty(array_intersect($groups, $this->permissions->memberOf()));
    }
}
