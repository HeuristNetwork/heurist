
Directory:	/server_management

Overview:	

Scripts for installation, update and verification of instances and management of databases on a server.

Includes functions for backing up databases locally allowing instant restoration from the web interface without using system backups, and for summarising content across databases.

Copy all the scripts in /server_management/move_to_scripts_directory to /srv/scripts on the server and edit as required.

The /server_management/model_configuration_files directory provides examples for setting up apache redirects, crontab, robots file etc.

The files in /movetoparent should be moved to the installation directory of Heurist
(the directory containing all the instances of the code, the filestore symlink, 
and various configuration files), normally /var/www/html/HEURIST

* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, 2024 - Heurist Network
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     7