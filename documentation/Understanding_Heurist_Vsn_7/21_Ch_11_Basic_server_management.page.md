# Ch 11: Basic server management

This chapter outlines some useful procedures for managing Heurist servers, including the servers managed by the Heurist development team / Heurist Network.

#### **General observations**

Heurist is designed to run on any Linux server

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