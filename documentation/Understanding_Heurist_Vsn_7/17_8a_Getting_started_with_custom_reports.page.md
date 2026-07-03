# 8a : Getting started with custom reports

Documentation rédigée le 06/11/2025 par Shannon Bruderer mise à jour le 05/12/2025 par Shannon Bruderer

---

### What is a Custom Report ? :

A **Custom Report** is a template that structures your database records into various output formats such as **HTML** (for web display), **plain text** (for transfers without formatting), **CSV** (for spreadsheet work, e.g. in Excel or Open Office), **JSON** (for data feeds), or **XML** (for tagged data exchange).

*Note however that CSV, JSON and XML are handled much more easily in the Export tab in Explore unless some very specialised formatting is required.*

<p class="callout info">The development of custom reports can be quite a slow process, so it is best to plan well what reports you need and apply good naming conventions. Some level of simple HTML will be required, and knowledge of CSS will allow for much greater control of the output. PHP and JS functions can also be included (optional). :::</p>

**Custom Reports** are useful when you need to extract and format specific data for further analysis or publication. They are particualrly useful in setting up web pages (see chapter 9).

For example, if you want to work only with female individuals stored in your database, you can:

1. Search or create a **Saved Filter** that selects all female persons.
2. Build your **Custom Report** based on this selection.
3. Export your data in the desired format for your analysis.

<p class="callout info">Tip : Before creating your Custom Report, define clearly what kind of analysis you plan to do. Your analytical goal will determine both the data you select and the export format you choose. :::</p>

Reports are built using **Smarty**, a templating language that combines standard **HTML** with **Smarty tags** to dynamically insert data from Heurist.

---

### How to start :

:::warning Explore → Filter → Report → Custom Report :::

To begin, click on \[Explore\]. ①

Perform a search or use a \[Saved Filter\] that returns the records you want to include in your report. ①

You can also focus on a specific record type by clicking on \[Entities\] and selecting My Record Types, either by Favorites or by Usage. ②

The selected data will appear in the middle section of your screen. ③

To work with Custom Reports, click on \[Report\] at the top left of your screen. ④

The Custom Report template for your filtered data will appear below the \[Report\] button. ⑤

![](https://heurist-doc.huma-num.fr/uploads/d72124d4-bb65-4cec-93fc-6f660b22ac00.png)

### The Toolbar

In the upper part of the **Custom Report** view, you’ll see a toolbar:

![](https://heurist-doc.huma-num.fr/uploads/d8e86cb5-488d-40bd-911c-baa19ccafca1.png)

#### Edit Tool

![](https://heurist-doc.huma-num.fr/uploads/79c1beb9-c035-44aa-81d6-6e206f432aa7.png)

Click \[Edit\] to open the template editor and start writing your **Custom Report** with **Smarty**. The editor is split into three panels:

- Right – Actions pane ① : insert fields, loops, and conditions via dropdown helpers ②, and browse record types ③ to quickly add the correct field ④
- Middle – Editor pane ⑤ : write and edit your HTML + Smarty template here.
    - In the editor, you’ll see the default starter message/template:
    
    ```
    {* This is a simple Smarty report template which you can edit into something more sophisticated.
       It should give basic output for any database, as it uses the standard record types which are part of all databases.
       Enter html for web pages or other text format. Use tree on right to insert fields, loops and tests.
       Use this format to include comments in your file, use <!-- --> for output of html comments.
       Smarty help describes many functions you can apply, loop counting/summing, custom functions etc.*}
    ```

*Below, we will go deeper into Smarty syntax — see X. Smarty Syntax in Heurist.*

- Left – Preview pane ⑨ : shows the output when you click \[Test\] ⑧ . You can choose to truncate the preview to *n* records ⑥ and select how to handle debug messages, warnings, and errors ⑦.

![](https://heurist-doc.huma-num.fr/uploads/8d546035-c69d-49a6-9162-866b14eb7905.png):::info Tips :

- Click \[Test\] to preview ! Nothing is saved when testing.
- Use \[Save\] (or \[Save As\]) to store your template and keep versions.
- Use Ctrl+Z / Cmd+Z to undo recent edits. :::

#### Create a new template

![](https://heurist-doc.huma-num.fr/uploads/b15e250f-4f31-448a-8dca-bb4bfe84c27e.png)

The \[Create a new templat\] works similarly to the \[Edit\] tool. It opens the same editor interface where you can create a new Custom Report template from scratch.

Use it when you want to **start a fresh layout** instead of editing an existing one.

*Below, we will go deeper into Smarty syntax — see X. Smarty Syntax in Heurist.*

#### Delete the Selected Template

![](https://heurist-doc.huma-num.fr/uploads/908eefb4-58d6-49be-ab40-6528f7ef25c9.png)

The \[Deleat\] tool allows you to **delete** the currently selected template.

When clicked, a **warning message** will pop up asking for confirmation.

![](https://heurist-doc.huma-num.fr/uploads/1563b9ca-7411-472c-998c-400a6f25bc05.png)

It will display the name of your template ① like *name\_file\_.tpl* . As here for exemple "Basic (inital record types).tpl"

Click \[Proceed\] to confirm deletion, or \[Cancel\] to abort the action.

#### Import and Export Templates

![](https://heurist-doc.huma-num.fr/uploads/e4cb072c-5139-4059-87be-a9e32f904de7.png)![](https://heurist-doc.huma-num.fr/uploads/ca292056-ca7e-460d-af1d-d32105aa548d.png)

The Import ← and Export → tools allow you to share and reuse Custom Report templates.

- Import lets you upload an existing template file (.tpl) from your computer into Heurist.
- Export lets you download your customized template as a .tpl file, so you can back it up or share it with others.

\[🔧COME BACK TO EXPLAIN PERMISSIONS SETTINGS\]![](https://heurist-doc.huma-num.fr/uploads/b1b32ab7-095e-4126-9757-34cd73600510.png)\[🔧COME BACK TO EXPLAIN PERMISSIONS SETTINGS\]

#### Obtain JavaScript to embed a report, and set a publishing schedule

![](https://heurist-doc.huma-num.fr/uploads/46ab971b-127c-4fd7-aef9-e97e59e5a277.png)

The \[Publish\] option lets : (a) embed a Custom Report in an external website (e.g., WordPress) (b) schedule periodic regeneration with caching for faster load times on large/complex reports.

##### How to publish (embed)

##### Set up a scheduled (cached) report

###### Pros/cons of scheduling

:::success Much faster for large tables, complex calculations, or media-heavy pages. ::: :::danger Content is a snapshot at the last generation time (not strictly real-time). :::

#### Print

![](https://heurist-doc.huma-num.fr/uploads/836bb8bc-875d-4e85-8e79-79efd7bd114a.png)

Finally, something super easy and super useful for good documentation and data archiving!

The \[Print\] buttom simply generates a PDF of the output from your current **Custom Report** template. It’s a quick and convenient way to export information in a easy readable, shareable, and archivable format.

:::info Tip: Don’t hesitate to use this feature to:

- Enrich your Data Management Plan (DMP),
- Keep track of specific datasets, or
- Share information with colleagues who may not be comfortable navigating Heurist or other “sophisticated” data formats. :::

#### Refresh

![](https://heurist-doc.huma-num.fr/uploads/5b2cda3b-cc08-4abc-8311-0bac888bf3ec.png)

Click on the \[Refresh\] buttom to update the data used by your Custom Report template.

If your database has been modified (new records, edits, deletions) but the output of your report does not reflect these changes, simply hit Refresh to reload the most recent data and ensure your preview is accurate.