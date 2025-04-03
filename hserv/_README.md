
Directory:    /hserv

Overview:     Server-side functions for Heurist Vsn 4 onwards (late 2015), see also hclient for client-side functions
                
                The server side functions are mainly PHP code which communicates with the client-side JS functions.
                The data-critical, search and visualisation parts of the Heurist infrastructure are handled by hserv and hclient
                
                Other directories in the root contain older code (pre 2015) which carries out simple tasks such as database setup
                and simple listing, or server scripts and configurations. They use a variety of methodologies, but we have not had
                the resources, or priorities, to rewrite them since they still work quite adequately and are low risk.

Notes:    -- controller - services, server side end points
          -- dbaccess
          -- entity - CRUD operations for all database entities (tables) except heurist data (Records and recDetails) +filestore 
	      -- records - CRUD operations heurist data (Records and recDetails)
                 edit
                 export
                 import
                 indexing
                 search
          -- structure
                 edit
                 export
                 import
                 search
          -- sync
          -- utilities
                 geo

Updated:     18 Oct 2015, updated 3 Jan 2024

----------------------------------------------------------------------------------------------------------------

