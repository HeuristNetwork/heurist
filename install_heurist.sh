#! /bin/sh

# install_heurist.sh: installation script for Heurist on any flavour of Linux
# Note: run install_prerequisities_ubuntu.sh first to install php packages, MySQL, SpatialLite etc.

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
base_dir="/var/www/html"

# -------------PRELIMINARIES ---------------------------------------------------------------------------------------------

echo -e "\n\n\n\n\n\n\n\n\n\n\n\n"
echo Checking parameters and availability ...
echo
echo

if [ -z $1 ]
   then
      echo -e "\n\n"
      echo "Please supply version eg. h5.x.x.alpha (this MUST exist as a tar.bz2 file "
      echo "on $ref_server/HEURIST/DISTRIBUTION or script will not download the Heurist code package)."
      echo "If you are not the root user, supply 'sudo' as the second argument eg.  "
      echo
      echo "       ./install_heurist.sh h6.1.1 sudo"
      exit
   fi

# Test download package is valid before we get half way and can't find it ...
curl --range 0-100 $ref_server/HEURIST/DISTRIBUTION/$1.tar.bz2 > /dev/null 2>&1

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


# Everything lives in $root/HEURIST with appropriate simlinks
echo "Changing to ${base_dir} and creating HEURIST directory"

# Ensure $root directory exists.
# If this is not apache web root, Heurist will be installed but
# not accessible at the web root address so we make simlinks.

# mkdirs will do nothing if directory already exists
$2 mkdir -p "${base_dir}/HEURIST"
$2 mkdir "${base_dir}/HEURIST/HEURIST_SUPPORT"

# download source to $root/HEURIST/temp directory
cd "${base_dir}/HEURIST"
$2 mkdir temp
cd "${base_dir}/HEURIST/temp"
echo -e "Fetching Heurist code from $ref_server"
$2 wget $ref_server/HEURIST/DISTRIBUTION/$1.tar.bz2
$2 tar -xjf $1.tar.bz2
$2 rm -f $1.tar.bz2
# move what was downloaded to $root/HEURIST/hx.x.x.xxx
# this will fail if hx.x.x.xxx already exists, use update script in this case
$2 mkdir ../$1
$2 cp -R $1/* ../$1/
$2 rm -rf $1
# TODO?: cd .. ; rmdir temp   (temp is empty from now on)

cd "${base_dir}/HEURIST/HEURIST_SUPPORT"

$2 wget $ref_server/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h5.tar.bz2
$2 tar -xjf external_h5.tar.bz2
$2 rm -f external_h5.tar.bz2

$2 wget $ref_server/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/vendor.tar.bz2
$2 tar -xjf vendor.tar.bz2
$2 rm -f vendor.tar.bz2

$2 wget $ref_server/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2
$2 tar -xjf help.tar.bz2
$2 rm -f help.tar.bz2

cd "${base_dir}/HEURIST/$1"
$2 ln -s ../HEURIST_SUPPORT/external_h5 external
$2 ln -s ../HEURIST_SUPPORT/help help
$2 ln -s ../HEURIST_SUPPORT/vendor vendor

# simlink in web root to this version of heurist
cd "${base_dir}"
$2 ln -s HEURIST/$1 $1

echo "Heurist unpacked"

echo -e "\n\n"
echo "Creating directories and setting permissions"

# set up the filestore, copy .htaccess to block direct web access to contents (overridden for rectype icons/thumbs)
$2 mkdir "${base_dir}/HEURIST/HEURIST_FILESTORE"
$2 cp "${base_dir}/HEURIST/$1/admin/setup/.htaccess_for_filestore" "${base_dir}/HEURIST/HEURIST_FILESTORE/.htaccess"

# set up override configIni files
$2 mv "${base_dir}/HEURIST/$1/movetoparent/heuristConfigIni.php" "${base_dir}/HEURIST/heuristConfigIni.php"
$2 mv "${base_dir}/HEURIST/$1/movetoparent/index.html" "${base_dir}/HEURIST/index.html"

# one or other of these will fail harmlessly
# on a two tier system you may need to map apache to nobody
echo "Trying both www-data (Debian) and apache (Redhat) as owner and group for data directories, one will succeed"
$2 chown -R www-data:www-data "${base_dir}/HEURIST/"
$2 chown -R apache:apache "${base_dir}/HEURIST/"

$2 chmod -R 775  "${base_dir}/HEURIST/"
$2 chmod -R 775  "${base_dir}/HEURIST/HEURIST_FILESTORE/"

# Simlink codebase as heurist from the root web directory
# heurist goes to index.php, nothing goes to the index.html switchboard

cd "${base_dir}"
$2 rm h4
$2 rm h5
$2 rm heurist
$2 rm heurist_switchboard
$2 ln -s HEURIST/$1 heurist
$2 ln -s HEURIST/$1 heurist_switchboard

cd "${base_dir}/HEURIST"
$2 rm h4
$2 rm h5
$2 rm heurist
$2 ln -s $1 heurist

# ------------------------------------------------------------------------------------------

echo -e "\n\n\n\n\n\n"

echo "---- Heurist installed in ${base_dir}/HEURIST/heurist -------------------------------------------"
echo
echo "If there is limited space on your partition, you may wish to move HEURIST_FILESTORE from"
echo "its current location - ${base_dir}/HEURIST/HEURIST_FILESTORE - to a location with plenty "
echo "of space allocated, such as /srv or /data, and add a simlink to this location in ${base_dir}/HEURIST "
echo
echo "Heurist program is accessible at https://serveraddress/heurist"
echo "Heurist switchboard is accessible at https://serveraddress/HEURIST or http://serveraddress/heurist_switchboard"
echo "Replace https with http where appropriate"
echo
echo "CONFIGURATION:"
echo
echo "Edit ${base_dir}/HEURIST/heuristConfigIni.php to set your MySQL root user password -documentation is in file comments"
echo
echo "You can do this by pasting the following at the command line - you may need to change nano to pico on some systems:"
echo
echo "           sudo nano ${base_dir}/HEURIST/heuristConfigIni.php"
echo
echo "Then run Heurist by navigating to Heurist on your web site at https://serveraddress/heurist_switchboard for switchboard or https://serveraddress/heurist for direct access to databases"
echo
