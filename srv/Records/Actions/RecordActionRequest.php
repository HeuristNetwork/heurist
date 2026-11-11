<?php
/**
* RecordActionRequest.php - Parameters of the background job "record-action" (plan 33)
*
*   action     field-add | field-replace | field-delete | ownership | titles | calculations | delete
*   title      text for job lists
*   scope      {query, ids?, rectypes?} as the export: ids (the selection) replace the query
*   field values:
*     field      field id (freetext, blocktext, date, float or enum)
*     values     new values (add, replace; not delete)
*     match      condition of the query format (=x, x, x%, -x, <>a,b ...), replace / delete only;
*                empty = all values of the field
*     addIfNone  replace with a condition: add the new values when no value matches
*   ownership:   owner (0 = any logged-in user), visibility (hidden|viewable|public|pending),
*                viewGroups (the groups that can view a "viewable" record)
*   delete:      password (the server password above DeleteGuard::FREE records); read by the job
*                in prepare() and never stored (toArray() leaves it out)
*
* @project     Heurist academic knowledge management system
* @package     Records\Actions
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Actions;

use InvalidArgumentException;

/** Validated parameters of a record action. */
final class RecordActionRequest
{
    /** Actions that are implemented. */
    public const ACTIONS = array('field-add', 'field-replace', 'field-delete', 'ownership', 'titles', 'calculations', 'delete');

    /** Field types the field value actions change. */
    public const FIELD_TYPES = array('freetext', 'blocktext', 'date', 'float', 'enum');

    /** Visibility values of the ownership action. */
    public const VISIBILITY = array('hidden', 'viewable', 'public', 'pending');

    public string $action;
    public string $title;
    /** @var mixed Query of the data source */
    public $query;
    /** @var int[] */
    public array $ids;
    /** @var int[] */
    public array $rectypes;
    public int $field = 0;
    /** @var string[] */
    public array $values = array();
    public string $match = '';
    public bool $addIfNone = false;
    public int $owner = 0;
    public string $visibility = 'viewable';
    /** @var int[] */
    public array $viewGroups = array();
    /** Server password of a large delete; never stored. */
    public string $password = '';

    /**
     * @param array $params Job parameters.
     * @throws InvalidArgumentException Invalid parameters (400).
     */
    public function __construct(array $params)
    {
        $this->action = (string)($params['action'] ?? '');
        if(!in_array($this->action, self::ACTIONS, true)){
            throw new InvalidArgumentException('Unknown record action "'.$this->action.'"');
        }
        $this->title = trim((string)($params['title'] ?? ''));
        $scope = is_array($params['scope'] ?? null) ? $params['scope'] : array();
        $this->query = $scope['query'] ?? null;
        $this->ids = self::idList($scope['ids'] ?? array());
        $this->rectypes = self::idList($scope['rectypes'] ?? array());
        if(empty($this->ids) && ($this->query === null || $this->query === '' || $this->query === array())){
            throw new InvalidArgumentException('The scope has no query and no records');
        }
        if($this->isFieldAction()){
            $this->readFieldValues($params);
        }elseif($this->action === 'ownership'){
            $this->owner = max(0, intval($params['owner'] ?? 0));
            $this->visibility = (string)($params['visibility'] ?? 'viewable');
            if(!in_array($this->visibility, self::VISIBILITY, true)){
                throw new InvalidArgumentException('Invalid visibility "'.$this->visibility.'"');
            }
            $this->viewGroups = $this->visibility === 'viewable' ? self::idList($params['viewGroups'] ?? array()) : array();
        }elseif($this->action === 'delete'){
            $this->password = (string)($params['password'] ?? '');
        }
    }

    /** True for field-add, field-replace and field-delete. */
    public function isFieldAction(): bool
    {
        return strpos($this->action, 'field-') === 0;
    }

    /** The same request with the scope replaced by these ids (delete: the confirmed records). */
    public function withIds(array $ids): self
    {
        $copy = clone $this;
        $copy->ids = self::idList($ids);
        $copy->query = null;
        $copy->rectypes = array();
        return $copy;
    }

    /** Normalized parameters stored with the job (never the password). */
    public function toArray(): array
    {
        $params = array(
            'action' => $this->action,
            'title' => $this->title,
            'scope' => array('query' => $this->query, 'ids' => $this->ids, 'rectypes' => $this->rectypes)
        );
        if($this->isFieldAction()){
            $params += array('field' => $this->field, 'values' => $this->values, 'match' => $this->match, 'addIfNone' => $this->addIfNone);
        }elseif($this->action === 'ownership'){
            $params += array('owner' => $this->owner, 'visibility' => $this->visibility, 'viewGroups' => $this->viewGroups);
        }
        return $params;
    }

    /**
     * PATCH body of one record: the field value operation of plan 32, or the header of the
     * ownership action (the stored edit groups are kept).
     *
     * @return array
     */
    public function patchBody(): array
    {
        if($this->action === 'ownership'){
            return array(
                'rec_OwnerUGrpID' => $this->owner,
                'rec_NonOwnerVisibility' => $this->visibility,
                'rec_NonOwnerVisibilityGroups' => $this->viewGroups
            );
        }
        $field = (string)$this->field;
        if($this->action === 'field-add'){
            return array('detailsAdd' => array($field => $this->values));
        }
        $body = array('details' => array($field => $this->values));
        if($this->match !== ''){ $body['detailsMatch'] = array($field => $this->match); }
        if($this->addIfNone){ $body['addIfNone'] = array($field); }
        return $body;
    }

    private function readFieldValues(array $params): void
    {
        $this->field = intval($params['field'] ?? 0);
        if($this->field < 1){
            throw new InvalidArgumentException('"field" must be a field id');
        }
        $values = $params['values'] ?? array();
        if(!is_array($values)){ $values = array($values); }
        foreach($values as $value){
            if(!is_scalar($value)){
                throw new InvalidArgumentException('"values" must be a list of texts');
            }
            if(trim((string)$value) !== ''){ $this->values[] = (string)$value; }
        }
        $this->match = trim((string)($params['match'] ?? ''));
        $this->addIfNone = !empty($params['addIfNone']);

        if($this->action === 'field-delete'){
            $this->values = array();
        }elseif(empty($this->values)){
            throw new InvalidArgumentException('A value is required');
        }
        if($this->action === 'field-add'){
            $this->match = '';
        }
        if($this->action !== 'field-replace' || $this->match === ''){
            $this->addIfNone = false;
        }
    }

    /** @return int[] */
    private static function idList($value): array
    {
        if(is_string($value)){ $value = explode(',', $value); }
        if(!is_array($value)){ return array(); }
        $ids = array();
        foreach($value as $id){
            $id = intval($id);
            if($id > 0){ $ids[$id] = $id; }
        }
        return array_values($ids);
    }
}
