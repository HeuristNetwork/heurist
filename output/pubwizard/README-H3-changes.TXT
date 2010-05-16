The styles are being restructured into folders such that the top level
only contains thsoe styles which appear in the menu or are called directly
- in fact the lsit should probably be based on those styles in that folder
rather than the internal namign convention, and display the file name
where no internal name is specified. Therre is then a reftypes folder for
individual reftype renderers and there may be others.

----

These styles are ONLY used by PubWizard and render old-style XML not
new-style HML

Top level files appearing in pulldown are specified by [Name] in the file

Other top level fiels may be called directly by external web sites and we
should not therefore remove them.
As far as we know these are:

Centernet: wiki.xsl, wiki_detail.xsl, wiki-simple.xsl

USyd Archaeology: reftype_render_js.xsl, random-image.xsl,

Wangka Maya: presumably wangkamaya.xsl

EARTH: harvard.xsl

<ask Steven about any others>

------

Main Cocoon sitemap: calls hml.php depth 1 limit 1000,
uses heurist_legacy_xml.xsl, random_image-js.xsl, javascript.xsl

The rest are jsut stylesheets passed in as a second parameter
ie heurist/id/style.xsl OR style.xsl-javascript OR style-javascript OR
style

If only the ID is passed, plain XML is generated from the HML

javascript.xsl is used when &js is specified (wich gets added as -js to
the exsiting xsl)
to transform the output into JS to be rendered in the web page.


