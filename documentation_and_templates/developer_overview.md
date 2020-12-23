# Heurist Developer Overview

This guide describes some most important components in the Heurist code base. Developers may use this document as a map if they want to contribute to Heurist.

By Yang Li, Systemik Solutions. July 2020

## hclient

This folder contains most of the Heurist UI components, which include UI HTML code, UI assets, client-side functions etc.

### Core JavaScript Classes

Some most used JS classes are located under folder _/hclient/core_. They were written in a consistent OOP manner. If a new class need to be added, _hclient/core/baseclass\_template.js_ can be used as a template to create the class.

#### Class hAPI

This class provides a number of interfaces to access the server-side services.

- Method `hSystemMgr` returns an object with functions handling user/groups, database definitions, and saved searches.
- Method `hRecordMgr` returns an object with functions of records CRUD operations.
- Method `hEntityMgr` returns an object with functions handling entities.

This class also maintains some system level information such as login user and preferences, which can be used globally.

#### Class HintDiv

This class deals with popups in Heurist. It can display a piece of provided HTML content as a popup at the specified coordinates.

#### Class hLayout

The class handles the creation and management of layout of the page. The layout of the Heurist UI is initiated by this class. And the contents of each pane in the layout are loaded by this class.

#### Class hRecordSet

This class deals with record set operations.

#### Class hSearchMinimal

This class is used for minimal searches, which returns the result IDs.

#### Class Temporal

This is a utility class to handle dates in Temporal format.

### Client-side utility functions

Many client-side utility functions can be found under _heurist/hclient/core_. These are helper functions used across the application.

- js: general utility functions.
- utils\_ajax.js: ajax related utility functions.
- utils\_collection.js: collection related utility functions.
- utils\_dbs.js: database related utility functions.
- utils\_geo.js: geoJSON related utility functions.
- utils\_msg.js: utility functions dealing with UI messages.
- utils\_ui.js: UI related utility functions.

### UI contents

The UI contents are in the directory _hclient/framecontent_. These contents are displayed as Heurist pages or dialogs. Pages such as record edit form and query builder can be found here. Each page contains the page HTML to render bundled with the page specific JS code.

### Custom jQuery widgets

There&#39;re several custom UI widgets extend to the jQuery in directory _hclient/widgets_. Some important UI components such as query builder and record access information are developed in this manner to generate the HTML content piece and provide according functionalities.

To create a new widget, _hclient/widgets/\_widget\_template.js_ can be used as a start point.

## hsapi

This folder contains most of the server-side components, which include services, script and common entities.

### System configurations

Many system settings such as the version number, MySQL user credentials, and mail server configurations are defined as constants in file _hsapi/consts.php_. Some constants&#39; values can be overwritten by the value specified in files _configIni.php_ and _heuristConfigIni.php_. These settings are used by the `System` class.

### System class

Class `System` gets instanced during the application initiation. It provides some higher-level methods such as binding databases, getting database connections, getting system settings, user control etc.

### Controllers

Files under directory _hsapi/controller_ are API services consumed by the class hAPI from the client-side, which performs various tasks such as editing or searching record data.

### Database functions

Directory _hsapi/dbaccess_ contains the functions of CURD operations for Heurist entities, such as Heurist database structure, user/group, and records. It also contains the functions used by searches to get data from the database. Most of these functions contains the raw SQL queries to the database.

### Entities

Directory _hsapi/entity_ contains classes to do CRUD operation on database tables. All table specific classes implements the base class _dbEntityBase.php_ with the naming convention of _db{TableName}.php_. Each table has a corresponding configuration file with the name convention of _{TableName}.json_. The configuration file contains the information of the table name, field names etc. The entity class will load the configuration file and provide the according methods to do operations on that table.

### Import classes

Directory _hsapi/import_ contains some classes used for Heurist import. These classes include parser of the import file and the code of importing data into Heurist.

### Utilities

Directory _hsapi/utilities_ contains various of utility services/functions/classes, which are used across the server-side code.

## Heurist database administrative components

Files under directory _admin_ are the pages and functions of Heurist database administration, such as database creation and clone.

### Database information pages

Directory _admin/describe_ has some pages which can generate information about the Heurist database. These pages include:

- Database statistic page.
- Database structure definitions in SQL.
- Database structure definitions in XML.
- Record type and field list page.

### Database operations

Files under directory _admin/setup_ are the pages and functions of database operations. Things such as creating a new database, cloning or deleting a database, updating a database can be found in this directory.

### Database structure management

Directory _admin/structure_ contains the pages and functions of structure management. These include record type design, field design, and term design pages and functions.

### Database verifications

Directory _admin/verification_ contains the pages and functions for verification or administration tasks used in Heurist.

## Export

Directory _export_ contains the pages and functions for Heurist export, such as export in HML and export the data archive package.
