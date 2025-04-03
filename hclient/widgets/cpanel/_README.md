
Directory:	/hclient/widgets/cpanel

Overview:   Control panel (header), Dropdown and Slider menus for major functions,

Updated: 	17 Sep 2024

------------------------------------------------------------------------------------------------------------------------------------------------------


MainMenu is a list of Heurist operations. They are grouped in several sections:
    
    Admin, Database, Export, Import, Help, Profile, Management etc. Each menu entry defined as <li> element
    with the following attributes:
        data-user-admin-status - accessibility according to user level 
            (2 db owner, 1 - db admin, 0 - logged in, -1 - all)
        data-logaction  - log tag 
        data-icon - icon in menu
        data-container - target element where to load dialog/form
        Action is defined by "id" attribute (like id="menu-database-clone")
        Menu Title and Hint are taken from localization files via id 
        (dashes are replaced with underscores: eg. menu_database_clone)
        Note: this seems like a quite unecessary complication and source of confusion

    If there is no localised version it takes title and hint from <li><a>
    Note: it is bad practice to have a localised version in English 
    which is the prime language of the interface, as the strings are redundant
    and cause problems for anyone not intimately familiar with the code.
        
    Main menu can be visible as standard horizontal menu (as in previous layout) or can be hidden. 
    
    Even if it is hidden, this widget is main handler for execution of operation via methods: 
        menuActionById or menuActionHandler.
    
    Other widgets, dialogs and functions (for example: menu v6, dashboard, export menu) 
    call Heurist actions via this widget.
    
    For example new menu groups actions in different groups and in different order, 
    however it uses menu actions id and calls this widget method to execute an operation.
