{foreach $results as $r}

{if ($r.recTypeName=="Internet bookmark")}
   {$r.title_name}<br/>
   {$r.short_summary}<br/>
   {$r.date}<br/>
   {$r.thumbnail}<br/>

{elseif $r.recTypeName=="Book"}
   </i><br>
   {foreach $r.Author_Editors as $Author_Editor}
      {$Author_Editor.title_name|upper},
      {$Author_Editor.given_names},
      {/foreach}
   {$r.year}
   <i>{$r.title_name}</i>.
   {$r.Publication_Series.Publisher.title_name}:
   {$r.Publication_Series.Publisher.place_published}<br/> xxxx

{elseif $r.recTypeName=="Journal Article"}
   {foreach $r.Author_Editors as $Author_Editor}
      {$Author_Editor.title_name},
      {$Author_Editor.given_names},
      {/foreach}

 <i>{$r.Journal_volume.recTitle}</i>

Does not come out: {$Journal_volume.Journal.Journal_Name}<br/>

 {$r.Journal_volume.volume}

(
{$r.Journal_volume.Part/Issue}
)
: {$r.start_page}-{$r.end_page}


  <br/>

{else}

{$r.recTypeName|UPPER} is not supported: {$r.recTitle}<br/>

{/if}

<hr/>
{/foreach}
