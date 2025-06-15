# Directory: hclient/framecontent

## Overview

This directory contains scripts and components used to render content (including standalone minimal pages) in the Heurist system. These are typically used for functionalities that are resource-intensive or require a focused interface, such as dialogs, export menus, batch record operations, and information/error pages. The pages are often loaded dynamically.

## Key files

- `exportMenu.js`: Client-side logic for the export menu functionality.
- `exportMenu.php`: Provides the HTML structure and initializes the JavaScript for the export menu, allowing users to export search results in various formats.
- `infoPage.php`: Displays system or error messages within a minimal Heurist page structure, with an option for user login if required.
- `initPage.php`: Standard initialization script for Heurist pages. Sets up the Heurist environment, handles authentication, and includes necessary CSS and JavaScript.
- `initPageCss.php`: Generates and outputs CSS styles for Heurist pages, including base styles and theme-specific styles.
- `initPageLogin.php`: Handles the initialization of page that require user login. It checks authentication status and initiates the login process if necessary.
- `initPageMin.php`: Minimal initialization script for pages that require very few resources, often used for simple interactions or utility functions within a frame.
- `initPageTheme.php`: Generates CSS rules based on the current user's theme settings or system defaults, applying them to Heurist pages.
- `publishDialog.html`: HTML structure for the "publish dialog", used for sharing or publishing content.
- `publishDialog.js`: Client-side JavaScript for handling interactions within the publish dialog.
- `recordAction.js`: Client-side JavaScript for record batch actions, such as bulk edit or delete.
- `recordAction.php`: Provides the HTML structure and initializes client-side scripts for performing batch actions on records (e.g., bulk edit, delete).
- `recordEdit.php`: Provides the interface and logic for editing record details within a frame or standalone page.
- `sendBulkEmail.php`: Handles the client and server-side logic for sending bulk emails to selected users.
