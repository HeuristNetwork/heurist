# This file copies all necessary (and some uncessary ..) Heurist vsn 3 distribution files 
# from the current directory to a temporary directory in the h3-setup directory
# (run git fetch/git stash/git pull first to synch with h3share - don't forget to 
# git pop afterwards to restore any local changes)
# Ian Johnson May 2013
cp -r admin /var/www/htdocs/h3-setup/temp
cp -r common /var/www/htdocs/h3-setup/temp
cp -r export /var/www/htdocs/h3-setup/temp
cp -r hapi /var/www/htdocs/h3-setup/temp
cp -r help /var/www/htdocs/h3-setup/temp
cp -r import /var/www/htdocs/h3-setup/temp
cp -r records /var/www/htdocs/h3-setup/temp
cp -r search /var/www/htdocs/h3-setup/temp
cp -r viewers /var/www/htdocs/h3-setup/temp
cp -r *.* /var/www/htdocs/h3-setup/temp
cp -r /var/www/htdocs/h3/external /var/www/htdocs/h3-setup/temp
