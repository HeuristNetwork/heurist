<?php
/**
* RecordSaveService.php - Insert and update of one record (POST / PUT / PATCH)
*
* Port of recordSave() and recordAdd() of hserv/records/edit/recordModify.php
* for every record type (plan 21: the list of writable system types was removed):
*   - insert: rec_ID 0 takes the next id, a negative rec_ID is used as the id
*     (import); owner is the current user and visibility "viewable" unless the
*     body gives them (defaults of website pages: Website/WebsiteEntityHandler, plan 27d);
*   - update: PUT replaces the whole record (missing fields and groups are
*     cleared); rec_Modified of the body must match the stored one unless forced (409);
*   - PATCH (plan 32, patchFields): only the fields of the body are written, the other
*     rows stay as they are; missing headers are kept. "details" replaces the values of
*     a field ([] deletes it; a required field is never left empty), "detailsAdd" appends
*     values to a multi-value field, "detailsMatch" limits replace / delete to the values
*     that match a value condition of the query format (FieldPredicateCompiler), "addIfNone"
*     adds the new values when nothing matches. Only the sent values are validated (record
*     checks such as CmsRecordCheck get the whole record); the title, the calculated fields
*     and the child titles are updated only when FieldDependencies says they depend on a
*     changed field;
*   - values of forbidden fields are kept as stored;
*   - workflow stage (sysWorkflowRules): an allowed stage sets the owner and
*     visibility of its rule, a refused stage keeps the stored stage (no emails);
*   - one transaction: header, edit/view groups, detail values (deleted and
*     inserted again, as in legacy), title from DT_NAME.
* Title from the title mask of the record type (TitleMask, fill only), else from DT_NAME,
* else "Record \NNN"; calculated fields of the record are run before the title
* (CalculatedFields, plan 14 / plan 15 Phase 6).
* Not ported (plan 14): ElasticSearch, HTML
* cache, emails, parent/child pointers, relationship re-titling.
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
use Heurist\Records\Query\Compiler\FieldPredicateCompiler;
use Heurist\Records\Query\Compiler\SqlBuildContext;
use Heurist\Records\Query\Compiler\SqlTables;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Store\RecordStore;
use Heurist\Records\Store\SplitValues;
use Heurist\Runtime\DatabaseSettings;
use Heurist\Runtime\RuntimeContext;
use Throwable;

/** Writes one record. */
final class RecordSaveService
{
    private RecordStore $store;
    private RecordValidator $validator;
    private RecordPermissionService $permissions;
    private DefinitionLookup $definitions;
    private RuntimeContext $runtime;
    private RecordTitle $title;
    private ?TitleMask $titleMask;
    private ?CalculatedFields $calculated;
    private ?FieldDependencies $dependencies;
    private ?FieldPredicateCompiler $matcher = null;
    /** @var array<string,int> cmsPage, workflowStage */
    private array $codes;

    /**
     * @param array<string,int> $codes Local ids: cmsPage (RT_CMS_PAGE), content (DT_EXTENDED_DESCRIPTION),
     *        cmsTranslations (DT_CMS_PAGE_TRANSLATIONS), workflowStage (DT_WORKFLOW_STAGE).
     * @param TitleMask|null $titleMask Title from the mask of the record type (null: DT_NAME only).
     * @param CalculatedFields|null $calculated Calculated fields run on save (null: none).
     * @param FieldDependencies|null $dependencies What a PATCH recalculates (null: built in memory from $titleMask).
     */
    public function __construct(
        RecordStore $store,
        RecordValidator $validator,
        RecordPermissionService $permissions,
        DefinitionLookup $definitions,
        RuntimeContext $runtime,
        RecordTitle $title,
        array $codes = array(),
        ?TitleMask $titleMask = null,
        ?CalculatedFields $calculated = null,
        ?FieldDependencies $dependencies = null
    ) {
        $this->store = $store;
        $this->validator = $validator;
        $this->permissions = $permissions;
        $this->definitions = $definitions;
        $this->runtime = $runtime;
        $this->title = $title;
        $this->codes = array_map('intval', $codes);
        $this->titleMask = $titleMask;
        $this->calculated = $calculated;
        $this->dependencies = $dependencies ?? ($titleMask !== null ? new FieldDependencies($definitions, $titleMask) : null);
    }

