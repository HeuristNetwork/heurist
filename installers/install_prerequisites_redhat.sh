#! /bin/sh

# THIS SCRIPT IS NOT UP-TO-DATE

# install.sh: installation script for RedHat
# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2019 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     3.2

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

# Preliminary Heurist prerequisites installation script for Heurist on Ubuntu
# Initial script Brian Ballsun-Stanton Sept 2013, extended and revised by Ian Johnson 2013, 2014
# This script Artem Osmakov Nov 2013, additional script content merged from Ubuntu install Ian Johnson Jan 2014

# This script takes something of a brute force approach to making sure everything is up-to-date. It could certainly be improved.


# Note: revised script has not yet been tested @ 21 July 2014


# -------------PRELIMINARIES ---------------------------------------------------------------------------------------------

echo -e "\n\n\n\n\n\n\n\n\n\n\n\n"

echo "---- Installing Prerequisites for Heurist(for Linux/RedHat 14.04) ----"
echo
echo
echo "WARNING 1: BEWARE OF TIMEOUT ON THE MYSQL PASSWORD REQUEST"
echo
echo "This installation is fairly radical in upgrading all required software to latest versions."
echo "We do not recommend using it on servers which depend on old versions of software for"
echo "existing applications to work. It should be fine on new servers without much in the way of" echo "existing applications."
echo
echo "Progress and errors go to terminal, other messages to a file called heurist_install.log"
echo "in the current directory. Typical install time on a virtual server is about 5 - 10 minutes, but could take longer"
echo
echo "Later you will be asked to supply a root password for MySQL, which sets the root password for your MySQL installation"
echo
echo "******* WARNING ********* Do not let your session time out or you will need to start over"

# Note: using apt-get rather than aptitude - the latter fixes problems automatically but is not always installed

echo
echo "You may get sudo errors unable-to-resolve-host below - this is normal, please do not be concerned"
echo



# ***************   PRETTY MUCH ALL THE SAME AS FOR UBUNTU ABOVE THIS POINT ****************************************



# TODO: check this is appropriate for RedHat
sudo sed 's/\(127.0.0.1 *\t*localhost\)/\1 '`cat /etc/hostname`'/' /etc/hosts | sudo tee /etc/hosts

echo "Update PHP"
echo -e "\n"

sudo yum install pdo_mysql
sudo yum install php-pdo
sudo yum install php-mbstring
sudo yum install php-gd
sudo yum install php-pecl-memcache
sudo yum install php-mysql
sudo yum install php-xsl
sudo yum install zip
sudo yum install unzip
sudo yum install php-curl

# php-mbstring added 16/1/14, needed for non-latin (accented) characters

# TODO: check this: Added from Ubuntu install 23/1/14 - may be superfluous, not yet tested
sudo yum install pv

# TODO: Review rest of script against the Ubuntu install script

sed 's/^display_errors = .*/display_errors = Off/' < /etc/php.ini | sudo tee /etc/php.ini
sed 's/^upload_max_filesize = .*/upload_max_filesize = 250M/' < /etc/php.ini | sudo tee /etc/php.ini
sed 's/^post_max_size.*/post_max_size = 251M/' < /etc/php.ini | sudo tee /etc/php.ini

# No longer used sudo pecl install uploadprogress

echo "Start services"
echo -e "\n"

sudo service memcached start
sudo service mysqld start

# why not use graceful?
sudo apachectl restart


echo -e "\n\n\n\n"

echo ----- Heurist Prerequisites installed --------------------------------------------------------------------
echo
echo Please check for any errors above
echo
echo PLEASE NOTE: This installation script has not been thoroughly tested at 21 July 2014
echo If Heurist fails to run correctly, please contact the Heurist team, email: info - a t - HeuristNetwork.org
echo
echo "WARNING: Bug in creation of php.ini at 21/7/14 - please check whether /etc/php5/apache2/php.ini is blank"
echo "         If so, copy a valid php.ini file into this location, otherwise the search interface fails to "
echo "         load completely and a memcached error shows up in the Database Summary popup"
echo
echo
echo Please now run install_heurist.sh
echo







