#! /bin/sh

# Preliminary Heurist installation script for Heurist on Ubuntu
# Initial script Brian Ballsun-Stanton Sept 2013, extended and revised by Ian Johnson

# (https://code.google.com/p/heurist/)
# This script takes something of a brute force approach to making sure everything is up-to-date. It could certainly be imporved.

if [ -z $1 ] 
   then 
      echo -e "\n\n"
      echo "Please supply version eg. h3.x.x-alpha or h3-alpha, h3-beta, h3-latest (this MUST exist as a tar.bz2 file on HeuristScholar.org/HEURIST/DISTRIBUTIONS or script will not download Heurist code package)" 
      exit
   fi
 
# Test download package is valid before we get half way and can't find it ...
curl --range 0-100 http://heuristscholar.org/HEURIST/DISTRIBUTION/$1.tar.bz2 > /dev/null 2>&1

rc=$?
if [ $rc -ne 0 ]
     then 
        echo -e "\n\n"
        echo "The version parameter you supplied does not point to a Heurist installation package"
        echo "Please check the Google Code website at https://code.google.com/p/heurist for the latest version"
        echo "The parameter should be eg. h3.1.4-alpha or h3-latest as given - do not include the url path or .tar.bz2"
        exit
     fi
      
echo -e "\n\n\n\n"  

echo "---- Installing Heurist Version 3 (for Linux/Ubuntu) ----"

echo -e "\n\n"
echo "WARNING: BEWARE OF TIMEOUT ON THE MYSQL PASSWORD REQUEST"
echo -e "\n\n"

echo "This installation is fairly radical in upgrading all required software to latest versions"
echo "We do not recommend using it on servers which depend on old versions of software for existing applications to work"
echo "It should be fine on new servers without much in the way of exsiting applications."
echo "Progress and errors go to terminal, other messages to file called install.log"
echo "Typical install time on a virtual server is about 15 - 30 minutes"

echo -e "\n"  

echo "Later you will be asked to supply a root password for MySQL"
echo "This sets the root password for your MySQL installation"
echo "Do not let your session time out or you will need to start over"

# Note: using apt-get rather than aptitude - the latter fixes 
# problems automatically but is not always installed

echo -e "\n"  

echo "You may see a couple of unable-to-resolve-host sudo errors below - do not panic, this is expected"

sudo sed 's/\(127.0.0.1 *\t*localhost\)/\1 '`cat /etc/hostname`'/' /etc/hosts | sudo tee /etc/hosts

sudo apt-get update > install.log 

echo "Installing tasksel etc"
# TODO erring on more is better, cleanup later
sudo apt-get install tasksel software-properties-common python-software-properties -y >> install.log
echo "Tasksel installed"

# needed by FAIMS module output
sudo apt-get install zip unzip

# contains backports of spatialite 3.1.0 RC 2 (aka 3.0.1 as released)

echo "Repo added for spatiallite"
if ! ls /etc/apt/sources.list.d/faims-mobile-web-*.list 2> /dev/null 1> /dev/null
    then sudo add-apt-repository ppa:faims/mobile-web -y
     sudo add-apt-repository ppa:ubuntugis/ppa -y 
fi


sudo apt-get update >> install.log
sudo apt-get upgrade -y >> install.log

echo -e "\n\n"

echo "System upgraded top latest versions, about to install LAMP stack, PHP 5 extensions, spatialLite"

sudo tasksel install lamp-server

sudo apt-get install php5-curl php5-xsl php5-mysql php5-memcache php5-gd php5-dev php-pear memcached php5-memcached sqlite3 pv php5-sqlite -y >> install.log

# Added from RedHat install 23/1/14 - may be superfluous, not yet tested
sudo apt-get install php5-pdo php5-mbstring

sudo apt-get build-dep libspatialite-dev -y >> install.log


wget http://www.fedarch.org/libspatialite-4.1.1.tar.gz http://www.fedarch.org/spatialite-tools-4.1.1.tar.gz -P /tmp/ || { echo "downloads failed. exiting." ; exit 1; }
cd /tmp/

tar -xzf libspatialite-4.1.1.tar.gz

cd /tmp/libspatialite-4.1.1

sudo ./configure | pv -p -s 9656 -e > /tmp/compile1.log
sudo make | pv -p -s 115351 -e > /tmp/compile2.log
sudo make install | pv -p -s 15628 -e > /tmp/compile3.log

sudo ldconfig


echo -e "\n\n"

echo "Main PHP libraries installed"

# this is only done because upload progress needs to compile. The lout. We should write it out of the system ...
sudo apt-get build-dep php5 -y >> install.log

echo "automakes installed"

# sudo sed 's/\(127.0.0.1 *\t*localhost\)/\1 '`cat /etc/hostname`'/w /tmp/hostNew' /etc/hosts
# sudo sed 's/;sqlite3.extension_dir =/sqlite3.extension_dir = \/usr\/lib/' /etc/php5/apache2/php.ini

sed 's/;sqlite3.extension_dir =/sqlite3.extension_dir = \/usr\/local\/lib/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini
sed 's/;extension=php_sqlite3.dll/extension=php_sqlite3.dll/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini
sed 's/^display_errors = .*/display_errors = Off/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini
sed 's/^upload_max_filesize = .*/upload_max_filesize = 30M/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini
sed 's/^post_max_size.*/post_max_size = 31M/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini

