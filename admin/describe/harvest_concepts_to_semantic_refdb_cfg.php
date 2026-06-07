<?php
/*
* Configuration of Heurist servers to be scanned by harvest_concepts_to_semantic_refdb.php
*/
$parentIni = __DIR__."/../../../heuristConfigIni.php";
if (is_file($parentIni) && file_exists($parentIni)){
    include_once $parentIni;
}

return [
     'target' => [
         'dbHost' => '127.0.0.1',
         'dbPort' => 3306,
         'dbAdminUsername' => $dbAdminUsername,
         'dbAdminPassword' =>  $dbAdminPassword,
         // 'database' => 'hdb_Heurist_Concept_Definitions', // optional
     ],
     'sources' => [
         [
             'server' => 'http://127.0.0.1/heurist/',
             'registryDatabase' => 'osmak_core',
             'username' => '2',
             'password' => $passwordForDatabaseAccess,
         ],
         /*
         [
             'server' => 'https://heurist.huma-num.fr/h7-alpha/',
             'registryDatabase' => 'osmak_1',
         ],
         */
     ],
 ];

