<?php
/**
 * mbtiles.php - MBTiles server endpoint.
 *
 * @fileOverview This script acts as an endpoint for serving MBTiles, likely by including
 * a tile server implementation. It includes the main Heurist configuration
 * and an external tile server library.
 * @project     Heurist academic knowledge management system
 * @package Core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 6.0
 */

include_once dirname(__FILE__).'/../heuristConfigIni.php';
include_once 'external/php/tileserver.php';
?>