    /**
     * Insert (POST) or update (PUT, PATCH) a record.
     *
     * @param string $method POST, PUT or PATCH.
     * @param int $pathId Record id of the URL (POST: 0 or negative).
     * @param RecordInput $input Request body.
     * @param bool $force Save even when rec_Modified differs (overwrite another change).
     * @param bool $dryRun Check only, write nothing (POST /records/{id}/validate).
     * @return array{recordId:int,created:bool,issues:array<int,array>}
     * @throws RecordEditException
     */
    public function save(string $method, int $pathId, RecordInput $input, bool $force = false, bool $dryRun = false): array
    {
        $method = strtoupper($method);
        $insert = $method === 'POST';
        if(!$insert && $method !== 'PUT' && $method !== 'PATCH'){
            throw RecordEditException::invalid('Method '.$method.' does not save records');
        }
        if($input->has('rec_ID') && $input->intHeader('rec_ID') !== $pathId){
            throw RecordEditException::invalid('rec_ID of the body ('.$input->intHeader('rec_ID').') differs from the record id of the URL ('.$pathId.')');
        }
        $this->permissions->assertAccount($insert ? 'add' : 'edit');
        if($method === 'PATCH'){
            return $this->patchFields($pathId, $input, $force, $dryRun);
        }
        if($input->hasPatchOptions()){
            throw RecordEditException::invalid('detailsAdd, detailsMatch and addIfNone are options of PATCH');
        }
        $plan = $insert ? $this->planInsert($pathId, $input) : $this->planUpdate($pathId, $input, $force);

        $result = $this->validator->validate($plan['rectype'], $plan['details']);
        $issues = $result->issues();
        if($result->hasErrors()){
            throw new RecordEditException(422, 'validation', 'The record has invalid values', $issues);
        }
        $rows = $result->rows();
        $byWorkflow = $this->applyWorkflow($plan, $rows, $issues);

        if($insert){
            $this->permissions->assertNewOwners($plan['owner'], $plan['editGroups'], $byWorkflow);
        }elseif(!$byWorkflow){
            $this->permissions->assertOwnerChange($plan['currentOwners'], $plan['owner'], $plan['editGroups']);
        }
        if($dryRun){
            return array('recordId'=>$pathId, 'created'=>false, 'issues'=>$issues);
        }

        $database = $this->store->database();
        $database->beginTransaction();
        try{
            $before = $insert ? null : $this->parentState($plan['recordId'], $plan['rectype']);
            $this->store->setUser($this->runtime->userId);
            $header = array(
                'rectype' => $plan['rectype'],
                'owner' => $plan['owner'],
                'visibility' => $plan['visibility'],
                'url' => $plan['url'],
                'scratchPad' => $plan['scratchPad'],
                'addedBy' => $this->runtime->userId
            );
            if($insert){
                $recordId = $this->store->insertRecord($header, $plan['recordId']);
            }else{
                $recordId = $plan['recordId'];
                $this->store->updateRecord($recordId, $header);
            }
            $this->store->setPermissions($recordId, $plan['editGroups'], $plan['viewGroups']);
            $this->store->replaceDetails($recordId, $rows, $plan['keepFields']);
            if($this->calculated !== null){
                foreach($this->calculated->update($recordId, $plan['rectype']) as $fieldId => $message){
                    $issues[] = array('field'=>(string)$fieldId, 'index'=>0, 'code'=>'calculation', 'severity'=>'warning',
                        'message'=>'Calculated field '.$fieldId.' was not updated: '.$message);
                }
            }
            $title = $this->titleMask !== null ? $this->titleMask->fill($recordId) : null;
            $this->store->setTitle($recordId, $title ?? $this->title->fromRows($rows, $recordId));
            $this->refreshChildTitles($recordId, $plan['rectype'], $before);
            $database->commit();
        }catch(Throwable $exception){
            $database->rollback();
            throw $exception;
        }
        return array('recordId'=>$recordId, 'created'=>$insert, 'issues'=>$issues);
    }

    /** Child targets of the flagged forward fields, without reverse details. */
    private function childIds(int $recordId, int $rectype): array
    {
        $fields = $this->definitions->childFields($rectype);
        return empty($fields) ? array() : array_values(array_unique(array_column(
            $this->store->records()->links(array($recordId), array('kind' => 'pointer', 'fields' => $fields)), 1
        )));
    }

    /**
     * Children, title and a fingerprint of the other values of a parent record (null: its
     * type has no child fields or titles are not filled from masks).
     *
     * @return array{children:array<int,int>,title:string,values:string}|null
     */
    private function parentState(int $recordId, int $rectype): ?array
    {
        if($this->titleMask === null){ return null; }
        $fields = $this->definitions->childFields($rectype);
        if(empty($fields)){ return null; }
        $values = array();
        foreach($this->store->records()->details(array($recordId), null, array('order' => 'field')) as $row){
            // links to the children are compared one by one (children), not in the fingerprint
            if(in_array($row['field'], $fields, true)){ continue; }
            $values[] = array($row['field'], $row['value'], $row['wkt'], $row['file']);
        }
        $header = $this->store->records()->headers(array($recordId), array('rec_ID', 'rec_Title'))[$recordId] ?? array();
        return array(
            'children' => $this->childIds($recordId, $rectype),
            'title' => (string)($header['rec_Title'] ?? ''),
            'values' => md5(serialize($values))
        );
    }

    /**
     * Refresh titles affected by linking/unlinking a child or changing its parent (plan 26):
     * all children when the title or other values of the parent changed, otherwise only the
     * linked and unlinked ones; changed child titles go on to their own children.
     *
     * @param array|null $before parentState() before the save (null for a new record).
     */
    private function refreshChildTitles(int $recordId, int $rectype, ?array $before): void
    {
        $after = $this->parentState($recordId, $rectype);
        if($after === null){ return; }
        if($before === null || $before['title'] !== $after['title'] || $before['values'] !== $after['values']){
            $pending = array_merge($before['children'] ?? array(), $after['children']);
        }else{
            $pending = array_merge(array_diff($before['children'], $after['children']),
                array_diff($after['children'], $before['children']));
        }
        $this->refreshTitles($pending, $recordId);
    }

