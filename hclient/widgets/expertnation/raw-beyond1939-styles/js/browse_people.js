// Heurist query object that retrieves only Beyond 1939 people
const coreQuery = [{
    "t": "10"
}, {
    "f:14058": "8971"
}, {
    "f:196": "4705"
}, {
    "sortby": "t"
}];

const pagination = $("#bor-pagination");
const sr = "search_group_1";

// Query string to retrieve all people whose name starts with letter
function getQuery(letter) {
    return [...coreQuery, {
        "title": `${letter}%`
    }];
}

// Display page of all persons with given initial
function displayPage(letter) {
    HAPI4.RecordSearch.doSearch(pagination, {
        "q": getQuery(letter),
        "detail": "ids",
        "search_realm": sr
    });
}

// Add listeners to links in pagination element
function initialisePagination() {

    // Arrays for initial letters of people's surnames
    let names = [];
    let initials;

    // Retrieve records & titles
    HAPI4.RecordMgr.search(request = {
        'q': coreQuery,
        'detail': 'detail'
    }, (response) => {
        people = new hRecordSet(response.data);
        // Record title is at idx 5
        people.each((id, rec) => names.push(rec[5]));
        initials = new Set(names.map((value) => value[0].toUpperCase()));
        // Activate page buttons
        let pages = pagination.children();
        // disable any that don't have records associated
        pages.each(
            function() {
                let letter = $(this).data("letter");
                if (!initials.has(letter)) {
                    $(this).addClass("disabled");
                }
            });
            // add event listener to others
        let enabled_pages = $(".pagination > li:not(.disabled)");
        enabled_pages.each(
            function() {
                let anchor = $(this).children("a");
                anchor.click(() => {
                    enabled_pages.removeClass("active");
                    $(this).addClass("active");
                    displayPage($(this).data("letter"));
                });
            }
        );
        // do initial search
        $(enabled_pages[0]).children("a").trigger("click");
        });


}

initialisePagination();
