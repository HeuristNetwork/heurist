# 8a bis: Custom reports OLD VERSION

Custom Reports are optional but they allow you to customize data display in powerful ways.  
By default, when a record is displayed on a Heurist website, the usual Record View template is used. If you would like to alter how records appear, then you will need to define a Custom Report.

*Note: the content was copied via markdown export and lost much of its minor formatting. The images in particalr have been downgraded. The source is here:* [*https://docs.google.com/document/d/1Jyytaln1-aCm3paZ4rBKho0puXBGaJ97/edit*](https://docs.google.com/document/d/1Jyytaln1-aCm3paZ4rBKho0puXBGaJ97/edit)

Custom Reports work together with Saved Filters to publish content on a Heurist website or elsewhere on the web. The \*filter \*will retrieve records from the database, and hand the records to the \*custom report \*to format and display them. When you choose the Report tab in the View Pane, you will see the selected Custom Report attempt to display information about your current result set. This will only work correctly if the selected report has been configured to display records like those in the result set (e.g. a Custom Report designed to display information about Persons will probably fail to display information about Books or Places properly.)

Smarty Heurist reports are powered by the Smarty Template Engine. For an overview of the Smarty template language, visit [Smarty Syntax](https://smarty-php.github.io/smarty/5.x/designers/language-basic-syntax/). It allows you to embed data from the database into an HTML template which determines the form of the output. You can use CSS, Javascript and even PHP within custom reports. Smarty is an extremely powerful system and almost anything is possible, if you know how.

Simple templates can produce neatly formatted lists in text (e.g. CSV and HTML formats), including media (e.g. images and videos). For example, a report might extract and display the first and last names of all writers born before 1900 along with an alphabetical list of their works.

More complex reports can be configured to retrieve and display information from related records of different nature. For instance, in the case of a database documenting archeological dig sites, excavation campaigns, and objects retrieved, each defined as a different entity, a custom template can produce a nested list of all sites, with the details of each of their respective campaigns ordered chronologically, and display a gallery with a picture of each object for each campaign.

Such complex reports can use all the power of the Smarty template language, including PHP functions (standard or user-defined) directly within the template. They can display data using complex layouts, such as grid or flexbox, and may also include JavaScript to provide interactivity and CSS to customise their appearance.

Custom reports can be used in many different places. You can use custom reports, for example, to:

Display search results on your website

Customise the popups on a Heurist map

Embed Heurist content in another page

Create periodically updated custom data feeds to be used in another platform (for instance, in csv, json, or xml format).

#### Report View Toolbar

![embedded-image-k2fhavam.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-k2fhavam.png)

The dropdown and buttons allow you to perform the following tasks:

  
**Select dropdown**. Select an existing report from the drop down. This is immediately run against the current list of queried records. This lets you test run the report against a set of records and view the report on-screen.

![embedded-image-vntdmmla.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-vntdmmla.png)**Edit button**. Edit the selected report template.

![embedded-image-aaos1qmq.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-aaos1qmq.png)**Create**. Create a new custom report template using Smarty syntax. (Note that you can also create a new report from an existing one by duplicating it. This can be achieved using the “Save as” button at the bottom of the Edit report pane)

![embedded-image-j27y2iqq.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-j27y2iqq.png)**Delete**. Deletes the current report template

![embedded-image-tj243kqj.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-tj243kqj.png)**Import**. Import a template exported from another database (as a .gpl file). The .gpl file format is a special file format that allows templates to be interpreted by multiple databases, even if their structure differs.

![embedded-image-4luqqnpm.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-4luqqnpm.png)**Export**. Export a template as a .gpl file (this can then be imported to another database). Export converts field IDs to concept IDs.

![embedded-image-balgsosq.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-balgsosq.png)**Publish**. If you wish to embed the report in another website (e.g. your Wordpress site), then click the globe icon to receive some html code that you can copy-and-paste directly into the relevant page, or a URL link with your data feed.

![embedded-image-0yfnyich.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-0yfnyich.png)**Print**. Print the report output or save as pdf.

![embedded-image-66fthcqz.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-66fthcqz.png)**Refresh**. Use this to refresh the data used by the report template, if your database has been updated.

#### Create a custom report template

Tip : Before creating a new report template or editing an existing one, ensure you have run a search in order to have a data subset to test your template.

Go to Report View and from the Report toolbar, click New.

![embedded-image-v4gyy8hn.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-v4gyy8hn.png)

The screen that opens is divided in three panels.

The Actions Pane on the right lets you quickly enter some basic actions (conditional functions, variable and loops) based on the fields and terms available for your record query. (See Actions Pane below \[@link\]).

The middle pane is the Editor pane, where you can enter code manually, using HTML and the Smarty syntax. Upon creation it contains some a basic example template by default, which you can then use as a starting point or remove and start from scratch. The basic template consists of:

- Some guidance as comments, enclosed in {\* \*}. Remove or add comments as you wish.
- A records loop which should enclose everything you want reproduced for each record.
- Some example fields within the loop (to create a simple report wich lists the record ID and the record title).

The left pane is the Preview pane. When you click the “test” button, it will run your code on the data subset currently selected and display the output of your custom template on the white space below. Note that nothing gets saved when you click “test”.

When complete, click Save to save your report structure. If it is the first time you save this template, you will be prompted to enter a name for your template. It will now be made available for selection when you choose to print or publish the report. Click Close Editor when finished.

#### ***Actions Pane***

The Actions Pane provides a records/fields tree of your database structure that assists you in creating a simple template (with loops, fields &amp; functions) to produce neatly formatted lists in text (e.g. CSV, and HTML formats, including images, video and so forth).

More complex formats can use all the power of the Smarty template language, including PHP functions (standard or user-defined) directly in the template.

To use the Actions pane, insert the cursor in the code where you wish to insert the syntax. Press Enter to add extra lines if required. Select the appropriate record type. For each code string, first position the cursor in the appropriate location in the report, then select the appropriate function from the actions pane. In the following example, the user has created an If statement wrapper, and can now enter variable code between the If statements as required

Note. Untyped pointers do not appear on this list; instead a dialog will assist you.

To enter field variable details, for an IF Statement or Repeat Loop, click on the insert option for the relevant field. This displays the Insert dialog:

Note. You can output the leaf term alone or the leaf term with its hierarchy (where terms are hierarchical).

These have the following elements which you can insert into the code:

<table id="bkmrk-insert-fields-valuei"><colgroup><col style="width: 126px;"></col><col></col></colgroup><tbody><tr><th>Insert Fields Value

</th><th>Inserts the value of the selected field. From the dropdown you can specify how the field information is displayed. Field Only. Normally use Field Only. This inserts the field specification to render the content of the field as-is. Field + Function Wrapper. This inserts the field with the wrap function, which is useful for special types such as URLs, images and videos, as it inserts required html code, for example: {wrap var=$r.recURL dt="url"} inserts a hyperlink. {wrap var=$r.thumbnail\_image\_originalvalue dt="file" width="300" height="auto"} inserts an image.

</th></tr><tr><td>Test Value (IF)

</td><td>Click this button to insert a test value for the loop.

</td></tr></tbody></table>

#### ***Preview Results***

To preview the results of your template, click TEST. The report output is shown in the bottom Pane (only a subset of the data is shown, for efficiency).

The two dropdowns let you set the the scope of the query (for the test only, not the published report), and to troubleshoot the code (show warnings, show errors etc.):

The test results are updated immediately.

#### ***Edit, Copy &amp; Delete Template***

To edit a template, go to Report View, select a report from the Select Template dropdown. Click Edit . The Template screen Displays. Edit the template as required (see above). When complete, click Save, or Save as to create (copy) a new report from an existing report. Click Close Editor when finished.

To delete a template, select a report from the Select Template dropdown and click Delete .

Warning. If you delete the report template at the next step you cannot retrieve the report again.

Click OK at the prompt to delete the report.

### Report Basics

To get started with a custom report, you need to understand the basics of html and the Smarty syntax.

https://heurist.huma-num.fr/heurist/

### Topics to be covered

@todo: these links go to the old pages

[Editing a Report Template](https://heuristref.net/h6-alpha/Heurist_Help_System/view/519): How to create a basic report to show data about your records in a customised format

[Publish Report](https://heuristref.net/h6-alpha/Heurist_Help_System/view/582): How to embed a report in an external website, or how to schedule large/complex reports to be regenerated and cached in the background

[Advanced Usage](https://heuristref.net/h6-alpha/Heurist_Help_System/view/737): How to use all the features of the Smarty templating language and Heurist's report editor

[Custom Reports Cookbook](https://heuristref.net/h6-alpha/Heurist_Help_System/view/784): Some recipes for commonly-requested features of custom reports, e.g. linking reports together or displaying records in an interactive table. The cookbook also has some tips for making the code of your reports more readable and easier to maintain

The basics are explained below. For more detail, see the help pages on [Editing a Report Template](https://heuristref.net/h6-alpha/?db=Heurist_Help_System&website&pageid=519), [Report Publishing Options](https://heuristref.net/h6-alpha/?db=Heurist_Help_System&website&pageid=582) and [Advanced Usage](https://heuristref.net/h6-alpha/?db=Heurist_Help_System&website&pageid=737).

### Publish Report

The publish option allows you to embed a custom report in an external website (e.g. a Wordpress blog). It also allows you to schedule reports to be regenerated periodically and then cached. Scheduling reports is a good option when you are generating large or complex reports, e.g. tabular displays of lots of data, or reports that perform complex computation or statistical analysis.

You can access the 'Publish Report' dialog by clicking the globe icon in the Report View:

![embedded-image-k9qmzsna.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-k9qmzsna.png)

In this view, you will see some code that you can copy-and-paste into your website to make the report appear. This code will generate the report with whatever records you are currently viewing in the Explore Menu. You should using a filter to ensure that the correct records are selected for the published version of the report. For example, if you would like the report to show every 'Film' in your database, then you should filter the database just to show the 'Films' before opening the 'Publish Report' dialog.

![embedded-image-lrdtijzw.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-lrdtijzw.png)

If you find that the 'embed' code does not work in your website, you can try the 'javascript wrap' option. You can test out the generated report by clicking 'open in new window'.

**Scheduled Reports**

Use the **Set up up publishing schedule** button to periodically regenerate the report according to a defined schedule. This is a good option for complex reports that are slow to generate. By generating the report in advance, you will provide a better experience for visitors to your site: a cached version of the report will be waiting on Heurist's servers to be downloaded instantly by the visitor. The drawback of this approach is that visitors may not see the most up-to-date information. They will instead see a snapshot of the database at the time the report was generated.

When you click on Set up publishing schedule, you will see a list of scheduled reports. This list will of course be empty if you have set up a publishing schedule before.

![embedded-image-dxvdynyd.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-dxvdynyd.png)

Click the icon in the 'edit' column to change the settings of the publication schedule, the icon in the 'exec' column to regenerate the report, or the icons in the 'html' or 'js' columns to obtain a copy of the code to embed into your external website. You can delete the publishing schedule by clicking the icon in the 'Del' column.

**NB:** This screen only edits or deletes *publishing schedules*. If you want to edit or the delete the actual *report*, then you need to go back to the 'Report View' and click the relevant icon.

When you click the edit icon, or the 'Add New Report Schedule' button, then the 'Edit report schedule' dialog will appear. If you are creating a new publication schedule, then the 'query' and 'template' fields will automatically be filled in for you. You will simply need to provide a title for the publication schedule, which is purely for your reference.

![embedded-image-zxfznjda.png](https://docs.heuristref.net/uploads/images/gallery/2026-07/embedded-image-zxfznjda.png)

<table id="bkmrk-idthis-identifies-th"><colgroup><col style="width: 117px;"></col><col></col></colgroup><tbody><tr><th>ID

</th><th>This identifies the published report and will be generated when the report is published.

</th></tr><tr><td>Title

</td><td>The title of the generated report.

</td></tr><tr><td>Type

</td><td>Select the type of report (i.e. the report syntax used). Note. Currently only Smarty reports are supported. See

[Create Report Template](https://heuristref.net/h6-alpha/viewers/smarty/CreateReportTemplate.html)

.

</td></tr><tr><td>File Path

</td><td>The file path where the report is generated to. Leave blank to use the default path which is: datbasename/generated-reports

</td></tr><tr><td>File Name

</td><td>The base name of the report files. This will be completed with file types.

</td></tr><tr><td>Query

</td><td>The Heurist query you used as a base for the report. This is required since the reports are generated dynamically, using the current set of records.

</td></tr><tr><td>Template

</td><td>The name of the template used to generate this report (defaults to current template).

</td></tr><tr><td>Interval

</td><td>To schedule a report to be run regularly, specify the interval in minutes between regenerations of the report output. The default is zero (only run on demand). Leave blank for no schedule.

</td></tr></tbody></table>

When complete, click Save. Your report schedule will be added to the list of scheduled reports.

### Editing a Report Template

You can create custom reports templates using the Smarty Report Engine. These templates then become available in the Report toolbar to be run against any result-set.

About Smarty

Note. Smarty is open source software, developed by the developers of the PHP programming language. Heurist uses the latest release of its software. This version is optimized for web servers that use PHP5. Any templates you create via the Report View adhere to the same syntax and structure of Smarty templates; all standard Smarty template plugins and modifiers can be used, and your templates are parsed, cached, and displayed by the latest release of Smarty.

Smarty works by allowing you to incorporate various variables and plugins into the HTML syntax of your reports. This gives you complete control over what is displayed to the end user. Smarty files are basic HTML files that can be edited in any text editor; you do not need to install anything extra to use Smarty. You therefore have complete control over the HTML displayed to the end user. You can link Smarty files to JavaScript, CSS stylesheets, and other files.

Note. Reports work with record type and fields codes rather than names; this prevents formats being broken if field names are edited.

You can get started developing Smarty-based templates with a modicum of Smarty knowledge. As you learn more about Smarty you can develop more sophisticated templates. For example, the following snippet of code is used to display a list of the five latest news headlines on a news site:

```
<ul>
{content type="headlines" var="headline" limit="5" sort="date" sort_dir="desc"}
<li>
<a href="{$headline.link}">{$headline.headline}</a> ({$headline.date|date_format: "%m %d, %Y"})
</li>
{/content}
</ul>
```

(See the Smarty Syntax section (next) for an overview of the smarty syntax, including worked examples. For complete Smarty Documentation go to the [Smarty Site](http://www.smarty.net/) itself.)

###   