    /**
     * Fill the titles of some child records again; a changed title goes on to their own children.
     *
     * @param array<int,int> $pending Child record ids.
     * @param int $recordId The saved record (not refreshed).
     */
    private function refreshTitles(array $pending, int $recordId): void
    {
        $parentField = $this->titleMask->parentField();
        $pattern = '/parent entity|record parent|2-247'.($parentField > 0 ? '|\[\s*'.$parentField.'\b' : '').'/i';
        $usesParent = array(); // record type => its mask refers to the parent
        $seen = array($recordId => true);
        while(!empty($pending)){
            $headers = $this->store->records()->headers(array_values(array_unique($pending)));
            $pending = array();
            foreach($headers as $id => $header){
                if(isset($seen[$id])){ continue; }
                $seen[$id] = true;
                $type = intval($header['rec_RecTypeID']);
                if(!isset($usesParent[$type])){
                    $usesParent[$type] = preg_match($pattern, (string)($this->definitions->rectype($type)['titleMask'] ?? '')) === 1;
                }
                if(!$usesParent[$type]){ continue; }
                $title = $this->titleMask->fill($id);
                if($title === null){
                    $rows = array_map(static fn($row) => array('dty' => $row['field'], 'value' => $row['value']),
                        $this->store->records()->details(array($id)));
                    $title = $this->title->fromRows($rows, $id);
                }
                if($title !== $header['rec_Title']){
                    $this->store->setTitle($id, $title);
                    $pending = array_merge($pending, $this->childIds($id, $type));
                }
            }
        }
    }

    /** Values of a new record. */
    private function planInsert(int $pathId, RecordInput $input): array
    {
        if($pathId > 0){
            throw RecordEditException::invalid('POST inserts a record (id 0, or a negative id for a given id); use PUT or PATCH to change record '.$pathId);
        }
        $rectype = $input->intHeader('rec_RecTypeID');
        if($rectype < 1){
            throw new RecordEditException(422, 'validation', 'rec_RecTypeID is required', array(
                array('field'=>'rec_RecTypeID', 'index'=>null, 'code'=>'required', 'severity'=>'error', 'message'=>'rec_RecTypeID is required')
            ));
        }
        $recordId = abs($pathId);
        if($recordId > 0 && $this->store->records()->header($recordId) !== null){
            throw new RecordEditException(409, 'conflict', 'Record '.$recordId.' already exists');
        }
        $owner = $input->has('rec_OwnerUGrpID') ? $input->intHeader('rec_OwnerUGrpID') : $this->runtime->userId;
        $visibility = $input->has('rec_NonOwnerVisibility') ? (string)$input->header('rec_NonOwnerVisibility') : 'viewable';
        $this->assertVisibility($visibility);
        return array(
            'recordId' => $recordId,
            'rectype' => $rectype,
            'owner' => $owner,
            'editGroups' => $this->withoutOwner($input->idsHeader('rec_EditGroups'), $owner),
            'visibility' => $visibility,
            'viewGroups' => $visibility === 'viewable' ? $input->idsHeader('rec_NonOwnerVisibilityGroups') : array(),
            'url' => $this->url($input->header('rec_URL')),
            'scratchPad' => $this->text($input->header('rec_ScratchPad')),
            'details' => $input->details(),
            'keepFields' => array(),
            'currentStage' => 0
        );
    }

    /** Values of a replaced record (PUT). */
    private function planUpdate(int $recordId, RecordInput $input, bool $force): array
    {
        $plan = $this->planHeader($recordId, $input, false, $force);
        $forbidden = array();
        foreach($this->definitions->structureRows($plan['rectype']) as $fieldId => $row){
            if($row['requirement'] === 'forbidden'){ $forbidden[] = $fieldId; }
        }
        $plan['currentStage'] = $this->currentStage($recordId);
        $plan['details'] = $input->details();
        $plan['keepFields'] = $forbidden;
        return $plan;
    }

    /**
     * Header values of a changed record (PUT or PATCH): permissions, the conflict check,
     * owner, groups, visibility, URL, scratchpad. PATCH keeps the missing headers.
     *
     * @throws RecordEditException 400, 403, 404, 409
     */
    private function planHeader(int $recordId, RecordInput $input, bool $patch, bool $force): array
    {
        if($recordId < 1){
            throw RecordEditException::invalid('PUT and PATCH change an existing record; use POST to insert');
        }
        $stored = $this->store->records()->header($recordId);
        if($stored === null){
            throw RecordEditException::notFound('Record '.$recordId.' does not exist');
        }
        $rectype = intval($stored['rec_RecTypeID']);
        if($input->has('rec_RecTypeID') && $input->intHeader('rec_RecTypeID') !== $rectype){
            throw RecordEditException::invalid('The record type of a record cannot be changed');
        }
        $owners = $this->permissions->owners($recordId);
        if(!$this->permissions->canEditOwned($owners['owner'], $owners['editGroups'])){
            throw RecordEditException::denied('You cannot change record '.$recordId
                .'; you must be the database administrator or a member of a group that owns it');
        }
        if($input->has('rec_Modified') && (string)$input->header('rec_Modified') !== (string)$stored['rec_Modified'] && !$force){
            throw new RecordEditException(409, 'conflict',
                'Record '.$recordId.' was changed by someone else after it was loaded', array(),
                array('rec_Modified'=>(string)$stored['rec_Modified']));
        }

        $owner = $input->has('rec_OwnerUGrpID') ? $input->intHeader('rec_OwnerUGrpID') : $owners['owner'];
        $editGroups = ($patch && !$input->has('rec_EditGroups')) ? $owners['editGroups'] : $input->idsHeader('rec_EditGroups');
        $visibility = $input->has('rec_NonOwnerVisibility') ? (string)$input->header('rec_NonOwnerVisibility')
            : (string)$stored['rec_NonOwnerVisibility'];
        if($visibility === 'public' && $stored['rec_NonOwnerVisibility'] === 'public' && $this->publicToPending()){
            $visibility = 'pending'; // sys_SetPublicToPendingOnEdit
        }
        $this->assertVisibility($visibility);
        $viewGroups = ($patch && !$input->has('rec_NonOwnerVisibilityGroups'))
            ? ($this->store->records()->owners(array($recordId))[$recordId]['viewGroups'] ?? array()) : $input->idsHeader('rec_NonOwnerVisibilityGroups');
        if($visibility !== 'viewable'){ $viewGroups = array(); }

        $keepUrl = $patch && !$input->has('rec_URL');
        $keepScratchPad = $patch && !$input->has('rec_ScratchPad');
        return array(
            'recordId' => $recordId,
            'rectype' => $rectype,
            'stored' => $stored,
            'owner' => $owner,
            'currentOwners' => $owners,
            'editGroups' => $this->withoutOwner($editGroups, $owner),
            'visibility' => $visibility,
            'viewGroups' => $viewGroups,
            'url' => $keepUrl ? $stored['rec_URL'] : $this->url($input->header('rec_URL')),
            'scratchPad' => $keepScratchPad ? $stored['rec_ScratchPad'] : $this->text($input->header('rec_ScratchPad')),
            'details' => array(),
            'keepFields' => array(),
            'currentStage' => 0
        );
    }

