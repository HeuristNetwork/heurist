<?php

declare(strict_types=1);

/**
 * harvest_semantics_global.php
 *
 * Experimental global-pass semantic definitions harvester.
 *
 * This script does not replace harvest_concepts_to_semantic_refdb_api.php.
 * It implements the newer pass-based approach:
 *   1. create target-only semantic tracking tables
 *   2. import RTY/DTY/TRM from origin DBs first
 *   3. import missing RTY/DTY/TRM from first encountered derived copies
 *   4. repair references using target-local IDs
 *   5. import origin RST structures
 *   6. append additional RTY+DTY structures as pending
 */

require_once __DIR__ . '/HarvestConfig.php';
require_once __DIR__ . '/HarvestLogger.php';
require_once __DIR__ . '/HarvestHelpers.php';
require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/TargetRepository.php';
require_once __DIR__ . '/SemanticSchema.php';
require_once __DIR__ . '/SemanticMapRepository.php';
require_once __DIR__ . '/SemanticEntityImporter.php';
require_once __DIR__ . '/SemanticStructureImporter.php';
require_once __DIR__ . '/SemanticReferenceRepair.php';
require_once __DIR__ . '/SemanticTermLinksImporter.php';
require_once __DIR__ . '/SemanticGlobalHarvester.php';

set_time_limit(0);//no limit

main();

function main(): void
{
    if (PHP_SAPI !== 'cli') {
        write_err("This script must be run from the command line.\n");
        //exit(1);
    }

    initialiseLogFile();

    try {
        $cfg = loadConfig(CONFIG_FILE);
        $targetDbName = (string)$cfg['target']['database'];
        $targetPdo = connectTarget($cfg['target']);

        // The semantic tracking tables are target-harvester specific and must
        // exist before any import pass starts. If this is the first global
        // harvest run, existing def* content is unsafe because it has no
        // semantic map, so the schema preparer clears it first.
        (new SemanticSchema($targetPdo))->prepareTargetForRun();

        $isSingleGroup = resolveSingleGroupOption($cfg);
        if ($isSingleGroup) {
            logLine('Group mode: single imported-definition group per definition table');
        } else {
            logLine('Group mode: origin/imported-via database groups');
        }

        $targetRepo = new TargetRepository($targetPdo);
        $semanticRepo = new SemanticMapRepository($targetPdo);
        $client = new ApiClient();
        $entityImporter = new SemanticEntityImporter($targetRepo, $semanticRepo, $isSingleGroup);
        $structureImporter = new SemanticStructureImporter($targetRepo, $semanticRepo);
        $referenceRepair = new SemanticReferenceRepair($client, $targetRepo, $semanticRepo);
        $termLinksImporter = new SemanticTermLinksImporter($client, $semanticRepo);

        $harvester = new SemanticGlobalHarvester(
            $client,
            $targetPdo,
            $targetRepo,
            $semanticRepo,
            $entityImporter,
            $structureImporter,
            $referenceRepair,
            $termLinksImporter,
            $targetDbName
        );
    } catch (Throwable $e) {
        logError('Initialisation failed: ' . $e->getMessage());
        exit(1);
    }

    logRunHeader($cfg['sources']);

    try {
        $harvester->run($cfg['sources']);
    } catch (Throwable $e) {
        logError('Global semantic harvest failed: ' . $e->getMessage());
        exit(1);
    }

    logLine(str_repeat('=', 90));
    logLine('Global semantic harvest completed');
}


function resolveSingleGroupOption(array $cfg): bool
{
    $value = null;

    if (isset($cfg['target']['isSingleGroup'])) {
        $value = $cfg['target']['isSingleGroup'];
    }

    if ($value === null) {
        return false;
    }

    if (is_bool($value)) {
        return $value;
    }

    $value = strtolower(trim((string)$value));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}
