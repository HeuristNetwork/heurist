#!/bin/sh
#
# Archive databases when /srv/BACKUP copy differs from copy in ARCHIVE directory
# 1. Rename archive with date suffix 
# 2. Copy db archive from BACKUP to ARCHIVE

cd /srv/BACKUP

# Allow others to read backups
umask 022


for f in `ls *.bz2`; do

        cmd=`diff -a -q $f ARCHIVE/$f   > /dev/null 2>&1`
        rc="$?"
        # echo "diff $f ARCHIVE/$f"
        #echo $rc
        # exit

        if [ $rc -eq  0 ]
        then
                echo "Do nothing with $f"
        fi

        if [ $rc -eq  2 ]
        then
                echo "File is new $f"
                cp $f ARCHIVE/$f
        fi

        if [ $rc -eq  1 ]
        then
                echo "File is changed to ARCHIVING $f"
                mv ARCHIVE/$f "ARCHIVE/$f.$(date +'%Y-%m-%d')" 2> /dev/null
                cp $f ARCHIVE/$f
        fi

done