    /** Stored workflow stage of a record (0: none, or no stage field in this database). */
    private function currentStage(int $recordId): int
    {
        $stage = $this->codes['workflowStage'] ?? 0;
        return $stage > 0 ? intval($this->store->records()->editValues($recordId, array($stage))[$stage][0] ?? 0) : 0;
    }

    // -------------------------------------------------------------- PATCH (plan 32)

    /**
     * Change some fields of a record: only their rows are deleted and inserted, only the sent
     * values are validated, and the title, calculated fields and child titles are updated only
     * when they depend on a changed field (FieldDependencies).
     *
     * @return array{recordId:int,created:bool,issues:array<int,array>,changed:array<int,int>,values:array<int,array>}
     *         changed = field => number of removed + inserted value rows;
     *         values = field => {found, added, removed}: found = the matched values (with a
     *         condition) or the stored ones (record actions report them, plan 33)
     * @throws RecordEditException
     */
    private function patchFields(int $recordId, RecordInput $input, bool $force, bool $dryRun): array
    {
        $plan = $this->planHeader($recordId, $input, true, $force);
        $rectype = $plan['rectype'];
        $structure = $this->definitions->structureRows($rectype);
        $replace = $input->details();
        $add = $input->detailsAdd();
        $match = $input->detailsMatch();
        $addIfNone = $input->addIfNone();
        $this->checkPatchOptions($rectype, $replace, $add, $match, $addIfNone);

        $matched = array();
        foreach($match as $fieldId => $condition){
            $matched[$fieldId] = $this->matchingIds($recordId, $fieldId, $condition);
        }
        $counts = $this->store->records()->fieldCounts($recordId,
            array_values(array_unique(array_merge(array_keys($replace), array_keys($add)))));

        $options = array('required' => false);
        if($this->validator->hasRecordCheck($rectype)){
            $options['recordDetails'] = $this->mergedDetails($recordId, $rectype, $replace, $add, $matched, $addIfNone);
        }
        $result = $this->validator->validate($rectype, $replace + $add, $options);
        $issues = $result->issues();
        if($result->hasErrors()){
            throw new RecordEditException(422, 'validation', 'The record has invalid values', $issues);
        }
        $rows = $result->rows();
        $stage = $this->codes['workflowStage'] ?? 0;
        if($stage > 0 && isset($replace[$stage])){ $plan['currentStage'] = $this->currentStage($recordId); }
        $byWorkflow = $this->applyWorkflow($plan, $rows, $issues);
        if(!$byWorkflow){
            $this->permissions->assertOwnerChange($plan['currentOwners'], $plan['owner'], $plan['editGroups']);
        }

        $newRows = array();
        foreach($rows as $row){ $newRows[intval($row['dty'])][] = $row; }
        $deleteFields = array();
        $deleteIds = array();
        $insert = array();
        $changed = array();
        $values = array();
        foreach($replace as $fieldId => $sent){
            $fieldRows = $newRows[$fieldId] ?? array();
            $stored = $counts[$fieldId] ?? 0;
            if(!isset($match[$fieldId])){
                $deleteFields[] = $fieldId;
                $remaining = count($fieldRows);
                $values[$fieldId] = array('found'=>$stored, 'added'=>count($fieldRows), 'removed'=>$stored);
            }elseif(!empty($matched[$fieldId])){
                $deleteIds = array_merge($deleteIds, $matched[$fieldId]);
                $remaining = $stored - count($matched[$fieldId]) + count($fieldRows);
                $found = count($matched[$fieldId]);
                $values[$fieldId] = array('found'=>$found, 'added'=>count($fieldRows), 'removed'=>$found);
            }elseif(in_array($fieldId, $addIfNone, true)){
                $remaining = $stored + count($fieldRows);
                $values[$fieldId] = array('found'=>0, 'added'=>count($fieldRows), 'removed'=>0);
            }else{
                $fieldRows = array(); // nothing matches: no change
                $remaining = $stored;
                $values[$fieldId] = array('found'=>0, 'added'=>0, 'removed'=>0);
            }
            $changed[$fieldId] = $values[$fieldId]['added'] + $values[$fieldId]['removed'];
            if($remaining === 0 && ($structure[$fieldId]['requirement'] ?? '') === 'required'){
                $name = $structure[$fieldId]['name'];
                throw new RecordEditException(422, 'validation', 'Field "'.$name.'" is required', array(
                    array('field'=>(string)$fieldId, 'index'=>null, 'code'=>'required', 'severity'=>'error',
                        'message'=>'Field "'.$name.'" is required: it cannot be deleted')
                ));
            }
            $insert = array_merge($insert, $fieldRows);
        }
        foreach($add as $fieldId => $sent){
            $fieldRows = $newRows[$fieldId] ?? array();
            $this->checkAdd($recordId, $rectype, $fieldId, $sent, $counts[$fieldId] ?? 0, $issues);
            $insert = array_merge($insert, $fieldRows);
            $changed[$fieldId] = count($fieldRows);
            $values[$fieldId] = array('found'=>$counts[$fieldId] ?? 0, 'added'=>count($fieldRows), 'removed'=>0);
        }
        $answer = array('recordId'=>$recordId, 'created'=>false, 'issues'=>$issues, 'changed'=>$changed, 'values'=>$values);
        if($dryRun){
            return $answer;
        }

        $changedFields = array_keys(array_filter($changed));
        $headerChanged = $this->headerChanged($plan, $input);
        $answer['header'] = $headerChanged;
        if(empty($changedFields) && !$headerChanged){
            // nothing matched and the header is as stored: no write, rec_Modified stays (plan 33)
            return $answer;
        }
        $affected = $this->dependencies !== null ? $this->dependencies->affected($rectype, $changedFields) : null;
        $childBefore = array();
        foreach($affected['childFields'] ?? array() as $fieldId){
            $childBefore[$fieldId] = $this->linkedIds($recordId, $fieldId);
        }
        $database = $this->store->database();
        $database->beginTransaction();
        try{
            $this->store->setUser($this->runtime->userId);
            $this->store->updateRecord($recordId, array(
                'owner' => $plan['owner'],
                'visibility' => $plan['visibility'],
                'url' => $plan['url'],
                'scratchPad' => $plan['scratchPad']
            ));
            if($byWorkflow || $input->has('rec_OwnerUGrpID') || $input->has('rec_EditGroups')
                || $input->has('rec_NonOwnerVisibility') || $input->has('rec_NonOwnerVisibilityGroups')
                || $plan['visibility'] !== (string)$plan['stored']['rec_NonOwnerVisibility']){
                $this->store->setPermissions($recordId, $plan['editGroups'], $plan['viewGroups']);
            }
            $this->store->deleteFields($recordId, $deleteFields);
            $this->store->deleteDetailIds($recordId, $deleteIds);
            $this->store->insertDetails($recordId, $insert);
            if(!empty($changedFields)){
                $this->recalculate($recordId, $rectype, $plan['stored'], $affected, $childBefore, $issues);
            }
            $database->commit();
        }catch(Throwable $exception){
            $database->rollback();
            throw $exception;
        }
        return array('issues'=>$issues) + $answer;
    }

