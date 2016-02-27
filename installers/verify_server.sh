#! /bin/sh

# verify_server.sh: Verifies packages etc. on Ubuntu/Debian server

# Note: This file is referenced in the installation instructions on HeuristNetwork.org
#       do not move, rename or delete

# Note: this file retrieved from old distribution on 16 sep 2015, may need updating

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2016 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @author      Brian Ballsun-Stanton <brian@fedarch.org>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     3.4

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

echo -e "\n\n\n\n\n\n\n\n\n\n\n\n"

echo
echo "----------------------- Checking server for installed packages Ubuntu/Debian ---------------------------"
echo
echo


echo -e "\n\n\n\n\n\n"
                               

/* REMOVED
echo "checking for: wget"
if ! dpkg -s wget > /dev/null; then
        echo "wget not found."
        exit 1
fi

echo "checking for: memcached"
if ! dpkg -s memcached > /dev/null; then
        echo "memcached not found."
        exit 1
fi

echo "checking for: libapache2-mod-php5"
if ! dpkg -s libapache2-mod-php5 > /dev/null; then
        echo "libapache2-mod-php5 not found."
        exit 1
fi

*/

/* ******** REQUIRED ************ */
                               
echo "checking for: curl"
if ! dpkg -s curl > /dev/null; then
        echo "curl not found."
        exit 1
fi

echo "checking for: xsl"
if ! dpkg -s xsl > /dev/null; then
        echo "xsl not found."
        exit 1
fi

echo "checking for: mysql"
if ! dpkg -s mysql > /dev/null; then
        echo "mysql not found."
        exit 1
fi

echo "checking for: mysqli"
if ! dpkg -s mysqli > /dev/null; then
        echo "mysqli not found."
        exit 1
fi

echo "checking for: memcache"
if ! dpkg -s php5-memcache > /dev/null; then
        echo "php5-memcache not found."
        exit 1
fi

echo "checking for: SPL"
if ! dpkg -s SPL > /dev/null; then
        echo "SPL not found."
        exit 1
fi

echo "checking for: filter"
if ! dpkg -s filter > /dev/null; then
        echo "filter not found."
        exit 1
fi

echo "checking for: pcre"
if ! dpkg -s pcre > /dev/null; then
        echo "pcre not found."
        exit 1
fi

echo "checking for: xml"
if ! dpkg -s xml > /dev/null; then
        echo "xml not found."
        exit 1
fi

echo "checking for: simpleXML"
if ! dpkg -s simpleXML > /dev/null; then
        echo "simpleXML not found."
        exit 1
fi

echo "checking for: gd"
if ! dpkg -s gd > /dev/null; then
        echo "gd not found."
        exit 1
fi

echo "checking for: zip"
if ! dpkg -s zip > /dev/null; then
        echo "zip not found."
        exit 1
fi

echo "checking for: mbstring"
if ! dpkg -s mbstring > /dev/null; then
        echo "mbstring not found."
        exit 1
fi

echo "checking for: json"
if ! dpkg -s json > /dev/null; then
        echo "json not found."
        exit 1
fi

echo "checking for: session"
if ! dpkg -s session > /dev/null; then
        echo "session not found."
        exit 1
fi

echo "checking for: dom"
if ! dpkg -s dom > /dev/null; then
        echo "dom not found."
        exit 1
fi

/* ******** OPTIONAL ************ */

echo "checking for: pdo"
if ! dpkg -s pdo > /dev/null; then
        echo "pdo not found (only req. for FAIMS)."
        exit 1
fi

echo "checking for: pdo_sqlite"
if ! dpkg -s pdo_sqlite > /dev/null; then
        echo "pdo_sqlite not found (only req. for FAIMS)."
        exit 1
fi

echo "checking for: exif"
if ! dpkg -s exif > /dev/null; then
        echo "exif not found  (only req. for file indexing)."
        exit 1
fi


echo "Checks completed. Errors, if any, are reported above."

echo


