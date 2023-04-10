#! /bin/sh

# create_database_from_archive.sh: 3 Feb 2020: creates a database based on a zip file containing a Heurist archive dump
#	unzips Heurist archive file to same location
#	creates new database with name of archive
#   loads SQL dump from unzipped archive into database (recreates database structue and content)
#	moves the unzipped archive to /var/www/html/HEURIST/HEURIST_FILESTORE

# BEWARE: LIMITED ERROR CHECKING in current version
#         Will fail if database exists and will overwrite existing filestore directory for the database 

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2019 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     5.1

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

# -------------PRELIMINARIES ---------------------------------------------------------------------------------------------

echo -e "\n\n\n\n\n\n\n"
echo Checking parameters and availability ...
echo WARNING: MUST USE IN THE DIRECTORY WHERE THE ZIP FILE IS LOCATED
echo -e "\n\n"

if [ -z $1 ]
   then
      echo -e "\n\n"
      echo "Please supply the name of the Heurist archive file EXCLUDING .zip extension"
      echo "ZIP file msut be in current directory"
      echo
      exit
   fi

echo -e "\n\n"

echo "unzip into a directory with name of zip file"

mkdir $1
unzip -d $1 $1.zip

# TODO: add collation, failign to recognise normal collations

echo
echo "Create database"
echo "create database hdb_$1 CHARACTER SET utf8mb4;" | mysql -uroot -pxxxxx_replace_with_pwd_xxxxx 

echo
echo "Load archive"
echo "use hdb_$1; source $1/$1_MySQL_Database_Dump.sql;"| mysql -uroot -pxxxxx_replace_with_pwd_xxxxx 

echo
echo "Move database directory"
# TODO  Doesn't find the directory, probalby b/c looking in source path of teh script not current directory
mv $1 /var/www/html/HEURIST/HEURIST_FILESTORE

# TO DO: Need to chow and chgrp and chmod the directory after moving

echo -e "\n\n"

echo -e "Done ... Please check results ..."

echo -e "\n\n"
