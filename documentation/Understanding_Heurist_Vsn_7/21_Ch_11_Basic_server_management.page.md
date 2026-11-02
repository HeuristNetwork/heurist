# Ch 11: Basic server management

This chapter outlines some useful procedures for managing Heurist servers, including the servers managed by the Heurist development team / Heurist Network.

#### **General observations**

Heurist is designed to run on any Linux server

#### **Emergency Unix commands**

<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">These commands may depend on the Unix version and the way MySQL is configured, refer to system documentation or ask AI for instructions if these do not work </span>

- <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">To restart MySQL (commonest form of failure eg. if disk space exhausted or stuck query). Works on HeuristRef.Net (OVH Cloud), HeuristAU.net (Intersect server) and Heurist.Huma-Num.fr</span>  
    <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);"> sudo service mysqld restart</span>  
    <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">Note: Sometimes </span>**restart** <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">does nothing (no response). In that case first use </span>**stop**<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">, then use </span>**start**
- <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">To restart Apache web server</span>  
    <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);"> sudo apachectl restart</span>

#### **Handy Unix commands &amp; other useful things**

- <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">Find Unix version: cat /etc/os-release</span>  
    <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);"></span>
- <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">Disk usage of subdirectories, largest first : </span>  
    <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);"> </span>*sudo du -sh /var/log/\*/ | sort -hr*
- <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">Delete files older than 30 days : </span>  
    <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);"> </span>*/usr/bin/find /var/log -maxdepth 1 -type f -name '\*.gz' -mtime +30 -delete*  
     (maxdepth 1 will go down one level into immediate subdirectory)
- The MySQL password (for the *root* and/or *heurist* user) <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">is set in the </span>*user*<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);"> table of the </span>*mysql*<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);"> database,</span>  
    <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">and configured in the /var/www/html/HEURIST/heuristConfigIni.php file for all instances, which may be overridden for a specific instance by /var/www/html/HEURIST/hx-xxxxxx/configIni.php.</span>

#### **Protocol for full update of Heurist servers**

**On HeuristRef.net (Heurit development team)**

<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">These are commands to be run from the unix interface accessed through SSH</span>

<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">Update the version number as required in the hx-alpha code on the reference server (HeuristRef.net) </span>  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">and commit to the gitHub repository</span>

<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">cd /var/www/html/HEURIST</span>  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">cd h7-alpha</span> *Change to the current development version (or beta)*  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">sudo nano configIni.php </span> *Update the version number (*<u>*also update on gitHub*</u>*)*  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">sudo ./copy\_distribution\_files.sh h7-alpha</span> *Update the development version distribution tarfile*  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">sudo ./copy\_distribution\_files.sh h7-test</span> *Update the test version distribution tarfile*

<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">cd .. </span> *Change back to HEURIST directory*  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">sudo ./copy\_h7-alpha-to-heurist.sh </span> *Update the ' production' version from h7-alpha*  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">cd heurist </span>*Change to the ' production' /heurist/ directory*  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">sudo ./copy\_distribution\_files.sh heurist</span> *Update the production version distribution tarfile*

<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">Contents of /var/www/html/HEURIST/DISTRIBUTION :</span>  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">-rw-rwxr--. 1 apache heurist 15980782 Jun 5 06:57 heurist.tar.bz2</span>  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">-rw-rwxr--. 1 apache heurist 2506 Jun 5 06:57 verifyInstallation.zip</span>  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">-rw-rwxr--. 1 apache heurist 6472 Jun 5 06:57 copy\_distribution\_files.sh</span>  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">-rw-rwxr--. 1 apache heurist 15947835 Jun 5 06:55 h7-alpha.tar.bz2</span>  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">-rw-rwxr--. 1 apache heurist 15954804 Jun 5 06:05 h7-test.tar.bz2</span>  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">-rw-rwxr--. 1 apache heurist 9324732 Jun 5 00:45 h8-alpha.tar.bz2</span>  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">-rwxrwxr--. 1 apache heurist 6333 Jan 16 09:44 update\_heurist.sh</span>  
<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">drwxrwxr-x. 2 apache heurist 75 Nov 25 2025 HEURIST\_SUPPORT</span>

**On other Heurist servers**

This protocol is that used on the servers managed by the Heurist development team as of 2026 (Heurist.Huma-Num.fr, HeuristAU.Net and Greek NHRF server)

<u>The same principles can be applied on any server</u>, but this should be automated (at least for h7-alpha)   
through a cron setting (typically edited with **sudo crontab -e**<span style="color: rgb(0, 0, 0);">)</span>

<span style="color: rgb(0, 0, 0);">\# Update heurist alpha version from reference server - build runs daily at 00:30</span>  
<span style="color: rgb(0, 0, 0);">\# Note: 'dummy' parameter replaces sudo param as some servers eg. Huma-Num do not accept sudo without a tty</span>  
<span style="color: rgb(0, 0, 0);">\# This does not update the support libraries, need to run manually without "codeonly" to do this</span>  
  
