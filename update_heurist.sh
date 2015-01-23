#! /bin/sh
set -e
set -u
set -o pipefail

# update_heurist.sh: update script for Heurist, creates new copy

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2014 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     3.4

# Licensed under the GNU General Pulic License, Version 3.0 (the "License"); you may not use this file except
# in compliance with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
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
      echo "on heurist.sydney.edu.au/HEURIST/DISTRIBUTION or script will not download the Heurist code package)"
      echo
        echo -e "Example:      sudo ./update_heurist.sh  h3.2.0-beta  /var/www/html/HEURIST \n\n\n\n"
      exit
   fi

if [ -z $2 ]
   then
      echo -e "\n\n\nPlease supply installation path as the second parameter, eg. /var/www/HEURIST or /var/www/html/HEURIST"
      echo "Note: This is the path where the root Heurist installation directory [normally called HEURIST]"
      echo "was created by the original installation. Instances of Heurist can co-exist in this directory."
      echo
        echo -e "Example:      sudo ./update_heurist.sh  h3.2.0-beta  /var/www/html/HEURIST \n\n\n\n"
      exit 1
   fi

# Test download package is valid before we get half way and can't find it ...
curl --range 0-100 http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/$1.tar.bz2 > /dev/null 2>&1

rc=$?
if [ $rc -ne 0 ]
     then
        echo -e "\n\n"
        echo "The version parameter you supplied does not point to a Heurist installation package"
        echo "Please check for the latest version at http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION"
        echo "The parameter should be eg. h3.2.0-beta as given - DO NOT include the url path or .tar.bz2"
        echo
        echo -e "Example:      sudo ./update_heurist.sh  h3.2.0-beta  /var/www/html/HEURIST \n\n\n\n"
        exit 1
     fi

if [ ! -d "$2" ]; then
        echo -e "\n\n\n\nThe installation path specified in the second parameter [ $2 ] does not exist \n\n\n\n"
        exit 1
fi

echo
echo
echo "----------------------- Installing Heurist Version 3 Update ---------------------------"
echo
echo
echo -e "Fetching Heurist code from heurist.sydney.edu.au/HEURIST/DISTRIBUTION/$1.tar.bz2"
echo
rm -f $1.tar.bz2
wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/$1.tar.bz2
tar -xjf $1.tar.bz2
rm -f $1.tar.bz2
mkdir -p $2/$1
cp -R $1/* $2/$1

echo
echo Obtaining updated support files - external, exemplars and help files
echo
cd $2/HEURIST_SUPPORT

rm -f external.tar.bz2
wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external.tar.bz2
tar -xjf external.tar.bz2
rm -f external.tar.bz2

rm -f external_h4.tar.bz2
wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h4.tar.bz2
tar -xjf external_h4.tar.bz2
rm -f external_h4.tar.bz2

rm -f help.tar.bz2
wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2
tar -xjf help.tar.bz2
rm -f help.tar.bz2

rm -f exemplars.tar.bz2
wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/exemplars.tar.bz2
tar -xjf exemplars.tar.bz2
rm -f exemplars.tar.bz2

# Simlinks to external functions and libraries
# this will fail in strict mode, and they should be there
# cd $2/$1
# ln -s $2/HEURIST_SUPPORT/external external
# ln -s $2/HEURIST_SUPPORT/help help
# ln -s $2/HEURIST_SUPPORT/exemplars exemplars
# Simlink for H4 externals
# cd $2/$1/applications/h4
# ln -s $2/HEURIST_SUPPORT/external_h4/ ext


echo "Heurist unpacked"

# This installation of elaastic search generated a number of security holes rated HIGH RISK
# We are therefore removing it pending investigation. Sept 2014
# mkdir -p $2/HEURIST_SUPPORT/external/elasticsearch
# cd $2/HEURIST_SUPPORT/external/elasticsearch
# wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external/elasticsearch/elasticsearch-1.3.2.tar.gz
# tar -zxvf elasticsearch-1.3.2.tar.gz
# cd  $2/HEURIST_SUPPORT/external/elasticsearch/elasticsearch-1.3.2
# ./bin/elasticsearch -d


# ------------------------------------------------------------------------------------------

echo -e "\n\n\n\n\n\n"

echo "---- Heurist update installed in $2/$1 -------------------------------------------"
echo
echo Unless you have a shared heuristConfigIni.php file in $2 (the normal situation)
echo or if you wish to override some settings for this particular instance of Heurist
echo you will need to edit the configIni.php file in the $2/$1 directory
echo See $2/$1/parentDirectory_heuristConfigIni.php for instructions
echo
echo Please visit Admin - Utilities - Verify installation to check that required components
echo are installed and then verify that Heurist runs correctly from the new location.
echo
echo Then replace your $2/h3 directory with $2/$1
echo
