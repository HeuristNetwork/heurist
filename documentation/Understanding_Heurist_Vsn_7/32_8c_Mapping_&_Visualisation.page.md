# 8c : Mapping & Visualisation

Documentation written on **13/11/2025** by **Sylvain Besson** (MSH Lyon Saint-Étienne / CNRS) Updated **12/05/2026** by **Maxine Schoehuys--Kreiss**

## 1. Spatio-temporal view

##### **🚀 How to start**

### Explore → Search → Map

- To begin, click on <span style="color: rgb(132, 63, 161);">\[Explore\]</span> ①
- Perform a search or use a <span style="color: rgb(132, 63, 161);">\[Saved Filter\]</span> that returns the records you want to include in your map ②
- Click on <span style="color: rgb(132, 63, 161);">\[Map\]</span> ③

![01be3e53-99d9-4e49-a184-4936175af504.png](https://heurist-doc.huma-num.fr/uploads/01be3e53-99d9-4e49-a184-4936175af504.png)

The **Map** view is divided into two parts: a map and a timeline. Both parts are interactive and interact with each other.

The map shows the current record set, if you want to display only some of your records, use a filter or make a specific search. The map will then show the results of your query. However, to be displayed on the map and the timeline the records should have at least one of:

- a field with **geospatial data** (to display on the map)
- a field with **temporal data** (to display on the timeline)

To get more information on **field types**, check [Chap 4. Data entries](https://heurist-doc.huma-num.fr/2iXJcCmcRZitNYoohOdAOg?view#3-Field-types) @TODO.

### 1.1. The map

The records are clustered depending on their spatial closeness. It changes as the view is zoomed in or out. Clustering can be set in the layer description record.

![5fa7e2bf-2cbc-4886-a7c7-e257544d40fc.gif](https://heurist-doc.huma-num.fr/uploads/5fa7e2bf-2cbc-4886-a7c7-e257544d40fc.gif)

The map will display pointers taken from the geodata in you records. There are two possible sources of geodata that can be drawn: **current result sets** (search results) and **map layers**. The displayed field from the result set is normally Location (a geospatial field).

A map document contains map layers, which define the appearance of the data in each layer.

Some tools are available in the header:

- <span style="color: rgb(132, 63, 161);">\[Legend\] </span>① :
    - <span style="color: rgb(132, 63, 161);">\[Result sets\]</span>: choose which set of records you want to display or hide
    - <span style="color: rgb(132, 63, 161);">\[Map Documents\]</span>: choose or import a background map (borders, geo features, tiled image background)
    - <span style="color: rgb(132, 63, 161);">\[Base map\]</span>: choose which base map you want to use (e.g. *OpenStreetMap*)
- <span style="color: rgb(132, 63, 161);">\[Zoom in\]</span> and <span style="color: rgb(132, 63, 161);">\[Zoom out\]</span>![ef55f59f-129b-454a-964f-73c64a20b627.png](https://heurist-doc.huma-num.fr/uploads/ef55f59f-129b-454a-964f-73c64a20b627.png)②
- <span style="color: rgb(35, 111, 161);">\[Zoom to full extent\]</span>![19f1a1b3-59a6-4d68-9a93-8118c79c82e9.png](https://heurist-doc.huma-num.fr/uploads/19f1a1b3-59a6-4d68-9a93-8118c79c82e9.png)③
- <span style="color: rgb(132, 63, 161);">\[Help\]</span>![a8ebe3b0-4128-4b0a-8a08-bcfb5084012a.png](https://heurist-doc.huma-num.fr/uploads/a8ebe3b0-4128-4b0a-8a08-bcfb5084012a.png)④

![75c029ec-1d56-457a-b5f4-2d2a5b84f487.png](https://heurist-doc.huma-num.fr/uploads/75c029ec-1d56-457a-b5f4-2d2a5b84f487.png)

On the map, several features are available:

- <span style="color: rgb(132, 63, 161);">\[Bookmarks\] </span>① : add a landmark directly inside the map to retrieve later
- <span style="color: rgb(132, 63, 161);">\[Search\]</span> ② : explore the map using place names (ex: "New York"). The results are those indexed by *OpenStreetMap*.
- <span style="color: rgb(132, 63, 161);">\[Print\]</span> ③ : print a selected view of the map
- <span style="color: rgb(132, 63, 161);">\[Map publication\]</span> ④ : generate an iframe to display the map on another web page and choose which map features to implement. It is also possible to export the map in KML format to use on Google Earth.

![58cc08c1-1954-465f-8abf-656f05d0347f.png](https://heurist-doc.huma-num.fr/uploads/58cc08c1-1954-465f-8abf-656f05d0347f.png)

#### 1.1.1 Focus on map publication

① You can display the map on another web page. You can configure the features you want on the web page :

- <span style="color: rgb(132, 63, 161);">\[Include\]</span>: choose what queery can be seen on the published map
    - current query
    - opened map documents
- <span style="color: rgb(132, 63, 161);">\[Controls\]</span>: choose which controls can be used on the published map
    - legend (shown on the right of the published map)
    - bookmark
    - geocoder / search feature
    - selector
    - print
- <span style="color: rgb(132, 63, 161);">\[Visible in legends\]</span>: choose what can be seen in the legend
    - basemap
    - result set
    - map documents
- <span style="color: rgb(132, 63, 161);">\[Other settings\]</span>: control general aspects of the presentation
    - use current basemap
    - allow modify symbology
    - show map
    - show timeline
    - markerclusters
- <span style="color: rgb(132, 63, 161);">\[Popup template\]</span>: choose another popup template than the basic one

② Copy the HTML iframe code directly in your web page. If you use a CMS like Wordpress, you must enclose within `<span class="editor-theme-code"></span>`. You can choose between embed or web safe code, the later only modifies the special characters.

③ You can export a map in KML format for Google Earth.

![1bd5bf0e-a92c-4def-b641-38100bfb274a.png](https://heurist-doc.huma-num.fr/uploads/1bd5bf0e-a92c-4def-b641-38100bfb274a.png)

#### 1.1.2. Focus on the Heurist map document

You can change the background of your map using the <span style="color: rgb(132, 63, 161);">map document</span> feature. To create a new map document, select the <span style="color: rgb(132, 63, 161);">\[Add +\]</span> button in the legend of the map view. A map document contains one or more map layers, and can be accompanied by a date, a creator, a creative commons licence, copyright information and a description. A <span style="color: rgb(132, 63, 161);">map layer</span> needs a file or service which delivers the map data - Shapefile, KML, GeoTIF, Tiled image, MrSID service etc.

<p class="callout warning">The Date field in the metadata of both map document and map layer will show on the timeline. You can choose to hide all dates from the timeline by unchecking the box, however this will also hide the results from your current query that also use a Date field.</p>

First, create a map document, these are the mandatory fields:

- <span style="color: rgb(132, 63, 161);">name of map document</span>
- <span style="color: rgb(132, 63, 161);">bounding box</span> : using the tool on the left select a rectangle, that expresses the maximum extents of the two-dimensional object you want to map
- <span style="color: rgb(132, 63, 161);">map layers</span> (see below)
- <span style="color: rgb(132, 63, 161);">map-zoom bookmarks</span>: pre-defined zoom areas. The first bookmark will determine the initial map zoom. Specify as Name,Min Longitude,Max Longitude, Min Latitude, Max Latitude, Start date/time and End date/time (both optional).
- <span style="color: rgb(132, 63, 161);">zoom on point selection (km)</span>: the area to which to zoom when you select a point object (by default: 5km)

Then, import one or more map layers by clicking <span style="color: rgb(132, 63, 161);">\[Map layers\]</span>, these are the mandatory fields:

- <span style="color: rgb(132, 63, 161);">layer name</span>
- <span style="color: rgb(132, 63, 161);">map layer data source</span>: can be a KLM file or snipet, a map image file (non-tiled or tiled), a mappable query, a shapefile. Each of those will need another bounding box and a title.
- <span style="color: rgb(132, 63, 161);">type of data source</span>: Google maps, Heurist query, raster or vector

<p class="callout info">**🛟 Tip:** Use the <span style="color: rgb(132, 63, 161);">symbology </span>field and the <span style="color: rgb(132, 63, 161);">style editor</span> feature to create the presentation you like on each layer of your map. You can choose to show or hide each map document and each layer in the <span style="color: rgb(132, 63, 161);">legend</span> of the map.</p>

### 1.2. The timeline

The records are distributed on the timeline under the map. ①

Any time field will be projected on the timeline. If several time fields are used on a record, they will all automatically show on the timeline. To hide a specific time field, uncheck the corresponding box on the left on the timeline under <span style="color: rgb(132, 63, 161);">\[Current query\]</span>.

<p class="callout info">**🛟 Tip:** Invalid dates will not be displayed on the timeline. Dates should be written according to ISO norm: yyyy, yyyy-mm, yyyy-mm-dd. Use minus (-) for BCE dates (eg. -375 for 375 BCE).</p>

The timeline has various navigation features:

- <span style="color: rgb(132, 63, 161);">\[Zoom in\]</span> and <span style="color: rgb(132, 63, 161);">\[Zoom out\]</span> ②
- <span style="color: rgb(132, 63, 161);">\[Zoom to all\]</span> ③
- <span style="color: rgb(132, 63, 161);">\[Zoom to selection\]</span> ④
- <span style="color: rgb(132, 63, 161);">\[Move to start\]</span> and <span style="color: rgb(132, 63, 161);">\[Move to end\]</span> of the records ⑤
- <span style="color: rgb(132, 63, 161);">\[Timeline options\]</span> ⑥ :
    - choose the length of the labels: full length labels, truncate label to bar, fixed label width, or hide labels
    - choose the label's position: within the bar, or above the bar
    - choose the bar's position: stacked on the above the other, or wrapped to minimise height of timeline
    - filter map with current timeline range

![3cdf26db-269b-4d18-a465-0c8b36a7b3b6.png](https://heurist-doc.huma-num.fr/uploads/3cdf26db-269b-4d18-a465-0c8b36a7b3b6.png)

## 2. Network view

##### **🚀 How to start:** Explore → Search → Network :::

- To begin, click on \[Explore\] ①
- Perform a search or use a \[Saved Filter\] that returns the records you want to include in your network ②
- Click on \[Network\] ③

![4ddf0f6b-9f0b-472d-b62f-13059848c950.png](https://heurist-doc.huma-num.fr/uploads/4ddf0f6b-9f0b-472d-b62f-13059848c950.png)

The **Network** view displays a records' network diagram. It provides an interactive visualisation of the current results set. Records are shown as nodes, and the connections (pointer fields and relationships) as the lines between nodes (edges).

<p class="callout info">**🛟 Tip:** To get it working, two conditions must be met:</p>

- records or records types should be linked
- the current results set should gather all the records you want in your diagram

**DON'T PANIC** if the diagram is not understandable immediately !

@todo: The diagram below has been replaced with a new and much more capable 'ego-network' diagram (from March 2026) which allows you to start with one or a small number of records, see all the connections from those records, and then expand the diagram outwards either by double-clicking records marked as having connections or from all the displayed records. This diagram will be further expanded with the ability to colour code or symbolise different characteristics of the nodes.

Here's an example of a network diagram:

![60e068e3-10ab-434e-b3f1-ecc207e52483.gif](https://heurist-doc.huma-num.fr/uploads/60e068e3-10ab-434e-b3f1-ecc207e52483.gif)

Each node displayed is a lab or a project. It shows how labs are interconnected through shared projects. Here you can see a record directly in the network viewer on the left.

Some features are available on the header to make your diagram more accessible:

- \[Node Control\]:
    - <span style="color: rgb(132, 63, 161);">Select mode</span> ① : select and drag simple node or select and drag multiple node by a selecting box (click-right and drag)
    - <span style="color: rgb(132, 63, 161);">Gravity </span>② : determine to what degree entities are repositioned around the selected entity based on their relative weightings. Turn it on to choose the best presentation, and turn it off to lock down its position.

![0d10fb6d-1eee-40a9-9958-4d819b94c652.png](https://heurist-doc.huma-num.fr/uploads/0d10fb6d-1eee-40a9-9958-4d819b94c652.png)

- \[Link Control\]:
    - <span style="color: rgb(132, 63, 161);">Links</span> ① : show or hide empty links and expand links
    - <span style="color: rgb(132, 63, 161);">Node Size Formula</span> ② : choose between linear or logarithmic formula
    - <span style="color: rgb(132, 63, 161);">Fixed </span>③ : fix the size of the links

![7cae3489-e972-4458-88b3-739817b6db77.png](https://heurist-doc.huma-num.fr/uploads/7cae3489-e972-4458-88b3-739817b6db77.png)

- <span style="color: rgb(132, 63, 161);">\[Graph Control\]</span>:
    - <span style="color: rgb(132, 63, 161);">Refresh Data</span> ① : go back to the original presentation of the nodes
    - <span style="color: rgb(132, 63, 161);">Open or close Fullscreen</span> ②
    - <span style="color: rgb(132, 63, 161);">View Mode</span> ③ : choose to show only the name of the record (big or small) or its name and its first field
    - <span style="color: rgb(132, 63, 161);">Set Zoom</span> ④ : zoom in or out of the diagram and set the view back to show the complete diagram
    - <span style="color: rgb(132, 63, 161);">Export</span> ⑤ : export the network data to a Gephi GEFX file

![fcb442d0-b49a-46ea-abe7-88c60885949a.png](https://heurist-doc.huma-num.fr/uploads/fcb442d0-b49a-46ea-abe7-88c60885949a.png)

## 3. Crosstabs

##### **🚀 How to start:** Explore → Search → Crosstabs

- To begin, click on <span style="color: rgb(132, 63, 161);">\[Explore\]</span> ①.
- Perform a search or use a <span style="color: rgb(132, 63, 161);">\[Saved Filter\]</span> that returns the records you want to include in your network. ②
- Click on <span style="color: rgb(132, 63, 161);">\[Crosstabs\]</span> ③

![bbf17797-f5e3-427c-8708-1e5fa2996770.png](https://heurist-doc.huma-num.fr/uploads/bbf17797-f5e3-427c-8708-1e5fa2996770.png)

The Crosstabs view provides a quantitative analysis of your data by calculating counts of aggregations sorted by category. A cross-tabulation is a way of calculating counts of aggregations sorted by category.

Imagine value (set to Var 1) is the value of a colour system that has the entire spectrum of colours encoded as numbers. Numbers that are close to each other represent colours that are close to each other. Imagine that the type field (set to Var 2) indicates what material the potsherd is made out of. We can use a cross-tabulation to generate instant categories by splitting up the entire range of entered values into 10 buckets, or deciles.

To run a simple cross-tabulation, search for the records you wish to analysis and select <span style="color: rgb(132, 63, 161);">\[Crosstabs\]</span>. The<span style="color: rgb(132, 63, 161);"> \[Crosstabs\]</span> dialog displays. In the <span style="color: rgb(132, 63, 161);">show fields for</span> dropdown ①, select the record type you wish to analysis. Complete the variables:

- ② <span style="color: rgb(132, 63, 161);">Var 1 </span>(rows) choose your first variable (this will simulate a tabulation by that variable).
- ③ <span style="color: rgb(132, 63, 161);">Var 2</span> (cols) choose the second variable (this splits the range of values into 10 'buckets' and counting how many values appear in each 'bucket' by type.
- ④ <span style="color: rgb(132, 63, 161);">Var 3</span> is an optional variable that breaks the analysis further, into 'pages'.

Additionally, you can assign intervals by clicking on the pen ⑤.

![7468dd0a-c049-42e3-a531-d8a09e38a484.png](https://heurist-doc.huma-num.fr/uploads/7468dd0a-c049-42e3-a531-d8a09e38a484.png)

### 3.1. Focus on intervals

It is possible to reassign intervals by merging, adding or deleting them.

First, select the available values. By default, all values are selected.

Then, add or remove intervals ② :

- Click on <span style="color: rgb(132, 63, 161);">\[Add Interval\]</span> ④ to add an interval.
- Click on left arrow to remove an interval, .
- If you want remove all intervals, click on the blue arrow.
- If you want to reset intervals, click on <span style="color: rgb(132, 63, 161);">\[Reset\]</span> ③.

![0d2271f5-1660-4720-af10-e3e8c71ee99f.png](https://heurist-doc.huma-num.fr/uploads/0d2271f5-1660-4720-af10-e3e8c71ee99f.png)

It is also possible to merge values:

1. click on <span style="color: rgb(132, 63, 161);">\[Add Interval\]</span> ④
2. select the values you wish to merge
3. click on the right arrow
4. rename the new interval

![2571548b-169a-4d19-bef7-1de2b1c4cabc.gif](https://heurist-doc.huma-num.fr/uploads/2571548b-169a-4d19-bef7-1de2b1c4cabc.gif)

Some other functionalities are available:

- Click on <span style="color: rgb(132, 63, 161);">\[Save\]</span> to save the current settings ①
- Show the values count, the total of each row and or column, and their percentage ②
- Counts aggregates values ③
- Display or hide null values and blank rows and columns ④

![00d304cf-5fb1-4762-a793-67ad9fc381e7.png](https://heurist-doc.huma-num.fr/uploads/00d304cf-5fb1-4762-a793-67ad9fc381e7.png)

### 3.2. Results

You can see the results in table form or in a pie chart.

<p class="callout warning">🚨 Warning: you must select at least one variable to see some results.</p>

The table's metadata is available ①. The table title can be customized ②. You can export the table in CSV or PDF format ③. You can search for a value in the table ④. The field or record type used as base for the crosstable is mentioned on its top ⑤.

![b8843d9a-6cc3-4f26-983e-c3a7c1889818.png](https://heurist-doc.huma-num.fr/uploads/b8843d9a-6cc3-4f26-983e-c3a7c1889818.png)

You can also display your data as a pie chart.

![3d94ef8f-e316-4a16-af81-47af703e3623.png](https://heurist-doc.huma-num.fr/uploads/3d94ef8f-e316-4a16-af81-47af703e3623.png)