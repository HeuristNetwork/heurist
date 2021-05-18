#! /bin/sh

# install_faims_ubuntu.sh: prerequisites installation script for FAIMS functions on Ubuntu
# installs extra functions needed by FAIMS (primarily SQLite and SpatialLite)

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2019 University of Sydney
# @author      Brian Ballsun-Stanton   <brian@fedarch.org>
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     3.2

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

# Preliminary prerequisites installation script for FAIMS Heurist functions on Ubuntu
# Initial script Brian Ballsun-Stanton Sept 2013, extended and revised by Ian Johnson 2013, 2014

echo -e "\n\n\n\n\n\n\n\n\n\n\n\n"

echo "---- Installing Prerequisites (SQLite, SpatialLite) for FAIMS Heurist functions (for Linux/Ubuntu) ----"
echo

sudo apt-get update > heurist_install.log

# contains backports of spatialite 3.1.0 RC 2 (aka 3.0.1 as released)

if ! ls /etc/apt/sources.list.d/faims-mobile-web-*.list 2> /dev/null 1> /dev/null
    then sudo add-apt-repository ppa:faims/mobile-web -y
     sudo add-apt-repository ppa:ubuntugis/ppa -y
fi

echo "Repo added for spatiallite"

sudo apt-get update >> heurist_install.log
sudo apt-get upgrade -y >> heurist_install.log


# ----- SQLite and SpatialLite ----------------------------------------------------

echo "Adding sqlite3 and spatialite3 support from FAIMS Fedarch.org server"

# sudo sed 's/;sqlite3.extension_dir =/sqlite3.extension_dir = \/usr\/lib/' /etc/php5/apache2/php.ini

sed 's/;sqlite3.extension_dir =/sqlite3.extension_dir = \/usr\/local\/lib/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini
sed 's/;extension=php_sqlite3.dll/extension=php_sqlite3.dll/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini

sudo apt-get build-dep libspatialite-dev -y >> heurist_install.log

wget http://www.fedarch.org/libspatialite-4.1.1.tar.gz http://www.fedarch.org/spatialite-tools-4.1.1.tar.gz -P /tmp/ || { echo "Spatialite downloads from Fedarch dot org failed." }

cd /tmp/

tar -xzf libspatialite-4.1.1.tar.gz

cd /tmp/libspatialite-4.1.1

sudo ./configure | pv -p -s 9656 -e > /tmp/compile1.log
sudo make | pv -p -s 115351 -e > /tmp/compile2.log
sudo make install | pv -p -s 15628 -e > /tmp/compile3.log

sudo ldconfig

# ----- Apache restart ----------------------------------------------------

sudo apache2ctl graceful

echo
echo "Apache restarted"


# ----- Heurist Prerequisites installed -------------------------------------------------------------------------------------------

echo -e "\n\n\n\n"

echo FAIMS extensions (primarily SQLite and SpatialLite) installed - please check for errors above

echo