#! /bin/sh

# verify_server.sh: Verifies packages etc. on Ubuntu/Debian server

# Note: This file is referenced in the installation instructions on HeuristNetwork.org
#       do not move, rename or delete

# Note: this file retrieved from old distribution on 16 sep 2015, may need updating

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2015 University of Sydney
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
echo "----------------------- Checking server for installed packages ---------------------------"
echo
echo


echo -e "\n\n\n\n\n\n"

echo "checking for: curl"
if ! dpkg -s curl > /dev/null; then
        echo "curl not found."
        exit 1
fi

echo "checking for: wget"
if ! dpkg -s wget > /dev/null; then
        echo "wget not found."
        exit 1
fi

echo "checking for: php5-curl"
if ! dpkg -s php5-curl > /dev/null; then
        echo "php5-curl not found."
        exit 1
fi

echo "checking for: php5-xsl"
if ! dpkg -s php5-xsl > /dev/null; then
        echo "php5-xsl not found."
        exit 1
fi

echo "checking for: php5-mysql"
if ! dpkg -s php5-mysql > /dev/null; then
        echo "php5-mysql not found."
        exit 1
fi

echo "checking for: php5-memcached"
if ! dpkg -s php5-memcached > /dev/null; then
        echo "php5-memcached not found."
        exit 1
fi

echo "checking for: memcached"
if ! dpkg -s memcached > /dev/null; then
        echo "memcached not found."
        exit 1
fi

echo "checking for: php5-gd"
if ! dpkg -s php5-gd > /dev/null; then
        echo "php5-gd not found."
        exit 1
fi

echo "checking for: libapache2-mod-php5"
if ! dpkg -s libapache2-mod-php5 > /dev/null; then
        echo "libapache2-mod-php5 not found."
        exit 1

echo -e "\n\n\n\n\n"
echo
echo "---- FINISHED -------------------------------------------"
echo
echo "If it gets to here, all expected packages are installed"
echo
echo
echo
echo
echo