# TODO: PROBLEM @ 2/10/13 - php.ini is ending up empty. Need to copy a valid php.ini into /etc/php5/apache2/php.ini
# otherwise the search interface fails to load completely and a memcached error shows up in the database summary popup

# If errors are on, errors are output to the browser and stuff up Heurist interface

# increase upload file size from default 2M


echo "hacking in sqlite3 and spatialite3 support"

sudo pear config-set php_ini /etc/php5/apache2/php.ini

sudo pecl install uploadprogress >> install.log

echo "uploadprogress installed"

sudo apache2ctl graceful

echo -e "\n"
echo "Apache restarted"

echo -e "\n\n\n\n"

# added 24/2/14. Everything now lives in /var/www/HEURIST with appropriate simlinks
echo "Changing to /var/www and creating HEURIST directory"
cd /var/www
sudo mkdir HEURIST
sudo mkdir /var/www/HEURIST/HEURIST_SUPPORT
sudo chown -R www-data:www-data /var/www/HEURIST/

echo -e "Now fetching Heurist code from HeuristScholar.org"
sudo wget http://heuristscholar.org/HEURIST/DISTRIBUTION/$1.tar.bz2 
sudo tar -xjf $1.tar.bz2
sudo rm $1.tar.bz2
sudo mv $1/ /var/www/HEURIST/h3
cd /var/www
ln -s HEURIST/h3 H3

cd /var/www/HEURIST/HEURIST_SUPPORT

# Note:  added sudos to wget 9/6/14 - check if needed/valid

sudo wget http://heuristscholar.org/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external.tar.bz2
sudo tar -xjf external.tar.bz2
sudo rm external.tar.bz2

sudo wget http://heuristscholar.org/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2
sudo tar -xjf help.tar.bz2
sudo rm help.tar.bz2

sudo wget http://heuristscholar.org/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/exemplars.tar.bz2
sudo tar -xjf exemplars.tar.bz2
sudo rm exemplars.tar.bz2

echo "Heurist unpacked"

cd /var/www/h3/

echo -e "\n\n\n"
echo "You will be asked for your mysql root password to allow creation of the default database"

# Added database creation to buildExampleDB script 24/2/14 so password doesn't need to be entered twice
# echo "CREATE DATABASE hdb_H3Sandpit" | mysql -uroot -p
echo "Please enter your MySQL root password to allow creation of the default database"
mysql -uroot -p  < /var/www/HEURIST/h3/admin/setup/dbcreate/buildExampleDB.sql

echo -e "\n\n"
echo "Creating directories and setting permissions"

sudo mkdir /var/www/HEURIST/HEURIST_FILESTORE
sudo mkdir /var/www/HEURIST/HEURIST_FILESTORE/H3Sandpit
sudo chown -R www-data:www-data /var/www/HEURIST/

sudo cp -r /var/www/HEURIST/h3/admin/setup/rectype-icons/ /var/www/HEURIST/HEURIST_FILESTORE/H3Sandpit
sudo cp -r /var/www/HEURIST/h3/admin/setup/smarty-templates/ /var/www/HEURIST/HEURIST_FILESTORE/H3Sandpit
sudo cp -r /var/www/HEURIST/h3/admin/setup/xsl-templates/ /var/www/HEURIST/HEURIST_FILESTORE/H3Sandpit

sudo mkdir /var/www/HEURIST/HEURIST_FILESTORE/H3Sandpit/filethumbs
sudo mkdir /var/www/HEURIST/HEURIST_FILESTORE/H3Sandpit/generated-reports
sudo mkdir /var/www/HEURIST/HEURIST_FILESTORE/H3Sandpit/hml-output
sudo mkdir /var/www/HEURIST/HEURIST_FILESTORE/H3Sandpit/html-output
sudo mkdir /var/www/HEURIST/HEURIST_FILESTORE/H3Sandpit/scratch
sudo mkdir /var/www/HEURIST/HEURIST_FILESTORE/H3Sandpit/settings
sudo mkdir /var/www/HEURIST/HEURIST_FILESTORE/H3Sandpit/backup

sudo chown -R www-data:www-data /var/www/HEURIST/HEURIST_FILESTORE
sudo chmod -R 755  /var/www/HEURIST/HEURIST_FILESTORE

echo -e "\n\n\n\n\n\n"

echo "---- Installation complete -----"
echo -e "\n"

echo -e "You should probably rename the installation directory to /var/www/h3 rather than the specific version ID entered"
echo -e "\n"

echo "Please edit configIni.php to set your MySQL root user + password (twice, clearly documented in file)"
echo -e "\n\n"
echo "Now do this:    cd /var/www/h3"
echo "                sudo nano configIni.php       and set MySQL passwords"
echo -e "\n\n"
echo "You may wish to move HEURIST_FILESTORE from its current location (/var/www/HEURIST) to a location with plenty of space allocation"
echo "and add a simlink in its original location. This move is recommended since there is normally limited space on /var/www"
echo -e "\n\n"
echo "WARNING: Bug in creation of php.ini at 2/10/13 - please check whether /etc/php5/apache2/php.ini is blank"
echo "         If so, copy a valid php.ini file into this location, otherwise the search interface fails to "
echo "         load completely and a memcached error shows up in the Database Summary popup"