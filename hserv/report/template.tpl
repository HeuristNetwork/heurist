<html>
{* This is a very simple Smarty report template which you can edit into something more sophisticated.
   Enter html for web pages or simple text for plain text formats. Use tree on right to insert fields
   and templates for commonly used patterns. Use <!-- --> for output of html comments.

{* Text like this, enclosed in matching braces + asterisk, is a comment. We suggest you start by removing
 our comments and adding your own - plentiful comments will help with ongoing maintenance of your templates.*}


<h2>Title for report</h2> {* Text here appears at start of report *}
<hr>

{*------------------------------------------------------------*}
{foreach $results as $r} {* Start records loop, do not remove *}
{$r = $heurist->getRecord($r)}
{*------------------------------------------------------------*}


  {* We STRONGLY advise visiting the Help link above - it will show you how to *}
  {* use this function, as well as access all its sophisticated capabilities. *}


  {* Put the data you want output for each record here - insert the *}
  {* fields using the tree of record types and fields on the right. *}
  {* Use the pulldown of templates to insert commonly used patterns.*}

  {* Examples - delete and replace with the fields you want to output: *}
     {$r.recID}  {* the unique record ID *}
     {$r.f1}     {* the name / title field - may or may not be present *}  


<br> {* line break between each record *}

{*------------------------------------------------------------*}
{/foreach} {* end records loop, do not remove *}
{*------------------------------------------------------------*}

<hr>
<h2>End of report</h2> {* Text here appears at end of report *} 
</html>
