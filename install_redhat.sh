#! /bin/sh

echo "This is a basic set of instructions used for installing on a RHEL Redhat Enterprise Linux server"
echo "Use it as a guide - it will need editing appropriately for your setup"

# Author: Artem Osmakov Nov 2013, additional script content merged from Ubuntu install Ian Johnson Jan 2014

# Note: revised script has not yet been tested @ 23 Jan 14

# TODO: This installation does not yet install SQLite requried by FAIMS

# (https://code.google.com/p/heurist/)
# This script takes something of a brute force approach to making sure everythign is up-to-date. It could certainly be imporved.

if [ -z $1 ] 
   then 
      echo -e "\n\n"
      echo "Please supply version eg. h3.x.x-alpha, h3-alpha, h3-beta, h3-latest as given on the Google Code website (this MUST exist as a tar.bz2 file on http://heuristscholar.org/HEURIST/DISTRIBUTION)" 
      exit
   fi
 
# Test download package is valid before we get half way and can't find it ...
curl --range 0-100  http://heuristscholar.org/HEURIST/DISTRIBUTION/$1.tar.bz2 > /dev/null 2>&1

rc=$?
if [ $rc -ne 0 ]
     then 
        echo -e "\n\n"
        echo "The version parameter you supplied does not point to a Heurist installation package"
        echo "Please check the Google Code website at https://code.google.com/p/heurist"
        echo "The parameter should be eg. h3.1.4-alpha or h3-latest - do not include the url path or .tar.bz2"
        exit
     fi
      
echo -e "\n\n\n\n"  

echo "---- Installing Heurist Version 3 (for RedHat RHEL) ----"

echo -e "\n\n"
echo "WARNING: BEWARE OF TIMEOUT ON THE MYSQL PASSWORD REQUEST"
echo -e "\n\n"

echo "This installation is fairly radical in upgrading all required software to latest versions"
echo "We do not recommend using it on servers which depend on old versions of software for existing applications to work"
echo "It should be fine on new servers without much in the way of exsiting applications."
echo "Progress and errors go to terminal, other messages to file install.log"
echo "Typical install time on a virtual server is about 15 - 30 minutes"

echo -e "\n"  

echo "Later you will be asked to supply a root password for MySQL"
echo "This sets the root password for your MySQL installation"
echo "Do not let your session time out or you will need to start over"

echo -e "\n"  

echo "You may see a sudo error or two below - unable to resolve host - do not panic, this is expected"

# TODO: check this is appropriate for RedHat
sudo sed 's/\(127.0.0.1 *\t*localhost\)/\1 '`cat /etc/hostname`'/' /etc/hosts | sudo tee /etc/hosts


echo "Update PHP"
echo -e "\n"

sudo yum install memcached
sudo yum install php-mysql php-pecl-memcache php-devel php-pecl-memcached php-gd php-pear php-pdo php-sqlite php-mbstring 
# php-mbstring added 16/1/14, needed for non-latin (accented) characters

# TODO: check this: Added from Ubuntu install 23/1/14 - may be superfluous, not yet tested
sudo yum install php-curl php-xsl pv

# needed by FAIMS module output
sudo yum install zip unzip

# TODO: Review rest of script against the Ubuntu install script

sed 's/^display_errors = .*/display_errors = Off/' < /etc/php.ini | sudo tee /etc/php.ini
sed 's/^upload_max_filesize = .*/upload_max_filesize = 30M/' < /etc/php.ini | sudo tee /etc/php.ini
sed 's/^post_max_size.*/post_max_size = 31M/' < /etc/php.ini | sudo tee /etc/php.ini

sudo pecl install uploadprogress

echo "Start services"
echo -e "\n"

sudo service memcached start
sudo service mysqld start
sudo apachectl restart

echo "Get Heurist code"
echo -e "\n"

sudo wget  http://heuristscholar.org/HEURIST/DISTRIBUTION/$1.tar.bz2 -P /tmp

cd /tmp

tar -xjf $1.tar.bz2 

# sudo mkdir /var/www/html/$1

