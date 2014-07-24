#! /bin/sh

# copy_distribution_files.sh: Creates a distribution package in h3-build from a Heurist working directory

# @package     Heurist academic knowledge management system
# @link        http://HeuristNetwork.org
# @copyright   (C) 2005-2014 University of Sydney
# @author      Ian Johnson     <ian.johnson@sydney.edu.au>
# @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
# @version     3.2

# Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
# with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
# Unless required by applicable law or agreed to in writing, software distributed under the License is
# distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
# See the License for the specific language governing permissions and limitations under the License.

if [ -z $1 ]
   then echo "Usage:   sudo ./copy_distribution_files.sh. h3.x.x.xxxx   - insert appropriate sub-versions and alpha or beta in x.x-xxxx"
   exit
   fi

# copy_distribution_files.sh
# --------------------------
# This file copies all necessary (and some uncessary ..) Heurist vsn 3 distribution files
# from the current directory (any Heurist h3-xx program directory) to a temporary directory in the
# h3-build directory in the same parent (/var/www/html/HEURIST) as the current directory.
# Make sure the current directory is the up-to-date version you want to package.
# The script is set up for the HeuristScholar server and may require modification for other directory layouts

# RUN THIS FILE FROM AN h3-xx DIRECTORY CONTAINING DESIRED HEURIST CODE

# Expects h3-build directory in same parent directory as h3-xx directory (ie. /var/www/html/HEURIST)
# Modified 9th dec 2013 to run on new virtual server, minor updates 9/6/14 and 16-23/7/14


echo -e "\n\n\n\n"
echo -------------------------------------------------------------------------------------------------
echo
echo "Heurist Vsn 3 distribution builder - run from /var/www/html/HEURIST/h3-xx program directory"

# File paths specified with explicit h3-build to ensure it will only remove from this specific directory
# and files in the subdirectory of this directory specified by the parameter

echo
echo removing existing h3-build/$1
rm -rf ../h3-build/$1

echo
echo copying files to ../h3-build/$1

mkdir ../h3-build/$1

# Copy all the files in the root of h3-xx
cp -r *.* ../h3-build/$1

# Remember to add any new directories here
cp -r admin ../h3-build/$1
cp -r applications ../h3-build/$1
cp -r common ../h3-build/$1
cp -r context_help ../h3-build/$1
cp -r documentation ../h3-build/$1
cp -r export ../h3-build/$1
cp -r hapi ../h3-build/$1
cp -r import ../h3-build/$1
cp -r records ../h3-build/$1
cp -r search ../h3-build/$1
cp -r viewers ../h3-build/$1

# Copy the simlinks for external, help and exemplars
cp -r ../h3/external ../h3-build/$1
cp -r ../h3/help ../h3-build/$1
cp -r ../h3/exemplars ../h3-build/$1

# copy the installation functions to DISTRIBUTION
cp install_* ../DISTRIBUTION

# remove any superfluous files - add others as appropriate
rm -f ../h3-build/$1/copy_distribution_files.sh
rm -f ../h3-build/$1/install_heurist_*.sh
rm -f ../h3-build/$1/install_prerequisites_*.sh

# Now zip it all up as a tarball for distribution on the Heurist web site

echo
echo creating tarball ../h3-build/$1.tar.bz2
rm -f ../h3-build/$1.tar.bz2
tar -cjf ../h3-build/$1.tar.bz2 -C ../h3-build/ $1/

# show what we have got in the directory
ls -alt ../h3-build

echo
echo "Now copy  ../h3-build/$1.tar.bz2 to /var/www/html/HEURIST/DISTRIBUTION"
echo
echo DO NOT CHANGE THE NAME of the tar.bz2 file - it extracts to a folder of this name
echo and the installation is dependant on the filename parameter to find this folder
echo
echo Use:  cd ../h3-build  then  sudo rm ../DISTRIBUTION/$1.tar.bz2  THEN  sudo mv $1.tar.bz2 ../DISTRIBUTION
echo
echo You may also wish to run    sudo ./copy_support_files.sh   to create new tarballs of support materials
echo
