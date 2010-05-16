<?php

require_once('HeuristImport.php');
require_once('HeuristReferImport.php');
require_once('HeuristEndnoteReferImport.php');

require_once('../php/modules/db.php');
require_once('../php/modules/cred.php');

jump_sessions();

mysql_connection_db_select(DATABASE);


if (! $_REQUEST['import_id']  ||  ! array_key_exists('id', $_REQUEST)) return;	// funny buggers
$session_data = &$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['heurist-import-' . $_REQUEST['import_id']];

if (! $session_data['ambiguities'][$_REQUEST['id']]) {	// feels like funny buggers, but just in case ...
	print '<html><body>Session data has expired</body></html>';
	return;
}

$entry = &$session_data['ambiguities'][$_REQUEST['id']];


// Might be printing data for this entry, or for its ambiguous ancestor
if ($entry->getPotentialMatches())
	$ambig_entry = &$entry;
else
	$ambig_entry = &$entry->_ancestor;

load_bib_requirement_names();
load_heurist_reftypes();
$type_names = $bib_requirement_names[$entry->getReferenceType()];

?>
<html>

 <head>
  <title>HEURIST - View draft reference #<?= $entry->getBiblioID() ?></title>

  <link rel="icon" href="../../favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="../../favicon.ico" type="image/x-icon">

  <link rel="stylesheet" type="text/css" href="../legacy/newshsseri.css">
 </head>

 <body>

  <div class="headline" style="width: 100%; padding: 3px 0px; background-color: #C00000;">&nbsp;Draft reference info</div>

  <br>

  <table>
   <tr>
    <td style="font-weight: bold; text-align: right; vertical-align: top;">Title&nbsp;&nbsp;&nbsp;</td>
    <td><b><?= htmlspecialchars($ambig_entry->getTitle()) ?></b>
        - <?= htmlspecialchars($heurist_reftypes[$ambig_entry->getReferenceType()]['rt_name']) ?></td>
   </tr>

   <tr>
    <td style="font-weight: bold; text-align: right; vertical-align: top;">Details&nbsp;&nbsp;&nbsp;</td>
    <td>
     <table border="0" cellspacing="0">
<?php
	$open_notes = '';
	$fields = &$ambig_entry->getFields();
	$field_values = array();
	foreach (array_keys($fields) as $i) {
		$field = &$fields[$i];
		if ($field->getType()) {
			array_push($field_values, array($field->getType(), $field->getRawValue()));
		} else {
			if ($open_notes) $open_notes .= "\n";
			$open_notes .= $field->getRawValue();
		}
	}

	function acmp($a, $b) { return ($a[0] - $b[0]); }
	usort($field_values, 'acmp');
	foreach ($field_values as $f) {
?>
      <tr>
       <td style="vertical-align: top;"><i><?= htmlspecialchars($type_names[$f[0]]) ?>&nbsp;&nbsp;</i></td>
       <td><?= htmlspecialchars($f[1]) ?></td>
      </tr>

<?php
	}
?>
     </table>
    </td>
   </tr>
<?php
	if ($open_notes) {
?>

   <tr>
    <td style="font-weight: bold; text-align: right; vertical-align: top;">Notes&nbsp;&nbsp;&nbsp;</td>
    <td><?= nl2br(htmlspecialchars($open_notes)) ?></td>
   </tr>
<?php
	}
?>

  </table>

 </body>
</html>
