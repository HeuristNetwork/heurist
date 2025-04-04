### **Heurist research data management system \- Overview**

Ian Johnson 3 april 2025

https://HeuristNetwork.org    (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network

### **Overview**   

Heurist is a mature web-based data management infrastructure that is specifically tailored to the needs of Humanities researchers. Heurist allows researchers to design, create, manage, analyse and publish their own richly-structured database(s) through a simple web interface, without the need for programmers or consultants. A complete application can be built in as little as half a day and complex databases in under a week. Database structure can be modified incrementally on live databases allowing them to evolve with project needs. The project runs free services for researchers independent of institution (through Intersect in Australia and the Huma-Num eResearch service in France), which frees the researcher from the need to manage servers, backups and upgrades.

Heurist is built in php, javascript and mysql. It has been developed under the direction of Dr Ian Johnson, formerly Director of the Archaeological Computing Laboratory, later Spatial Science Innovation Unit, then Arts eResearch, University of Sydney, with the assistance of ACL programmers. From 2015 onwards it continued with administrative processing but no funding from the University until 2024, when the administrative support was removed and it was transferred to a non-profit association Heurist Network based in Paris. Work is underway in 2025 / 2026 with the help of Huma-Num (huma-num.fr) to expand to a community of volunteer technical and documentation contributors.

**See \_README.md in the root of the codebase** for further information on the functions of the user interface and other background information. See files in the /documentation directory for general documentation, contribution and installation information

## ---

## **Understanding the code tree**

These are the directories in the root of the Heurist codebase. This codebase will generally be placed in /var/www/html/HEURIST/xxxxxxx where xxxxxxxx is either **heurist**  or a variant such as h6-alpha, h6-beta, heurist-prev, etc. 

Multiple versions of the software can thus co-exist on the server and be run interchangeably (the differences in database format between successive versions at the sub-version level are generally small and permit backwards and forwards compatibility; later versions will update the format with new tables, fields, field lengths etc. but will never change the definition or order of existing tables and fields).

### **/admin**

This directory contains all the administrative interface code from Heurist Vsn 3, also used by Heurist Vsn 4 onwards. In the end, all this is fairly simple code which has worked for years and has no relation to the performance and structure of the main data entry and retrieval functions, so it has been left untouched. It is the product of many hands, it is not elegant, but it works.

### **/documentation**

All general technical documentation should be placed here. Specific technical documentation can be found within code files, but this provides more general information on structure as well as specific context help files.

### **/hclient**

Client-side functions from version 4 onwards (late 2015). The client side functions are html and javascript which communicate with the server-side php functions. Server-side functions are in hserv. 

The data-critical, search and visualisation parts of the Heurist infrastructure are handled by hserv and hclient. Other directories in the root contain older code (pre 2015\) which carries out simple tasks such as database setup and simple listing, or server scripts and configurations. They use a variety of methodologies, but we have not had the resources, or priorities, to rewrite them properly (other than fixing identified security holes and maintainability issues) since they still work quite adequately and are low risk.

### **/hserv**

erver-side functions from version 4 onwards (late 2015), see also hclient for client-side functions. The server side functions are mainly PHP code which communicates with the client-side JS functions. The data-critical, search and visualisation parts of the Heurist infrastructure are handled by hserv and hclient               

### **/startup**

Overview:  Setup sequence \- register new user, create new database, getting started

