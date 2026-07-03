# Ch 03: Basic structuring concepts

:::info Documentation régigée le 07/11/2025 par Barbara Bonazzi mise à jour le 03/03/2026 par Barbara Bonazzi :::

## 1. Structuring your Database

The **Design** menu serves to configure the structure of the database to accept your data.

New databases are pre-populated with a range of useful **record** (*entity*) **types**, **fields** and **vocabularies** which shortcuts basic setup.

The existing **entity types** can be modified to fit your needs, including adding, deleting or modifying fields. You can add entirely new **record types**, or import suitable record types from any database that has been registered with the Heurist service. The menu also includes functions to register your database so others can borrow your structure (not data), change some basic settings and personal preferences and configure a toolbar of shortcuts. Functions for modifying the structure of the database and various settings.

**Modify**

- **Record types** - add/edit the record (entity) types making up the database
- **Vocabularies** - add/edit vocabularies and the terms which comprise them
- **Base fields** - add/edit shared fields which can be reused in many record types
- **Browse templates** - borrow structural elements from other Heurist databases
- **Visualise** – visualise record types and relationships between them as a spider diagram

**Setup**

- **My Preferences** – set personal preferences relating to the way this database operates
- **Properties** – set various parameters relating to how this database operates
- **Workflow stages**– set rules which are applied when a record changes workflow stage.
- **External lookups**– lookup of external resources already defined or imported by the user
- **External repositories** – storage and retrieve of external media from an external repository
- **Register**– register the database with a central index to make it findable
- **Shortcuts bar**– create a shortcut bar and choose to display below the page header bar

**Download**

- **Structure (XML)** - export the complete structure of the database as XML
- **Structure (Text)** - export structure as an SQL-like dump, primarily for internal use
- **Refresh memory** - cleans up browser memory; may help fix minor interface problems

### 1.1. Record types

**Record** (*entity*) **types** are the core of designing an effective database. Each new database comes pre-populated with a lot of record types which crop up in most databases, eg. Person, and record types which need to be structured in a particular way for specific functions, eg. map documents, layers and data sources. Record types are divided into groups to reduce mental overload, and the groups can be reordered by dragging. Record types you use all the time should be dragged over into a group near the top so that they appear at the top of dropdown lists. You can create new groups to organise your concepts. You do not need to get rid of record types you don't require, just drag them over into a group towards the end of the list.

Before creating a brand new record type, look to see if you can find something suitable using Browse Templates or consider if you can re-use an existing one already defined in your database. However, don't change the general intent of an existing record. For instance, don't change a Media item record into a Document, even if most of your media items represent documents or a Person into an Animal, even though they may have a name, date of birth, sex etc.

### 1.2. Vocabularies

**Vocabularies** organise a set of terms which can be used in the dropdown list for one or many term list fields. Vocabularies can contain links to terms in other vocabularies to allow the construction of new vocabularies without repeating terms - for example, a vocabulary containing a few countries being studied from the full set of world countries which are pre-configured as a vocabulary in all new databases. Vocabularies can contain hierarchies of terms allowing broader/narrower definition of categories. Like record types, vocabularies are organised into groups, which can be reordered, and vocabularies can be moved into a different group by drag and drop. Terms can also be moved between vocabularies with drag and drop, or can be nested below other terms or merged with other terms (in which case all records using the term will be re-assigned to the term with which it has been combined).

Terms are defined by six fields:

- a label (the term itself);
- a description (multi-line text);
- a standard code (for example Munsell Colour code, international country codes);
- a semantic URI (for use in linked data);
- a status (of the term within the database, generally this should be left as Open\*);
- an image (allowing illustration of the terms for use by people less familiar with their meaning). :::info
- ***Open***\_ indicates that the record type can be modified or deleted.
- ***Approved***\_ indicates a record type which has been carefully developed for general use.
- ***Reserved-Locked***\_ indicates a record type which is required by the system and cannot be deleted (this value cannot be selected by users other than the Heurist team). :::

### 1.3. Base fields

**Base fields** are fields which can be reused in many different record types. They are available when adding fields to a record type; the base field type, name, help text, vocabulary and target record types (where applicable) are automatically applied to the field in the record type, but name, help text, requirement and repeatability may be overidden with customised versions for the specific record type. A new base field is created automatically if one creates a field from scratch rather than using an existing base field. One will not normally need to edit base fields directly, but this menu item allows direct access when required, for example if one wishes to change the default name or description.

