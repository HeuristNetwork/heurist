# Directory: /documentation/structue_and_libraries/heurist_libraries

## Overview

This directory describes the Heurist modules developed by the Heurist team from July 2026.

They serve to isolate significant blocks of functionality which can be rewritten and
compiled into minified form for runtime loading efficiency. They communicate with
the core Heurist code through the API.

These modules are inserted in compiled form into the current Heurist alpha codebase 
(eg.h7-alpha) on the HeusitRef.net server at /var/www/html/HEURIST/hx-alpha/hclient/bundles,
where x is the current Heurist version.
 
The /hclient/bundles folders do not contain editable source code and are not therefore
included in the base Heurist repo (see .gitignore) but developed as separate repos 
under the same general HeuristNetwork umbrella.

--------------

build_client_modules.sh (located in /server_management/server_synchronisation/heurist_modules/) 
compiles the Heurist modules and copies them to /hclient/bundles in the alpha codebase 
on HeuristRef.net. 

As of Aug 2026 there are two modules, heurist-map and heurist-mirador4, 
which are inserted into h7-alpha.
 
---------------

Our standard update_heurist.sh script (in .../HEURIST/DISTRIBUTION/ on HeuristRef.net),
which we recommend running on Heurist servers nightly, syncs the code from HeuristRef.net 
to the server which runs it (generally on a cron job). /hclient/bundles is synced as part 
of that update, since it is within the Heurist codebase. No special steps are therefore 
required to ensure that the Heurist moduels are kept up-to-date with the rest of the code. \
This reflects their rapid evolution  as part of the Heurist code.

On the other hand, third party external libraries such as Leaflet are only synced if 
the update script is run with the codeonly flag removed. 

----------------

For an explanation of the build process, see the documentation file:

   /server_management/server_synchronisation/heurist_modules/README.MD 