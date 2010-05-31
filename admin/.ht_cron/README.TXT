
COULD WE RENAME .ht_cron TO A STANDARD NAME SUCH AS cronjobs

The .ht... namign suggests it is a directory with special functions

actually it is a special function setup in the httpd.conf

# The following lines prevent .htaccess and .htpasswd (any things begining with .ht) files from being 
# viewed by Web clients. 
#
<FilesMatch "^\.ht">
    Order allow,deny
    Deny from all
    Satisfy All
</FilesMatch>


