/**
 * @file guidedIntroTour.js
 * @brief A simple guided tour which pops up a series of windows attached to elements of the interface. 
 * @fileOverview Configuration of a guided tour based on https://introjs.com/ 
 * This file specifies the element to be described and the description in the order in which they are displayed 
 * @project Heurist academic knowledge management system
 *
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 7.0
 */

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


  // Configure the content of the tour here
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
    // May be useful for debugging: console.log('Before step:', this._currentStepSignal.rawVal, targetElement);

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
