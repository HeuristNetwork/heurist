#!/bin/sh

# This script lists out the filestore sizes for all databases on the server.
# Load into a spreadsheet or use _ less _ to find the ones exceeding or nearing the defautl quota
# Ian Johnson 21/11/2023

# run from /srv/scripts
# Input: none, assumes data is in standard location
# Output: Simple text file written into results directory in current directory

du -s /var/www/html/HEURIST/HEURIST_FILESTORE/* |sort -rn > results/filestore_sizes.txt

echo DONE
