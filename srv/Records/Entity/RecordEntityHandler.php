<?php
/**
* RecordEntityHandler.php - Hooks and actions of one record-based entity (plan 28 §6, plan 27d)
*
* An entity (website, page, ...) is a record type with a name in the URL:
* /api/{db}/{entity}/{id}/{action}. Reads and writes go through the generic
* record controllers and services; a handler only adds what is special to the
* record type:
* - hooks around the generic save and delete (defaults of a new record, records
*   created with it, cascade on delete); RecordEditController runs them for every
*   write of the record type, also through /records/{id};
* - describe(): values added to meta of GET /{entity}/{id};
* - validate(): extra problems for GET /{entity}/{id}/validate;
* - action(): specific actions (assign, import, setup ...).
* The defaults do nothing; an entity overrides what it needs.
*
* @project     Heurist academic knowledge management system
* @package     Records\Entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Entity;

use Heurist\Records\Edit\RecordEditException;

/** Hooks and actions of a record-based entity; the defaults do nothing. */
abstract class RecordEntityHandler
{
    /**
     * Before an insert (POST), update (PUT, PATCH) or validation: may change the body. It may
     * write records the saved one needs (e.g. a page its required menu points to), but not when
     * $dryRun; saveFailed() undoes them.
     *
     * @param string $method POST, PUT or PATCH.
     * @param int $id Record id of the URL (0 or negative for an insert).
     * @param array $body Record object of the request.
     * @param array $params Request parameters.
     * @param bool $dryRun Only validated, nothing is saved.
     * @return array The body to save.
     * @throws RecordEditException
     */
    public function beforeSave(string $method, int $id, array $body, array $params, bool $dryRun = false): array
    {
        return $body;
    }

    /** The save after beforeSave() failed: undo what beforeSave() wrote. */
    public function saveFailed(string $method, array $body): void
    {
    }

    /**
     * After a saved insert or update (own transaction).
     *
     * @param int $id The saved record.
     * @param array $body The body that was saved.
     * @return array<int,array> Warnings added to meta.issues.
     * @throws RecordEditException
     */
    public function afterSave(string $method, int $id, array $body, array $params): array
    {
        return array();
    }

    /**
     * Before a delete; the answer is given to afterDelete().
     *
     * @throws RecordEditException
     */
    public function beforeDelete(int $id, array $params): array
    {
        return array();
    }

    /**
     * After a delete.
     *
     * @param array $state Answer of beforeDelete().
     * @param array $result Answer of RecordDeleteService::delete().
     * @return array The answer of the request.
     */
    public function afterDelete(int $id, array $params, array $state, array $result): array
    {
        return $result;
    }

    /**
     * Values added to meta of GET /{entity}/{id}.
     *
     * @return array<string,mixed>
     */
    public function describe(int $id): array
    {
        return array();
    }

    /**
     * Problems of a stored record for GET /{entity}/{id}/validate.
     *
     * @return array<int,array>
     */
    public function validate(int $id, array $params): array
    {
        return array();
    }

    /**
     * A specific action: /{entity}/{id}/{action}, or /{entity}/{action} for the whole entity (id 0).
     *
     * @param array $body Decoded JSON body (empty for GET).
     * @return array The answer.
     * @throws RecordEditException 404 for an unknown action, 405 for a wrong method.
     */
    public function action(string $action, string $method, int $id, array $params, array $body): array
    {
        throw new RecordEditException(404, 'not_found', 'Unknown action: '.$action);
    }

    /** @throws RecordEditException 405 */
    protected static function allow(string $method, array $allowed): void
    {
        if(!in_array($method, $allowed, true)){
            throw new RecordEditException(405, 'method_not_allowed', 'Use '.implode(' or ', $allowed));
        }
    }
}
