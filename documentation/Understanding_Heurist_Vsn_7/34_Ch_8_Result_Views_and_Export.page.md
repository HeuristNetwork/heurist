# Ch 8: Result Views and Export

Documentation written on 04/11/2025 by Sylvain Besson (MSH Lyon Saint-Étienne / CNRS)  
Updating 25/06/2026 by Vincent Paillusson (HTL)

## 1. Record View

<p class="callout warning">Explore → Filter → Record</p>

The **Record** view shows all the elements of a recording. The differents fields and associated metadata and possibly relationship between recordings.

How to start:

- To begin, click on **\[Explore\]**. ①
- Perform a search or use a **\[Saved Filter\]** that returns the records you want to include in your report. ①
- Select a [**record**](https://heurist-doc.huma-num.fr/6SWzTuSCQtShLJfucCVoUg#) ②
- Click on **\[Record\]** ③

![Record view](https://heurist-doc.huma-num.fr/uploads/f565dfd5-0d20-43a5-b1ca-7a74314533e1.png)

Once on the **\[Record\]** view selected, the record’s metadatas appear. There is several informations:

- Title ①: title of the record
- Icon ②: record’s type icon
- **Record H-ID** ②: Intern Heurist record ID
    - Workflow stage ④ which give some information on the workflow stage
- **Media** ⑤: Picture of the media

![311dc84a-6eec-4d9e-a2d9-610f0c0e8e9f.png](https://heurist-doc.huma-num.fr/uploads/311dc84a-6eec-4d9e-a2d9-610f0c0e8e9f.png)

#### Focus on medias

- If a media exists in the **record**, a thumbnail is displayed
- It is possible to display in **\[full screen\]** ![19f1a1b3-59a6-4d68-9a93-8118c79c82e9.png](https://heurist-doc.huma-num.fr/uploads/19f1a1b3-59a6-4d68-9a93-8118c79c82e9.png) ① or viewing it in popup ![2af48b38-0885-4685-97b2-ede4e8407969.png](https://heurist-doc.huma-num.fr/uploads/2af48b38-0885-4685-97b2-ede4e8407969.png) ②
- The media can also be displayed on **\[Mirador\]** (using Heurist’s automatic IIIF manifest) ③ or **\[OpenSeadragon\]** ![78e932f2-d82a-4c69-9fba-c9efd90ae890.png](https://heurist-doc.huma-num.fr/uploads/78e932f2-d82a-4c69-9fba-c9efd90ae890.png) viewer ④
- You can **\[download\]** ![60955b67-0421-4c06-b809-0dac5661efea.png](https://heurist-doc.huma-num.fr/uploads/60955b67-0421-4c06-b809-0dac5661efea.png) it ! ⑤
- By hovering over **\[description\]**, a description appears if this field has been completed ⑥
- Finally by going over **\[rights\]**, the rights of the media appears if it was filled during the record creation ⑦

![Media buttons](https://heurist-doc.huma-num.fr/uploads/a5b63664-f99d-4d3b-acdd-c32076b2f8b3.png)

##### Click on **\[More…\]**

- **Cite as** ① : The record can be cited in XML or HTML. The updating date of the record is shown as the lastest modification date.
- **Added** ②: Creation date of the **record**
- **Updated** ③: Last **record** update
- **Ownership** ④: Communicate who owns the record and who can read it
- **Rating** ⑤: You can rate each record from one to five
- **Tags** ⑥: You can tag records to find them more easily

  
![More button](https://heurist-doc.huma-num.fr/uploads/4a49ab1d-3766-4fdc-b422-3edcbdb52858.png)

## 2. List view

<p class="callout warning">Explore → List View</p>

**\[List view\]** allows essentialy to show the whole selected data in table format ③.

You can choose the field you need ①. You can also save the settings ②. It is also possible to export by simple copy/past (CSV with tab as separator), by excel format and PDF format ③.

![list view fonctionnalities](https://heurist-doc.huma-num.fr/uploads/790e143e-6f77-447b-aa09-4ba167459e04.png)

<p class="callout info">**🛟**   
**Tip:** If you want to export data with more export formats, you may want to try the Export view described in the following section.</p>

## 3. Export View

The **Export view** allows to export the request results under differents data formats:

**CSV ①**  
**XML ②**  
**JSON ③**  
**RDF ④**  
**GeoJSON ⑤**  
**KML ⑥**  
**GEPHI ⑦**  
**IIIF ⑧**  
**HuNI ⑨**

<p class="callout info">Several formats can be exported as a data feed, as well as a fixed format file. The data feed capability is particualrly useful for sending live data to a processing workflow, often combined with a saved search which filters the required set of output records. It can be a very useful, simpler alteraitv eto usign th Heurist API.</p>

![5a247a55-e8bd-460f-b8ac-53413a0c45e5.png](https://heurist-doc.huma-num.fr/uploads/5a247a55-e8bd-460f-b8ac-53413a0c45e5.png)

### CSV / TSV (Delimited) files

When you click on **\[CSV\]**, a pop-up opens allowing you to choose the fields you want to export and a range of settings.

<p class="callout info"> Note that by default CSV files are exported as tab-separated, since this causes much fewer problems with complex text (which often contains commas and unmatched quote marks, but very rarely contains actual tab characters)</p>

<p class="callout info">CSV/TSV exports are a particularly good way of temporarily exporting some fields, carrying out some manipulation in an externa program eg. a spreadsheet, Open Refine or R, and reimporting the results with Populate &gt; Import - Delimtied text / CSV. Because all exported CSV/TSV files automatically include the Hursit ID (H-ID)\_ which is uniqiue to each record, it is very easy to reimport the data into the soure records, overwriting or adding to existing values in the same or differnet fields.</p>

First, you must choose the records you want to export ①. You can choose between the current result set and any single type of record occuring within the resultset.

<p class="callout info">We STRONGLY recommend only exporting one record type at a time. Delimited files are really not meant for dealing with heterogeneous data, and mixed exports will restrict the exportable fields to record metadata and shared fields.</p>

[![image.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/scaled-1680-/rLHimage.png)](https://docs.heuristref.net/uploads/images/gallery/2026-07/rLHimage.png)

After the records selection, you choose one of the two available export settings ② :  
\- a single joined file  
\- a file by record type (if multiple types are selected).

You can choose to display the fields in either Form order (the default) or alphabetic order. Alphabetic order may make it easier to select fields in some circumstances.

The main step of the export setting is to select the fields you want to export ③ . They can be any type of field including the constructed title, **record pointers** and **relationship markers**.

You can export metadata about the records ④ as well as the data from the data fields ⑤ .

<p class="callout info">If you have a current resultset with more than one record type in it, you will only be able to choose the metadata and fields which are shared by all the record types in the resultset. </p>

If the record type you are exporting contains record pointer or relationship marker fields, you can drill down into those record types and export fields from within those records. They can be included in the main file ("single joined file") or exported as separate files with linking IDs ("File by record type").

<p class="callout info">You should use "File by record type" where there are multiple values in record pointer fields, since the values in each related record will need to be kept separate. For simple cases without repeated value record pointers "single joined file" may be appropriate.</p>

[![image.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/scaled-1680-/PUoimage.png)](https://docs.heuristref.net/uploads/images/gallery/2026-07/PUoimage.png)

Note how you have some additional options appearing on the right against any selected fields.

For most purposes you will want to use *Value*, as the other options can be done more effectively in an external spreadsheet. However *Group by* will group records into a single record for each value encountered in the column, while *Count* will count the number of occurrences for the field in those groups. The results can be a little hard to interpret.

<p class="callout info">**🛟 Tip:**  
The H-ID is ALWAYS included in the export because it uniquely identifies every record, so it is essential if you need to import/update data back into the database or to make links between records eg. record pointers</p>

Finally, when ending the CSV export setting, you can change the field/column delimiter for the csv (for example: tab, semicolon, comma, etc.) and character used for quotemarking the textual content.

You can also save settings ⑦ by giving it a name in order to use it later.

[![image.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/scaled-1680-/DXyimage.png)](https://docs.heuristref.net/uploads/images/gallery/2026-07/DXyimage.png)

**Handling of record pointers and relationships**

For all other export types, it is possible to choose between:

- - Export the records and their relationships (relationship of the type pointer or the relationships maker)
    - Export only the pointer relationships
    - Don’t follow the pointer relationships or the relationships makers
    - Follow all relationships including inverse pointers (🚨**warnings**: it could export the entire database)

[![image.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/scaled-1680-/qwUimage.png)](https://docs.heuristref.net/uploads/images/gallery/2026-07/qwUimage.png)

### XML

“**Extensible Markup Language (XML)** is a markup language and file format for storing, transmitting, and reconstructing data. It defines a set of rules for encoding documents in a format that is both human-readable and machine-readable.” (source: [**Wikipedia**](https://en.wikipedia.org/wiki/XML))

Heurist defines an XML schema called Heurist Markup Language (HML). HML can be used both as an interpretable archivable format (it is included as the primary element of Publish &gt; Safeguard file) and as a data source which can be transformed with XSLT transforms, Python, PHP, or many other languages to a required format.

Check the box "Include human-readable names and local IDs for everything" if you plan to look at the XML file and interpret its structure (this will create a very large file duer to repetition). It is often better to export an explanation of the structure through Populate &gt;Heurist XML/JSON - Download template.

[![image.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/scaled-1680-/P0Jimage.png)](https://docs.heuristref.net/uploads/images/gallery/2026-07/P0Jimage.png)

### JSON

“**JSON (JavaScript Object Notation)** is an open standard file format and data interchange format that uses human-readable text to store and transmit data objects consisting of name–value pairs and arrays (or other serializable values).” (source: [**Wikipedia**](https://en.wikipedia.org/wiki/JSON))

The choices are similar to XML/HML, except that it cannot include the human readable forms. However, as with XML, you can download the information in the form of a JSon tempalte through Populate &gt;Heurist XML/JSON - Download template.

### RDF

“The **Resource Description Framework (RDF)** is a method to describe and exchange graph data.” (source: [**Wikipedia**](https://en.wikipedia.org/wiki/Resource_Description_Framework))

In this format, you can specify the serialisation you want: rdfxml, json, ntriples or turtle

As this function is still in development (July 2026) it requires a special password to access. Contact the Heurist development team for further information.

#### GeoJSON

“**GeoJSON** is an open standard format designed for representing simple geographical features, along with their non-spatial attributes. It is based on the JSON format.” (source: [**Wikipedia**](https://en.wikipedia.org/wiki/GeoJSON))  
You can select the export detail between: No = no detail, Inline = Inline detail, and Full = maximum detail

### KML

“**Keyhole Markup Language (KML)** is an XML notation for expressing geographic annotation and visualization within two-dimensional maps and three-dimensional Earth browsers. It is best known for its use in Google Maps but is widely importable into GIS and mapping packages” (source: [**Wikipedia**](https://en.wikipedia.org/wiki/Keyhole_Markup_Language))

There are no options for this export format, it is exported immediately as soon as you click on the button.

### GEPHI

GEPHI export generates a GEFX file which can be loaded immediately into GEPHI. Note that this export function is also available directly within the network visualisation graph in the **Network** tab.

[![image.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/scaled-1680-/GE2image.png)](https://docs.heuristref.net/uploads/images/gallery/2026-07/GE2image.png)

In addition to the normal node and edge fields, you can choose to add additional fields to the export ①.

![7b64c475-727f-4705-a325-d423ed6666ef.png](https://heurist-doc.huma-num.fr/uploads/7b64c475-727f-4705-a325-d423ed6666ef.png)

This leads to the pop-up below. We recommend only selecting fields relevant to the record type being exported. You can also export with jsut the default fields ② which will be sufficient in most cases.

[![image.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/scaled-1680-/SQ3image.png)](https://docs.heuristref.net/uploads/images/gallery/2026-07/SQ3image.png)

<p class="callout info">**🛟 Tip:**  
It can be useful to check the box limiting the export to the first 1000 nodes in order to check that the export gives you what you want in GEPHI, befor exporting a very large dataset.</p>

### IIIF

“The International Image Interoperability Framework (IIIF, spoken as ‘triple-I-F’) defines several application programming interfaces that provide a standardised method of describing and delivering images over the web, as well as “presentation based metadata”\[1\] (that is, structural metadata) about structured sequences of images” . (source: [**Wikipedia**](https://en.wikipedia.org/wiki/International_Image_Interoperability_Framework)). IIIF can also handle tiled image delivery andimage annotation.

Heursit acts as both a IIIF manifest delivery system and image server, and as an IIIF display and annotation client, notably through the use of Mirador Vsn 4 and Open Sea Dragon viewers, and the MAE annotation framework. Heurist can also read and atomise manifests containing annotations, and recompose manifests including those annotaitons and others created within Heurist.

IIIF is a rich and complex system. Heurist's IIIF implementation is discussed in detial in chapter 8e.

### HuNI

“**HuNI** (pronounced “honey”) brings together information about the people, works, events, organisations and places that form Australia and Canada’s past and present.” (source: [**huni.net**](https://huni.net.au/#/search)). It is an old infrastrucute project dating to the 2000s with limited functionality (essentially harvesting simple metadata from 40+ Australian sources, providing simple search, bookmarking as a 'collecction' and exporting a CSV file with title and URL of the bookmarked records. The HuNI export format has the particularity of exporting one XML file per record. It may be of some use if that fits with your needs.

#### HTML

 This option exports HTML pages for public records (one file-per-record) using the Record view format.

**📊 Export‑type quick‑reference table – Which format to choose?**

<table id="bkmrk-needcsvxmlrdfjsongeo" style="border-collapse: collapse; border-spacing: 0px; background-color: transparent; margin-top: 0px; display: block; width: 728px; overflow: auto;"><colgroup><col style="width: 122px;"></col><col style="width: 61px;"></col><col style="width: 67px;"></col><col style="width: 62px;"></col><col style="width: 70px;"></col><col style="width: 107px;"></col><col style="width: 63px;"></col><col style="width: 77px;"></col><col style="width: 57px;"></col><col style="width: 70px;"></col></colgroup><tbody><tr style="background-color: rgb(255, 255, 255); border-top: 1px solid rgb(204, 204, 204);"><th class="align-left" style="padding: 6px 13px; text-align: left; font-weight: bold; border: 1px solid rgb(221, 221, 221);">Need

</th><th class="align-left" style="padding: 6px 13px; text-align: left; font-weight: bold; border: 1px solid rgb(221, 221, 221);">CSV

</th><th class="align-left" style="padding: 6px 13px; text-align: left; font-weight: bold; border: 1px solid rgb(221, 221, 221);">XML

</th><th class="align-left" style="padding: 6px 13px; text-align: left; font-weight: bold; border: 1px solid rgb(221, 221, 221);">RDF

</th><th class="align-left" style="padding: 6px 13px; text-align: left; font-weight: bold; border: 1px solid rgb(221, 221, 221);">JSON

</th><th class="align-left" style="padding: 6px 13px; text-align: left; font-weight: bold; border: 1px solid rgb(221, 221, 221);">GeoJSON

</th><th class="align-left" style="padding: 6px 13px; text-align: left; font-weight: bold; border: 1px solid rgb(221, 221, 221);">KML

</th><th class="align-left" style="padding: 6px 13px; text-align: left; font-weight: bold; border: 1px solid rgb(221, 221, 221);">Gephi

</th><th class="align-left" style="padding: 6px 13px; text-align: left; font-weight: bold; border: 1px solid rgb(221, 221, 221);">IIIF

</th><th class="align-left" style="padding: 6px 13px; text-align: left; font-weight: bold; border: 1px solid rgb(221, 221, 221);">HuNI

</th></tr><tr style="background-color: rgb(255, 255, 255); border-top: 1px solid rgb(204, 204, 204);"><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">Spreadsheet

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td></tr><tr style="background-color: rgb(248, 248, 248); border-top: 1px solid rgb(204, 204, 204);"><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">Markup (tagging)

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td></tr><tr style="background-color: rgb(255, 255, 255); border-top: 1px solid rgb(204, 204, 204);"><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">Versatile (generic data)

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td></tr><tr style="background-color: rgb(248, 248, 248); border-top: 1px solid rgb(204, 204, 204);"><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">Spatial (geographic)

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td></tr><tr style="background-color: rgb(255, 255, 255); border-top: 1px solid rgb(204, 204, 204);"><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">Networks

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td></tr><tr style="background-color: rgb(248, 248, 248); border-top: 1px solid rgb(204, 204, 204);"><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">High‑resolution images

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);">✅

</td><td style="padding: 6px 13px; border: 1px solid rgb(221, 221, 221);"></td></tr></tbody></table>

1Password menu is available. Press down arrow to select.