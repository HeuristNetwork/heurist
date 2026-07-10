# 8a: Getting started with custom reports

Documentation rédigée le 06/11/2025 par Shannon Bruderer mise à jour le 05/12/2025 par Shannon Bruderer

---

### What is a Custom Report ? :

A **Custom Report** is a template that structures your database records into various output formats such as **HTML** (for web display), **plain text** (for transfers without formatting), **CSV** (for spreadsheet work, e.g. in Excel or Open Office), **JSON** (for data feeds), or **XML** (for tagged data exchange).

*Note however that CSV, JSON and XML are handled much more easily in the Export tab in Explore unless some very specialised formatting is required.*

<p class="callout info">The development of custom reports can be quite a slow process, so it is best to plan well what reports you need and apply good naming conventions. Some level of simple HTML will be required, and knowledge of CSS will allow for much greater control of the output. PHP and JS functions can also be included (optional). :::</p>

**Custom Reports** are useful when you need to extract and format specific data for further analysis or publication. They are particualrly useful in formatting data to appear in web pages (see chapter 9). They can also be used to download formatted information or set up feeds of data for other purposes.

They are also useful for displaying a single record in Record View, a popup on the map, or wherever data needs to be displayed in response to selection of one or more records.

<p class="callout info">Tip : Before creating your Custom Report, define clearly what sort of display you plan to do. Your formatting goal will determine both the data you select and the way you format the output.</p>

Reports are built using **Smarty**, a templating language that combines standard **HTML** with **Smarty tags** to dynamically insert data from Heurist.

---

### How to start :

In the Explore menu ① you can also focus on a specific record type <span style="color: rgb(132, 63, 161);">(Explore &gt; Entities)</span> and select the record type from the list ②

You may wish to perform a more flexible, either by entering it in the Filter field ③ or using the Filter builder just below it, or use a <span style="color: rgb(132, 63, 161);">\[Saved Filter\]</span> from the Filters ection ② that returns the records you want to include in your report.

The selected records will appear in the middle section of your screen. ④

To work with Custom Reports, click on the <span style="color: rgb(132, 63, 161);">\[Report\]</span> tab ⑤

The Custom Report template for your filtered data will appear below the <span style="color: rgb(132, 63, 161);">\[Report\]</span> button.

