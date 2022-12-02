#! /bin/sh

# THIS SCRIPT IS WORK IN PROGRESS AUGUST 2014

# install.sh: installation script for ElasticSearch (Lucene index wrapper)
# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2019 University of Sydney
# @author      Ian Johnson       <ian.johnson@sydney.edu.au>
# @author      Artem Osmakov     <artem.osmakov@sydney.edu.au>
# @author      Jan Jaap de Groot <jjedegroot@gmail.com>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     4.0

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.




TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO



see: http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/setup-repositories.html
for RHEL (yum) and Ubuntu (apt-get) installation

-------------------

export http_proxy="http://www-cache.usyd.edu.au:8080"

------------------

sudo nano /etc/yum.conf

add this at the end:

proxy=http://www-cache.usyd.edu.au:8080

-------------------

sudo nano /etc/yum.repos.d/elasticsearch.repo

should contain this:

[elasticsearch-1.3]
name=Elasticsearch repository for 1.3.x packages
baseurl=http://packages.elasticsearch.org/elasticsearch/1.3/centos
gpgcheck=1
gpgkey=http://packages.elasticsearch.org/GPG-KEY-elasticsearch
enabled=1

-------------------

You need to install JRE 1.7 for this version

-------------------

sudo yum install elasticsearch

-------------------

Start elastic search with:

sudo /etc/rc.d/init.d/elasticsearch start

---------------------------------------------------------------------------------------

CLEAN INSTALL
sudo yum install java-1.7.0-openjdk-devel
sudo ./var/www/html/HEURIST/HEURIST_SUPPORT/external/elasticsearch/elasticsearch-1.3.2/bin/elasticsearch -d

UPGRADING OR WHATEVER
Check installed java packages:
rpm -qa | grep -i j

Check java version:
java -version

Install JDK 1.7
sudo yum install java-1.7.0-openjdk-devel

Run elasticsearch
cd  /var/www/html/HEURIST/HEURIST_SUPPORT/external/elasticsearch/elasticsearch-1.3.2
sudo ./bin/elasticsearch -d

Check status
sudo ./etc/init.d/elasticsearch status
Any errors, restart!
sudo /etc/init.d/elasticsearch restart

Check if it works
curl -XGET 129.78.67.200:9200

Example
{
  "status" : 200,
  "name" : "Jerry Jaxon",
  "version" : {
    "number" : "1.1.2",
    "build_hash" : "e511f7b28b77c4d99175905fac65bffbf4c80cf7",
    "build_timestamp" : "2014-05-22T12:27:39Z",
    "build_snapshot" : false,
    "lucene_version" : "4.7"
  },
  "tagline" : "You Know, for Search"
}
