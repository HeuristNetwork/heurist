
Directory:    /hserv/controller

Overview:   All files in this folder are services. They are utilized in hapi.js to obtain various data (in json format) from server side.



utilsCollection.js - manages user's collection of record ids stored in SESSION


--------------
usr_info.php - controller for request from client side HAPI4.hSystemMgr (@todo rename to "system something")





Notes:      Work process:
            1. check permission
            2. parse  $_REQUEST
            3. call functions from   common/db_***.php
            4. json response

            
            
record_map_source.php - Converts kml,csv to geojson or downloads file (or zip) based on Datasource record id
record_shp.php - Converts shp+dbf files to geojson output or downloads zip archive based on Datasource record id
 
 
deprecated
map_data.php - for google map interface only
sys_structure.php  - used in import defintions only to retrieve structure from different database          
            
Updated:     29 Nov 2021

----------------------------------------------------------------------------------------------------------------