### 1.4. Browse templates

Heurist has a sophisticated system to allow databases to import structure selectively from any registered database (databases are registered with **Design &gt; Setup &gt; Register**). This is a powerful way of sharing modeling work and promoting standardisation by encouragement rather than obligation.

The function browses and selects a registered database, opens up a list of any record types not currently in the target database, displays the fields within a record type if required (base fields already in the target are shown in grey), and can then download the record type along with all connected record types, fields, vocabularies and terms required to create a coherent set of data structures for import.

### 1.5. Visualise

The relationships between record (*entity*) types in the database can be visualised in the form of a spider diagram. The diagram also shows the number of records for each **node** (size of circular shaded area around node) and the number of **connections** (thickness of connecting lines). The connections include **record pointer fields** and **relationship markers**, but not free-floating relationships created by creating Relationship records directly (the creation of Relationship records directly is not recommended). As a diagram of all record types would be far too complex, the record types to be represented are selected from a dropdown list. Gravity can be switched on to create a self-organising diagram, then switched off to allow dragging of nodes to clarify the diagram. Links can also be built between record types by dragging the link icon.

### 1.6. My Preferences

Personal preferences for this database can be set in the **Preferences dialogue**. These include startup search, number of records to display per page, the use of clustering on maps and complexity of the map controls. Personal preferences are specific to each database. The Preferences dialogue also provides a bookmarklet which can be dragged to the browser toolbar and used to grab infromation from a web page and create a web bookmark record in the database. At this time the information which can be grabbed from secure https pages is limited to URL and title, but highlighted text will also be grabbed from non-secure http pages.

### 1.7. Properties @todo-link to chapter 10 Admin &gt; Properties

General behavioural parameters of the database can be set through the Properties function. This allows metadata for the database including a description, rights and a representative icon to display in lists, configuration of connections to Zotero libraries, Nakala and mail servers, configuration of lookups to external reference sources, file types to be indexed and specific behaviours relating to place records, user registration and others.

### 1.8. Workflow stages

When a record changes its workflow stage, the defined rules are applied. This rules apply to changing access restriction, ownership, record visibility or sending an e-mail notification.

### 1.9. External lookup

Connect with services enabling the lookup of external resources (gazeeter, thesaurus, library catalogue...) from within a data entry form and insert of one or more fields derived from the external resource into the data. They can also be used to provide specialised processing such as predictive setting of keywords based on frequency of usage and matching with external resources. Some services are already defined (AGHP, BnF Library, ESTC, GeoNames, LRC18C, MPCE, Nakala, Nomisma, and Opentheso). It is also possible to import new services using the template and guidelines provided in the source code. If likely to be of general use, please contribute them to the GitHub repository.

### 1.10. External repositories

Store and retrieve external resources such as images, documents, video on/from the already defined external repositories. The currently available repositories are DSpace, Flikr, Isidore, MediHAL, Nakala and Zenodo. It is also possible to define who can access these resources, e.g. logged-in user, current user or database managers. :::warning

### 1.11. Register @todo-link

Register the database with the central Heurist index database. This has several functions: it allows elements of the structure of the database to be imported into a new database promoting re-use and standardisation; it allows XML files exported from any registered database to be imported into any other database by reference to the structure of the source database. Last but not least, it attributes a unique ID to the database and thence a unique ID (known as a 'concept code') to every record type, field, vocabulary and term which has been defined within the database. This is particularly useful in defining special behaviours which can operate across databases, in linking data across databases, and in providing a PID redirection system which can reference any element of any database. :::

### 1.12. Shortcuts bar

The shortcuts bar appears (optionally) below the page header bar, and can be used to provide quick access to frequently used functions. The dialogue allows addition of functions from a list of common functions, with a user-defined label and icon, and allows the bar to be displayed or hidden (hidden by default for new databases). The bar can also be modified from the gearwheel icon on the left of the bar itself.

### 1.13. Download &gt; Structure (XML)

The complete structure of the database is downloaded in well documented XML. Record types, fields, vocabularies and terms are identified both by their names and by their concept codes. It is recommended to first register the database (Design &gt; Setup &gt; Register), as this means that the concept codes are unique across all databases and will be carried with the structural elements wherever the data is imported, even if re-exported and imported further down the chain.

