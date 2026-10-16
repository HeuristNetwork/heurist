<html>  {* See instructions at end. *}

{* ------------------------------------------------------------------------- *}
{* This secion for any title or headings you want at the start of the report *}


<h2>Title for report</h2> {* replace with  suitable heading or title*}



{* DO NOT REMOVE ------------------------------ *}
{* Start records loop          *}
   {foreach $results as $r}
   {$r = $heurist->getRecord($r)}
{*----------------------------------------------*}

{* Place fields you want to output for each record 
   below (between the start and end loop instructions) *}

     

   {* insert content in this section *}  
     
     
     

   <br> {* line break between each record *}

   
{* DO NOT REMOVE --------------------------------- *}
{* End records loop *}
        {/foreach}
{*-------------------------------------------------*}


{* -------------------------------------------------------------------------- *}
{* This section for any title or headings you want at the end of the report *}


<hr><h2>End of report</h2> {* Text here appears at end of report *} 


</html> {* END OF THE OUTPUT INSTRUCTIONS *}


{* ------- DOCUMENTATION -------------------*}

{* This is a very basic outline for a custom report template. 
   Text enclosed in matching braces + asterisk, is a comment. 
   Plentiful comments will help with ongoing maintenance of your templates.

   We strongly advise visiting the Help link (top left) - it will show you how to
   use this function, as well as access all its sophisticated capabilities. *}

 {* Start and end the report with <html> and </html>. Omit for plain text output.
   Enter html for web pages or simple text for plain text formats. 
   Use tree on the left to insert fields.
   Use the dropdown above it for commonly used patterns. 
   Use <!-- --> for output of html comments.

   Put the data you want output for each record beteeen the instructions 
   for start and end record loop.
 *}  