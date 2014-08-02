#! /bin/sh

# install.sh: installation script for Ubuntu
# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2014 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @author      Brian Ballsun-Stanton   <brian@fedarch.org>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     3.2

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

# Preliminary Heurist prerequisites installation script for Heurist on Ubuntu
# Initial script Brian Ballsun-Stanton Sept 2013, extended and revised by Ian Johnson 2013, 2014

# This script takes something of a brute force approach to making sure everything is up-to-date. It could certainly be improved.

# -------------PRELIMINARIES ---------------------------------------------------------------------------------------------

echo -e "\n\n\n\n\n\n\n\n\n\n\n\n"

echo "---- Installing Prerequisites for Heurist for Linux/Ubuntu ----"
echo
echo
echo "WARNING 1: BEWARE OF TIMEOUT ON THE MYSQL PASSWORD REQUEST"
echo
echo "This installation is fairly radical in upgrading all required software to latest versions."
echo "We do not recommend using it on servers which depend on old versions of software for"
echo "existing applications to work. It should be fine on new servers without much in the way of"
echo "existing applications."
echo
echo "Progress and errors go to terminal, other messages to a file called heurist_install.log"
echo "in the current directory. Typical install time on a virtual server is about 5 minutes, but could take longer"
echo
echo "Later you will be asked to supply a root password for MySQL, which sets the root password for your MySQL installation"
echo
echo "******* WARNING ********* Do not let your session time out or you will need to start over"
echo
echo
echo "Please do not be concerned by warnings such as:"
echo "        sudo: unable to resolve host Your-Server-Name"
echo "If you get these warnings, you will get dozens, but"
echo "they have no effect on the results of the script"
echo
echo

# -------------PRE-REQUISITES---------------------------------------------------------------------------------------------

# Note: using apt-get rather than aptitude - the latter fixes problems automatically but is not always installed

# This does not seem to be populating /etc/hosts, removed 30/7/14
# sudo sed 's/\(127.0.0.1 *\t*localhost\)/\1 '`cat /etc/hostname`'/' /etc/hosts | sudo tee /etc/hosts

sudo sed 's/\(127.0.0.1 *\t*localhost\)/\1 '`cat /etc/hostname`'/w /tmp/hostNew' /etc/hosts

echo "Upgrading package versions from repositories"

# update package list from the repositories
sudo apt-get update > heurist_install.log

# install updated packages
sudo apt-get upgrade -y >> heurist_install.log

# TODO erring on more is better, tasksel not always installed, cleanup later
sudo apt-get install tasksel software-properties-common python-software-properties -y >> heurist_install.log

echo "System packages upgraded to latest versions, about to install LAMP stack"

sudo tasksel install lamp-server

# ----- PHP extensions --------------------------------------------

echo "Installing PHP 5 extensions"

sudo apt-get install php5-curl php5-xsl php5-mysql php5-memcache php5-gd zip unzip -y >> heurist_install.log

# mbstring - multilingual character handling - appears to be installed by default on Ubuntu
# pdo appears to be installed by php5-mysql, but may also require pdo_mysql

sudo apt-get install pdo_mysql
sudo apt-get install php5-pdo
sudo apt-get install php5-mbstring

sudo ldconfig

# ----- PHP Ini ----------------------------------------------------

# If errors are on, errors are output to the browser and stuff up Heurist interface
sed 's/^display_errors = .*/display_errors = Off/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini

# increase upload file size from default 2M
sed 's/^upload_max_filesize = .*/upload_max_filesize = 250M/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini
sed 's/^post_max_size.*/post_max_size = 251M/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini

# Why do we need this here? Removed Ian 30/7/14
# sudo pear config-set php_ini /etc/php5/apache2/php.ini

# ----- Apache restart ----------------------------------------------------

sudo apache2ctl graceful

echo
echo "Apache restarted"

echo -e "\n\n\n\n"

echo ----- Heurist Prerequisites installed --------------------------------------------------------------------
echo
echo Please check for any errors above - please advise info@heuristscholar.org of any errors
echo
echo "If you are getting lots of warnings along the lines of sudo: unable to resolve host Your-Server-Name"
echo "we suggest editing /etc/hosts with    sudo nano /etc/hosts    and insert the following:"
echo
echo "    127.0.1.1 localhost"
echo "    IPaddress ServerName"
echo
echo IPaddress and hostname are:
hostname -i
hostname
echo
echo "Please now run step 2 - install_heurist.sh - on the Heurist installation instructions"
echo "to install the Heurist software on your server"
echo