index.php \- main script   
            a) to show Register new user and/or SetUp new database wizard   
            b) to show list of all databases (in case database not found or db parameter   
              
listDatabases.php \- returns json array with all databases on server   
gettingStarted.html   \- html snippets for introductory guides on startup  
userRegistration.html \- content of new user registration form

### **/viewers**

Functions for viewing and analysing user's Heurist data. They can work within the main Heurist interface or be loaded independently. It is planned to move them as widgets into /hclient in 2025\.

crosstab \- crosstab (pivot) diagramm  
gmap     \- mapping based on google maps (only used in legacy projects)  
map      \- mapping based on leaflet and timeline  
record   \- general record data viewer/renderer  
smarty   \- interface to modify and render Custom Reports (Smarty templates)  
visualize \- connection diagram visualisation (graph renderer)

Note: Although these functions are mostly Version 3 code, they have been modified to work with the Heurist 4 and later search interfaces

### **/redirects**

Redirector files to the functions which are called across servers, notably to the Heurist Reference Index Server at HeuristRef.net. This includes **resolver.php** the basis of a PID system allowing access to any record, field value, or definition (of record type, field, vocabulary or term) through a single point of entry. 

By using these redirectors, we avoid dependency on scripts remaining in the same location    
eg. if the program code is restructured or new versions are written. That means that a newer, restructured version of the code can be accessed by an older version on a remote server, without the latter seeing any difference.

Version ( eg. \_V1) suffix is used to allow new versions of the target scripts to generate different output without breaking older code on third party servers

Some files are intended to provide a stable shortcut to a specific output, such as record view,  
which is human readable and may be referenced at a stable URL. If no version suffix is included the redirect is to human-readable output which may be changed/improved ie. it is a stable reference but to content which may potentially change in its detailed form.

### **/import**

With the exception of the delimited (CSV, TSV) importer, which has been extensively revised and improved over the years since it was first developed, these are Heurist version 3 functions for importing and disambiguating external data which have not been significantly updated since version 3\. They do what they do … 

These functions will probably be moved as widgets into /hclient in 2025/2026.

### **/export**

Functions for exporting result sets created by search/filter to various file output formats.      Although these functions are mostly Version 3 code, they have been modified to work with the Heurist 4 and later search interface.

These functions will probably be moved as widgets into /hclient in 2025/2026.

### **/movetoparent**

This directory contains files which should be placed in the parent directory of the codebase during installation of Heurist. We STRONGLY recommend using /var/www/html/HEURIST as the parent directory as all our server scripts, redirects and json jobs are written for this address. Heurist does not touch anything outside the HEURIST root directory, except through simlinks you may add.

heuristConfigIni.php is CRITICAL, as it sets all the local configuration parameters including server names, directory locations, and passwords for accessing the databases.

### **/server\_scripts**

Scripts for installation, update and verification of instances and management of databases on a server. These should be copied to an appropriate place eg. /srv/scripts and configured for the local environment, including creation of a global cron with *sudo crontab \-e*. 

---

## **Further information**

### **HEURIST**

Core development repository, Vsn 6 (2021 \- ), Vsn 5 (2018 \- 2020), Vsn 4 (2014-2017). Vsn 1 \- 3 on Sourceforge (2005 \- 2013\)

Note that the latest code is in h6-dev, h7-dev etc. Heurist version 7 will be relesed in 2025\.

Heurist is a mature web-based data management infrastructure that is specifically tailored to the needs of Humanities researchers. Heurist allows researchers to design, create, manage, analyse and publish their own richly-structured database(s) through a simple web interface, without the need for programmers or consultants. A complete application can be built in as little as half a day and complex databases in under a week. Database structure can be modified incrementally on live databases allowing them to evolve with project needs. The project runs free services for researchers independent of institution (through the University of Sydney in Australia and the Huma-Num eResearch service in France), which frees the researcher from the need to manage servers, backups and upgrades.

### **Version 6 Interface**

Advanced features include foreign keys and relationships seen and modified by the user as intuitive pointer fields, multi-level facet searches and network expansion rules built via a wizard, interactive maps and timelines, built-in CMS websites which embed saved searches, media and widgets from the database interface to publish searches linked to result lists, maps etc. A central index allows Heurist databases to import structural elements from any registered database to promote sharing of data models. Right-to-left scripts and Asian characters are fully supported. There is provision to translate the interface and for cross-database searching although we have not had the resources to do this.

### **Research database workflow**

Heurist has been developed at the University of Sydney since 2005, based on years of prior work, and is used by dozens of projects, particularly in Australia, France, Germany and the USA. These projects span the fields of history, archaeology, art history, geography, and literature. A selection of projects can be found here.

Heurist is in active development with more than 1000 commits/year since moving to gitHub in 2014\. Version 6 was released in February 2021 and represents a complete redesign of the menu system and overall appearance in collaboration with a professional UX designer (Brant Trim, Serata Digital, Canberra) but is fully backward compatible with databases developed in version 5\.

We transferred the help system from help builder software to a Heurist database in Nov 2020, which now generates the Heurist Help System (also accessible within Heurist). This is now being updated to reflect version 6 (work-in-progress 2021). Issue tracking is also handled by a Heurist database. 

### **Heurist Projects**

Heurist has a built-in capability to generate data-driven interactive websites. The CMS website pages are stored and edited directly in the database and can render media, searches, maps, blog pages etc. using the data. Heurist hosts dozens of research websites around the world, several of which are displayed below. You can see featured projects on our website.

### **Contributing**

We very much welcome collaboration and invite contributions to the development of Heurist. We will be delighted to exchange design ideas and our development roadmap with developers, researchers, documenters and trainers who would be interested in contributing to the system or developing specific extensions or training programs for their own use. Please feel free to add suggestions to our job tracking system (see below) or email support@heuristnetwork.org.

In 2025, we are undergoing significant refactoring and code documentation, and have formed a documentation group of power users and developers. To get started with the source code, you can view some of the items in the /documentation/ directory. Most of the server-side code is in the \`hserv\` directory. Most of the client code is in the \`hclient\` directory.

### **Source code & licensing**

The gitHub repository for Heurist source code is freely available under the standard GNU GPL 3 license. The main branch is \`h6-dev\`. This is occasionally merged with \`master\` for archiving purposes. This will change to h7-dev in 2025\.

### **Issues and feature requests**

Please note, we do not use gitHub issues. Please visit our \[job tracking system\]  
(https://HeuristRef.net/heurist/?db=Heurist\_Job\_Tracker\&website) developed in Heurist.   
You can raise an issue or request a feature there: first click the Add Job link at top left,   
then use login guest \+ guest (or request a personal login via the link on the login page). 

Issues and feature requests can also be submitted via the Issue tracker link in any Heurist database (Help \> Bug report/feature request, also a separate link in the top bar of the web interface) \- this also saves additional information about your use environment which may help resolve the issue and attributes a Ticket ID and sends you email when the ticket is cleared.

### **Online Help**

Comprehensive help is available in a dedicated Heurist database,   
[https://HeuristRef.net/heurist/?db=Heurist\_Help\_System\&website](https://HeuristRef.net/heurist/?db=Heurist_Help_System&website) Note that this is now being updated (April \- June 2025\) into a more narrative format, and will be republished in a searchable form.

### **Installation**

The distribution version at HeuristNetwork.org in the installation page is updated approximately once a month more frequently in case of urgent bug fixes. Monthly updates generally contain numerous tweaks and bug fixes, while significant new features are added every two to three months. The installation and update scripts (see installation page) are very simple and have been proven over a decade \- they should run on most Unix servers and have been designed specifically to restrict their activity to a single directory (/var/www/html/HEURIST) so that they do not mess with anything else installed on the server.

Heurist can also be installed on a Windows server \- Systemik Solutions are working on documentation, please contact us for information.

### **Feedback / questions**

Please add a Feature request / bug report and we will get back to you promptly.

