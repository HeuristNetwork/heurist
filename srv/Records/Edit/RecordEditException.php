<?php
/**
* RecordEditException.php - Failure of a record write with its HTTP meaning
*
* Carries the HTTP status, a short error code, the field issues of a failed
* validation and extra response values (references of a refused delete,
* the stored rec_Modified of a conflict).
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

use RuntimeException;

/** Record write refused or failed (400/401/403/404/409/422). */
final class RecordEditException extends RuntimeException
{
    private int $status;
    private string $errorCode;
    /** @var array<int,array> */
    private array $issues;
    private array $extra;

    /**
     * @param int $status HTTP status.
     * @param string $errorCode Short code: invalid_request, access_denied, not_found, conflict, references, validation.
     * @param string $message Message for people.
     * @param array<int,array> $issues Field issues {field, index, code, severity, message}.
     * @param array $extra Extra response values.
     */
    public function __construct(int $status, string $errorCode, string $message, array $issues = array(), array $extra = array())
    {
        parent::__construct($message);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->issues = $issues;
        $this->extra = $extra;
    }

    public static function invalid(string $message): self
    {
        return new self(400, 'invalid_request', $message);
    }

    public static function denied(string $message): self
    {
        return new self(403, 'access_denied', $message);
    }

    public static function notFound(string $message): self
    {
        return new self(404, 'not_found', $message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<int,array> */
    public function issues(): array
    {
        return $this->issues;
    }

    public function extra(): array
    {
        return $this->extra;
    }
}
