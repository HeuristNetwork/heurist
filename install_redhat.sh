
echo "This is a basic set of instructions used for installing on a Redhat Linux server"
echo "Use it as a guide - it will need editing appropriately for your setup"

# Author: Artem Osmakov Nov 2013

echo "Update PHP"
echo -e "\n"

sudo yum install memcached
sudo yum install php-mysql php-pecl-memcache php-devel php-pecl-memcached php-gd php-pear php-pdo php-sqlite

sed 's/^display_errors = .*/display_errors = Off/' < /etc/php.ini | sudo tee /etc/php.ini
sed 's/^upload_max_filesize = .*/upload_max_filesize = 30M/' < /etc/php.ini | sudo tee /etc/php.ini
sed 's/^post_max_size.*/post_max_size = 31M/' < /etc/php.ini | sudo tee /etc/php.ini

sudo pecl install uploadprogress

echo "Start servics"
echo -e "\n"

sudo service memcached start
sudo service mysqld start
sudo apachectl restart

echo "Get Heurist code"
echo -e "\n"

wget https://heurist.googlecode.com/files/h3-beta.tar.bz2 -P /tmp

cd /tmp

tar -xjf h3-beta.tar.bz2 

# sudo mkdir /var/www/html/vh_test001/h3

sudo mv /tmp/h3-beta/* /var/www/html/vh_test001

echo "Set permissions and SELinux type"
echo -e "\n"

sudo chown -R apache:apache /var/www/html/vh_test001/
sudo chmod -R 755 /var/www/html/vh_test001/
sudo chcon -Rt httpd_sys_content_t vh_test001/

echo "Create Example DB"
echo -e "\n"

echo "CREATE DATABASE hdb_ExampleDB" | mysql -uroot -p

echo "Please enter your mysql root password... again..."
mysql -uroot -p hdb_ExampleDB < /var/www/h3/admin/setup/buildExampleDB.sql


echo "Create folders for ExampleDB"
echo -e "\n"

sudo mkdir /dev/shm/HEURIST_FILESTORE/ExampleDB

sudo cp -r /var/www/html/heurist/admin/setup/rectype-icons/ /dev/shm/HEURIST_FILESTORE/ExampleDB
sudo cp -r /var/www/html/heurist/admin/setup/smarty-templates/ /dev/shm/HEURIST_FILESTORE/ExampleDB
sudo cp -r /var/www/html/heurist/admin/setup/xsl-templates/ /dev/shm/HEURIST_FILESTORE/ExampleDB

sudo mkdir /dev/shm/HEURIST_FILESTORE/ExampleDB/filethumbs
sudo mkdir /dev/shm/HEURIST_FILESTORE/ExampleDB/generated-reports
sudo mkdir /dev/shm/HEURIST_FILESTORE/ExampleDB/hml-output
sudo mkdir /dev/shm/HEURIST_FILESTORE/ExampleDB/html-output
sudo mkdir /dev/shm/HEURIST_FILESTORE/ExampleDB/scratch
sudo mkdir /dev/shm/HEURIST_FILESTORE/ExampleDB/settings
sudo mkdir /dev/shm/HEURIST_FILESTORE/ExampleDB/backup

sudo chown -R apache:apache /dev/shm/HEURIST_FILESTORE
sudo chmod -R gu+rwx  /dev/shm/HEURIST_FILESTORE
sudo chmod -R a+r /dev/shm/HEURIST_FILESTORE

echo "DONE"
echo -e "\n"

