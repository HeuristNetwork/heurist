#! /bin/sh
set -e
set -u
set -o pipefail

# verify_server.sh: Verifies packages etc. on Ubuntu/Debian server

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2014 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @author      Brian Ballsun-Stanton <brian@fedarch.org>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU Public License 3.0
# @version     3.4

# Licensed under the GNU General Pulic License, Version 3.0 (the "License"); you may not use this file except
# in compliance with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

echo -e "\n\n\n\n\n\n\n\n\n\n\n\n"

echo
echo "----------------------- Checking server for installed packages ---------------------------"
echo
echo


echo -e "\n\n\n\n\n\n"

echo "checking for: curl"
if ! dpkg -s curl > /dev/null; then
        echo "curl not found. Please install following instructions above, then run script again."
        exit 1
fi

echo "checking for: wget"
if ! dpkg -s wget > /dev/null; then
        echo "wget not found. Please install following instructions above, then run script again."
        exit 1
fi

echo "checking for: php5-curl"
if ! dpkg -s php5-curl > /dev/null; then
        echo "php5-curl not found. Please install following instructions above, then run script again."
        exit 1
fi

echo "checking for: php5-xsl"
if ! dpkg -s php5-xsl > /dev/null; then
        echo "php5-xsl not found. Please install following instructions above, then run script again."
        exit 1
fi

echo "checking for: php5-mysql"
if ! dpkg -s php5-mysql > /dev/null; then
        echo "php5-mysql not found. Please install following instructions above, then run script again."
        exit 1
fi

echo "checking for: php5-memcache"
if ! dpkg -s php5-memcache > /dev/null; then
        echo "php5-memcache not found. Please install following instructions above, then run script again."
        exit 1
fi

echo "checking for: memcached"
if ! dpkg -s memcached > /dev/null; then
        echo "memcached not found. Please install following instructions above, then run script again."
        exit 1
fi

echo "checking for: php5-gd"
if ! dpkg -s php5-gd > /dev/null; then
        echo "php5-gd not found. Please install following instructions above, then run script again."
        exit 1
fi

echo "checking for: libapache2-mod-php5"
if ! dpkg -s libapache2-mod-php5 > /dev/null; then
        echo "libapache2-mod-php5 not found. Please install following instructions above, then run script again."
        exit 1
fi

echo -e "\n\n\n\n\n"
echo
echo "---- FINISHED -------------------------------------------"
echo
echo -e "If it gets to here, all expected packages are installed \n\n\n"
