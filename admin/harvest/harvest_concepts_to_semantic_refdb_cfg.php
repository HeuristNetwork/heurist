<?php
/*
* Configuration of Heurist servers to be scanned by harvest_concepts_to_semantic_refdb.php
*/
return [
     'target' => [
         'dbHost' => '127.0.0.1',
         'dbPort' => 3306,
         'database' => 'hdb_osmak_core2',  
         'isSingleGroup' => 1,
         //'database' => 'hdb_Heurist_Concept_Definitions',
         //'dbAdminUsername' => '',
         //'dbAdminPassword' =>  '',
     ],
     'sources' => [
         [
             'server' => 'http://127.0.0.1/heurist/',
             'registryDatabase' => 'Heurist_Job_Tracker',  // API entry point
             //'username' => '2',
             //'password' => '',
         ]
         /*,
         [
             'server' => 'https://heurist.huma-num.fr/h7-alpha/',
             'registryDatabase' => 'osmak_3',
         ],
         [
             'server' => 'https://heuristau.net/h7-alpha/',
             'registryDatabase' => 'osmak_3',
         ],
         */
     ],
 ];
