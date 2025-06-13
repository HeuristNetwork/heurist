
Directory:	/export/publish

Overview:	This directory contains scripts related to managing and publishing scheduled reports.
            The core functionality revolves around the `usrReportSchedule` database table, allowing users
            to define reports (often based on Smarty templates and Heurist queries) that can be
            generated periodically or on demand.

            Key components include:
            - `loadReports.php`: An AJAX backend that handles CRUD operations (Create, Read, Update, Delete)
              for report schedules.
            - `manageReports.html` & `manageReports.js`: Provide the main user interface for listing,
              searching, and initiating actions on scheduled reports (e.g., edit, delete, run).
            - `editReportSchedule.html` & `editReportSchedule.js`: Provide the popup form for creating
              and editing the details of a specific report schedule.

            The system allows for reports to be generated and their output (e.g., HTML, JS, TXT, CSV, XML, JSON, CSS)
            to be made available via a URL or saved to a file.

Note:       The comment "To be replaced with widgets/entity/manageUsrReportSchedule class" indicates
            that this module is planned for a future rewrite or integration into a more modern
            widget-based architecture within Heurist.
            Although these functions are mostly Version 3 code, they have been modified to work with the Heurist 4 search interface.

Updated:    17 december 2015 (Note: This 'Updated' date refers to the last significant review of this overview text or the directory's V3 components.)

---------------------------------------------------------------------

* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2