    /** True when the input sets a header field (owner, visibility, groups, URL, scratchpad). */
    private function writesHeader(RecordInput $input): bool
    {
        foreach(array('rec_OwnerUGrpID', 'rec_NonOwnerVisibility', 'rec_NonOwnerVisibilityGroups',
            'rec_EditGroups', 'rec_URL', 'rec_ScratchPad') as $header){
            if($input->has($header)){ return true; }
        }
        return false;
    }

    /**
     * True when the sent header fields differ from the stored ones: owner, visibility, view and
     * edit groups, URL, scratchpad (the Actions tool reports unchanged records, plan 33).
     */
    private function headerChanged(array $plan, RecordInput $input): bool
    {
        if(!$this->writesHeader($input)){ return false; }
        $stored = $plan['stored'];
        $ids = static function(array $list): array {
            $list = array_values(array_unique(array_map('intval', $list)));
            sort($list);
            return $list;
        };
        if($plan['owner'] !== intval($plan['currentOwners']['owner'])
            || $plan['visibility'] !== (string)$stored['rec_NonOwnerVisibility']
            || ($input->has('rec_URL') && (string)$plan['url'] !== (string)($stored['rec_URL'] ?? ''))
            || ($input->has('rec_ScratchPad') && (string)$plan['scratchPad'] !== (string)($stored['rec_ScratchPad'] ?? ''))){
            return true;
        }
        $storedEdit = $this->withoutOwner($plan['currentOwners']['editGroups'] ?? array(), $plan['owner']);
        if($ids($plan['editGroups']) !== $ids($storedEdit)){ return true; }
        $storedView = $this->store->records()->owners(array($plan['recordId']))[$plan['recordId']]['viewGroups'] ?? array();
        return $ids($plan['viewGroups']) !== $ids($storedView);
    }

    // -------------------------------------------------------------- record actions (plan 33)

    /**
     * Fill the title of a record again from the title mask of its record type (Actions tool
     * "Rebuild record titles"). A changed title is written, with the titles of the children that
     * use it; a mask that gives no text is not written (status "blank").
     *
     * @return array{status:string,title:string} status changed | unchanged | blank
     * @throws RecordEditException 403, 404
     */
    public function rebuildTitle(int $recordId): array
    {
        $stored = $this->editableHeader($recordId);
        $rectype = intval($stored['rec_RecTypeID']);
        $old = (string)$stored['rec_Title'];
        $mask = trim((string)($this->definitions->rectype($rectype)['titleMask'] ?? ''));
        $title = $this->titleMask !== null && $mask !== '' ? $this->titleMask->fill($recordId) : null;
        if($title === null){
            if($mask !== '' && $this->titleMask !== null){
                return array('status' => 'blank', 'title' => $old);
            }
            $title = $this->titleFromValues($recordId);
        }
        if($title === $old){
            return array('status' => 'unchanged', 'title' => $old);
        }
        $database = $this->store->database();
        $database->beginTransaction();
        try{
            $this->store->setUser($this->runtime->userId);
            $this->store->setTitle($recordId, $title);
            if($this->titleMask !== null){
                $children = $this->childIds($recordId, $rectype);
                if(!empty($children)){ $this->refreshTitles($children, $recordId); }
            }
            $database->commit();
        }catch(Throwable $exception){
            $database->rollback();
            throw $exception;
        }
        return array('status' => 'changed', 'title' => $title);
    }

