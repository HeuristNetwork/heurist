#! /bin/sh

# install_heurist.sh: installation script for Heurist on any flavour of Linux
# Note: run install_prerequisities_ubuntu.sh first to install php packages, MySQL, SpatialLite etc.

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2019 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     5.0

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
      echo "Please supply version eg. h5.x.x.alpha (this MUST exist as a tar.bz2 file "
      echo "on Heuristplus.sydney.edu.au/HEURIST/DISTRIBUTION or script will not download the Heurist code package)."
      echo "If you are not the root user, supply 'sudo' as the second argument eg.  "
      echo
      echo "       ./install_heurist.sh h5.0.0.beta sudo"
      exit
   fi

# Test download package is valid before we get half way and can't find it ...
curl --range 0-100 https://heuristplus.sydney.edu.au/HEURIST/DISTRIBUTION/$1.tar.bz2 > /dev/null 2>&1

rc=$?
if [ $rc -ne 0 ]
     then
        echo -e "\n\n"
        echo "The version parameter you supplied does not point to a Heurist installation package"
        echo "Please check for the latest version at HeuristNetwork.org/installation"
        echo "The parameter should be eg. h5.2.1.beta as given - DO NOT include the url path or .tar.bz2"
        exit
     fi

echo
echo
echo "----------------------- Installing Heurist  ---------------------------"
echo
echo


# Everything lives in /var/www/html/HEURIST with appropriate simlinks

echo "Changing to /var/www/html and creating html (if required) and HEURIST directory"

# ensure html directory exists - if this is not apache web root, Heurist will be installed but
# not accessible at the web root address so we make simlinks.

cd /var/www
# will do nothing if already exists
$2 mkdir /var/www/html

cd /var/www/html
# mkdirs will do nothing if directory already exists
$2 mkdir HEURIST
$2 mkdir /var/www/html/HEURIST/HEURIST_SUPPORT

# download source to temp directory
cd /var/www/html/HEURIST
$2 mkdir temp
cd /var/www/html/HEURIST/temp
echo -e "Fetching Heurist code from Heuristplus.sydney.edu.au"
$2 wget https://heuristplus.sydney.edu.au/HEURIST/DISTRIBUTION/$1.tar.bz2
$2 tar -xjf $1.tar.bz2
$2 rm -f $1.tar.bz2

# this will fail if hx.x.x.xxx already exists, use update script in this case
$2 mkdir /var/www/html/HEURIST/$1
$2 cp -R $1/* /var/www/html/HEURIST/$1/
$2 rm -rf $1

cd /var/www/html/HEURIST/HEURIST_SUPPORT

$2 wget https://heuristplus.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h5.tar.bz2
$2 tar -xjf external_h5.tar.bz2
$2 rm -f external_h5.tar.bz2

$2 wget https://heuristplus.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/vendor.tar.bz2
$2 tar -xjf vendor.tar.bz2
$2 rm -f vendor.tar.bz2

$2 wget https://heuristplus.sydney.edu.au/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2
$2 tar -xjf help.tar.bz2
$2 rm -f help.tar.bz2

cd /var/www/html/HEURIST/$1
$2 ln -s /var/www/html/HEURIST/HEURIST_SUPPORT/external_h5 external
$2 ln -s /var/www/html/HEURIST/HEURIST_SUPPORT/help help
$2 ln -s /var/www/html/HEURIST/HEURIST_SUPPORT/vendor vendor

# simlink in web root to this version of heurist
cd /var/www/html
$2 ln -s /var/www/html/HEURIST/$1 $1

echo "Heurist unpacked"

echo -e "\n\n"
echo "Creating directories and setting permissions"

# set up the filestore, copy .htaccess to block direct web access to contents (overridden for rectype icons/thumbs)
$2 mkdir /var/www/html/HEURIST/HEURIST_FILESTORE
$2 cp /var/www/html/HEURIST/$1/admin/setup/.htaccess_for_filestore /var/www/html/HEURIST/HEURIST_FILESTORE/.htaccess

# set up override configIni files
$2 mv /var/www/html/HEURIST/$1/move_to_parent_as_heuristConfigIni.php /var/www/html/HEURIST/heuristConfigIni.php
$2 mv /var/www/html/HEURIST/$1/move_to_parent_as_index.html /var/www/html/HEURIST/index.html

# one or other of these will fail harmlessly
# on a two tier system you may need to map apache to nobody
echo "Trying both www-data (Debian) and apache (Redhat) as owner and group for data directories, one will succeed"
$2 chown -R www-data:www-data /var/www/html/HEURIST/
$2 chown -R apache:apache /var/www/html/HEURIST/

$2 chmod -R 775  /var/www/html/HEURIST/
$2 chmod -R 775  /var/www/html/HEURIST/HEURIST_FILESTORE/

# Simlink codebase as heurist from the root web directory
# heurist goes to index.php, nothing goes to the index.html switchboard

cd /var/www/html
$2 rm h4
$2 rm h5
$2 rm heurist
$2 rm heurist_switchboard
$2 ln -s /var/www/html/HEURIST/$1 heurist
$2 ln -s /var/www/html/HEURIST/$1 heurist_switchboard

cd /var/www/html/HEURIST
$2 rm h4
$2 rm h5
$2 rm heurist
$2 ln -s /var/www/html/HEURIST/$1 heurist

# ------------------------------------------------------------------------------------------

echo -e "\n\n\n\n\n\n"

echo "---- Heurist installed in /var/www/html/HEURIST/heurist -------------------------------------------"
echo
echo "There is normally limited space on /var/www, so you may wish to move HEURIST_FILESTORE from"
echo "its current location - /var/www/html/HEURIST/HEURIST_FILESTORE - to a location with plenty "
echo "of space allocated, such as /srv or /data, and add a simlink to this location in /var/www/html/HEURIST "
echo
echo "Heurist program is accessible at https://serveraddress/heurist"
echo "Heurist switchboard is accessible at https://serveraddress/HEURIST or http://serveraddress/heurist_switchboard"
echo "Replace https with http where appropriate"
echo
echo "CONFIGURATION:"
echo
echo "Edit /var/www/html/HEURIST/heuristConfigIni.php to set your MySQL root user password - twice, clearly documented in file"
echo
echo "You can do this by pasting the following at the command line - you may need to change nano to pico on some systems:"
echo
echo "           sudo nano /var/www/html/HEURIST/heuristConfigIni.php"
echo
echo "Then run Heurist by navigating to Heurist on your web site at https://serveraddress/heurist_switchboard for switchboard or https://serveraddress/heurist for direct access to databases"
echo
