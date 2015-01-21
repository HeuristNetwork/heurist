#! /bin/sh
set -e
set -u
set -o pipefail

# install_heurist.sh: installation script for Heurist on any flavour of Linux
# Note: install prerequisities first - php, php packages, MySQL, Apache - see documentation

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2014 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License 3.0
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
      echo -e "\n\n\n\nPlease supply version eg. h3.x.x-alpha or h3-alpha, h3-beta, h3-latest (this MUST exist as a tar.bz2 file "
      echo "on heurist.sydney.edu.au/HEURIST/DISTRIBUTION or script will not download the Heurist code package)."
      echo "If you are not the root user, use 'sudo'. For example:"
      echo
      echo "     sudo ./install_heurist.sh  h3.2.0-beta  /var/www/html  www-data"
      exit
   fi

if [ -z $2 ]
   then
      echo -e "\n\n\nPlease supply installation path as the second parameter, eg. /var/www or /var/www/html"
      echo "If you are not the root user, use 'sudo'. For example:"
      echo
      echo "      sudo ./install_heurist.sh  h3.2.0-beta  /var/www/html  www-data"
      exit 1
   fi

if [ -z $3 ]
   then
      echo -e "\n\n\nPlease supply the user under which apache runs as the third parameter, eg. apache or www-data"
      echo "If you are not the root user, use 'sudo'. For example:"
      echo
      echo "      sudo ./install_heurist.sh  h3.2.0-beta  /var/www/html  www-data"
      exit 1
   fi

# Test download package is valid before we get half way and can't find it ...
curl --range 0-100 http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/$1.tar.bz2 > /dev/null 2>&1

rc=$?
if [ $rc -ne 0 ]
     then
        echo -e "\n\n\n\nThe first parameter you supplied (version) does not point to a Heurist installation package"
        echo "Please check for the latest version at http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION"
        echo "The parameter should be eg. h3.2.0-beta as given - DO NOT include the url path or .tar.bz2"
        exit 1
     fi

if [ ! -d "$2" ]; then
        echo -e "\n\n\n\nThe installation path specified in the second parameter [ $2 ] does not exist"
        exit 1
fi

if ! grep -q "$3" /etc/passwd; then
        echo -e "\n\n\n\nThe user specified in the third parameter [ $3 ] does not exist"
        exit 1
fi



echo
echo
echo -e "----------------------- Installing Heurist Version $1 ---------------------------"
echo
echo


# Everything normally lives in /var/www/HEURIST or /var/www/html/HEURIST with appropriate simlinks

# apache on Ubuntu used to install at /var/www, now it installs at /var/www/html

# mkdir -p will do nothing if directory already exists, creates parents if they don't exist
mkdir -p $2/HEURIST
mkdir -p $2/HEURIST/scratch
mkdir -p $2/HEURIST/HEURIST_SUPPORT

cd $2/HEURIST/scratch

echo -e "Fetching Heurist code from HeuristScholar.org"
wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/$1.tar.bz2
tar -xjf $1.tar.bz2
rm -f $1.tar.bz2