    /**
     * Run the calculated fields of a record again (Actions tool "Rebuild calculation fields");
     * only changed values are written (CalculatedFields), then the title and child titles when
     * they depend on a changed field (FieldDependencies).
     *
     * @return array{status:string,fields:array<int,int>,errors:array<int,string>} status changed |
     *         unchanged | error (a formula failed and nothing changed); errors = field => message
     * @throws RecordEditException 403, 404
     */
    public function rebuildCalculated(int $recordId): array
    {
        $stored = $this->editableHeader($recordId);
        $rectype = intval($stored['rec_RecTypeID']);
        if($this->calculated === null || empty($this->definitions->calculatedFields($rectype))){
            return array('status' => 'unchanged', 'fields' => array(), 'errors' => array());
        }
        $changed = array();
        $issues = array();
        $database = $this->store->database();
        $database->beginTransaction();
        try{
            $this->store->setUser($this->runtime->userId);
            $errors = $this->calculated->update($recordId, $rectype, null, $changed);
            if(!empty($changed)){
                $affected = $this->dependencies !== null ? $this->dependencies->affected($rectype, $changed) : null;
                if($affected !== null){ $affected['calc'] = array(); } // all were run just now
                $this->recalculate($recordId, $rectype, $stored, $affected, array(), $issues);
            }
            $database->commit();
        }catch(Throwable $exception){
            $database->rollback();
            throw $exception;
        }
        $status = !empty($changed) ? 'changed' : (!empty($errors) ? 'error' : 'unchanged');
        return array('status' => $status, 'fields' => $changed, 'errors' => $errors);
    }

    /**
     * Header of a record the user may edit.
     *
     * @throws RecordEditException 403, 404
     */
    private function editableHeader(int $recordId): array
    {
        $this->permissions->assertAccount('edit');
        $stored = $this->store->records()->header($recordId);
        if($stored === null){
            throw RecordEditException::notFound('Record '.$recordId.' does not exist');
        }
        $owners = $this->permissions->owners($recordId);
        if(!$this->permissions->canEditOwned($owners['owner'], $owners['editGroups'])){
            throw RecordEditException::denied('You cannot change record '.$recordId
                .'; you must be the database administrator or a member of a group that owns it');
        }
        return $stored;
    }

    /** Title from the values when there is no title mask (DT_NAME, else "Record NNN"). */
    private function titleFromValues(int $recordId): string
    {
        $rows = array_map(static fn($row) => array('dty' => $row['field'], 'value' => $row['value']),
            $this->store->records()->details(array($recordId)));
        return $this->title->fromRows($rows, $recordId);
    }

    /**
     * Checks of the PATCH options that need no values: one key per field, conditions and
     * addIfNone only for fields of "details", no condition on a field saved in parts.
     *
     * @throws RecordEditException 400
     */
    private function checkPatchOptions(int $rectype, array $replace, array $add, array $match, array $addIfNone): void
    {
        foreach(array_keys($add) as $fieldId){
            if(array_key_exists($fieldId, $replace)){
                throw RecordEditException::invalid('Field '.$fieldId.' is in "details" and in "detailsAdd"');
            }
        }
        foreach(array_merge(array_keys($match), $addIfNone) as $fieldId){
            if(!array_key_exists($fieldId, $replace)){
                throw RecordEditException::invalid('"detailsMatch" and "addIfNone" are for fields of "details"; field '.$fieldId.' is not there');
            }
        }
        foreach(array_keys($match) as $fieldId){
            if($this->isSplitField($rectype, $fieldId)){
                throw RecordEditException::invalid('Field '.$fieldId.' is saved in parts: replace or delete all its values (no "detailsMatch")');
            }
        }
    }

    /** Website page content and page translations are saved in parts (SplitValues). */
    private function isSplitField(int $rectype, int $fieldId): bool
    {
        $content = $this->codes['content'] ?? 0;
        $translations = $this->codes['cmsTranslations'] ?? 0;
        return ($translations > 0 && $fieldId === $translations)
            || ($content > 0 && $fieldId === $content && $rectype === ($this->codes['cmsPage'] ?? 0));
    }

