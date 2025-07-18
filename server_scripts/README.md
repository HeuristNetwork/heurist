
Directory:	/server_scripts

Overview:	

Scripts for installation, update and verification of instances and management of databases on a server.
Includes functions for backing up databases locally allowing instant restoration from the web interface
without using system backups, and for summarising content across databases.

Copy all the scripts in srv_scripts_xxxxx to /srv/scripts on the server and edit as required

The model_xxxxxx files in the root of /server_scripts provide examples for setting up 
apache redirects, crontab, robots file etc.

The files in /movetoparent should be moved to the installation directory of Heurist
(the directory containing all the instances of the code, the filestore symlink, 
and various configuration files), normally /var/www/html/HEURIST