### 1.14. Download &gt; Structure (Text)

This is a specialised legacy format based on SQL insert statements, used for transferring structure between databases. It is unlikely to be useful beyond this application.

## 2. Defining Record Types

The first task is to organise the **entity types** (record types) that you wish to use through **Design &gt; Record types**. The browser serves to organise record types into groups and create new groups and record types. It also allows you to get an overview of the record types available.

![](https://heurist-doc.huma-num.fr/uploads/dbf53db1-8185-4f18-a491-b41c625780b0.png)

### 2.1 Record type groups

Record types are organised into groups (the third column above). The groups are purely an organising mechanism to help you find your way around a long list of record types. Changing the order or membership will have absolutely no effect on the data in the database. In addition to the standard groups supplied by default, you can create your own groups by clicking on the **Add** button.

![](https://heurist-doc.huma-num.fr/uploads/642f99dc-1143-4f87-ac07-ac79d4701839.png)

After clicking on the **Add** button, you can fill in the title and description of the new record type group :

![](https://heurist-doc.huma-num.fr/uploads/ad0ac6a2-221c-4af8-bb0b-0bfd660020fd.png)

You can also add new record types in a group and move records types between groups simply by dragging them to the group where you want them located. The groups can also be reordered simply by dragging them up and down. They can be renamed and described by clicking the ✏️ icon which appears next to the group name on rollover.

They can be deleted, only if they are empty. You can also drag record types into the Trash group at the bottom if you don’t want to see them. They do not affect performance and can be recovered later by dragging them back out of trash.

:::info **IMPORTANT TIP**

Always organise the record types you use frequently into the first couple of groups of record types. In this way they will appear at the top of any dropdown lists which saves hunting for them further down. A small investment in well-organised groups will make it much easier to pick from lists or find record types when you need to make changes. The same applies to fields and vocabularies. :::

![](https://heurist-doc.huma-num.fr/uploads/59164b67-9362-4f90-a16c-41dfc28389fe.png)

### 2.2. Columns in the form

The columns in the image above are generally self-explanatory.

- **Count** is the number of records of that type.
- Clicking on the magnifying glass in the **Filter** column will trigger a new browser tab with a search result for the selected record type.
- The plus icon in the **Add** column will add a new record of the selected type and open the data entry form for it.
- The **Show** checkbox determines whether the record type is shown in lists in the interface. This may be useful for hiding types you never wish to add individually or search on so that the dropdowns are not cluttered.
- The icon in the **Dup** (duplicate) column will create a copy of the record type with the same fields – this can be useful where one needs to create several similar record types.
- You can delete record types by dragging them into the Trash group (from which they can later be recovered) or using the dustbin icon in the **Del** column (permanent deletion). Some record types are protected from permanent deletion (shown by a lock symbol in the **Del** column) as they have special functions within the system e.g. Place, Person and Organisation and all the Mapping record types. Any record type referenced by another record type is also protected from deletion (shown by a grey dustbin icon), as is any record type for which records exist. Any of these record types may however be dragged into the Trash group (where they continue to exist and from which they can be recovered later).
- **ID and ConceptID** @todo-link: these are an important feature of Heurist’s design - please see separate explanation below.
- **Description**: record type description, completed in the description field of the record type. You can configure the interface, choosing which columns you want to display, from the bottom right gear.

### 2.3. Define new record types

Before defining a new record type definition, check whether a similar record type already exists in the database structure, which can be reused or tailored. We strongly recommend using an existing record type where one exists which is broadly what you need, for example such standard types as Person, Organisation, Place, Media, Structure, Site, Document etc., as well as the existing Bibliographic types which are required for synchronisation with Zotero. he use of existing record types will save you an awful lot of time and are some guarantee of a coherent structure. Also consider whether a record structure can be imported from another database located through the Heurist Master Index using the **Browse templates** function @todo-link. The reuse of database types can save time and add to the overall consistency of databases.

## 3. Add record types

You may add new record types as required. Some databases will require very few new types, others will require many new types, but always re-use existing types that more or less fit your needs (with some changes to the list of fields recorded). :::info **Tip:** if you need to create several similar record types, we recommend creating one type with all required fields then using the Duplicate function (**Dup** column) to create copies which can be renamed and adapted. Don’t change an existing record type into something completely different, e.g. changing a Document into a Museum or a Place into an Event, as this will make your database incompatible with other databases which have retained the original meaning, and some record types, e.g. Place and Event, have special behaviours associated with them (display on maps or timelines for example). :::

Select the group in which you would like the record type created and click the **Add** button:

![](https://heurist-doc.huma-num.fr/uploads/56ab6d81-3bdd-447c-8b97-6431ac53fb4d.png)

You will be encouraged to find an existing record type:

![](https://heurist-doc.huma-num.fr/uploads/0027d104-631c-4c02-8b79-79aa39aad78c.png)

Click **Continue** and you will first be asked to choose a new icon for the record type. This is a limited list of default icons (which we plan to improve with some more Humanities-appropriate icons) – you may find nothing particularly suitable for a medieval scroll, Greek pottery, wall paintings, a writer or a brutalist structure. Go ahead and choose a reasonable icon (use a different icon for each record type as this will allow you to distinguish them quickly) and then later replace it with an icon from an icon library or one that you create yourself:

![](https://heurist-doc.huma-num.fr/uploads/2bc7c0ce-5e8c-41a9-8b60-b68f899641c0.png)

After choosing an icon, you can fill in the basic attributes of the new record type:

![](https://heurist-doc.huma-num.fr/uploads/6b7cdfaf-65ff-4ea6-88a7-383375ba783b.png)

- **Record type name** may contain: alphanumeric characters, $, &lt;, &gt;, /, \_, – (en dash) or — (em dash). {, }, \[, \], \*, ‘, and - symbols are not allowed as they are used extensively in SQL queries which underly Heurist. You may also use basic html tag such as &lt;br&gt;, &lt;p&gt;, &lt;b&gt;, &lt;i&gt; and &lt;a href&gt;.
- **Description** should be a concise but informative description of the record type, both for your own use and to assist other users of the database (this displays when the user hovers the cursor over the record). It is important to include a clear description, not just a repetition of the record type name, for long-term documentation of the content of the database, as it is part of the archive package.
- **Semantic reference URI** for the entity concept is optional but highly recommended if you plan to export Linked Open Data. Multiple URIs may be separated by semi-colons.
- **Show Record URL** checkbox is used to display a special URL field at the top of the record editing form. This URL is attached to the record in result list displays allowing Heurist to be used as a bookmarking tool. In general we recommend not checking this box unless each record will have a specific canonical URL associated with it. Other URLs can be recorded in standard single-line text fields, which will be recognised as a hyperlink if they start with http://, https://,
- The **thumbnail** and **icon** can be chosen from the library, but if uploaded from another source should generally be of the order of 16x16 and 75x75 pixels. They will rescaled to these sizes. The icons are used in result lists, at the top of the data entry form and record view panel, as the default icon on maps, and anywhere else the record type needs to be quickly visually identified.
- **Additional information** is a normally-closed section of the form which shows which group the record type will be assigned to, its status (generally this should be left as Open\*), whether the record type should appear in lists and dropdowns (the Show checkbox on the record types browser), whether the description of the record type should be shown on the data entry form when help is switched on and the number of records of that type. :::warning
- ***Open***\_ indicates that the record type can be modified or deleted.
- ***Approved***\_ indicates a record type which has been carefully developed for general use.
- ***Reserved-Locked***\_ indicates a record type which is required by the system and cannot be deleted (this value cannot be selected by users other than the Heurist team) :::

### 3.1. Defining fields

You will note that there is no ability to define the fields when you first create a new record type (this capability is however available if you click on the **edit icon** next to the new record type in the screen below and then on the **edit field button**).

![](https://heurist-doc.huma-num.fr/uploads/49c08674-73e1-4c6a-b62e-a498bd3cbd45.png)

It will open a **data editing form** for your record type.

![](https://heurist-doc.huma-num.fr/uploads/64a0c7ec-b299-428c-99e7-af37f51a8e97.png)

You can access to the field editing panel by clicking on the gear next to the field name. It allows you to edit the field information or add a field below the selected field, for more information see **@todo-link to chap 5**.

Rather than adding fields *in vacuo*, we strongly recommend immediately adding a new record of this type by **editing the fields**, setting up both the attributes (fields) and the connections (also set up through fields) directly from the **data editing form** and **saving your record type** (**save button** at the right bottom of the windows) so that you can work iteratively and see how it will actually be presented.

### 3.2. Importing new record types

Heurist can import a list of new record types from a CSV file, or manually entered data in this form, using

**Design &gt; Record types &gt; Import from CSV**. This allows for rapid basic setup of new record types.

![](https://heurist-doc.huma-num.fr/uploads/4bebb94a-4c50-40b4-b631-a3470afdd53d.png)

After uploading a CSV or manually entered data in the form, you need to choose the field separator (comma, tab, semicolon or space), indicate if the first line contains the labels of the field of the record type and **click on the analyse button**. Finally, you need to select the record type group and field assignment (at least name and description).

![](https://heurist-doc.huma-num.fr/uploads/da65693b-4e41-443f-8089-2824cdc2e669.png)

The allocation of headings, fields, labelling and behaviours within each record type is, however, too complicated to be set up as an external file (although it is handled automatically in the case of XML import between Heurist databases) and as noted above is best handled through modifying the record structure iteratively while entering real data.

### 3.3. Browsing templates

Heurist has a powerful mechanism for finding and importing database structure (entity/record types, fields and vocabularies/terms) from another database, which is covered in detail in a separate section **Browsing templates** @todo-link. This is very useful where either the Heurist team has set up a template for a particular type of use (which we may have borrowed, with acknowledgement, from a Heurist user) or where colleagues have developed a useful database structure you would like to re-use, or use as a basis for developing your own. This re-use of structure can be an enormous time-saver and also encourages data compatibility and \_de facto \_standards.

### 3.4. Change Record Type

First of all, check that the **record type you want to change to already exists**, and if not, create it. You can change your record type from the **Explore** tab. Select the item(s) whose record type you wish to change. Click on the **Recode&gt;Change record types** drop-down menu.

![](https://heurist-doc.huma-num.fr/uploads/7c4df59c-fc06-4120-a35c-2ee5ccb29dfe.png)

It will open a windows in which you can **change the record type** by selecting another record type in **“Convert to record type”**. The record scope define the item(s) on which you want to apply the change.

![](https://heurist-doc.huma-num.fr/uploads/646783a6-7073-4582-a2b3-84c91bf3f4bd.png)

After validation, the following warning appears. Before validating, make sure that the fields in your record match those in the new one, otherwise you risk losing information and invalidating your data.

![](https://heurist-doc.huma-num.fr/uploads/936a24b2-8eb2-4aad-9315-650afbf9a9fd.png)

If you check “tag affected records (auto-generated tag)”, a **tag** will be associated with the modified record type. It will be visible in the admin panel of the **data editing form** on the right.

![](https://heurist-doc.huma-num.fr/uploads/1d52fd77-f6f2-4c74-bd48-bc757f632fd0.png)

### 3.5. New Record : permission settings

The access permissions to all the data entry of a specific record type can be changed by selecting **Permission settings** at the top of the list (right-hand panel below) which pops up on rollover of **New**, or by clicking on \*\*Settings \*\*below **New**. It allows to have additional control over the new record parameters:

![](https://heurist-doc.huma-num.fr/uploads/394314c8-8b14-44c5-8a97-ba2daba09eee.png)

By default, records in a new database will be visible only to logged in users. **Settings / Permission settings** brings up a dialogue allowing you to control the type and permission settings for future additions (cf. tab that explain database management permission explicated @todo-link chapter 2 / chapter 10.) This can be used not only to determine the future record type and permissions which will be created when you click on **New**, but also provides a URL which can be bookmarked or added to a web page to create new records with those permissions. The use of a tag or tags can be used to flag new records added, for example, by guests, that can be retrieved for editorial vetting. Other values can also be set with suitable parameters in the URL.

![](https://heurist-doc.huma-num.fr/uploads/0f022757-5aa4-4a11-89ca-de86eb26f017.png)

## 4. Heurist Identifiers (H-IDs)

Heurist attributes a **new sequential identifier** (known as an H-ID) to every record in the database when it is created, regardless of type, and these identifiers never change and are never re-used Unlike conventional relational databases, the sequential numbering of records is across the whole database and not across individual tables. This may encourage users to create additional sequential identifiers in specific tables using the field increment function, but we strongly discourage this. H-IDs are unique identifiers which can drill down to a specific record anywhere in the Heurist domain of registered databases. Their invariant nature is ideal for sustainable identification of items. Once something is recorded as H-ID 3456 it will always remain 3456. In fact, if you accidentally record something twice (or more) and later merge the records, the identifier of the merged records will point to the remaining record, so any of the H-IDs used will reference the actual record for the item. Note that a field *Original ID* is defined in all new databases. We encourage the use of this field (which may be renamed) to record the identifier or identifier history of any records imported from another system.

### 4.1. Registering a database

The creator and owner of a database, user #2, can register the database with the **Heurist Master Index** (the system administrator can also do this with an override password defined in the system configuration). To do so, go to **Design &gt; Register**, enter a description of your database and then click on the **register button**. The URL will be automatically created with the name of your database after “db=”.

![](https://heurist-doc.huma-num.fr/uploads/edc80712-2939-403a-88c8-29b6cc4dcfc3.png)

In the Database Registration Screen enter a description of this database (for public consumption). This must be 40 characters or more before you can select Register. If successful, your registration details are shown:

![](https://heurist-doc.huma-num.fr/uploads/fa73e5f6-bd1e-4c9e-a58f-c7a6f1163cb3.png)

### 4.2. Heurist Master Index

This is a publicly accessible list of Heurist databases, which makes all Heurist core databases, curated database templates and all registered end-user generated databases available for reference. Only the database structure is available by default; data is only accessible where individually authorised within the database (there is no central control of this). Curated templates are well-developed schemas developed by the Heurist team or members of the Heurist community. Optionally registering your database with the Heurist Index provides a number of advantages:

- Gives access to certain advanced features; if you have not registered and select such a feature, you will be notified to register first.
- Gives your database a globally unique code, named **ConceptID** @todo-link . The code is the next available sequence number in the Heurist index, which is unique and permanently identifies that database, even if it no longer exists.
- Makes your database available to other Heurist users. Registration of the database publishes the structure (but not the data) of your database to the Heurist Index Page, for use by other Heurist users. This allows other database users to import structural elements of your database (record types, field types and terms) but does NOT confer any form of access to data in this database.
- Any data you export will be interpretable by other systems with the help of Heurist's central index, allowing any Json or XML file exported from your database to be immediately imported into any other Heurist database even if the target does not (yet) have the record types, fields and vocabularies required to hold this data (they are imported automatically).

### 4.3. Collection Metadata

After registering the database you should edit the database's collection metadata in the **Heurist Master Index**. If you are asked to login, use your email address and the same login password as your current database (or the first database you registered, if different).

![](https://heurist-doc.huma-num.fr/uploads/7d794834-0ad8-489f-a715-0469d5ea05ef.png)

Please fill in as much detail as possible to help people find your dataset/collection if it is relevant to them. You can later edit this record as any other record. You can **unregister your database** by deleting the record (you own it). The database will still have a registration number but it will not appear in the database.

## 5. IDs and Concept IDs

In **Design &gt; Record types**, you will find frames of your records types and in these frame, two identifiers associated to the record types (respectively in the columns **ID** and **ConceptID**). The **ID** column of the record type frame shows the **internal ID** of the record type in this database. The **ConceptID** column shows a very important piece of information – the unique **ID** assigned to every record type defined **within the entire Heurist system** when the database had been registered.

The **Concept ID** is made up of:

- A 4 digit number which uniquely identifies the database and is assigned when a database is registered with the Heurist master index, running at HeuristRef.net. The value 0000 indicates that this database has not yet been registered, Values below 100 indicate databases created or curated by the Heurist team.
- A second number of up to 4 digits which is the internal code of the record type in the database in which the record type was defined.

When a database is registered, the **Concept ID** migrates from 0000-xxx to nnnn-xxx where nnnn is the **registration ID of the database**. When the record type is later imported into another database it retains this concept ID so that it can be automatically aligned with the same record type in other databases. This also allows Heurist to carry out specific actions based on known concept IDs or to import a copy of a needed record type for a specific function. :::info **NOTE**: the same system of Concept IDs applies to every base field, every vocabulary and every term within the Heurist domain. :::