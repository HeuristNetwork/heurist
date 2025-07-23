function startIntroTour() {
  // Clear previous overlays (not strictly needed for Intro.js but safe)
  const leftovers = [
    '.introjs-overlay', 
    '.introjs-helperLayer', 
    '.introjs-tooltipReferenceLayer',
    '.introjs-tooltip',
    '.introjs-fixParent'
  ];
  leftovers.forEach(sel => {
    document.querySelectorAll(sel).forEach(el => el.remove());
  });


  introJs.tour().setOptions({
    showProgress: true,
    steps: [
      {
        title: 'Welcome',
        intro: 'Hello World!'
      },
      {
        element: document.querySelector('div.ui-heurist-admin'),
        intro: 'This is Admin section. It contains menu items for database operations',
        position: 'right'
      },
      {
        element: document.querySelector('[data-action="menu-database-browse"]'),
        intro: 'List of databases on this server. Pickup and open your DB',
        position: 'bottom'
      },
      {
        element: document.querySelector('div.ui-heurist-explore'),
        intro: 'Menu section that allows you filter, search and explore your data',
        position: 'right'
      }
    ],
    showProgress: true,
    scrollToElement: true,
    scrollTo: 'tooltip'  // or 'element'
  }).onbeforechange(function(targetElement) { // Hook before each step is shown
    
    const currentStep = this._currentStepSignal.rawVal;  
//console.log('Before step:', this._currentStepSignal.rawVal, targetElement);

    switch (currentStep) {
      case 1:
        // Make admin section visible if needed
        $('.ui-menu6').slidersMenu('switchContainer', 'admin', true);
        break;

      case 2:
        $('.ui-menu6').slidersMenu('switchContainer', 'admin', true);
        window.hWin.HAPI4.actionHandler.executeActionById('menu-database-browse');
        break;

      case 3:
        $('.ui-menu6').slidersMenu('switchContainer', 'explore', true);
        break;
    }
    
  }).start();

}
