Note: **URL checking is currently disabled**  

**WARNING:** Although the script accepts the `url` option, it explicitly sets: $do_url_check = false;
        Consequently, it does not currently check record URLs, text-field URLs, or file URLs.
    
This file performs these actions:

1. **Checks that it is running from the command line**  
    It refuses to run through a web request.
2. **Processes command-line options**
    - `reminder` — sends scheduled reminders.
    - `report` — regenerates scheduled reports.
    - `url` — intended to check URLs.
    - `database NAME` — restricts processing to one database.
    - If no action is specified, it enables all three actions.
3. **Connects to MySQL and initialises Heurist**  
    It stops if the database server connection cannot be established.
4. **Obtains the database list**  
    It processes either the specified database or every Heurist database on the server.
5. **Reads the cron exclusion list**  
    Database names are loaded from `databases_exclude_cronjobs.txt`. Blank lines and comments are ignored.
6. **Regenerates scheduled reports**  
    For every database, it:
    
    - Creates reports that do not yet exist.
    - Updates reports that have changed.
    - Counts unchanged reports.
    - Records report-generation errors.
    - Records reports taking longer than 10 seconds.
    
    **Important:** report processing occurs before the exclusion-list check, so excluded databases still have their reports processed.
    
7. **Skips other processing for excluded databases**  
    After report generation, databases in the exclusion list are skipped.
8. **Processes scheduled reminders**  
    It sends due reminder emails configured in each database and counts them by frequency.
9. **Handles a lost MySQL connection**  
    If MySQL reports “server has gone away,” processing stops and an alert email identifies the database where it failed.
10. **Prints and emails an overall summary**  
    The summary includes:
    - Reminder emails sent.
    - Reports created, updated and unchanged.
    - Report errors.
    - Invalid URLs, although URL checking is disabled.
11. **Sends a separate slow-report warning**  
    Reports taking more than 10 seconds generate an additional administrator email.
12. **Sends a separate report-error warning**  
    Any reports that failed to generate are listed in another administrator email.