# Specific to version 3
mkdir -p $2/HEURIST/h3
cp -R $1/* $2/HEURIST/h3/
rm -rf $1

cd $2/HEURIST/HEURIST_SUPPORT

wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external.tar.bz2
tar -xjf external.tar.bz2
rm -f external.tar.bz2

wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h4.tar.bz2
tar -xjf external_h4.tar.bz2
rm -f external_h4.tar.bz2

wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2
tar -xjf help.tar.bz2
rm -f help.tar.bz2

wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/exemplars.tar.bz2
tar -xjf exemplars.tar.bz2
rm -f exemplars.tar.bz2

cd $2/HEURIST/h3
ln -s $2/HEURIST/HEURIST_SUPPORT/external external
ln -s $2/HEURIST/HEURIST_SUPPORT/exemplars exemplars
ln -s $2/HEURIST/HEURIST_SUPPORT/help help
cd $2/HEURIST/h3/applications/h4
ln -s $2/HEURIST/HEURIST_SUPPORT/external_h4 ext


# This installation of elaastic search generated a number of security holes rated HIGH RISK
# We are therefore removing it pending investigation. Sept 2014
# mkdir -p $2/HEURIST/HEURIST_SUPPORT/external/elasticsearch
# cd $2/HEURIST/HEURIST_SUPPORT/external/elasticsearch
# wget http://heurist.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external/elasticsearch/elasticsearch-1.3.2.tar.gz
# tar -zxvf elasticsearch-1.3.2.tar.gz
# cd  $2/HEURIST/HEURIST_SUPPORT/external/elasticsearch/elasticsearch-1.3.2
# ./bin/elasticsearch -d


echo "Heurist unpacked"

echo -e "\n\nCreating directories, setting up sandpit database and setting permissions"

mkdir -p $2/HEURIST/HEURIST_FILESTORE

mkdir -p $2/HEURIST/HEURIST_FILESTORE/H3Sandpit

cp -r $2/HEURIST/h3/admin/setup/rectype-icons/ $2/HEURIST/HEURIST_FILESTORE/H3Sandpit
cp -r $2/HEURIST/h3/admin/setup/smarty-templates/ $2/HEURIST/HEURIST_FILESTORE/H3Sandpit
cp -r $2/HEURIST/h3/admin/setup/xsl-templates/ $2/HEURIST/HEURIST_FILESTORE/H3Sandpit

mkdir -p $2/HEURIST/HEURIST_FILESTORE/H3Sandpit/filethumbs
mkdir -p $2/HEURIST/HEURIST_FILESTORE/H3Sandpit/generated-reports
mkdir -p $2/HEURIST/HEURIST_FILESTORE/H3Sandpit/hml-output
mkdir -p $2/HEURIST/HEURIST_FILESTORE/H3Sandpit/html-output
mkdir -p $2/HEURIST/HEURIST_FILESTORE/H3Sandpit/scratch
mkdir -p $2/HEURIST/HEURIST_FILESTORE/H3Sandpit/settings
mkdir -p $2/HEURIST/HEURIST_FILESTORE/H3Sandpit/backup

# set up override configIni files
mv $2/HEURIST/h3/parentDirectory_heuristConfigIni.php $2/HEURIST/heuristConfigIni.php
mv $2/HEURIST/h3/parentDirectory_index.html $2/HEURIST/index.html

chown -R $3:$3 $2/HEURIST/

chmod -R 775 $2/HEURIST/

# TODO: NEED TO ADD .htaccess file to the filestore

# ------------------------------------------------------------------------------------------

echo -e "\n\n\n\n\n\n"

echo "---- Heurist Vsn 3 installed in $2/HEURIST/h3 -------------------------------------------"
echo
echo "NOTE:"
echo
echo "There is normally limited space on /var/www, so you may wish to move HEURIST_FILESTORE from"
echo -e "its current location - $2/HEURIST/HEURIST_FILESTORE - to a location with plenty "
echo "of space allocated, such as /srv or /data, and add a simlink to this location in $2/HEURIST "
echo
echo "CONFIGURATION:"
echo
echo "Edit $2/HEURIST/heuristConfigIni.php to set your MySQL passwords - twice, clearly documented in file"
echo "and the path and relative URL to your Heurist filestore, eg. /var/www/html/HEURIST/HEURIST_FILESTORE/"
echo "and /HEURIST/HEURIST_FILESTORE, as well as contact details for the adminstrator""
echo
echo "You can do this by pasting the following at the command line - you may need to change nano to pico on some systems:"
echo
echo "           sudo nano $2/HEURIST/heuristConfigIni.php"
echo
echo "Then run Heurist by navigating to Heurist on your web site eg. myserver.com/HEURIST/h3"
echo "You may wish to add a simlink in your web root directory to access Heurist at an address such as myserver.com/h3"
echo
echo
