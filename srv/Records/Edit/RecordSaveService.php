<?php
/**
* RecordSaveService.php - Insert and update of one record (POST / PUT / PATCH)
*
* Port of recordSave() and recordAdd() of hserv/records/edit/recordModify.php
* for every record type (plan 21: the list of writable system types was removed):
*   - insert: rec_ID 0 takes the next id, a negative rec_ID is used as the id
*     (import); owner is the current user and visibility "viewable" unless the
*     body gives them; website pages (RT_CMS_PAGE) belong to group 1 and are public;
*   - update: PUT replaces the whole record (missing fields and groups are
*     cleared), PATCH replaces only the fields in the body ([] clears one) and
*     keeps missing headers; rec_Modified of the body must match the stored one
*     unless forced (409);
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
use Heurist\Records\Store\RecordStore;
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
    /** @var array<string,int> cmsPage, workflowStage */
    private array $codes;

    /**
     * @param array<string,int> $codes Local ids: cmsPage (RT_CMS_PAGE), workflowStage (DT_WORKFLOW_STAGE).
     * @param TitleMask|null $titleMask Title from the mask of the record type (null: DT_NAME only).
     * @param CalculatedFields|null $calculated Calculated fields run on save (null: none).
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
        ?CalculatedFields $calculated = null
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
        $plan = $insert ? $this->planInsert($pathId, $input) : $this->planUpdate($pathId, $input, $method === 'PATCH', $force);

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
            $database->commit();
        }catch(Throwable $exception){
            $database->rollback();
            throw $exception;
        }
        return array('recordId'=>$recordId, 'created'=>$insert, 'issues'=>$issues);
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
        if($rectype === ($this->codes['cmsPage'] ?? -1)){
            // website pages: database managers group, public (legacy recordAdd)
            $owner = 1;
            $visibility = 'public';
        }
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

    /** Values of a changed record (PUT or PATCH). */
    private function planUpdate(int $recordId, RecordInput $input, bool $patch, bool $force): array
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

        $forbidden = array();
        foreach($this->definitions->structureRows($rectype) as $fieldId => $row){
            if($row['requirement'] === 'forbidden'){ $forbidden[] = $fieldId; }
        }
        if($patch){
            $details = $this->store->records()->editValues($recordId);
            foreach($input->details() as $fieldId => $values){ $details[$fieldId] = $values; }
            foreach($forbidden as $fieldId){ unset($details[$fieldId]); }
        }else{
            $details = $input->details();
        }
        $stage = $this->codes['workflowStage'] ?? 0;
        $currentStage = $stage > 0 ? intval($this->store->records()->editValues($recordId, array($stage))[$stage][0] ?? 0) : 0;

        $keepUrl = $patch && !$input->has('rec_URL');
        $keepScratchPad = $patch && !$input->has('rec_ScratchPad');
        return array(
            'recordId' => $recordId,
            'rectype' => $rectype,
            'owner' => $owner,
            'currentOwners' => $owners,
            'editGroups' => $this->withoutOwner($editGroups, $owner),
            'visibility' => $visibility,
            'viewGroups' => $viewGroups,
            'url' => $keepUrl ? $stored['rec_URL'] : $this->url($input->header('rec_URL')),
            'scratchPad' => $keepScratchPad ? $stored['rec_ScratchPad'] : $this->text($input->header('rec_ScratchPad')),
            'details' => $details,
            'keepFields' => $forbidden,
            'currentStage' => $currentStage
        );
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
        $value = (string)$value;
        return trim($value) === '' ? null : $value;
    }

    /** Database setting sys_SetPublicToPendingOnEdit. */
    private function publicToPending(): bool
    {
        return DatabaseSettings::shared($this->store->database())->publicToPendingOnEdit();
    }
}