![d72124d4-bb65-4cec-93fc-6f660b22ac00.png](https://heurist-doc.huma-num.fr/uploads/d72124d4-bb65-4cec-93fc-6f660b22ac00.png)

### The Toolbar

In the upper part of the **Custom Report** tab, you’ll see a toolbar:

![d8e86cb5-488d-40bd-911c-baa19ccafca1.png](https://heurist-doc.huma-num.fr/uploads/d8e86cb5-488d-40bd-911c-baa19ccafca1.png)

#### Edit Tool

![79c1beb9-c035-44aa-81d6-6e206f432aa7.png](https://heurist-doc.huma-num.fr/uploads/79c1beb9-c035-44aa-81d6-6e206f432aa7.png)

Click<span style="color: rgb(132, 63, 161);"> \[Edit\] </span>to open the template editor and start writing your **Custom Report** with **Smarty**. The editor is split into three panels:

- **Actions pane** ① :   
      
    Insert fields, loops, and conditions via dropdown helpers ②, and browse record types ③ to quickly add the correct field ④
- **Editor pane** ⑤ : write and edit your HTML + Smarty template here.  
      
    In the editor, you’ll see the default starter message/template (note that this template may chage through time as we improve on it, but it will contain a basic loop for records and some instructions to get started).   
    *{\* This is a simple Smarty report template which you can edit into something more sophisticated.*  
     *It should give basic output for any database, as it uses the standard record types which are part of all databases.*  
     *Enter html for web pages or other text format. Use tree on right to insert fields, loops and tests.*  
     *Use this format to include comments in your file, use &lt;!-- --&gt; for output of html comments.*  
     *Smarty help describes many functions you can apply, loop counting/summing, custom functions etc. \*}*  
      
    Below, we will go deeper into Smarty syntax — see X. Smarty Syntax in Heurist.
- **Preview pane** ⑨ : shows the output when you click <span style="color: rgb(132, 63, 161);">\[Test\]</span> ⑧ .   
      
    You can choose to truncate the preview to *n* records ⑥ and select how to handle debug messages, warnings, and errors ⑦.

![8d546035-c69d-49a6-9162-866b14eb7905.png](https://heurist-doc.huma-num.fr/uploads/8d546035-c69d-49a6-9162-866b14eb7905.png):::info Tips :

<p class="callout info">Click <span style="color: rgb(132, 63, 161);">\[Test\]</span> to preview ! Nothing is saved when testing.  
Use <span style="color: rgb(132, 63, 161);">\[Save\]</span> (or <span style="color: rgb(132, 63, 161);">\[Save As\]</span>) to store your template and keep versions.  
Use <span style="color: rgb(132, 63, 161);">Ctrl+Z</span> / <span style="color: rgb(132, 63, 161);">Cmd+Z</span> to undo recent edits. You can undo a lot of edits by repeating this.</p>

<p class="callout info">We strongly recommend making only one or two changes at a time and clickign Test to see the results. If somethign doesn't work, you can immediately undo it and try an alternative.   
  
Don't get tempted to write a lot of code and then test it because then you will have trouble finding the problem.</p>

#### Create a new template

![b15e250f-4f31-448a-8dca-bb4bfe84c27e.png](https://heurist-doc.huma-num.fr/uploads/b15e250f-4f31-448a-8dca-bb4bfe84c27e.png)

\[<span style="color: rgb(132, 63, 161);">Create a new template\]</span> works similarly to the <span style="color: rgb(132, 63, 161);">\[Edit\]</span> tool. It opens the same editor interface where you can create a new Custom Report template from scratch.

Use it when you want to **start a fresh layout** instead of editing an existing one.

*Below, we will go deeper into Smarty syntax — see X. Smarty Syntax in Heurist.*

#### Delete the Selected Template

![908eefb4-58d6-49be-ab40-6528f7ef25c9.png](https://heurist-doc.huma-num.fr/uploads/908eefb4-58d6-49be-ab40-6528f7ef25c9.png)

The \[<span style="color: rgb(132, 63, 161);">Delete\]</span> tool allows you to **delete** the currently selected template.

When clicked, a **warning message** will pop up asking for confirmation.

![1563b9ca-7411-472c-998c-400a6f25bc05.png](https://heurist-doc.huma-num.fr/uploads/1563b9ca-7411-472c-998c-400a6f25bc05.png)

It will display the name of your template ① like *name\_file\_.tpl* . As here for exemple "Basic (inital record types).tpl"

Click <span style="color: rgb(132, 63, 161);">\[Proceed\]</span> to confirm deletion, or<span style="color: rgb(132, 63, 161);"> \[Cancel\] </span>to abort the action.

#### Import and Export Templates

![e4cb072c-5139-4059-87be-a9e32f904de7.png](https://heurist-doc.huma-num.fr/uploads/e4cb072c-5139-4059-87be-a9e32f904de7.png)![ca292056-ca7e-460d-af1d-d32105aa548d.png](https://heurist-doc.huma-num.fr/uploads/ca292056-ca7e-460d-af1d-d32105aa548d.png)

The Import ← and Export → tools allow you to share and reuse Custom Report templates.

For this we have developed a 'global template' format (,gpl) which uses Heurist's unique Concept IDs so that the template can be usd by any database that includes those concepts (definitions of record types, fields and terms). Template files stored in the Heurist database are the same as global files except that they use local codes rather than the unique global concept IDs.

<p class="callout info">Templates can only be exported from a registered database to ensure that there are Concept IDs for any definitions used in the template. If the database is not registered you will see the following message.</p>

![b1b32ab7-095e-4126-9757-34cd73600510.png](https://heurist-doc.huma-num.fr/uploads/b1b32ab7-095e-4126-9757-34cd73600510.png)

Import lets you upload an existing global template file (.gpl) and convert it to a local template file (.tpl)

Export lets you download your customized template as a .gpl file, so you can back it up or share it with others.

##### Obtain JavaScript to embed a report, and set a publishing schedule

![46ab971b-127c-4fd7-aef9-e97e59e5a277.png](https://heurist-doc.huma-num.fr/uploads/46ab971b-127c-4fd7-aef9-e97e59e5a277.png)

The \[Publish\] option lets you :

- embed a Custom Report in an external website in another CMS
- schedule periodic regeneration with caching for faster load times on large/complex reports.

##### How to publish (embed)

##### Setting up a scheduled (cached) report

###### Pros/cons of scheduling

<p class="callout success">Much faster for large tables, complex calculations, or media-heavy pages. </p>

<p class="callout warning">Content is a snapshot at the last generation time (not strictly real-time), so frequency of update needs to be approriately set</p>

#### Download

[![image.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/scaled-1680-/CMoimage.png)](https://docs.heuristref.net/uploads/images/gallery/2026-07/CMoimage.png)

This allows the download of a plain text file without html formatting (assumng you did nopt use html tags in the report format)

#### Print

![836bb8bc-875d-4e85-8e79-79efd7bd114a.png](https://heurist-doc.huma-num.fr/uploads/836bb8bc-875d-4e85-8e79-79efd7bd114a.png)

The \[Print\] buttom simply generates a PDF of the output from your current **Custom Report** template. It’s a quick and convenient way to export information in an easy readable and shareable format.

<p class="callout info">Tip: Don’t hesitate to use this feature to:  
 Enrich your Data Management Plan (DMP),  
 Keep track of specific datasets, or  
 Share information with colleagues who may not be comfortable navigating Heurist or other “sophisticated” data formats. :::</p>

#### Refresh

![5b2cda3b-cc08-4abc-8311-0bac888bf3ec.png](https://heurist-doc.huma-num.fr/uploads/5b2cda3b-cc08-4abc-8311-0bac888bf3ec.png)

Click on the \[Refresh\] buttom to update the data used by your Custom Report template.

If your database has been modified (new records, edits, deletions) but the output of your report does not reflect these changes, simply hit Refresh to reload the most recent data and ensure your preview is accurate.