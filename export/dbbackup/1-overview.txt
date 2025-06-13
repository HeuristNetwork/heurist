
Directory:	/export/dbbackup

Overview:	Scripts in this directory are used to set up and perform exports of Heurist database content.
            This typically involves generating full database dumps in HML (Heurist Markup Language - XML) format
            or as comprehensive ZIP archives which can include record data, structure information,
            and uploaded files. The `buildArchivePackagesCMD.php` script is a key command-line tool
            for creating these backup packages, often utilizing `flathml.php` from the `/export/xml/` directory.
            The `exportMyDataPopup.php` provides a user interface for administrators to initiate these exports.

Note:       Although these functions are mostly Version 3 code, they have been modified to work with the Heurist 4 search interface.
            The primary mechanism involves packaging data for backup, transfer, or archival.

Updated:    17 december 2015 (Note: This 'Updated' date refers to the last significant review of this overview text or the directory's V3 components.)

---------------------------------------------------------------------

* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2

