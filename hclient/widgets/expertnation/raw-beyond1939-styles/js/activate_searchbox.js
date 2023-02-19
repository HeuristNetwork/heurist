/* 

This script is loaded on the homepage, and enables the searchbox. It loads the 'Search by Biographical Details'
page with a query for the 'search everything' box.

*/

let searchbox = document.getElementById("search_query");
let search_button = document.getElementById("search_button");

const search_page = 121979;

function getQuery(search_string) {
    const base_query = [{
        "t": "10"
    }, {
        "f:14058": "8971"
    }, {
        "f:196": "4705"
    }, {
        "sortby": "t"
    }];
    return [...base_query, { "f": search_string }];
}

function doMainSearchboxSearch(elem) {
    if (searchbox && searchbox.value) {
        let search_string = searchbox.value;
        let request = {
            q: getQuery(search_string),
            w: "a", // get all records
            detail: "ids",
            source: searchbox.id,
            search_realm: "search_realm_1",
            search_page: search_page
        };
        window.hWin.HAPI4.RecordSearch.doSearch(elem, request);
    }
}

// bind click/keypress events
search_button.addEventListener( "click", function() {
    doMainSearchboxSearch(this);
    });
searchbox.addEventListener("keypress", function(event) {
    if (event.key == "Enter") {
        window.hWin.HEURIST4.util.stopEvent(event);
        event.preventDefault();
        doMainSearchboxSearch(this);
    }
});