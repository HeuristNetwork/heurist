#! /bin/sh

# Preliminary installation script for Heurist, rev 7th June 2013
# (https://code.google.com/p/heurist/)
# This script is a bit of a hack to get us up and running

if [ -z $1 ] 
   then 
      echo -e "\n\n"
      echo "Please supply version eg. h3.x.x or h3_alpha, h3_beta, h3_latest (this MUST exist as tar.bz2 on Google Code or script will not download Heurist code)" 
      exit
   fi
 
# Test download package is valid before we get half way and can't find it ...
wget https://heurist.googlecode.com/files/$1.tar.bz2 > /dev/null 2>&1
rc=$?
if [ $rc -ne 0 ]
     then 
        echo -e "\n\n"
        echo "The version parameter you supplied does not point to a Heurist installation package"
        echo "Please check the Google Code website at https://code.google.com/p/heurist/downloads/list"
        echo "The parameter should be eg. h3.1.4 or h3_latest - do not include the url path or .tar.bz2"
        exit
     fi
      
echo -e "\n\n\n\n"  

echo "---- Installing Heurist Version 3 (for Linux/Ubuntu) ----"

echo "This installation is fairly radical in upgrading all required software to latest versions"
echo "Progress and errors go to terminal, other messages to file install.log"
echo "Typical install time on a virtual server is about 15 minutes"

echo -e "\n"  

echo "Later you will be asked to supply a root password for MySQL"
echo "This sets the root password for your MySQL installation"
echo "Do not let your session time out or you will need to start over"

# Note: using apt-get rather than aptitude - the latter fixes 
# problems automatically but is not always installed

echo -e "\n"  

echo "You may see a sudo error or two - unable to resolve host; do not panic"

echo "127.0.0.1       " `sudo cat /etc/hostname` | sudo tee /etc/hosts

# If errors are on, errors are output to the browser and stuff up Heurist interface
echo "display_errors=Off" `sudo cat /etc/php5/apache2/php.ini` | sudo tee /etc/php5/apache2/php.ini
# increase upload file size from default 2M
echo "upload_max_filesize=30M" `sudo cat /etc/php5/apache2/php.ini` | sudo tee /etc/php5/apache2/php.ini
echo "post_max_size=31M" `sudo cat /etc/php5/apache2/php.ini` | sudo tee /etc/php5/apache2/php.ini

sudo apt-get update > install.log 

echo "Installing tasksel etc"
# TODO erring on more is better, cleanup later
sudo apt-get install tasksel software-properties-common python-software-properties -y >> install.log
echo "Tasksel installed"

# contains backports of spatialite 3.1.0 RC 2 (aka 3.0.1 as released)
sudo add-apt-repository ppa:ubuntugis/ubuntugis-unstable -y > /dev/null

echo "Repo added for spatiallite"

sudo apt-get update >> install.log
sudo apt-get upgrade -y >> install.log

echo -e "\n"

echo "System upgraded. I hope you wanted that done ..."

# this doesn’t work to pause
read -p "Press [Enter] key to start LAMP stack install..."

sudo tasksel install lamp-server

sudo apt-get install php5-curl php5-xsl php5-mysql php5-memcache php5-gd php5-dev php-pear memcached php5-memcached libspatialite3 -y >> install.log

echo "Main PHP libraries installed"

# this is only done because upload progress needs to compile. The lout. We should write it out of the system ...
sudo apt-get build-dep php5 -y >> install.log

echo "automakes installed"

sudo ln -s /usr/lib/libspatialite.so.3 /usr/lib/libspatialite.so

echo "sqlite3.extension_dir = /usr/lib" | sudo tee /etc/php5/apache2/php.ini

echo "hacking in sqlite3 and spatialite3 support"

sudo pear config-set php_ini /etc/php5/apache2/php.ini

sudo pecl install uploadprogress >> install.log

echo "uploadprogress installed"

sudo apache2ctl graceful

echo -e "\n"
echo "Apache restarted"

echo -e "\n\n\n\n"

echo -e "Now fetching Heurist code from Google Code site"
wget https://heurist.googlecode.com/files/$1.tar.bz2 

tar -xjf $1.tar.bz2

echo "Heurist unpacked"

sudo mv $1/ /var/www/h3

cd /var/www/

sudo wget https://heurist.googlecode.com/git/favicon.ico

cd /var/www/h3/

# not sure why we fetched this as it is already in the code
# sudo wget https://heurist.googlecode.com/git/favicon.ico

echo -e "\n\n\n"

echo "You will be asked for your mysql root password to allow creation of the default database"

echo "CREATE DATABASE hdb_ExampleDB" | mysql -uroot -p

echo "Please enter your mysql root password... again..."
mysql -uroot -p hdb_ExampleDB < /var/www/h3/admin/setup/buildExampleDB.sql

echo -e "\n\n"
echo "Creating directories and setting permissions"

sudo chown -R www-data:www-data /var/www/h3/

sudo mkdir /var/www/HEURIST_FILESTORE
sudo chown www-data:www-data /var/www/HEURIST_FILESTORE

sudo mkdir /var/www/HEURIST_FILESTORE/ExampleDB

sudo cp -r /var/www/h3/admin/setup/rectype-icons/ /var/www/HEURIST_FILESTORE/ExampleDB
sudo cp -r /var/www/h3/admin/setup/smarty-templates/ /var/www/HEURIST_FILESTORE/ExampleDB
sudo cp -r /var/www/h3/admin/setup/xsl-templates/ /var/www/HEURIST_FILESTORE/ExampleDB

sudo mkdir /var/www/HEURIST_FILESTORE/ExampleDB/filethumbs
sudo mkdir /var/www/HEURIST_FILESTORE/ExampleDB/generated-reports
sudo mkdir /var/www/HEURIST_FILESTORE/ExampleDB/hml-output
sudo mkdir /var/www/HEURIST_FILESTORE/ExampleDB/html-output
sudo mkdir /var/www/HEURIST_FILESTORE/ExampleDB/scratch
sudo mkdir /var/www/HEURIST_FILESTORE/ExampleDB/settings
sudo mkdir /var/www/HEURIST_FILESTORE/ExampleDB/backup

sudo chown -R www-data:www-data /var/www/HEURIST_FILESTORE
sudo chmod -R gu+rwx  /var/www/HEURIST_FILESTORE
sudo chmod -R a+r /var/www/HEURIST_FILESTORE

echo -e "\n\n\n\n\n\n"

echo "---- Installation complete -----"
echo -e "\n"
echo "Please edit configIni.php to set your MySQL root user + password (twice, clearly documented in file)"
echo -e "\n\n"
echo "Now do this:    cd /var/www/h3"
echo "                sudo nano configIni.php       and set MySQL passwords"
echo -e "\n\n"
