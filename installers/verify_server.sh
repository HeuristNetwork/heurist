#! /bin/sh

# verify_server.sh: Verifies packages etc. on Ubuntu/Debian server

# Note: This file is referenced in the installation instructions on HeuristNetwork.org
#       do not move, rename or delete

# Note: this file retrieved from old distribution on 16 sep 2015, may need updating

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2016 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
# @author      Abed Kassis     <abed.kassis@sydney.edu.au>
# @author      Brian Ballsun-Stanton <brian@fedarch.org>

# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     4.0

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

echo
echo "----------------------- Checking server for installed packages Ubuntu/Debian ---------------------------"
echo
echo

# ******** REQUIRED ************ 
                               
         
# TODO: need to use rpm for RHEL - how do you set the appropriate package manager and what parameter for rpm?
                               
echo "checking for: memcache"
if ! dpkg -s memcache > /dev/null; then
        echo " WARNING: memcache not found."
fi

echo "checking for: libapache2-mod-php5"
if ! dpkg -s libapache2-mod-php5 > /dev/null; then
        echo " WARNING: libapache2-mod-php5 not found."
fi

echo "checking for: gd"
if ! dpkg -s gd > /dev/null; then
        echo " WARNING: gd not found."
fi

echo "checking for:  pdo"
if ! dpkg -s  pdo > /dev/null; then
        echo " WARNING: pdo not found."
fi

echo "checking for:  mbstring"
if ! dpkg -s  mbstring > /dev/null; then
        echo " WARNING: mbstring not found."
fi

echo "checking for:  mysql"
if ! dpkg -s  mysql > /dev/null; then
        echo " WARNING: mysql not found."
fi

echo "checking for:  mysqli"
if ! dpkg -s  mysqli > /dev/null; then
        echo " WARNING: mysqli not found."
fi

echo "checking for:  json"
if ! dpkg -s  json > /dev/null; then
        echo " WARNING: json not found."
fi

echo "checking for:  session"
if ! dpkg -s  session > /dev/null; then
        echo " WARNING: session not found."
fi

echo "checking for:  dom"
if ! dpkg -s  dom > /dev/null; then
        echo " WARNING: dom not found."
fi

echo "checking for: curl"
if ! dpkg -s curl > /dev/null; then
        echo " WARNING: curl not found."
fi

echo "checking for: xsl"
if ! dpkg -s xsl > /dev/null; then
        echo " WARNING: xsl not found."
fi

echo "checking for: simpleXML"
if ! dpkg -s simpleXML > /dev/null; then
        echo " WARNING: simpleXML not found."
fi

echo "checking for: xml"
if ! dpkg -s xml > /dev/null; then
        echo " WARNING: xml not found."
fi

echo "checking for: apache2handler"
if ! dpkg -s apache2handler > /dev/null; then
        echo " WARNING: apache2handler not found."
fi

echo "checking for: pcre"
if ! dpkg -s pcre > /dev/null; then
        echo " WARNING: pcre not found."
fi

echo "checking for: filter"
if ! dpkg -s filter > /dev/null; then
        echo " WARNING: filter not found."
fi

echo "checking for: SPL"
if ! dpkg -s SPL > /dev/null; then
        echo " WARNING: SPL not found."
fi

echo "checking for: zip"
if ! dpkg -s zip > /dev/null; then
        echo " WARNING: zip not found."
fi


# ******** OPTIONAL ************ 

echo "checking for: pdo-sqlite"
if ! dpkg -s pdo-sqlite > /dev/null; then
        echo " WARNING: pdo-sqlite not found (optionsal, for FAIMS import)."
fi

echo "checking for: exif"
if ! dpkg -s exif > /dev/null; then
        echo " WARNING: exif not found (optional, for in-situ image indexing)."
fi

echo 
echo "Checks completed. Warnings, if any, are reported above."

echo


