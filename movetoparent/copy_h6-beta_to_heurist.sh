#! /bin/sh

# This file is intended for use on the Heurist development server at HeuristRef.net

# It copies h6-alpha to the production version of heurist (.../HEURIST/heurist) and the current version to heurist-prev

cd /var/www/html/HEURIST

chown -R apache  heurist
chgrp -R heurist heurist

rm -Rf heurist-temp
cp -R h6-beta heurist-temp
rm -Rf heurist-prev
mv heurist heurist-prev
mv heurist-temp heurist

chown -R apache heurist
chgrp -R heurist heurist

rm -Rf heurist-temp

