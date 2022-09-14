// searchbox
const requestStub = {detail:'ids', search_realm:'storymap_search'};

function __startMapSearch(){
    let svalue =  $('#inMainSearch').val() ? $('#inMainSearch').val() : "";
    let query = [{"t":"10"},{"f":svalue},{"f:1089":""},{"sortby":"t"}];
    window.hWin.HAPI4.RecordSearch.doSearch( $('#inMainSearch'), {...requestStub, "q":query} );
    
    show_person_gallery();
    hide_story_elements();
    if($('#story-elements').app_storymap('instance')){
                    $('#story-elements').app_storymap('clearStory');
     }   

}

$('#btnMainSearch').button().click( __startMapSearch );

$('#inMainSearch').keypress(function(e){
    let code = (e.keyCode ? e.keyCode : e.which);
    // console.log('>>>'+code);
    if (code == 13) {
        window.hWin.HEURIST4.util.stopEvent(e);
        e.preventDefault();
        __startMapSearch();
    }
});

// toggle ui elements
const animation_speed = 500;

function toggle_person_gallery() {
    $("#person-gallery-widget").toggle("blind", animation_speed);
    $("#person-gallery-btn .ui-icon").toggle(animation_speed);
}
function toggle_story_elements() {
    $("#story-elements").toggle("blind", {"direction":"left"}, animation_speed);
    $("#story-window-btn .ui-icon").toggle(animation_speed);
}


function show_person_gallery() {
    $("#person-gallery-widget").show("blind", animation_speed);
    $("#person-gallery-btn .ui-icon-arrowthickstop-1-n").hide(animation_speed);
    $("#person-gallery-btn .ui-icon-arrowthickstop-1-s").show(animation_speed);
}
function hide_person_gallery() {
    $("#person-gallery-widget").hide("blind", animation_speed);
    $("#person-gallery-btn .ui-icon-arrowthickstop-1-n").show(animation_speed);
    $("#person-gallery-btn .ui-icon-arrowthickstop-1-s").hide(animation_speed);
}
function show_story_elements() {
    $("#story-elements").show("blind", {"direction":"left"}, animation_speed);
    $("#story-window-btn .ui-icon-arrowthickstop-1-w").show(animation_speed);
    $("#story-window-btn .ui-icon-arrowthickstop-1-e").hide(animation_speed);
}
function hide_story_elements() {
    $("#story-elements").hide("blind", {"direction":"left"}, animation_speed);
    $("#story-window-btn .ui-icon-arrowthickstop-1-w").hide(animation_speed);
    $("#story-window-btn .ui-icon-arrowthickstop-1-e").show(animation_speed);
}



$("#person-gallery-btn > button").click(toggle_person_gallery)
$("#story-window-btn > button").click(toggle_story_elements)

// persistent references to people on the page
const recID_searchParam = "cartographie";


// keep current story as a parameter of page url
$(document).on(hWin.HAPI4.Event.ON_REC_SELECT, function (e, data) {

    if (data.selection) {
        let selected_rec = data.selection[0];
        let url = new URL(window.location.href);
        
        if (!url.searchParams.has(recID_searchParam) || url.searchParams.get(recID_searchParam) != selected_rec) {
            url.searchParams.set(recID_searchParam, selected_rec);
            window.history.pushState("nostate", "", url.href);

            if($('#story-elements').app_storymap('instance')){
                    $('#story-elements').app_storymap('option','storyRecordID',selected_rec);
             }   

        }
        hide_person_gallery();
        show_story_elements();
        
        // scroll to selected
        document.querySelector('#person-gallery-widget div.recordDiv.selected').scrollIntoView();
    }
});

// modify style for person list
$('#person-gallery-widget').resultList('option','onPageRender',function(){
    this.div_content.find('.recordIcons').hide();
    this.div_content.find('.recordTitle').hide();
    this.div_content.find('.action-button-container').css('visibility','hidden');
    this.div_content.find('.recTypeThumb').css({top:0,bottom:0,height:'auto'});
});

// Load initial record set
__startMapSearch();

// Load storymap from URL param
// eventdata - is argument passed to this script from cms page
if(eventdata && eventdata['url_params'] && eventdata['url_params'][recID_searchParam]>0){
    if($('#story-elements').app_storymap('instance')){
        $('#story-elements').app_storymap('option','storyRecordID',eventdata['url_params'][recID_searchParam]);
        hide_person_gallery();
        show_story_elements();
    }   
}else{
    // Hide story elements on page load if no story in URL    
    toggle_story_elements(); 
}

var mapwidget = $('#map-for-story').app_timemap('getMapping');
mapwidget.setMapMargins({paddingTopLeft:[500,50],paddingBottomRight:[50,0]});
