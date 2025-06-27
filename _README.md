### **Heurist Research Data Management System - Overview**

**Ian Johnson, 3 April 2025**

**https://HeuristNetwork.org**
**(C) 2005-2023 University of Sydney**
**(C) 2024 onwards Heurist Network**

### **Overview**

Heurist is a mature, web-based data management infrastructure specifically tailored to the needs of Humanities researchers. It allows researchers to design, create, manage, analyze, and publish their own richly-structured databases through a simple web interface, without needing programmers or consultants. A complete application can be built in as little as half a day, and complex databases in under a week. Database structures can be modified incrementally on live databases, allowing them to evolve with project needs. The project runs free services for researchers, independent of their institution (through Intersect in Australia and the Huma-Num eResearch service in France). This frees researchers from managing servers, backups, and upgrades.

Heurist is built using PHP, JavaScript, and MySQL. It was developed under the direction of Dr. Ian Johnson (formerly Director of the Archaeological Computing Laboratory, later the Spatial Science Innovation Unit, then Arts eResearch at the University of Sydney) with assistance from ACL programmers. From 2015, it continued with administrative processing but no funding from the University until 2024. At that point, administrative support was removed, and Heurist was transferred to a non-profit association, Heurist Network, based in Paris. Work is underway in 2025/2026, with help from Huma-Num (huma-num.fr), to expand to a community of volunteer technical and documentation contributors.

**See the `_README.md` file in the root of the codebase** for further information on user interface functions and other background information. See files in the `/documentation` directory for general documentation, contribution guidelines, and installation information.

---

## **Understanding the Code Tree**

These are the directories in the root of the Heurist codebase. This codebase will generally be placed in `/var/www/html/HEURIST/xxxxxxx`, where `xxxxxxx` is either `heurist` or a variant such as `h6-alpha`, `h6-beta`, `heurist-prev`, etc.

Multiple versions of the software can thus coexist on the server and be run interchangeably. The differences in database format between successive sub-versions are generally small and permit backward and forward compatibility. Later versions will update the format with new tables, fields, field lengths, etc., but will never change the definition or order of existing tables and fields.

### **/admin**

This directory contains all the administrative interface code from Heurist Version 3, also used by Heurist Version 4 and later. This is fairly simple code that has worked for years and does not affect the performance or structure of the main data entry and retrieval functions, so it has been left untouched. It is the product of many hands; it is not elegant, but it works.

### **/documentation**

This directory serves as the central repository for all general technical documentation related to the Heurist project. While specific technical details may be found within individual code files, this section provides broader technical overviews, architectural details, coding conventions, context-sensitive help files, and information about the libraries and overall structure of the Heurist system.

### **/hclient**

This directory contains the client-side functionalities for the Heurist application (H4 and onwards, from late 2015). It comprises HTML, JavaScript, and CSS assets that communicate with server-side PHP functions located in the `hserv` directory. These components are responsible for the data-critical aspects, search capabilities, and visualization features of the Heurist infrastructure.

### **/hserv**

This directory contains server-side functions from Version 4 onwards (late 2015). See also `/hclient` for client-side functions. The server-side functions are mainly PHP code that communicates with client-side JavaScript functions. The data-critical, search, and visualization parts of the Heurist infrastructure are handled by `/hserv` and `/hclient`.

### **/startup**

This directory contains files related to the setup sequence, including new user registration, new database creation, and getting started guides.

### **/viewers**

Functions for viewing and analysing user's Heurist data. They can work within main Heurist interface or be loaded independently.
Note: Although these functions are mostly Version 3 code, they have been modified to work with the Heurist 6 search interfaces.
It is planned to move them as widgets into `/hclient` in 2025. 

- crosstab/: crosstab (pivot) diagram
- map/: mapping based on Leaflet and Timeline
- record/: general record data viewer/renderer
- smarty/: interface to modify and render custom reports (Smarty templates).
- visualize/: connection diagram visualisation (graph renderer)

### **/redirects**

This directory contains redirector files for functions called across servers, notably to the Heurist Reference Index Server at HeuristRef.net. This includes `resolver.php`, the basis of a PID system allowing access to any record, field value, or definition (of a record type, field, vocabulary, or term) through a single point of entry.

Using these redirectors avoids dependency on scripts remaining in the same location (e.g., if the program code is restructured or new versions are written). This means that a newer, restructured version of the code can be accessed by an older version on a remote server without the latter seeing any difference.

A version suffix (e.g., `_V1`) allows new versions of the target scripts to generate different output without breaking older code on third-party servers.

Some files are intended to provide a stable shortcut to specific output, such as a record view, which is human-readable and may be referenced at a stable URL. If no version suffix is included, the redirect is to human-readable output that may be changed/improved (i.e., it is a stable reference but to content that may potentially change in its detailed form).

### **/import**

This directory contains various modules and utilities for importing data into the Heurist system. This includes functionalities for synchronizing with external bibliographic managers (like Zotero), tools for importing data from delimited files (CSV, TSV), bookmarklet functionality, hyperlink importing, and other general import utilities.

Many of these functions originated in Heurist Version 3 and, with the exception of the delimited file importer (which has seen significant updates), have not been extensively revised. They are slated to be redeveloped as widgets within `/hclient` in 2025/2026.

### **/export**

This directory provides utilities for exporting Heurist database content, structures, and search results in various formats. This includes database backups, data exports (e.g., JSON, XML/HML, KML), and tools for managing scheduled reports.

Like the import modules, many of these functions originated in Heurist Version 3. While adapted for later search interfaces, they are also planned to be redeveloped as widgets within `/hclient` in 2025/2026.

### **/movetoparent**

