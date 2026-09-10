<?php
/**
* DefinitionController.php - Database-definitions HTTP adapter
*
* Serves the read-only structure snapshot for the modern /api/{db}/def family.
* This is the first /def service; it will eventually supersede the legacy
* /api/{db}/rty|dty|trm|rst|trl definition routes.
*
* @project     Heurist academic knowledge management system
* @package     Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\Controller;

use Heurist\Records\Query\QueryValidationException;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use Heurist\Definitions\DefinitionSnapshotService;

/** Writes public-compatible responses for /api/{db}/def. */
final class DefinitionController
{
    private DefinitionSnapshotService $snapshots;
    private RuntimeContext $runtime;
    private ApiResponse $response;
    private ErrorReporter $errors;

    public function __construct(
        DefinitionSnapshotService $snapshots,
        RuntimeContext $runtime,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->snapshots = $snapshots;
        $this->runtime = $runtime;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
    }

    /** Execute without writing output, primarily for integration tests. */
    public function execute(array $params, ?string $type = null): array
    {
        $type = strtolower(trim((string)($type ?? 'snapshot')));
        if($type === '' || $type === 'snapshot'){
            return $this->snapshots->get($params);
        }
        throw new QueryValidationException('Unknown definition resource: '.$type);
    }

    /** Execute and emit one definition response, honouring conditional GETs. */
    public function output(array $params, ?string $type = null): void
    {
        try{
            $result = $this->execute($params, $type);
            $etag = '"'.($result['meta']['version'] ?? '0').'"';

            if(defined('HEADER_CORS_POLICY')){ header(HEADER_CORS_POLICY); }
            header('ETag: '.$etag);
            header('Cache-Control: no-cache, must-revalidate');

            $ifNoneMatch = (string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
            if($ifNoneMatch !== '' && $this->normalizeEtag($ifNoneMatch) === $this->normalizeEtag($etag)){
                http_response_code(304);
                return;
            }
            $this->response->send($result);
        }catch(QueryValidationException $error){
            $this->response->sendError(400, 'invalid_request', $error->getMessage());
        }catch(\Throwable $error){
            $this->errors->report($error, $this->runtime);
            $this->response->sendError(500, 'server_error', 'Definition query execution failed');
        }
    }

    private function normalizeEtag(string $value): string
    {
        return trim(preg_replace('/^W\//', '', trim($value)), '"');
    }
}
