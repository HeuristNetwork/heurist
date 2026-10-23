<?php
/**
* EntityWriteService.php - Base of the write services of system entities in the record format
*
* The writes of /files and /sys (plan 20, §8.3, §10.4) take a record as GET gives
* it (optionally wrapped as {"records":[one]}), POST to id 0 for a new one and
* PUT / PATCH for a change, with rec_Modified as the concurrency token (409 when
* the stored row is newer, unless forced). This class holds these shared steps;
* a subclass maps the record to its table and adds its own checks.
*
* @project     Heurist academic knowledge management system
* @package     System\Entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\System\Entity;

use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Edit\RecordInput;
use Heurist\Runtime\RuntimeContext;

/** Shared steps of the entity writes. */
abstract class EntityWriteService
{
    protected RuntimeContext $runtime;

    public function __construct(RuntimeContext $runtime)
    {
        $this->runtime = $runtime;
    }

    /** Name of the entity in messages ("file", "tag"). */
    abstract protected function noun(): string;

    /** Route of the entity in messages ("files", "sys/tag"). */
    abstract protected function route(): string;

    /**
     * The record of a request body: one record, or {"records": [one]}.
     *
     * @throws RecordEditException 400
     */
    protected function unwrap(?array $body): array
    {
        $body = $body ?? array();
        if(isset($body['records']) && is_array($body['records']) && count($body['records']) === 1){
            $body = array_values($body['records'])[0];
            if(!is_array($body)){ throw RecordEditException::invalid('The record must be a JSON object'); }
        }
        return $body;
    }

    /**
     * Parsed record of a request for the entity of the path.
     *
     * @throws RecordEditException 400 for another rec_ID than the path, or a body that is not a record.
     */
    protected function input(array $body, int $id): RecordInput
    {
        $input = RecordInput::fromBody($body);
        if($input->has('rec_ID') && $input->intHeader('rec_ID') !== $id){
            throw RecordEditException::invalid('rec_ID of the body is not the '.$this->noun().' of the path');
        }
        return $input;
    }

    /**
     * POST creates (id 0), PUT and PATCH change an existing one.
     *
     * @throws RecordEditException 400
     */
    protected function assertMethod(string $method, int $id): void
    {
        if($id < 1){
            if($method !== 'POST'){
                throw RecordEditException::invalid('A new '.$this->noun().' is added with POST /'.$this->route().'/0');
            }
            return;
        }
        if($method === 'POST'){ throw RecordEditException::invalid('Change a '.$this->noun().' with PUT or PATCH'); }
        if(!in_array($method, array('PUT', 'PATCH'), true)){ throw RecordEditException::invalid('Unsupported method '.$method); }
    }

    /** @throws RecordEditException 403 */
    protected function assertAccount(string $message): void
    {
        if($this->runtime->userId < 1){ throw RecordEditException::denied($message); }
    }

    /**
     * rec_Modified of the request against the stored one (the concurrency token).
     *
     * @throws RecordEditException 409 with the stored rec_Modified
     */
    protected function assertNotChanged(RecordInput $input, string $stored, bool $force): void
    {
        if($input->has('rec_Modified') && !$force && (string)$input->header('rec_Modified') !== $stored){
            throw new RecordEditException(409, 'conflict', 'The '.$this->noun().' was changed by someone else', array(),
                array('rec_Modified' => $stored));
        }
    }

    /** A warning of a save (the write is done). */
    protected function issue(string $code, string $message): array
    {
        return array('code' => $code, 'severity' => 'warning', 'message' => $message);
    }
}
