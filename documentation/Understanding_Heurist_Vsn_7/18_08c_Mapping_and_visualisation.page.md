# 08c: Mapping and visualisation

Summary automatically generated on 11/25/2025 using the gpt-oss:120b model from the servers of [**Onyxia**](https://datalab.sspcloud.fr/) (INSEE) based on the complete document of the chapter.

---

### 1️⃣ Spatio‑temporal **Map** tab

<table id="bkmrk-step-action-1-click-"><colgroup><col></col><col></col></colgroup><tbody><tr><th>Step

</th><th>Action

</th></tr><tr><td>**1**

</td><td>Click

**\[Explorer\]**

.

</td></tr><tr><td>**2**

</td><td>Run a search

**or**

 use a

**\[Saved Filter\]**

 to retrieve the records you want to map.

</td></tr><tr><td>**3**

</td><td>Select a

**record**

 (any record that contains the required fields).

</td></tr><tr><td>**4**

</td><td>Click

**\[Map\]**

.

</td></tr></tbody></table>

> **Result:** The interface splits into two synchronized panels – a **map** (top) and a **timeline** (bottom).

#### 1.1 Prerequisites for a spatio‑temporal view

- **At least one spatial field** (coordinates, GeoJSON, etc.).
- **At least one temporal field** (date, date‑range, etc.).

If one of these is missing the map/timeline will stay empty.

#### 1.2 Map panel

<table id="bkmrk-tool-description-leg"><colgroup><col></col><col></col></colgroup><tbody><tr><th>Tool

</th><th>Description

</th></tr><tr><td>**Legend**

</td><td>•

**Result sets**

 – show/hide points belonging to the current result set.

•

**Map Documents**

 – (see

*Publish*

 section).

•

**Base map**

 – choose background (e.g.,

*OpenStreetMap*

).

</td></tr><tr><td>**Zoom/Dezoom**

</td><td>Standard zoom controls (🔍).

</td></tr><tr><td>**\[full screen\]**

</td><td>Expand the map to full‑screen mode.

</td></tr><tr><td>**Help**

</td><td>Opens the contextual help window.

</td></tr><tr><td>**\[Bookmark\]**

</td><td>Add a temporary point you can later retrieve.

</td></tr><tr><td>**\[Search\]**

</td><td>Geocode a place name (searches OSM index).

</td></tr><tr><td>**\[Print\]**

</td><td>Print the current map view.

</td></tr><tr><td>**\[Publish map\]**

</td><td>Generates an

**iframe**

 snippet to embed the map on another page, optionally with controls (legend, bookmark, geocoder, selector, print).

• You can also export the map as

**KML**

 for Google Earth.

</td></tr><tr><td>**Clusterisation**

</td><td>Points are automatically clustered; clusters recompute on zoom/dezoom.

</td></tr></tbody></table>

#### 1.3 Publishing a map (iframe)

<table id="bkmrk-setting-what-it-does"><colgroup><col></col><col></col></colgroup><tbody><tr><th>Setting

</th><th>What it does

</th></tr><tr><td>**Include**

 –

*current query*

</td><td>The iframe displays the map built from the query you just ran.

</td></tr><tr><td>**Include**

 –

*opened map documents*

</td><td>(still under documentation – shows any additional map layers you have opened).

</td></tr><tr><td>**Controls**

</td><td>Choose which UI elements appear inside the iframe (legend, bookmark, geocoder, selector, print).

</td></tr><tr><td>**Visible in legends**

</td><td>Choose which legend items are shown (basemap, result set, map documents).

</td></tr><tr><td>**Other settings**

</td><td>*Use current basemap*

,

*Allow modify symbology*

,

*Show map*

,

*Show timeline*

,

*Marker clusters*

.

</td></tr><tr><td>**Popup template**

</td><td>You can pick a custom HTML template for the pop‑ups (create a new template in the

**Templates**

 section of Heurist).

</td></tr><tr><td>**Copy code**

</td><td>Two formats are offered:

**embed**

 (standard

`<span class="editor-theme-code"><iframe …></span>`

 ) and

**web‑safe**

 (escaped for direct insertion in CMSs).

</td></tr><tr><td>**Export to KML**

</td><td>Generates a KML file that can be opened in Google Earth.

</td></tr></tbody></table>

#### 1.4 Timeline (chronological strip)

- Synchronized with the map: moving the timeline brush filters the points shown on the map.
- **Tools** (bottom bar)
    - **Zoom / De‑zoom** – change the temporal resolution.
    - **Reset** – return to the full time span.
    - **Downloading** – export the timeline data (CSV) – *see Export section for details.*
    - **Left / Right navigation** – step forward or backward in time.
    - **Timeline options** – label display (full, truncated, fixed width, hidden), label placement (inside / above bar), bar stacking, bar wrapping, and a **“Filter map with current timeline range”** checkbox.

---

### 2️⃣ **Network** tab

<table id="bkmrk-step-action-1-click--1"><colgroup><col></col><col></col></colgroup><tbody><tr><th>Step

</th><th>Action

</th></tr><tr><td>**1**

</td><td>Click

**\[Explorer\]**

.

</td></tr><tr><td>**2**

</td><td>Run a search

**or**

 use a

**\[Saved Filter\]**

 to collect the records you want in the network.

</td></tr><tr><td>**3**

</td><td>Select a

**record**

 that will be the entry point of the network.

</td></tr><tr><td>**4**

</td><td>Click

**\[Map\]**

 (the same button as for the spatio‑temporal view; the

**Network**

 view appears).

</td></tr></tbody></table>

#### 2.1 What you need

- **Linked records** (relationship fields) **or** record types that are already defined as linked.
- A **query** that returns **all** records you want to appear in the graph.

#### 2.2 Main controls (header)

<table id="bkmrk-control-function-nod"><colgroup><col></col><col></col></colgroup><tbody><tr><th>Control

</th><th>Function

</th></tr><tr><td>**Node Control**

</td><td>•

**Select mode**

 – click‑drag a single node or draw a selection rectangle (right‑click + drag).

•

**Gravity**

 – toggle node‑to‑node attraction; turn

**on**

 to let the layout settle, then

**off**

 for a static view.

</td></tr><tr><td>**Link Control**

</td><td>•

**Links**

 – show/hide empty links and

*expanded*

 links (links that open to show nested relationships).

•

**Node Size Formula**

 – choose linear or logarithmic scaling of node size.

•

**Fixed**

 – set a fixed link thickness.

</td></tr><tr><td>**Graph Control**

</td><td>•

**Refresh Data**

 – re‑load the graph if new records were added.

•

**Open/Close Fullscreen**

 – toggle full‑screen mode.

•

**View Mode**

 –

*Icon view*

,

*Basic info box*

,

*Full info box with link view*

.

•

**Set Zoom**

 – manual zoom slider.

•

**Export**

 – download the graph as

**GEXF**

 (Gephi format).

</td></tr></tbody></table>

#### 2.3 Tips for a readable network

- Turn **Gravity** **on**, let the layout settle, then turn it **off**.
- If the graph still looks tangled, click **Refresh Data** under **Graph Control**.
- Use the **Select mode** to isolate a subset of nodes and move them manually.

---

### 3️⃣ **Cross‑tabular (Pivot) View**

<table id="bkmrk-step-action-1-click--2"><colgroup><col></col><col></col></colgroup><tbody><tr><th>Step

</th><th>Action

</th></tr><tr><td>**1**

</td><td>Click

**\[Explorer\]**

.

</td></tr><tr><td>**2**

</td><td>Run a search

**or**

 use a

**\[Saved Filter\]**

.

</td></tr><tr><td>**3**

</td><td>Select a

**record**

 (any type).

</td></tr><tr><td>**4**

</td><td>Click

**\[Cross‑tables\]**

 (also called

*Tableaux croisés*

).

</td></tr></tbody></table>

#### 3.1 Building the table

1. **Choose the record type** you want to analyse (right‑hand panel).
2. Pick **Variable 1** (dropdown *Var 1*) – the first field to cross.
3. Pick **Variable 2** (dropdown *Var 2*) – the second field (optional).
4. Optionally add a **Variable 3** (click the “+” icon).
5. Click **“Update results”**.

If only one variable is chosen, you obtain a **simple frequency table**; with two variables you get a **cross‑tabulation**.

#### 3.2 Assign / edit intervals (value grouping)

- Click the **pencil icon** (✏️) to open the *Assign intervals* dialog.
- **Add Interval** – press **\[Add Interval\]**, select the values to merge, click the right‑arrow, then give the new interval a name.
- **Remove Interval** – select an interval and click the left‑arrow (or the blue “←” button).
- **Reset** – press **\[🔄Reset\]** to revert to the original value list.

All changes are reflected instantly in the table.

#### 3.3 Table options

<table id="bkmrk-option-what-it-does-"><colgroup><col></col><col></col></colgroup><tbody><tr><th>Option

</th><th>What it does

</th></tr><tr><td>**\[save\]**

</td><td>Store the current table configuration for later reuse.

</td></tr><tr><td>**Show**

 –

*Values*

</td><td>Show raw counts.

</td></tr><tr><td>**Show**

 –

*Totals*

</td><td>Show row/column totals.

</td></tr><tr><td>**Show**

 –

*Row % / Column %*

</td><td>Show percentages per row or column.

</td></tr><tr><td>**Aggregates Counts**

</td><td>Switch between sum, average, etc.

</td></tr><tr><td>**Hide / Show**

 –

*null values*

,

*empty rows/columns*

</td><td>Clean up the display.

</td></tr></tbody></table>

#### 3.4 Export &amp; visualisation

- **Export** – click the **Export** button (top‑right) to download **CSV** or **PDF**.
- **Chart (pie)** – when only **one** variable is selected, a **pie chart** button becomes active; it displays the distribution of that variable.

> **🚨 Warning:** At least **one** variable must be selected before any result (table or chart) appears.

---

## 📊 Quick‑reference of Heurist visualisation tabs

<table id="bkmrk-need-map%E2%80%AF%2B%E2%80%AFtimeline-"><colgroup><col></col><col></col><col></col><col></col></colgroup><tbody><tr><th>Need

</th><th>Map + Timeline

</th><th>Network

</th><th>Cross‑tabular (pivot)

</th></tr><tr><td>**Geographic + temporal exploration**

</td><td>✅

</td><td></td><td></td></tr><tr><td>**Relationship graph**

</td><td></td><td>✅

</td><td></td></tr><tr><td>**Quantitative cross‑tabulation**

</td><td></td><td></td><td>✅

</td></tr><tr><td>**Quick export (CSV / PDF)**

</td><td>✅ (via

*Publish*

 or

*Download timeline*

)

</td><td>✅ (GEXF)

</td><td>✅

</td></tr><tr><td>**Embedding in external site**

</td><td>✅ (iframe)

</td><td>✅ (iframe)

</td><td>–

</td></tr><tr><td>**Custom pop‑ups / symbology**

</td><td>✅ (Popup template)

</td><td>–

</td><td>–

</td></tr></tbody></table>

---

### 🔖 Key take‑aways

- **Map + Timeline** requires **both** a spatial **and** a temporal field; otherwise the view stays empty.
- Use the **Publish** button to generate an embeddable iframe; you can customise which controls appear and even export a KML for Google Earth.
- In the **Network** view, turning **Gravity** on, letting the layout settle, then turning it off yields the cleanest static graph.
- The **Cross‑tabular** view is ideal for quick quantitative overviews; remember to **assign intervals** when you need to group raw values.
- All export actions (CSV, PDF, GEXF, KML, iframe) are reachable from the respective tab’s header toolbar.

---