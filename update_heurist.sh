#! /bin/sh

# uopdate_heurist.sh: update script for Heurist, creates new copy

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2014 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     3.2

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

# -------------PRELIMINARIES ---------------------------------------------------------------------------------------------

echo -e "\n\n\n\n\n\n\n\n\n\n\n\n"
echo Checking parameters and availability ...
echo
echo

if [ -z $1 ]
   then
      echo -e "\n\n"
      echo "Please supply version eg. h3.x.x-alpha or h3-alpha, h3-beta, h3-latest (this MUST exist as a tar.bz2 file "
      echo "on HeuristScholar.org/HEURIST/DISTRIBUTION or script will not download the Heurist code package)"
      exit
   fi

# Test download package is valid before we get half way and can't find it ...
curl --range 0-100 http://heuristscholar.org/HEURIST/DISTRIBUTION/$1.tar.bz2 > /dev/null 2>&1

rc=$?
if [ $rc -ne 0 ]
     then
        echo -e "\n\n"
        echo "The version parameter you supplied does not point to a Heurist installation package"
        echo "Please check for the latest version at http://heuristscholar.org/HEURIST/DISTRIBUTION"
        echo "The parameter should be eg. h3.2.0-beta as given - DO NOT include the url path or .tar.bz2"
        exit
     fi

echo
echo
echo "----------------------- Installing Heurist Version 3 ---------------------------"
echo
echo "Progress and errors go to terminal, other messages to a file called heurist_install.log"
echo "in the current directory"
echo
echo ----- Now fetching and installing updated Heurist code -----------------------------------

echo -e "Fetching Heurist code from HeuristScholar.org"
sudo wget http://heuristscholar.org/HEURIST/DISTRIBUTION/$1.tar.bz2
sudo tar -xjf $1.tar.bz2
sudo rm $1.tar.bz2
sudo cp -r $1/ /var/www/html/HEURIST/$1

cd /var/www/html/HEURIST/HEURIST_SUPPORT

sudo wget http://heuristscholar.org/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external.tar.bz2
sudo tar -xjf external.tar.bz2
sudo rm external.tar.bz2

sudo wget http://heuristscholar.org/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2
sudo tar -xjf help.tar.bz2
sudo rm help.tar.bz2

sudo wget http://heuristscholar.org/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/exemplars.tar.bz2
sudo tar -xjf exemplars.tar.bz2
sudo rm exemplars.tar.bz2

echo "Heurist unpacked"

# ------------------------------------------------------------------------------------------

echo -e "\n\n\n\n\n\n"

echo "---- Heurist update installed in /var/www/html/HEURIST/$1 -------------------------------------------"
echo
echo You will need to edit the configIni.php file in the /var/www/html/HEURIST/$1 directory
echo unless you have a shared heuristConfigIni.php file in /var/www/html/HEURIST
echo See /var/www/html/HEURIST/$1/parentDirectory_heuristConfigIni.php for instructions
echo
echo Please verify that Heurist runs correctly from the new location, then
echo overwrite your /var/www/html/HEURIST/h3 directory with /var/www/html/HEURIST/$1
echo
