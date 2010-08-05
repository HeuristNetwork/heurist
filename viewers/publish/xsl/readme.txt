How to add new stylesheet and make it appear "Choose output style" drop down list (Publishing Wizard)
_____________________________________________________________________________________________________


1.Include the following bit of code in your stylesheet:

<!-- begin including code -->
<xsl:comment>
<!-- name (desc.) that will appear in dropdown list -->
[name]Name of style[/name]
<!-- match the name of the stylesheet (no extention)-->
[output]filename[/output]
</xsl:comment>
<!-- end including code -->

For example, we have a stylesheet that renders text in Harvard Output Style, and the file is called newharvard.xsl. We will use the following bit of text:
[name]Harvard Output Style[/name]
[output]newharvard[/output]

Name of the stylesheet will appear in the dropdown list, filename will be referenced upon its selection.

2. Place file in "stylesheets" directory of heurist folder.


________________________
added by MS 10/09/2007