function startDriverTour(){
    
const selectors = [
'.driver-popover',
'.driver-overlay',
'.driver-stage',
'.driver-page-overlay',
'.driver-popover-item'
];

selectors.forEach(sel => {
document.querySelectorAll(sel).forEach(el => el.remove());
});

document.body.classList.remove('driver-active');    
   

const driver = new Driver(); 
   
driver.defineSteps([
        {
          element: 'div.ui-heurist-admin',
          popover: {
            title: 'Admin',
            description: 'This is Admin section. It contains menu items for database operations',
            position: 'right'
          },
          onHighlightStarted : () => {
            // Make menu section visible
console.log('step 1');
//            $('.ui-menu6').slidersMenu('switchContainer', 'admin', true);
          }
        }, //#heurist_slidersMenu_2  
        {
          element: '[data-action="menu-database-browse"]',
          popover: {
            title: 'Find your database',
            description: 'List of databases on this server. Pickup and open your DB',
            position: 'bottom'
          },
          onHighlightStarted: () => {
console.log('step 2');
                $('.ui-menu6').slidersMenu('switchContainer', 'admin', true);
                //execute heurist action
                window.hWin.HAPI4.actionHandler.executeActionById('menu-database-browse');
           }
        },
        {
          element: 'div.ui-heurist-explore',
          popover: {
            title: 'Explore you data',
            description: 'Menu section that allows you filter, search and exlore your data',
            position: 'right'
          },
          onHighlightStarted : () => {
console.log('step 3');
            // Make menu section visible
            $('.ui-menu6').slidersMenu('switchContainer', 'explore', true);
          }
        }
]);

try {
      driver.start();
} catch (err) {
      console.error('Driver.js failed to start:', err);
}
}