#! /bin/sh

# update_heurist.sh: update script for Heurist, creates new copy

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2019 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     6

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

# installation source: Heurist reference server

ref_server=http://heuristref.net

# -------------PRELIMINARIES ---------------------------------------------------------------------------------------------


# Checking parameters and availability ...
if [ -z $1 ]
   then
      echo "Usage: ./update_heurist.sh hx.X.X.beta [sudo]"
      echo "Please supply version eg. hx.x.x.beta (this MUST exist as a tar.bz2 file "
      echo "on $ref_server/HEURIST/DISTRIBUTION or script will not download the Heurist code package)"
      exit
   fi

# Test download package is valid before we get half way and can't find it ...
if ! curl -fs --range 0-100 $ref_server/HEURIST/DISTRIBUTION/$1.tar.bz2 > /dev/null; then
        echo "The version parameter you supplied does not point to a Heurist installation package"
        echo "Please check for the latest version at HeuristNetwork.org/installation"
        echo "The parameter should be eg. h6.2.1 as given - DO NOT include the url path or .tar.bz2"
        echo "If you are not the root user, supply 'sudo' as the second argument eg.  "
        echo
        echo "       ./update_heurist.sh h6.2.1 sudo"
        exit
fi

echo
echo
echo "----------------------- Installing Update to Heurist ---------------------------"
echo
echo

cd /var/www/html/HEURIST
$2 mkdir -p temp
cd temp

echo "Fetching Heurist code from $ref_server/HEURIST/DISTRIBUTION/$1.tar.bz2"
echo
$2 rm -f $1.tar.bz2
$2 curl -O# $ref_server/HEURIST/DISTRIBUTION/$1.tar.bz2
$2 tar -xjf $1.tar.bz2
$2 rm -f $1.tar.bz2
# remove existing directroy if present then make directory and copy over Heurist
$2 rm -Rf /var/www/html/HEURIST/$1
$2 mkdir /var/www/html/HEURIST/$1
$2 cp -R $1/* /var/www/html/HEURIST/$1

echo
echo Obtaining updated support files - external and help files
echo
cd /var/www/html/HEURIST/HEURIST_SUPPORT

$2 rm -f external_h5.tar.bz2
$2 curl -O# $ref_server/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h5.tar.bz2
$2 tar -xjf external_h5.tar.bz2
$2 rm -f external_h5.tar.bz2

$2 rm -f vendor.tar.bz2
$2 curl -O# $ref_server/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/vendor.tar.bz2
$2 tar -xjf vendor.tar.bz2
$2 rm -f vendor.tar.bz2

$2 rm -f help.tar.bz2
$2 curl -O# $ref_server/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2
$2 tar -xjf help.tar.bz2
$2 rm -f help.tar.bz2

# No longer used
$2 rm -f exemplars.tar.bz2

# Simlink to this version in the root html directory
cd /var/www/html
$2 ln -s HEURIST/$1 $1

# Place simlinks in instance directory
cd /var/www/html/HEURIST/$1
$2 ln -s /var/www/html/HEURIST/HEURIST_SUPPORT/external_h5 external
$2 ln -s /var/www/html/HEURIST/HEURIST_SUPPORT/vendor vendor
$2 ln -s /var/www/html/HEURIST/HEURIST_SUPPORT/help help

echo "Heurist unpacked"

# This installation of elastic search generated a number of security holes rated HIGH RISK
# We are therefore removing it pending investigation. Sept 2014
# $2 mkdir /var/www/html/HEURIST/HEURIST_SUPPORT/external/elasticsearch
# cd /var/www/html/HEURIST/HEURIST_SUPPORT/external/elasticsearch
# $2 curl -O# $ref_server/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external/elasticsearch/elasticsearch-1.3.2.tar.gz
# $2 tar -zxvf elasticsearch-1.3.2.tar.gz
# cd  /var/www/html/HEURIST/HEURIST_SUPPORT/external/elasticsearch/elasticsearch-1.3.2
# ./bin/elasticsearch -d


# ------------------------------------------------------------------------------------------

echo ""
echo ""
echo ""
echo ""
echo ""


echo "---- Heurist update installed in /var/www/html/HEURIST/$1 -------------------------------------------"
echo
echo "If you do not have a shared heuristConfigIni.php file in /var/www/html/HEURIST (which you should have ...)"
echo "you need to edit the configIni.php file in /var/www/html/HEURIST/$1"
echo "See /var/www/html/HEURIST/$1/move_to_parent_as_heuristConfigIni.php for instructions"
echo
echo "Please test your new Heurist install from  https://yourserver/$1 and log in to a database;"
echo "Use Database->Verify installation to check all is installed correctly and test major functions"
echo "such as add record, add image, search, facet search, view record and map."
echo 
echo "When you have confirmed that the this Heurist upgrade is running correctly, make it the default by"
echo "redirecting your /var/www/html/heurist symlink to point to /var/www/html/HEURIST/$1"
echo
echo
