{capture name='_smarty_debug' assign=debug_output}
<style type="text/css">
{literal}
 .odd {
    background-color: #eeeeee;
}

.even {
    background-color: #fafafa;
}

.exectime {
    font-size: 0.8em;
    font-style: italic;
}

#table_assigned_vars th {
    color: blue;
}

#table_config_vars th {
    color: maroon;
}
{/literal}
</style>

<h1>Smarty Debug Console  -  {if isset($template_name)}{$template_name|debug_print_var}{else}Total Time {$execution_time|string_format:"%.5f"}{/if}</h1>

{if !empty($template_data)}
<h2>included templates &amp; config files (load time in seconds)</h2>

<div>
{foreach $template_data as $template}
  <font color=brown>{$template.name}</font>
  <span class="exectime">
   (compile {$template['compile_time']|string_format:"%.5f"}) (render {$template['render_time']|string_format:"%.5f"}) (cache {$template['cache_time']|string_format:"%.5f"})
  </span>
  <br>
{/foreach}
</div>
{/if}

<h2>assigned template variables</h2>

<table id="table_assigned_vars">
    {foreach $assigned_vars as $vars}
       <tr class="{if $vars@iteration % 2 eq 0}odd{else}even{/if}">
       <th>${$vars@key|escape:'html'}</th>
       <td>{$vars|debug_print_var}</td></tr>
    {/foreach}
</table>

<h2>assigned config file variables (outer template scope)</h2>

<table id="table_config_vars">
    {foreach $config_vars as $vars}
       <tr class="{if $vars@iteration % 2 eq 0}odd{else}even{/if}">
       <th>{$vars@key|escape:'html'}</th>
       <td>{$vars|debug_print_var}</td></tr>
    {/foreach}

</table>
{/capture}
<br/>
{$debug_output}
