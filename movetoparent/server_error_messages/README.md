Apache ErrorDocument setup (example vhost snippet)
--------------------------------------------------

# Place these files under: /var/www/html/server_error_messages/
#  - 403.html
#  - 404.html
#  - 500.html
#  - 50x.html
#  - heurist.css (copied here)
#  - h6logo_inv.png
#  - favicon.ico

# Modify /etc/httpd/conf/httpd.conf
# ---------------------------------

# Map specific codes
ErrorDocument 404 /server_error_messages/404.html
ErrorDocument 403 /server_error_messages/403.html
ErrorDocument 500 /server_error_messages/500.html

# Map other 5xx family codes to the generic page
ErrorDocument 502 /server_error_messages/50x.html
ErrorDocument 503 /server_error_messages/50x.html
ErrorDocument 504 /server_error_messages/50x.html
ErrorDocument 505 /server_error_messages/50x.html

# ------------------------------------------------

# If you proxy to upstreams and want your pages for upstream errors (e.g., PHP-FPM 404/5xx):
ProxyErrorOverride On

# ------------------------------------------------

# Reload after changes:

sudo service apachectl restart

# or maybe:

#   apachectl configtest && systemctl reload apache2   # Debian/Ubuntu
#   apachectl configtest && systemctl reload httpd     # RHEL/CentOS/Alma/Rocky
