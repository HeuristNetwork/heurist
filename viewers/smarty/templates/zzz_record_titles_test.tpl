Information: This is a simple formkat to lsit out constructed record titles one to a line<p>

Name: {$name|capitalize}<br>
Addr: {$address|escape}<br>
Date: {$smarty.now|date_format:"%Y-%m-%d"}<br>

{foreach $records as $record}
        {$record.recTitle}<br/>
{/foreach}
