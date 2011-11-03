





{foreach $results as $r}
	{$r.recTitle}<br/>
{if ($r.recTypeName=="Image element")}
>>>{$r._origimages['URL']}<<<
  image file:<img src="{$r.images}" width="300"/>
<br/>

{out2 lbl="Image fullsize" var=$r.images_originalvalue dt="file"}<br/>

{/if}
{if ($r.recTypeName=="TestURIinclude")}


{out2 lbl="SecondURLinclude" var=$r.secondurlinclude_originalvalue dt="urlinclude"}<br/>


{/if}




{/foreach}
