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

echo
echo Minimal set of components for Heurist, assuming LAMP server is installed

# sudo apt-get update
# sudo apt-get upgrade -y

# sudo apt-get install tasksel software-properties-common python-software-properties -y
# sudo tasksel install lamp-server

sudo apt-get install php5-curl php5-xsl php5-mysql php5-memcache php5-gd zip unzip
sudo apt-get install pdo_mysql
sudo apt-get install php5-pdo
sudo apt-get install php5-mbstring

# If errors are on, errors are output to the browser and stuff up Heurist interface
sed 's/^display_errors = .*/display_errors = Off/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini

# increase upload file size from default 2M
sed 's/^upload_max_filesize = .*/upload_max_filesize = 250M/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini
sed 's/^post_max_size.*/post_max_size = 251M/' < /etc/php5/apache2/php.ini | sudo tee /etc/php5/apache2/php.ini

cd /var/www/html/HEURIST
sudo curl -L -O http://download.elasticsearch.org/download/elasticsearch-1.3.1.tar.gz
sudo tar -zxvf elasticsearch-1.3.1.tar.gz
cd  elasticsearch-1.3.1
./bin/elasticsearch -d

sudo apache2ctl graceful

echo
echo done ...
echo