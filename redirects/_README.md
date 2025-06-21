<!--
_README.md - Explains the purpose of the /redirects directory and its files.
@fileOverview This file describes the /redirects directory, which contains redirector files for cross-server function calls, particularly to the Heurist Reference Index Server. These redirectors ensure stability even if the underlying code structure or versions change.
@package Heurist academic knowledge management system
@subpackage /redirects
@link https://HeuristNetwork.org
@copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
@license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
@author Ian Johnson ian.johnson.heurist@gmail.com
@since 24th December 2015
-->
Directory:	/redirects

Overview:

This directory contains redirector files to the functions which are called across servers,
notably to the Heurist Reference Index Server.

By using these redirectors, we avoid dependency on scripts remaining in the same location  
eg. if the program code is restructured or new versions are written. That means that a newer, restructured version of
the code can be accessed by an older version on a remote server, without the latter seeing any difference.

Version ( eg. _V1) suffix is used to allow new versions of the target scripts to generate different output
without breaking older code on third party servers

Some files are intended to provide a stable shortcut to a specific output, such as record view,
which is human readable and may be referenced at a stable URL. If no version suffix is included
the redirect is to human-readable output which may be changed/improved ie. it is a stable reference
but to content which may potentially change in its detailed form.

Updated: 	24th December 2015

----------------------------------------------------------------------------------------------------------------
