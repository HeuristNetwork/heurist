#! /bin/sh

# install_heurist.sh: installation script for Heurist on ny flavour of Linux
# Note: run install_prerequisities_ubuntu.sh first to install php packages, MySQL, SpatialLite etc.

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
      echo "on HeuristScholar.org/HEURIST/DISTRIBUTION or script will not download the Heurist code package)."
      echo "If you are not the root user, supply 'sudo' as the second argument eg.  "
      echo
      echo "       ./install_heurist.sh h3.2.0-beta sudo"
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
echo


# Everything lives in /var/www/html/HEURIST with appropriate simlinks

echo "Changing to /var/www/html and creating html (if required) and HEURIST directory"

# apache on Ubuntu used to install at /var/www, now it installs at /var/www/html

# ensure html directory exists - if this is not apache web root, Heurist will be installed but
# not accessible at the web root address so we make simlinks.

cd /var/www
# will do nothing if already exists
$2 mkdir /var/www/html

cd /var/www/html
# mkdirs will do nothing if directory already exists
$2 mkdir HEURIST
$2 mkdir /var/www/html/HEURIST/HEURIST_SUPPORT

echo -e "Fetching Heurist code from HeuristScholar.org"
$2 wget http://heuristscholar.org/html/HEURIST/DISTRIBUTION/$1.tar.bz2
$2 tar -xjf $1.tar.bz2
$2 rm $1.tar.bz2
# this will fail if h3 already exists, use update script in this case
$2 mkdir /var/www/html/HEURIST/h3
$2 cp -R $1/* /var/www/html/HEURIST/h3/
$2 rm -rf $1

cd /var/www/html/HEURIST/HEURIST_SUPPORT

$2 wget http://heuristscholar.org/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external.tar.bz2
$2 tar -xjf external.tar.bz2
$2 rm external.tar.bz2

$2 wget http://heuristscholar.org/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h4.tar.bz2
$2 tar -xjf external_h4.tar.bz2
$2 rm external_h4.tar.bz2

$2 wget http://heuristscholar.org/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2
$2 tar -xjf help.tar.bz2
$2 rm help.tar.bz2

$2 wget http://heuristscholar.org/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/exemplars.tar.bz2
$2 tar -xjf exemplars.tar.bz2
$2 rm exemplars.tar.bz2

cd /var/www/html/HEURIST/h3
$2 ln -s /var/www/html/HEURIST/HEURIST_SUPPORT/external external
$2 ln -s /var/www/html/HEURIST/HEURIST_SUPPORT/external_h4 external_h4
$2 ln -s /var/www/html/HEURIST/HEURIST_SUPPORT/help help
$2 ln -s /var/www/html/HEURIST/HEURIST_SUPPORT/exemplars exemplars

echo "Heurist unpacked"

echo -e "\n\n"
echo "Creating directories, sandpit database and setting permissions"

$2 mkdir /var/www/html/HEURIST/HEURIST_FILESTORE

$2 mkdir /var/www/html/HEURIST/HEURIST_FILESTORE/H3Sandpit

$2 cp -r /var/www/html/HEURIST/h3/admin/setup/rectype-icons/ /var/www/html/HEURIST/HEURIST_FILESTORE/H3Sandpit
$2 cp -r /var/www/html/HEURIST/h3/admin/setup/smarty-templates/ /var/www/html/HEURIST/HEURIST_FILESTORE/H3Sandpit
$2 cp -r /var/www/html/HEURIST/h3/admin/setup/xsl-templates/ /var/www/html/HEURIST/HEURIST_FILESTORE/H3Sandpit
$2 cp -r /var/www/html/HEURIST/h3/admin/setup/settings/ /var/www/html/HEURIST/HEURIST_FILESTORE/H3Sandpit

$2 mkdir /var/www/html/HEURIST/HEURIST_FILESTORE/H3Sandpit/filethumbs
$2 mkdir /var/www/html/HEURIST/HEURIST_FILESTORE/H3Sandpit/generated-reports
$2 mkdir /var/www/html/HEURIST/HEURIST_FILESTORE/H3Sandpit/hml-output
$2 mkdir /var/www/html/HEURIST/HEURIST_FILESTORE/H3Sandpit/html-output
$2 mkdir /var/www/html/HEURIST/HEURIST_FILESTORE/H3Sandpit/scratch
$2 mkdir /var/www/html/HEURIST/HEURIST_FILESTORE/H3Sandpit/backup

# set up override configIni files
$2 mv /var/www/html/HEURIST/h3/parentDirectory_heuristConfigIni.php /var/www/html/HEURIST/heuristConfigIni.php
$2 mv /var/www/html/HEURIST/h3/parentDirectory_index.html /var/www/html/HEURIST/index.html

# one or other of these will fail harmlessly
echo Trying both www-data and apache as owner:group for data directories, one will succeed
$2 chown -R apache:apache /var/www/html/HEURIST/
$2 chown -R www-data:www-data /var/www/html/HEURIST/

$2 chmod -R 775  /var/www/html/HEURIST/
$2 chmod -R 775  /var/www/html/HEURIST/HEURIST_FILESTORE/

# Simlink Heurist root as heurist and codebase as h3 from the root web directory
# do both /var/www and /var/www/html for good measure
cd /var/www
$2 ln -s /var/www/html/HEURIST/h3/ h3
$2 ln -s /var/www/html/HEURIST/ heurist
cd /var/www/html
$2 ln -s /var/www/html/HEURIST/h3/ h3
$2 ln -s /var/www/html/HEURIST/ heurist

# TODO: NEED TO ADD .htaccess file to the filestore

# ------------------------------------------------------------------------------------------

echo -e "\n\n\n\n\n\n"

echo "---- Heurist installed in /var/www/html/HEURIST/h3 -------------------------------------------"
echo
echo "NOTE:"
echo
echo "There is normally limited space on /var/www, so you may wish to move HEURIST_FILESTORE from"
echo "its current location - /var/www/html/HEURIST/HEURIST_FILESTORE - to a location with plenty "
echo "of space allocated, such as /srv or /data, and add a simlink to this location in /var/www/html/HEURIST "
echo
echo "CONFIGURATION:"
echo
echo "Edit /var/www/html/HEURIST/heuristConfigIni.php to set your MySQL root user password - twice, clearly documented in file"
echo
echo "You can do this by pasting the following at the command line - you may need to change nano to pico on some systems:"
echo
echo "           sudo nano /var/www/html/HEURIST/heuristConfigIni.php"
echo
echo "Then run Heurist by navigating to heurist on your web site eg. myserver.com/heurist"
echo