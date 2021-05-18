{* This is a simple Smarty report template which you can edit into something more sophisticated.
   It should give basic output for any database, as it uses the standard record types which are part of all databases.
   Enter html for web pages or other text format. Use tree on right to insert fields, loops and tests.
   Use this format to include comments in your file, use <!-- --> for output of html comments.
   Smarty help describes many functions you can apply, loop counting/summing, custom functions etc.*}

<h2>Basic report as an example</h2> {* Text here appears at start of report *}

<i><pre>
     Please use this as an example from which to create your own reports, by copying this template.<br/>
     To do this, choose <b>Edit</b> (first button at top right), then click the <b>Save As</b> button.<br/>
     As you edit, hit the <b>Test</b> button repeatedly to see how your changes are working.<br/>
     Ctrl-Z will undo most recent change - can be repeated to backtrack through changes.<br/>
</pre></i>

<hr>

{*------------------------------------------------------------*}
{foreach $results as $r} {* Start records loop, do not remove *}
{$r = $heurist->getRecord($r)}
{*------------------------------------------------------------*}

  {* put the data you want output for each record here - insert the *}
  {* fields using the tree of record types and fields on the right *}

  {* Examples - note use of:

       IF to check record type and presence of fields

       FOR to loop through values for a repeating field

       <br/> for line break, <b> for bold, <i> for italics

       WRAP function to get images, maps and hyperlinks

  *}


     {* --------------------------------------------------*}


     {if ($r.recTypeID=="5")}{* Digital media item *}

       Media: <b>{$r.recID}  {* the unique record ID *}

       {$r.f1}<br/> <br/> </b> {*Title*}

       {wrap var=$r.f38_originalvalue dt="file" width="300" height="auto"}<br/>

       {if ($r.f3)} {* Test if summary present *}
         {$r.f3}{*Summary*}<br/>
       {/if}

     {else}


     {* --------------------------------------------------*}


     {if ($r.recTypeID=="4")}{* Organisation *}

       Organisation: <b>{$r.recID} {* the unique record ID *}

       {$r.f1} </b>

       {foreach $r.f22s as $f22}{* Organisation Type - repeating value field*}
         {$f22.term}
       {/foreach}{* Organisation Type *}

       {if ($r.f2)}
         {$r.f2}{*Name - Short*}
       {/if}{* Name - Short *}

       {wrap var=$r.recURL dt="url"}<br/>

       {if ($r.f3)}
         <br/>{$r.f3}{*Summary*}
       {/if}


       {if ($r.f39)}
         <br/>
       	 {wrap var=$r.f39_originalvalue dt="file" width="150" height="auto"}<br/> {* Thumbnail Image *}
       {/if}

     {else}{* Organisation *}


     {* --------------------------------------------------*}


     {if ($r.recTypeID=="10")}{* Person - detailed *}

       Person: <b>{$r.recID}  {* the unique record ID *}

       {$r.f18}{*Given Name(s)*}
       {$r.f1}{*Family name*}
       </b>

       {$r.f10}{*Date Of Birth*}

       {if ($r.f11)}
          - {$r.f11}. {*Date Of Death*}
       {/if}

       {if ($r.f26)}
       		Birth country: {$r.f26.term}{*Birth Place (country) >> Term*}
       {/if}

       {if ($r.f3)}
         <br/><br/>{$r.f3}{*Summary*}
       {/if}

			 <br/><br/>

       {wrap var=$r.f39_originalvalue dt="file" width="300" height="auto"}<br/> {*Image*}

       {if ($r.f28)} {* Test if map data present, put on new line *}
         <br/>Birth place map below:<br/>{wrap var=$r.f28_originalvalue dt="geo"} {*Map*}
       {/if}

     {else}{* Person - detailed *}


     {* --------------------------------------------------*}


     {if ($r.recTypeID=="11")}{* Person - minimal *}

     Author: <b>{$r.recID}  {* the unique record ID *}

     <i>
     {$r.f1}, {*Family name*}
     {$r.f18}{*Given Name(s)*}
     </i>
     </b> <br/>

     {else}{* Person - minimal *}


     {* --------------------------------------------------*}


     {if ($r.recTypeID=="2")}{* Web site / page *}

       URL: <b>{$r.recID}  {* the unique record ID *}

       {$r.f1}{*Note Title*} </b>

       <br/><br/>{wrap var=$r.recURL dt="url"}

       {if ($r.f3)}
         <br/><br/>{$r.f3}{*Summary*}
       {/if}

     {else}{* Web site / page *}


     {* --------------------------------------------------*}


     {if ($r.recTypeID=="12")}{* Place *}

       Place: <b>{$r.recID}  {* the unique record ID *}

       {$r.f1}{*Place name*}
       [{$r.f133.term}]{*Place Type >> Term*}
       {$r.f26.term}{*Country >> Term*}
       </b><br/>

       <br/>{wrap var=$r.f28_originalvalue dt="geo"} {*Map*}

     {else}{* Place *}


     {* --------------------------------------------------*}


     {if ($r.recTypeID=="3")}{* Notes *}

       Note: <b>{$r.recID}</b>  {* the unique record ID *}

       <b>{$r.f1}</b><br/> {*Title*}

       <br/>{$r.f3}{*Short Note / Summary*}

     {else}{* Notes *}


     {* --------------------------------------------------*}


       {$r.recTypeName}: <b>{$r.recID}</b>  {* the unique record ID *}

       <b>{$r.f1}</b><br/> {*Title*}

			 <br/> Unsupported record type: Please edit the template and copy and modify an existing format to add support for this record type, if required.



{* Close all the IF statements *}

  {/if}
  {/if}
  {/if}
  {/if}
  {/if}
  {/if}
  {/if}



<br/> <hr> <br/> {* line breaks / horizontal rule between each record *}


{*------------------------------------------------------------*}
{/foreach} {* end records loop, do not remove *}
{*------------------------------------------------------------*}


<h2>End of report</h2> {* Text here appears at end of report *}
