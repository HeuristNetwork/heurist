# Directory: /hclient/widgets/viewers

## Overview

This directory contains various widgets and scripts for displaying Heurist data in different visual formats. These include viewers for 3D models, story maps, network connections, media collections, and general record lists with extended capabilities.

## Key files

-   **3dViewer.php**: PHP script to render 3D models using the online 3DViewer.net library. It supports formats like obj, 3ds, stl, ply, gltf, glb, etc.
-   **3dhopViewer.php**: PHP script to render Nexus 3D object formats (nxs, nxz, ply) using the 3DHOP (3D Heritage Online Presenter) library.
-   **app_storymap.js**: jQuery UI widget that acts as a controller for creating and displaying story maps. It integrates with mapping (potentially `app_timemap`) and timeline components to navigate a sequence of story elements.
-   **app_timemap.js**: jQuery UI widget that manages the display of Heurist data on a map (Leaflet) and timeline. It embeds an iframe (map.php) and handles data loading and event listening.
-   **connections.js**: jQuery UI widget for displaying network diagrams of Heurist result sets. It embeds an iframe (`springDiagram.php`) to visualize connections.
-   **mediaViewer.js**: jQuery UI widget for displaying various media types (images, PDFs, audio, video, IIIF, 3D models). Supports thumbnail generation and FancyBox lightbox integration.
-   **miradorViewer.php**: PHP script that initializes and handles the Mirador viewer, typically for IIIF manifests. It supports integration with Heurist annotations.
-   **recordListExt.js**: jQuery UI widget for displaying Heurist record sets or selections within an iframe or directly in a div. Supports loading content from a URL (e.g., Smarty reports).
-   **resultList.js**: jQuery UI widget for rendering a collection of records in various formats (list, grid, table). Handles incremental rendering, selection, view mode switching, and pagination.
-   **resultListCollection.js**: jQuery UI widget for managing a temporary collection of records. Allows adding from a selection, clearing, and performing actions like creating a map or saving the collection.
-   **resultListDataTable.js**: jQuery UI widget that renders a Heurist record set into an interactive HTML table using the DataTables.net plugin. Supports server-side processing and column configuration.
-   **resultListMenu.js**: jQuery UI widget that creates and manages context-sensitive dropdown menus for actions related to a result list (e.g., operations on selected records, collected items).
-   **resultListMenuCollected.html**: HTML template for the 'Collected Items' submenu in the result list menu.
-   **resultListMenuLayout.html**: HTML template for the 'Layout' submenu in the result list menu (e.g., list, grid views).
-   **resultListMenuRecode.html**: HTML template for the 'Recode' submenu in the result list menu (e.g., batch editing operations).
-   **resultListMenuSelected.html**: HTML template for the 'Selected Items' submenu in the result list menu.
-   **resultListMenuShared.html**: HTML template for the 'Shared' submenu in the result list menu (e.g., sharing, embedding).
-   **staticPage.js**: jQuery UI widget for displaying static HTML content from a URL, either directly or in an iframe.