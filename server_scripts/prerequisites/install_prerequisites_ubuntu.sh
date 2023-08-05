#! /bin/sh

# install.sh: installation script for Ubuntu
# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2019 University of Sydney
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
echo "WARNING: BEWARE OF TIMEOUT ON THE MYSQL PASSWORD REQUEST"
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

# -------------PRE-REQUISITES---------------------------------------------------------------------------------------------

# Note: using apt-get rather than aptitude - the latter fixes problems automatically but is not always installed

# This does not seem to be populating /etc/hosts, removed 30/7/14
# sudo sed 's/\(127.0.0.1 *\t*localhost\)/\1 '`cat /etc/hostname`'/' /etc/hosts | sudo tee /etc/hosts

# removed 6 sep 09 b/c i am very doubtful it works ...
# sudo sed 's/\(127.0.0.1 *\t*localhost\)/\1 '`cat /etc/hostname`'/w /tmp/hostNew' /etc/hosts

echo "Upgrading package versions from repositories"

# update package list from the repositories
sudo apt-get update > heurist_install.log

# install updated packages
sudo apt-get upgrade -y >> heurist_install.log

# TODO erring on more is better, tasksel not always installed, cleanup later
sudo apt-get install tasksel software-properties-common python-software-properties -y >> heurist_install.log


# ----- LAMP - MySQL  --------------------------------------------

echo "System packages upgraded to latest versions, about to install LAMP stack"


sudo tasksel install lamp-server

# DOESN'T WORK ON DEBIAN ???    sudo tasksel install lamp-server

apt-get install apache2
apt-get install mysql-server
mysql_secure_installation


# Enter current password for root (enter for none):
# You already have a root password set, so you can safely answer 'n'.
# Change the root password? [Y/n] n
# Remove anonymous users? [Y/n] Y
# Disallow root login remotely? [Y/n] Y
# Remove test database and access to it? [Y/n] T^HY
# Reload privilege tables now? [Y/n] Y
#  ... Success!

 apt-get install php5 php-pear php5-mysql

# ----- PHP extensions --------------------------------------------

echo "Installing PHP 5 extensions"

sudo apt-get install php5-curl  -y >> heurist_install.log
sudo apt-get install php5-xsl -y >> heurist_install.log
sudo apt-get install php5-mysql -y >> heurist_install.log
sudo apt-get install php5-memcache -y >> heurist_install.log
sudo apt-get install php5-gd -y >> heurist_install.log
sudo apt-get install zip -y >> heurist_install.log
sudo apt-get install unzip -y >> heurist_install.log

# mbstring - multilingual character handling - appears to be installed by default on Ubuntu
# pdo appears to be installed by php5-mysql, but may also require pdo_mysql

# Debian and Ubuntu 14:04  unable to locate any of the below
sudo apt-get install pdo_mysql -y >> heurist_install.log
sudo apt-get install php5-pdo -y >> heurist_install.log
sudo apt-get install php5-mbstring -y >> heurist_install.log

# This appears to be hangover from recompiling php
# sudo ldconfig

# ----- PHP Ini ----------------------------------------------------

# This heap of junk - the first one - tends to delete the php.ini file. Removed Ian 6 sep 2014
# TODO: we need a more reliable way of updating the php.ini file settings

# If errors are on, errors are output to the browser and stuff up Heurist interface
# sed 's/^display_errors = .*/display_errors = Off/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini

# increase upload file size from default 2M to 250M upload / 251M post
# sed 's/^upload_max_filesize = .*/upload_max_filesize = 250M/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini
# sed 's/^post_max_size.*/post_max_size = 251M/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini

# increase session timeout to approx. 1 month
# sed 's/^session.gc_maxlifetime = .*/session.gc_maxlifetime = 3000000/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini


 integer


# Why do we need this here? Removed Ian 30/7/14
# sudo pear config-set php_ini /etc/php5/apache2/php.ini

# ----- Apache restart ----------------------------------------------------

sudo apache2ctl graceful

echo
echo "Apache restarted"

echo -e "\n\n\n\n"

echo ----- Heurist Prerequisites installed --------------------------------------------------------------------
echo
echo Please check for any errors above - please advise info - a t - HeuristNetwork.org of any errors
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
