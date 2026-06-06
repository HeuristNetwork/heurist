<?php
/*
# Configuration of Heurist servers to be scanned by harvest_concepts_to_semantic_refdb.php

# PLACE THIS FILE IN /var/www/html/HEURIST and configure pserver access below
# Since it contains passwords it must be inaccessible to browse 

# There is no error checking on this file, as it will be set up only once

# each server starts with externalServer indicating an IP address or server domain, if this is a blank value it means the current server
# Server 1 (server on which this is running. dbhost blank indicates local host)
    externalServer  = ''
    dbPort = 3307
    dbAdminUsername = 'heurist'
    dbAdminPassword = '<password to be defined>>'

# Server 2 (a remote server containing Heurist databases)
    externalServer  = 'heurist.huma-num.fr' 
    dbPort = 3307
    dbAdminUsername = 'heurist'
    dbAdminPassword = '<password to be defined>'

# add more servers here following pattern above
*/
$parentIni = __DIR__."/../../../heuristConfigIni.php";
if (is_file($parentIni) && file_exists($parentIni)){
    include_once $parentIni; //include_once
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