<span style="color: rgb(0, 0, 0);">30 00 \* \* \* curl -l https://heuristref.net/HEURIST/DISTRIBUTION/update\_heurist.sh | bash -s h7-alpha dummy codeonly &gt;&gt; /var/www/html/HEURIST/h7-alpha\_install.log 2&gt;&amp;1</span>

<span style="color: rgb(0, 0, 0);">\# precautionary fix of group ownership and permissions</span>  
<span style="color: rgb(0, 0, 0);">00 01 \* \* \* chown -R apache:heurist /var/www/html/HEURIST</span>  
<span style="color: rgb(0, 0, 0);">00 01 \* \* \* chmod -R g+rwx /var/www/html/HEURIST</span>  
<span style="color: rgb(0, 0, 0);">00 01 \* \* \* chown -R apache:heurist /data/HEURIST\_FILESTORE</span>  
<span style="color: rgb(0, 0, 0);">00 01 \* \* \* chmod -R g+rwx /data/HEURIST\_FILESTORE</span>

*Omitting codeonly at the end causes the support files to be updated - this is generally unnecessary and is only needed once, as all versions share the same support files (we make sure that is always the case, even if there are copies of different versions of the same library)*

<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">Update the test version including the support files</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">curl -l https://HeuristRef.net/HEURIST/DISTRIBUTION/update\_heurist.sh | bash -s h7-test sudo </span>

<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">Update the code only for h7-alpha:</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">curl -l https://HeuristRef.net/HEURIST/DISTRIBUTION/update\_heurist.sh | bash -s h7-alpha sudo codeonly</span>

<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">Copy h7-alpha to heurist:</span>|  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">cd /var/www/html/HEURIST</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">sudo ./copy\_h7-alpha-to-heurist.sh</span>

<span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">or alternatively: </span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">cd /var/www/html/HEURIST</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">chown -R apache heurist</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">chgrp -R heurist heurist</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">rm -Rf heurist-temp</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">cp -R h7-alpha heurist-temp</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">rm -Rf heurist-prev</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">mv heurist heurist-prev</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">mv heurist-temp heurist</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">chown -R apache heurist</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">chgrp -R heurist heurist</span>  
 <span style="color: rgb(34, 34, 34); background-color: rgb(255, 255, 255);">rm -Rf heurist-temp</span>

<p class="callout warning">**Remember to update the version number in configIni.php**   
**and commit to gitHub with a title suh as "Version 7.?.? distribution"** </p>

#### Server Manager functions

Heurist's web interface includes a restricted menu (Admin &gt; Server Manager) which is accessed through a special password set in the heuristConfigIni.php file. This allows the managers of a particular instance to carry out operations across all the databases on the server, including some general integrity checks and maintenance operations, obtaining lists of users and the databases they are attached to, statistics about usage and disk space, bulk mailing users etc.

Since this is only available to the server managers, and since the functions are relatively self obvious and include some explanation when selected, we will not bother with further documentation.

![](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-kfkdrrre.png)

#### Log files and performance

There are often numerous timestamped tables such as:

```
import20260709033509.ibd
import20260710034725.ibd
import20260711005435.ibd
```

These are import-working tables and may collectively consume substantial space across thousands of databases (in practice on Huma-Num in July 2026 they only consume a few hundred MBytes). Investigate them with:

```
SELECT TABLE_SCHEMA, TABLE_NAME,
       ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 1) AS size_mb
FROM information_schema.TABLES
WHERE TABLE_NAME REGEXP '^import[0-9]{14}$'
ORDER BY DATA_LENGTH + INDEX_LENGTH DESC;
```

They should only be dropped after confirming that Heurist no longer needs them.

To see the largest tables across the server :

*Note: on the Huma-Num server this times out. It's probably a good idea to focus on rec\_Details which is generally the largest table i nteh database. Records could also be large.*

```
SELECT TABLE_SCHEMA, TABLE_NAME,
       ENGINE,
       ROUND(DATA_LENGTH / 1024 / 1024, 1) AS data_mb,
       ROUND(INDEX_LENGTH / 1024 / 1024, 1) AS index_mb,
       ROUND(DATA_FREE / 1024 / 1024, 1) AS free_mb
FROM information_schema.TABLES
ORDER BY DATA_LENGTH + INDEX_LENGTH DESC
LIMIT 50;
```

### Performance

Nothing in the filenames suggests an obvious InnoDB performance fault. The useful checks are:

```
SELECT VERSION();

SHOW VARIABLES WHERE Variable_name IN
('innodb_buffer_pool_size',
 'innodb_file_per_table',
 'slow_query_log',
 'long_query_time',
 'innodb_temp_data_file_path');

SHOW GLOBAL STATUS WHERE Variable_name IN
('Innodb_buffer_pool_reads',
 'Innodb_buffer_pool_read_requests',
 'Created_tmp_disk_tables',
 'Created_tmp_tables');
```

