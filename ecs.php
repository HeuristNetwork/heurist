<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/admin',
        // __DIR__ . '/data',
        __DIR__ . '/docker',
        __DIR__ . '/export',
        __DIR__ . '/hclient',
        __DIR__ . '/hserv',
        __DIR__ . '/import',
        __DIR__ . '/movetoparent',
        __DIR__ . '/redirects',
        __DIR__ . '/server_management',
        __DIR__ . '/srv',
        __DIR__ . '/startup',
        __DIR__ . '/viewers',
    ])
    // include *.php files in the root directory
    ->withRootFiles()

    // add a single rule
    // ->withRules([
    //     NoUnusedImportsFixer::class,
    // ])

    // add sets - group of rules, from easiest to more complex ones
    // uncomment one, apply one, commit, PR, merge and repeat
    //->withPreparedSets(
    //      spaces: true,
    //      namespaces: true,
    //      docblocks: true,
    //      arrays: true,
    //      comments: true,
    //)

    // ...but first: take it step by step
    ->withSpacesLevel(1)
    ->withArrayLevel(0)
    ->withControlStructuresLevel(0)
    ->withDocblockLevel(6);