sudo mv /tmp/$1/* /var/www/html/$1

echo "Set permissions and SELinux type"
echo -e "\n"

sudo chown -R apache:apache /var/www/html/$1/
sudo chmod -R 755 /var/www/html/$1/
sudo chcon -Rt httpd_sys_content_t $1/

# GET THE EXTERNAL, HELP AND EXEMPLARS DIRECTORIES

cd /var/www/html
sudo mkdir HEURIST_SUPPORT
cd HEURIST_SUPPORT

sudo wget http://heuristscholar.org/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external.tar.bz2
sudo tar -xjf external.tar.bz2
sudo rm external.tar.bz2

sudo wget http://heuristscholar.org/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2
sudo tar -xjf help.tar.bz2
sudo rm help.tar.bz2

sudo wget http://heuristscholar.org/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/exemplars.tar.bz2
sudo tar -xjf exemplars.tar.bz2
sudo rm exemplars.tar.bz2

# ----------------------------------------------------------------

echo -e "\n\n\n\n"
echo "Creating HEURIST_FILESTORE in /srv"
echo -e "\n"

# Heurist filestore is best created in /srv where there is normally significant 
# data space, but needs to be web accessible (for now)
sudo mkdir /srv/HEURIST_FILESTORE
sudo chown -R apache:apache /srv/HEURIST_FILESTORE
sudo chmod -R gu+rwx  /srv/HEURIST_FILESTORE
sudo chmod -R a+r /srv/HEURIST_FILESTORE

# TODO: remove simlink once web accessiblity of HEURIST_FILESTORE is no longer mandatory
cd /var/www/html
sudo ln -s /srv/HEURIST_FILESTORE  HEURIST_FILESTORE
sudo chown apache:apache HEURIST_FILESTORE
sudo chmod gu+rwx  HEURIST_FILESTORE
sudo chmod a+r HEURIST_FILESTORE

# ----------------------------------------------------------------

echo -e "\n\n\n\n"
echo "Creating H3Sandpit database"
echo -e "\n"

echo "CREATE DATABASE hdb_H3Sandpit" | mysql -uroot -p

echo "Please enter your mysql root password... again..."
mysql -uroot -p hdb_H3Sandpit < /var/www/html/$1/admin/setup/dbcreate/buildExampleDB.sql

# Build H3Sandpit database as starting point
sudo mkdir /srv/HEURIST_FILESTORE/H3Sandpit
sudo mkdir /srv/HEURIST_FILESTORE/H3Sandpit/filethumbs
sudo mkdir /srv/HEURIST_FILESTORE/H3Sandpit/generated-reports
sudo mkdir /srv/HEURIST_FILESTORE/H3Sandpit/hml-output
sudo mkdir /srv/HEURIST_FILESTORE/H3Sandpit/html-output
sudo mkdir /srv/HEURIST_FILESTORE/H3Sandpit/scratch
sudo mkdir /srv/HEURIST_FILESTORE/H3Sandpit/settings
sudo mkdir /srv/HEURIST_FILESTORE/H3Sandpit/backup
sudo cp -r /var/www/html/$1/admin/setup/rectype-icons/ /srv/HEURIST_FILESTORE/H3Sandpit
sudo cp -r /var/www/html/$1/admin/setup/smarty-templates/ /srv/HEURIST_FILESTORE/H3Sandpit
sudo cp -r /var/www/html/$1/admin/setup/xsl-templates/ /srv/HEURIST_FILESTORE/H3Sandpit

# ----------------------------------------------------------------

echo -e "\n\n\n\n\n\n"

echo "---- Installation complete -----"
echo -e "\n"

echo -e "You should probably rename the installation directory to /var/www/html/h3 rather than the specific version ID entered"
echo -e "\n"

echo "Please edit configIni.php to set your MySQL root user + password (twice, clearly documented in file)"
echo -e "\n\n"
echo "Now do this:    cd /var/www/HTML/$1"
echo "                sudo nano configIni.php       and set MySQL passwords"
echo -e "\n\n"

# TODO: Check if this is a problem and update appropriately for RedHat
echo "WARNING: Bug in creation of php.ini at 2/10/13 - please check whether /etc/php5/apache2/php.ini is blank"
echo "         If so, copy a valid php.ini file into this location, otherwise the search interface fails to "
echo "         load completely and a memcached error shows up in the Database Summary popup"