This directory contains files that should be placed in the parent directory of the codebase during Heurist installation. We **STRONGLY** recommend using `/var/www/html/HEURIST` as the parent directory, as all our server scripts, redirects, cron jobs, and JSON jobs are written for this address. Heurist does not touch anything outside the `HEURIST` root directory, except through symlinks you may add.

`heuristConfigIni.php` is **CRITICAL**, as it sets all local configuration parameters, including server names, directory locations, and database access passwords.

### **/server_scripts**

This directory contains scripts for installing, updating, and verifying instances, and for managing databases on a server. These should be copied to an appropriate place (e.g., `/srv/scripts`) and configured for the local environment, including creating a global cron job with `sudo crontab -e`.

---

## **Further Information**

### **HEURIST**

Core development repository: Version 6 (2021-), Version 5 (2018-2020), Version 4 (2014-2017). Versions 1-3 are on SourceForge (2005-2013).

**Note:** The latest code is in `h6-dev`, `h7-dev`, etc. Heurist Version 7 will be released in 2025.

Heurist is a mature, web-based data management infrastructure specifically tailored to the needs of Humanities researchers. It allows researchers to design, create, manage, analyze, and publish their own richly-structured databases through a simple web interface, without needing programmers or consultants. A complete application can be built in as little as half a day, and complex databases in under a week. Database structures can be modified incrementally on live databases, allowing them to evolve with project needs. The project runs free services for researchers, independent of their institution (through the University of Sydney in Australia and the Huma-Num eResearch service in France). This frees researchers from managing servers, backups, and upgrades.

### **Version 6 Interface**

Advanced features include foreign keys and relationships (seen and modified by the user as intuitive pointer fields), multi-level facet searches, network expansion rules built via a wizard, interactive maps and timelines, and built-in CMS websites that embed saved searches, media, and widgets from the database interface to publish searches linked to result lists, maps, etc. A central index allows Heurist databases to import structural elements from any registered database to promote sharing of data models. Right-to-left scripts and Asian characters are fully supported. There is provision to translate the interface and for cross-database searching, although we have not had the resources to implement this.

### **Research Database Workflow**

Heurist has been developed at the University of Sydney since 2005, based on years of prior work. It is used by dozens of projects, particularly in Australia, France, Germany, and the USA. These projects span the fields of history, archaeology, art history, geography, and literature. A selection of projects can be found [here](https://heuristnetwork.org/projects-2/). <!-- Assuming a relevant link exists, otherwise remove "here" or specify -->

Heurist is in active development, with more than 1000 commits per year since moving to GitHub in 2014. Version 6, released in February 2021, represents a complete redesign of the menu system and overall appearance in collaboration with a professional UX designer (Brant Trim, Serata Digital, Canberra). It is fully backward compatible with databases developed in Version 5.

We transferred the help system from help builder software to a Heurist database in November 2020. This database now generates the Heurist Help System (also accessible within Heurist). It is currently being updated to reflect Version 6 (work-in-progress 2021). Issue tracking is also handled by a Heurist database.

### **Heurist Projects**

Heurist has a built-in capability to generate data-driven interactive websites. CMS website pages are stored and edited directly in the database and can render media, searches, maps, blog pages, etc., using the data. Heurist hosts dozens of research websites around the world, several of which are displayed below. You can see featured projects on our [website](https://heuristnetwork.org/projects-2/). <!-- Assuming a relevant link exists -->

### **Contributing**

We very much welcome collaboration and invite contributions to the development of Heurist. We will be delighted to exchange design ideas and our development roadmap with developers, researchers, documenters, and trainers interested in contributing to the system or developing specific extensions or training programs for their own use. Please feel free to add suggestions to our job tracking system (see below) or email support@heuristnetwork.org.

In 2025, we are undergoing significant refactoring and code documentation, and have formed a documentation group of power users and developers. To get started with the source code, you can view some of the items in the `/documentation/` directory. Most server-side code is in the `hserv` directory, and most client-side code is in the `hclient` directory.

### **Source Code & Licensing**

The GitHub repository for Heurist source code is freely available under the standard GNU GPL 3 license. The main branch is `h6-dev`. This is occasionally merged with `master` for archiving purposes. This will change to `h7-dev` in 2025.

### **Issues and Feature Requests**

Please note: we do not use GitHub issues. Please visit our [job tracking system](https://HeuristRef.net/heurist/?db=Heurist_Job_Tracker&website) developed in Heurist.
You can raise an issue or request a feature there: first, click the "Add Job" link at the top left, then use login `guest` + `guest` (or request a personal login via the link on the login page).

Issues and feature requests can also be submitted via the "Issue tracker" link in any Heurist database (Help > Bug report/feature request, also a separate link in the top bar of the web interface). This also saves additional information about your use environment, which may help resolve the issue, and attributes a Ticket ID, sending you an email when the ticket is cleared.

### **Online Help**

Comprehensive help is available in a dedicated Heurist database:
[https://HeuristRef.net/heurist/?db=Heurist_Help_System&website](https://HeuristRef.net/heurist/?db=Heurist_Help_System&website)
Note that this is being updated (April - June 2025) into a more narrative format and will be republished in a searchable form.

### **Installation**

The distribution version at HeuristNetwork.org on the installation page is updated approximately once a month, more frequently for urgent bug fixes. Monthly updates generally contain numerous tweaks and bug fixes, while significant new features are added every two to three months. The installation and update scripts (see installation page) are very simple and have been proven over a decade. They should run on most Unix servers and have been designed specifically to restrict their activity to a single directory (`/var/www/html/HEURIST`) so that they do not interfere with anything else installed on the server.

Heurist can also be installed on a Windows server. Systemik Solutions is working on documentation; please contact us for information.

### **Feedback / Questions**

Please add a Feature request / bug report, and we will get back to you promptly.