    /**
     * Rules of "detailsAdd" for one field: multi-value only, not more than its maximum; an added
     * language that exists already is refused for the page translations (their parts would be
     * joined) and a warning for the other multilingual fields.
     *
     * @throws RecordEditException 422
     */
    private function checkAdd(int $recordId, int $rectype, int $fieldId, array $values, int $stored, array &$issues): void
    {
        $row = $this->definitions->structureRows($rectype)[$fieldId] ?? null;
        $name = $row['name'] ?? ($this->definitions->field($fieldId)['name'] ?? (string)$fieldId);
        // values in another language ("fre:") of a multilingual field do not count for rst_MaxValues (as in RecordValidator)
        $multilang = $this->validator->isMultilang($rectype, $fieldId);
        $existing = array();
        if($multilang){
            $stored = 0;
            foreach($this->store->records()->editValues($recordId, array($fieldId))[$fieldId] ?? array() as $value){
                $language = self::languageOf($value);
                if($language !== ''){ $existing[$language] = true; }else{ $stored++; }
            }
        }
        $given = count(array_filter($values, static function($value) use ($multilang){
            return $value !== null && $value !== '' && $value !== array() && (!$multilang || self::languageOf($value) === '');
        }));
        if($row !== null && $row['max'] === 1 && $given > 0){
            throw new RecordEditException(422, 'validation', 'Field "'.$name.'" has one value', array(
                array('field'=>(string)$fieldId, 'index'=>null, 'code'=>'single_value', 'severity'=>'error',
                    'message'=>'Field "'.$name.'" has one value: replace it with "details"')
            ));
        }
        if($row !== null && $row['max'] > 1 && !$this->isSplitField($rectype, $fieldId) && $stored + $given > $row['max']){
            throw new RecordEditException(422, 'validation', 'Field "'.$name.'" allows '.$row['max'].' values', array(
                array('field'=>(string)$fieldId, 'index'=>null, 'code'=>'max', 'severity'=>'error',
                    'message'=>'Field "'.$name.'" allows '.$row['max'].' values; it has '.$stored.', '.$given.' more given')
            ));
        }
        if(!$multilang){ return; }
        foreach($values as $index => $value){
            $language = self::languageOf($value);
            if($language === '' || !isset($existing[$language])){ continue; }
            if($fieldId === ($this->codes['cmsTranslations'] ?? 0)){
                throw new RecordEditException(422, 'validation', 'The translation "'.$language.'" exists', array(
                    array('field'=>(string)$fieldId, 'index'=>$index, 'code'=>'language_exists', 'severity'=>'error',
                        'message'=>'Field "'.$name.'" has a translation "'.$language.'" already: replace the field with "details"')
                ));
            }
            $issues[] = array('field'=>(string)$fieldId, 'index'=>$index, 'code'=>'language_exists', 'severity'=>'warning',
                'message'=>'Field "'.$name.'" has a value in language "'.$language.'" already');
        }
    }

    /** Language prefix ("fre:") of a value, lower case ('' = none). */
    private static function languageOf($value): string
    {
        $text = is_array($value) ? (string)($value['value'] ?? '') : (string)$value;
        return preg_match('/^([A-Za-z]{2,3}):/', $text, $found) ? strtolower($found[1]) : '';
    }

    /**
     * Value rows of a field that match a value condition of the query format.
     *
     * @return array<int,int> dtl_ID
     * @throws RecordEditException 422 for a condition the query compiler refuses
     */
    private function matchingIds(int $recordId, int $fieldId, string $condition): array
    {
        if(trim($condition) === ''){
            throw RecordEditException::invalid('The value condition of field '.$fieldId.' is empty');
        }
        if($this->matcher === null){
            $this->matcher = new FieldPredicateCompiler($this->store->database(), new SqlTables($this->store->records()->schema()));
        }
        $state = new SqlBuildContext();
        try{
            $sql = $this->matcher->valueCondition($fieldId, $condition, $state, 'd', null, true);
        }catch(QueryValidationException $exception){
            throw new RecordEditException(422, 'validation', $exception->getMessage(), array(
                array('field'=>(string)$fieldId, 'index'=>null, 'code'=>'match', 'severity'=>'error',
                    'message'=>'Value condition "'.$condition.'": '.$exception->getMessage())
            ));
        }
        $tables = new SqlTables($this->store->records()->schema());
        return $this->store->records()->matchingDetailIds($recordId, $fieldId, $tables->resolve($sql), $state->values());
    }

    /**
     * The record after the PATCH in the write format, for the record checks (CmsRecordCheck):
     * the stored values (split values joined) with the changes applied.
     */
    private function mergedDetails(int $recordId, int $rectype, array $replace, array $add, array $matched, array $addIfNone): array
    {
        $exclude = array();
        foreach($matched as $ids){ $exclude = array_merge($exclude, $ids); }
        $details = SplitValues::joinDetails($this->store->records()->editValues($recordId, null, $exclude), $rectype,
            $this->codes['cmsPage'] ?? 0, $this->codes['content'] ?? 0, $this->codes['cmsTranslations'] ?? 0);
        foreach($replace as $fieldId => $values){
            if(!array_key_exists($fieldId, $matched)){
                $details[$fieldId] = $values;
            }elseif(!empty($matched[$fieldId]) || in_array($fieldId, $addIfNone, true)){
                $details[$fieldId] = array_merge($details[$fieldId] ?? array(), $values);
            }
        }
        foreach($add as $fieldId => $values){
            $details[$fieldId] = array_merge($details[$fieldId] ?? array(), $values);
        }
        return $details;
    }

    /** Targets of the pointer values of one field of a record. */
    private function linkedIds(int $recordId, int $fieldId): array
    {
        return array_values(array_unique(array_map('intval', array_column(
            $this->store->records()->links(array($recordId), array('kind' => 'pointer', 'fields' => array($fieldId))), 1
        ))));
    }