The main performance consideration will usually be whether `<span class="editor-theme-code">innodb_buffer_pool_size</span>` is suitably matched to the server’s RAM and workload—not reducing `<span class="editor-theme-code">ibdata1</span>`. The slow-query log is valuable here: analyse it before discarding the old contents, because it can identify the queries and indexes responsible for poor performance.

#### Log rotation

### Immediate cleanup: rotate the slow-query log

First check whether it is already managed:

```
sudo grep -R "slow.log\|slow_query" /etc/logrotate.d /etc/logrotate.conf
```

If not, create `<span class="editor-theme-code">/etc/logrotate.d/mysql-slow</span>` containing:

```
/var/lib/mysql/*-slow.log {
    weekly
    rotate 12
    size 100M
    compress
    delaycompress
    missingok
    notifempty
    create 640 mysql mysql
    sharedscripts
    postrotate
        /usr/bin/mysqladmin flush-logs >/dev/null 2>&1 || true
    endscript
}
```

Do not simply delete the active slow log: MySQL may retain the open file handle, meaning the disk space is not actually released until the log is reopened.

Test the configuration with:

```
sudo logrotate -d /etc/logrotate.d/mysql-slow
```

### Setting up a new server

1/11/2026

We recommend asking ChatGPT or another AI to step through all the verificaitons necessary. The following set of instructions for Alma Linux, which assume prior installation of MySQL and PHP, is a useful starting point but will vary according to the Linux version.

```
sudo dnf update
sudo dnf install mc -y
sudo useradd -m your_user
sudo passwd your_user

#===== SSH service
sudo dnf install openssh-server -y
sudo systemctl enable sshd
sudo systemctl start sshd


#====== APACHE
sudo dnf install httpd -y
sudo systemctl restart httpd

#====== MYSQL (version 8.0.43)

sudo dnf install mysql-server -y

sudo systemctl start mysqld
sudo systemctl enable mysqld

sudo systemctl status mysqld

sudo mysql_secure_installation

mysql -u root -p
CREATE USER 'heurist'@'localhost' IDENTIFIED BY '[password]';
GRANT ALL PRIVILEGES ON *.* TO 'heurist'@'localhost';
FLUSH privileges;

CREATE USER 'heurist'@'127.0.0.1' IDENTIFIED BY '[password]';
GRANT ALL PRIVILEGES ON *.* TO 'heurist'@'127.0.0.1';
FLUSH privileges;

ALTER USER 'heurist'@'127.0.0.1' IDENTIFIED BY '[password]';
ALTER USER 'heurist'@'localhost' IDENTIFIED BY '[password]';
FLUSH PRIVILEGES;

#====== PHP (php 8.0.30)
sudo dnf install php php-cli php-mysqlnd -y      

# Note php-fpm is installed along with php, otherwise

sudo dnf install php-fpm -y
sudo systemctl enable --now php-fpm
sudo systemctl status php-fpm

====== Firewall
# If you need install firewall
sudo dnf install -y firewalld
sudo systemctl enable --now firewalld
sudo systemctl status firewalld

# Add services:
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
sudo firewall-cmd --list-all

====== Composer for development

sudo dnf install php-cli php-json php-mbstring git wget unzip -y
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer 

php /usr/local/bin/composer --version

====== HEURIST

chmod +x check_heurist_prereqs.sh
./check_heurist_prereqs.sh

# It list all missed reqs and hints how to install them.
# Install missed prerequirements. Like 

sudo dnf install wget
wget --version

# download and install heurist
chmod +x install_heurist7.sh
./install_heurist7.sh h7-alpha sudo

#in heurist folder (if you wish update vendor folder)
php /usr/local/bin/composer update

# ensure folder has access for php
sudo chown -R apache:apache /var/www/html/HEURIST/HEURIST_FILESTORE
sudo chmod 2775 /var/www/html/HEURIST/HEURIST_FILESTORE

# Check SELinux may block php folder operations

# Give httpd writeable context to file_storage (recursively)
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/html/HEURIST/HEURIST_FILESTORE(/.*)?'
sudo restorecon -Rv /var/www/html/HEURIST/HEURIST_FILESTORE
# to verify later anytime
ls -ldZ /var/www/html/HEURIST/HEURIST_FILESTORE
# If PHP needs outbound network
sudo setsebool -P httpd_can_network_connect on
======
#Configure apache
# To install SSL 
sudo dnf install -y mod_ssl
httpd -M | grep ssl

# https://certbot.eff.org/instructions?ws=apache&os=snap
#Installing snap on AlmaLinux OS https://snapcraft.io/docs/installing-snap-on-almalinux
sudo dnf install epel-release
sudo dnf upgrade
sudo dnf install snapd
sudo systemctl enable --now snapd.socket
sudo ln -s /var/lib/snapd/snap /snap
sudo systemctl start snapd.service
sudo snap wait system seed.loaded
sudo snap version
sudo snap changes
#install CertBot
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot

======
# Copy files from move_to_parent to installation folder /var/www/html/HEURIST
# Set params in heuristConfigIni.php
# Most important are: dbserver user+pwd and url+path to storage folder
```