{* heurist:refresh-on-relationship-change *}
{assign var=latestLocation value=$heurist->latestRelatedRecord($r,114,"9878,9879,13113",10)}
{if $latestLocation}{$latestLocation}{elseif isset($r.f1177)}{$r.f1177}{/if}