    /**
     * Calculated fields, title and child titles after a PATCH, only where FieldDependencies
     * says they depend on the changed fields (no dependencies known: title and all calculated fields).
     *
     * @param array|null $affected FieldDependencies::affected().
     * @param array<int,array<int,int>> $childBefore Child pointer field => targets before the change.
     */
    private function recalculate(int $recordId, int $rectype, array $stored, ?array $affected, array $childBefore, array &$issues): void
    {
        if($this->calculated !== null && ($affected === null || !empty($affected['calc']))){
            foreach($this->calculated->update($recordId, $rectype, $affected['calc'] ?? null) as $fieldId => $message){
                $issues[] = array('field'=>(string)$fieldId, 'index'=>0, 'code'=>'calculation', 'severity'=>'warning',
                    'message'=>'Calculated field '.$fieldId.' was not updated: '.$message);
            }
        }
        $oldTitle = (string)($stored['rec_Title'] ?? '');
        $newTitle = $oldTitle;
        if($affected === null || $affected['title']){
            $newTitle = $this->titleMask !== null ? $this->titleMask->fill($recordId) : null;
            if($newTitle === null){
                $rows = array_map(static fn($row) => array('dty' => $row['field'], 'value' => $row['value']),
                    $this->store->records()->details(array($recordId)));
                $newTitle = $this->title->fromRows($rows, $recordId);
            }
            if($newTitle !== $oldTitle){ $this->store->setTitle($recordId, $newTitle); }
        }
        if($affected === null || $this->titleMask === null){ return; }

        $pending = array();
        $types = $affected['childTypes'];
        if($newTitle !== $oldTitle){ $types = array_merge($types, $affected['childOnTitle']); }
        if(!empty($types)){
            $children = $this->childIds($recordId, $rectype);
            if(!empty($children)){
                foreach($this->store->records()->headers($children, array('rec_ID', 'rec_RecTypeID')) as $id => $header){
                    if(in_array(intval($header['rec_RecTypeID']), $types, true)){ $pending[] = intval($id); }
                }
            }
        }
        foreach($childBefore as $fieldId => $before){
            $after = $this->linkedIds($recordId, $fieldId);
            $pending = array_merge($pending, array_diff($before, $after), array_diff($after, $before));
        }
        if(!empty($pending)){ $this->refreshTitles($pending, $recordId); }
    }

    /**
     * Workflow stage rules (legacy recordWorkFlowStage without emails). Changes the
     * owner and visibility of the plan when the new stage is allowed; returns true
     * when the owner comes from the rule (no ownership checks then).
     */
    private function applyWorkflow(array &$plan, array &$rows, array &$issues): bool
    {
        $stageField = $this->codes['workflowStage'] ?? 0;
        if($stageField < 1){ return false; }
        $index = null;
        foreach($rows as $position => $row){
            if(intval($row['dty']) === $stageField){ $index = $position; break; }
        }
        if($index === null){ return false; }
        $newStage = intval($rows[$index]['value']);
        if($newStage < 1 || $newStage === $plan['currentStage']){ return false; }

        $rule = $this->definitions->workflowRule($plan['rectype'], $newStage);
        $allowed = false;
        if($rule !== null){
            $restricted = array_filter(array_map('intval', explode(',', $rule['restrictedTo'])));
            $allowed = empty($restricted) || $this->runtime->isAdmin
                || !empty(array_intersect($restricted, $this->permissions->memberOf()));
        }
        if(!$allowed){
            if($plan['currentStage'] > 0){
                $rows[$index]['value'] = (string)$plan['currentStage'];
                $issues[] = array('field'=>(string)$stageField, 'index'=>0, 'code'=>'workflow', 'severity'=>'warning',
                    'message'=>'You cannot set this workflow stage; the stage is not changed');
            }
            return false;
        }
        $byWorkflow = false;
        if($rule['setOwnership'] !== null && $rule['setOwnership'] >= 0){
            $plan['owner'] = $rule['setOwnership'];
            $plan['editGroups'] = $this->withoutOwner($plan['editGroups'], $plan['owner']);
            $byWorkflow = true;
        }
        $visibility = $rule['setVisibility'];
        if($visibility !== ''){
            if(in_array($visibility, array('public', 'viewable', 'hidden'), true)){
                $plan['visibility'] = $visibility;
                if($visibility !== 'viewable'){ $plan['viewGroups'] = array(); }
            }else{
                $plan['visibility'] = 'viewable';
                $plan['viewGroups'] = array_values(array_filter(array_map('intval', explode(',', (string)$visibility))));
            }
        }
        return $byWorkflow;
    }

    /** @throws RecordEditException 422 for an unknown visibility value */
    private function assertVisibility(string $visibility): void
    {
        if(!RecordValidator::isVisibility($visibility)){
            throw new RecordEditException(422, 'validation', 'Invalid non-owner visibility "'.$visibility.'"', array(
                array('field'=>'rec_NonOwnerVisibility', 'index'=>null, 'code'=>'visibility', 'severity'=>'error',
                    'message'=>'Use viewable, hidden, public or pending')
            ));
        }
    }

    /** Edit groups without the owner itself. */
    private function withoutOwner(array $groups, int $owner): array
    {
        return array_values(array_filter($groups, static function($group) use ($owner){ return $group !== $owner; }));
    }

    /** Trimmed URL without tags and control characters (null when empty). */
    private function url($value): ?string
    {
        $value = trim(strip_tags((string)$value));
        $value = (string)preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        return $value === '' ? null : mb_substr($value, 0, 2000);
    }

    private function text($value): ?string
    {
        $value = \Heurist\Utilities\StoredText::clean((string)$value, 'blocktext');
        return $value === '' ? null : $value;
    }

    /** Database setting sys_SetPublicToPendingOnEdit. */
    private function publicToPending(): bool
    {
        return DatabaseSettings::shared($this->store->database())->publicToPendingOnEdit();
    }
}
