# Directory

`hclient`

## Overview

This directory contains the client-side functionalities for the Heurist application (H4 and onwards, from late 2015). It comprises HTML, JavaScript, and CSS assets that communicate with server-side PHP functions located in the `hserv` directory. These components are responsible for the data-critical aspects, search capabilities, and visualization features of the Heurist infrastructure.

## Key files

(This directory primarily serves as a container for subdirectories and doesn't have key operational files at its immediate level beyond this README)

## Subdirectories

-   `assets/`: Contains static resources like images (icons, logos, backgrounds), CSS stylesheets, localization files, and other visual or data assets used by the client.
    -   `branding/`: Specific branding assets like logos.
    -   `css/`: Stylesheets for the application.
    -   `localization/`: Files for internationalization and localization.
-   `core/`: Contains core JavaScript modules and PHP scripts that form the building blocks of the hclient application, including system management, layout control, API interactions, and various utility functions.
-   `framecontent/`: Manages content displayed within frames or specific sections of the UI, such as export menus, information pages, and initialization scripts for different page contexts.
-   `widgets/`: A collection of UI components (widgets) that provide specific functionalities and user interactions. Each subdirectory within `widgets` typically represents a distinct UI module or feature set.
    -   `admin/`: Widgets for administrative tasks.
    -   `cms/`: Widgets related to the Content Management System.
    -   `cpanel/`: Widgets for the control panel or dashboard.
    -   `database/`: Widgets for database management operations.
    -   `editing/`: Widgets for various editing functionalities.
    -   `entity/`: Widgets for managing and searching different types of entities (records, terms, users, etc.).
    -   `lookup/`: Widgets for external lookup services.
    -   `profile/`: Widgets for user profile management and login.
    -   `record/`: Widgets for record-specific actions like adding, deleting, exporting, and tagging.
    -   `report/`: Widgets for creating and viewing reports.
    -   `search/`: Widgets for building and executing search queries.
    -   `viewers/`: Widgets for displaying data in various formats, such as maps, timelines, and media viewers.
    

