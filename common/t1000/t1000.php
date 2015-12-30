<?php

/*
* Copyright (C) 2005-2015 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* T1000 web database templating system
* Primary author Tom Murtagh approx. 2003 or 2004, used pretty much unchanged ever since
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


/* configuration stuff */
$MAINTABLES = array();
$PKEY = array();

$OWNER_FIELD = array();
$MODDATE_FIELD = array();
$INIDATE_FIELD = array();
$DELETED_FIELD = array();

$LOOKUPS = array();
$FILES = array();
$JOINS = array();
$LINK_KEYS = array();
$SAME_JOINS = array();
$SEARCHES = array();

$MYSQL_ERRORS = array();


// Uses include_once rather than require_once, as it does its own error checking

if (! include_once(dirname(__FILE__).'/../php/dbMySqlWrappers.php')) {
    print("dbMySqlWrappers.php not found: check PHP include path\n");
    exit();
}
$ht_stdefs = '.ht_stdefs';
if (defined('T1000_DEFS')) $ht_stdefs = constant('T1000_DEFS');
if (! include_once($ht_stdefs)) {
    // check likely causes of .ht_stdefs failure

    if (! file_exists($ht_stdefs))
        print("$ht_stdefs not found\n");
    else if (! is_readable($ht_stdefs))
        print("$ht_stdefs cannot be read by PHP - check file permissions\n");
        else
            print("$ht_stdefs is not a valid PHP script - run 'php $ht_stdefs' to find syntax errors\n");
    exit();
}

if (! defined('RESULTS_PER_PAGE')) define('RESULTS_PER_PAGE', 20);

if (! defined('MIMETYPE_TABLE')) define('MIMETYPE_TABLE', 'defFileExtToMimetype');
if (! defined('MIMETYPE_EXTENSION_FIELD')) define('MIMETYPE_EXTENSION_FIELD', 'fxm_Extension');
if (! defined('MIMETYPE_TYPE_FIELD')) define('MIMETYPE_TYPE_FIELD', 'fxm_MimeType');


if (! function_exists('get_user_id')) {
    // User is not using login operation
    function get_user_id() { return 0; }
}
if (! function_exists('get_user_access')) {
    // User is not using login operation
    function get_user_access() { return NULL; }
}


/* Convenient mappings from string literals to constants defined by the user */
if (@$MAINTABLES['RESOURCE'] and $MAINTABLES['RESOURCE_VERSION'])
    $LOOKUPS['RESOURCE'] = array_merge(
        array($MAINTABLES['RESOURCE_VERSION'] => ($PKEY['RESOURCE'].'='.RESOURCE_VERSION_RESOURCEKEY)),
        $LOOKUPS['RESOURCE'], $LOOKUPS['RESOURCE_VERSION']);

$KEY_TO_TABLE_TYPE = array();
$TABLE_TYPE_TO_KEY = array();
foreach ($MAINTABLES as $type => $table) {
    if (@$PKEY[$type]) {
        $KEY_TO_TABLE_TYPE[$PKEY[$type]] = $type;
        $TABLE_TYPE_TO_KEY[$type] = $PKEY[$type];
    }
}
if (@$MAINTABLES['RESOURCE_VERSION']) {
    $KEY_TO_TABLE_TYPE[RESOURCE_VERSION_RESOURCEKEY] = 'RESOURCE_VERSION';
    $KEY_TO_TABLE_TYPE[RESOURCE_VERSION_VERSIONKEY] = 'RESOURCE_VERSION';
}


/* variable stuff */
function add_vars($type, $name, &$src_vars, &$dst_vars, $suppress_rv=false) {
    // $type	type (ENTRY, RESOURCE, ENTRY_RESOURCE_LINK etc) of table
    // $name	name specified for this scope
    // $src_vars	environment variables already available from outer scopes
    // $dst_vars	environment variables to augment

    // augments the $dst_vars to reflect the new scope
    //print "<!-- "; print_r($src_vars); print " -->\n";

    global $MAINTABLES, $LINK_KEYS, $LOOKUPS, $TABLE_TYPE_TO_KEY, $DELETED_FIELD, $JOINS;
    global $not_found;


    // Fetch all the columns from the main table ...
    $stmt = 'select '.$MAINTABLES[$type].'.*';

    if (!$suppress_rv  and  ($type == 'RESOURCE'  or  $type == 'RESOURCE_VERSION')) {
        $stmt .= ', if(rv_size,if(rv_size<1024,"1k",if(rv_size<1024000,concat(round(rv_size/10.24)/100,"k"),
        concat(round(rv_size/10240)/100,"M"))),
        if(length(rv_comments)<1024,"1k",concat(round(length(rv_comments)/10.24)/100,"k"))) as rv_nicesize';
    }

    // ... plus the columns from any defined lookup tables (includes the RESOURCE_VERSION joins) ...
    if (@$LOOKUPS[$type]) foreach ($LOOKUPS[$type] as $table => $join_clause)
        if (!$suppress_rv  or  $table != $MAINTABLES['RESOURCE_VERSION']) $stmt .= ", $table.*";
        $stmt .= ' from '.$MAINTABLES[$type];
    if (@$LOOKUPS[$type]) foreach ($LOOKUPS[$type] as $table => $join_clause)
        $stmt .= " left join $table on $join_clause";

    if ($type != 'RESOURCE_VERSION') {
        if (! @$TABLE_TYPE_TO_KEY[$type])
            fatal("Don't know how to handle type $type in scope $name");
        if (! array_key_exists($type.'-ID', $src_vars))
            fatal("Scope enclosing $name does not define a unique $type");
        $stmt .= ' where ' . $TABLE_TYPE_TO_KEY[$type] . '="' . mysql_real_escape_string($src_vars[$type.'-ID']) . '"';

        if (@$DELETED_FIELD[$type])
            $stmt .= ' and (!'.$DELETED_FIELD[$type].' or '.$DELETED_FIELD[$type].' is null) ';

    } else {
        if (! array_key_exists('RESOURCE-ID', $src_vars))
            fatal("Scope enclosing $name does not define a unique $type");
        if (array_key_exists('VERSION-ID', $src_vars)) {
            $stmt .= ' where ' . RESOURCE_VERSION_RESOURCEKEY.'="'.mysql_real_escape_string($src_vars['RESOURCE-ID']) . '" and '
            . RESOURCE_VERSION_VERSIONKEY.' ="'.mysql_real_escape_string($src_vars['VERSION-ID']) . '"';
        } else {	// fudge: if no version specified, grab the latest
            $stmt .= ' where ' . RESOURCE_VERSION_RESOURCEKEY.'="'.mysql_real_escape_string($src_vars['RESOURCE-ID']) . '" '
            . 'order by ' . RESOURCE_VERSION_VERSIONKEY . ' desc';
        }
    }

    // Order the returned values to make sure we get the latest version of a resource.
    if (!$suppress_rv  and  $type == 'RESOURCE')
        $stmt .= ' order by ' . RESOURCE_VERSION_VERSIONKEY . ' desc';

    //print "<!-- $stmt -->\n";
    if (defined('T1000_DEBUG')) error_log($stmt);

    // Populate the environment with unqualified <var-name> and fully qualified <scope-name>.<var-name> lookups
    $res = mysql_query($stmt) or fatal(mysql_error());
    if (mysql_num_rows($res) == 0) {
        $not_found = 1;
    } else {

        $field_names = array();
        while ($field = mysql_fetch_field($res))
            array_push($field_names, $field->name);

        $cols = mysql_fetch_row($res);
        foreach ($cols as $value) {
            $key = array_shift($field_names);
            $dst_vars[$key] = $dst_vars["$name.$key"] = $value;
            if (! array_key_exists("-nodups-$name.$key", $dst_vars)) $dst_vars["-nodups-$name.$key"] = $value;
        }

        /* old code: mysql_fetch_assoc can only fetch one column with a given name
        $cols = mysql_fetch_assoc($res);
        foreach ($cols as $key => $value) {
        $dst_vars[$key] = $dst_vars["$name.$key"] = $value;
        if (! array_key_exists("-nodups-$name.$key", $dst_vars)) $dst_vars["-nodups-$name.$key"] = $value;
        }
        */

        if (@$JOINS[$type]) foreach ($JOINS[$type] as $joined_key => $type_key)
            $dst_vars[$joined_key] = $dst_vars[$type_key];
    }
}



function get_iter_statement($type, $vars) {
    // BE CAREFUL editing this function:
    // Evaluate::render() uses get_iter_statement, and replaces the "select X from" with "select FUNCTION from"

    global $MAINTABLES, $LINK_KEYS, $TABLE_TYPE_TO_KEY, $LOOKUPS, $DELETED_FIELD, $JOINS, $SAME_JOINS;


    // DWIM stuff: work out what we're iterating over

    // If it's a link type, then we iterate over whichever keys have been omitted
    $iters = array();
    if (@$LINK_KEYS[$type]  and  ! array_key_exists($type.'-ID', $vars)) {
        // we select * here, but it is sufficient to pull out just the key fields
        $stmt = 'select * from '.$MAINTABLES[$type];
        $stmt .= ' where 1';

        foreach ($LINK_KEYS[$type] as $varname => $field) {
            if (array_key_exists($varname, $vars))
                $stmt .= ' and '.$field.' = "'.mysql_real_escape_string($vars[$varname]).'"';
            else
                $iters[$field] = $varname;
        }
        if (@$DELETED_FIELD[$type])
            $stmt .= ' and (!'.$DELETED_FIELD[$type].' or '.$DELETED_FIELD[$type].' is null) ';

        $iters[$TABLE_TYPE_TO_KEY[$type]] = "$type-ID";

    } else if (@$SAME_JOINS[$type]  and  ! array_key_exists($type.'-ID', $vars)) {
        $stmt = 'select '.$MAINTABLES[$type].'.*';
        foreach ($SAME_JOINS[$type] as $varname => $fields) {
            $stmt .= ', if('.$fields[0].'="'.mysql_real_escape_string($vars[$varname]).'", '
            .$fields[1].', '.$fields[0].') as _samejoin_id';	// only look at two keys
            break;	// only looks at the first join
        }
        $stmt .= ' from '. $MAINTABLES[$type]. ' where ';

        foreach ($SAME_JOINS[$type] as $varname => $fields) {
            $stmt .=    '('.$fields[0].' = "'.mysql_real_escape_string($vars[$varname]).'"'.
            ' or '.$fields[1].' = "'.mysql_real_escape_string($vars[$varname]).'")';
            $iters["_samejoin_id"] = $varname;
            break;
        }

        if (@$DELETED_FIELD[$type])
            $stmt .= ' and (!'.$DELETED_FIELD[$type].' or '.$DELETED_FIELD[$type].' is null) ';

        $iters[$TABLE_TYPE_TO_KEY[$type]] = "$type-ID";

        // especially DWIM for special case of RESOURCE_VERSION: iterate only over the current resource
    } else if ($type == 'RESOURCE_VERSION') {
        $stmt = 'select ' . RESOURCE_VERSION_VERSIONKEY . ' from '.$MAINTABLES['RESOURCE_VERSION'];
        $stmt .= ' where '.RESOURCE_VERSION_RESOURCEKEY.'="'.mysql_real_escape_string($vars['RESOURCE-ID']).'"';
        $iters[RESOURCE_VERSION_VERSIONKEY] = 'VERSION-ID';

    } else {
        // iterate over ALL objects of the specified type,
        // unless we're restricted by fields from the $JOINS array
        if (@$MAINTABLES[$type]) {
            $stmt = 'select '.$TABLE_TYPE_TO_KEY[$type].' from '.$MAINTABLES[$type];

            // if (! $JOINS[$type]  and  array_key_exists($type.'-ID', $vars))
            if (array_key_exists($type.'-ID', $vars)) {
                // primary key already defined for this type of object: just select that object, but warn about it
                error_log("warning: $type-ID already defined in foreach $type scope");
                $stmt .= ' where '.$TABLE_TYPE_TO_KEY[$type].' = "'.mysql_real_escape_string($vars[$type.'-ID']).'"';
            } else {
                $stmt .= ' where 1';
                if (@$JOINS[$type]) foreach ($JOINS[$type] as $typeID => $fieldname) {
                    if (array_key_exists($typeID, $vars))
                        $stmt .= ' and '.$fieldname.' = "'.mysql_real_escape_string($vars[$typeID]).'"';
                }
            }

            if (@$DELETED_FIELD[$type])
                $stmt .= ' and (!'.$DELETED_FIELD[$type].' or '.$DELETED_FIELD[$type].' is null) ';

            $iters[$TABLE_TYPE_TO_KEY[$type]] = "$type-ID";
        } else {
            fatal("Can't iterate over objects of type $type");
        }
    }

    return array($stmt, $iters);
}



function get_lvalues($type) {
    global $MAINTABLES, $TABLE_TYPE_TO_KEY;

    $res = mysql_query('describe '.$MAINTABLES[$type]);

    $fields = array();
    while (($row = mysql_fetch_assoc($res))) {
        // insert a value for each field: true for all but the primary key (which can't be used as an lvalue)
        $field = $row['Field'];
        $fields[$field] = ($field != $TABLE_TYPE_TO_KEY[$type]);
    }

    if ($type == 'RESOURCE') {	// need to augment resource with the stuff from the latest RESOURCE_VERSION
        $rv_fields = get_lvalues('RESOURCE_VERSION');
        foreach ($rv_fields as $field => $dummy)
            $fields[$field] = true;
    }

    return $fields;
}



function get_fields($type) {
    // get an array of all fields available to an object of type $type
    // (this includes the fields in associated lookup tables)

    global $MAINTABLES, $LOOKUPS;

    $res = mysql_query('describe '.$MAINTABLES[$type]);

    $fields = array();
    while (($row = mysql_fetch_assoc($res)))
        $fields[$row['Field']] = true;

    if (@$LOOKUPS[$type]) foreach ($LOOKUPS[$type] as $table => $ignored) {
        $res = mysql_query('describe '.$table);
        while (($row = mysql_fetch_assoc($res)))
            $fields[$row['Field']] = true;
    }

    return $fields;
}



function do_update($updates, $name, $type, $vars) {
    global $MYSQL_ERRORS;

    if ($type == 'RESOURCE')
        return do_update_resource($updates, $name, $vars);

    global $MAINTABLES, $LINK_KEYS, $TABLE_TYPE_TO_KEY, $OWNER_FIELD, $MODDATE_FIELD;

    if (@$updates['-file']) {
        $file = $updates['-file'];
        unset($updates['-file']);
    }


    if (@$TABLE_TYPE_TO_KEY[$type])
        $condition = $TABLE_TYPE_TO_KEY[$type].' = "'.mysql_real_escape_string($vars["$type-ID"]).'"';
    else
        fatal("trying to update unknown object-type $type");

    if (@$OWNER_FIELD[$type]) $updates[$OWNER_FIELD[$type]] = get_user_id();
    if (@$MODDATE_FIELD[$type]) $updates[$MODDATE_FIELD[$type]] = date('Y-m-d H:i:s');

    if ($rval = mysql__update($MAINTABLES[$type], $condition, $updates)) {
        $pkey = mysql_real_escape_string($vars["$type-ID"]);
        if (@$file) do_file_update($file, $type, ($TABLE_TYPE_TO_KEY[$type].'="'.$pkey.'"'), $type . '/' . $pkey);
    } else {
        array_push($MYSQL_ERRORS, mysql_error());
    }

    return $rval;
}



function do_update_resource($updates, $name, $vars) {

    global $OWNER_FIELD, $MODDATE_FIELD, $MAINTABLES, $PKEY;
    global $MYSQL_ERRORS;

    if (@$updates['-file']) {
        $file = $updates['-file'];
        unset($updates['-file']);
    }

    $is_ver_field = get_lvalues('RESOURCE_VERSION');

    $res_fields = array();
    $ver_fields = array();
    // separate out the stuff that goes in the RESOURCE table from the stuff that goes in RESOURCE_VERSION
    foreach ($updates as $field => $val) {
        if (@$is_ver_field[$field])
            $ver_fields[$field] = $val;
        else
            $res_fields[$field] = $val;
    }

    $ver_fields[RESOURCE_VERSION_RESOURCEKEY] = $vars['RESOURCE-ID'];
    if (@$MODDATE_FIELD['RESOURCE_VERSION']) $ver_fields[$MODDATE_FIELD['RESOURCE_VERSION']] = date('Y-m-d H:i:s');
    if (@$OWNER_FIELD['RESOURCE_VERSION']) $ver_fields[$OWNER_FIELD['RESOURCE_VERSION']] = get_user_id();


    $pkey = mysql_real_escape_string($vars['RESOURCE-ID']);

    if ($rval = mysql__update($MAINTABLES['RESOURCE'], $PKEY['RESOURCE'].'="'.$pkey.'"', $res_fields)) {
        $rval = mysql__insert($MAINTABLES['RESOURCE_VERSION'], $ver_fields);
        if ($rval)
            $ver = mysql_insert_id();
        else
            array_push($MYSQL_ERRORS, mysql_error());
    } else {
        array_push($MYSQL_ERRORS, mysql_error());
    }

    if ($file and $rval)
        do_file_update($file, 'RESOURCE_VERSION', (RESOURCE_VERSION_RESOURCEKEY.'="'.$pkey.'" and '
            . RESOURCE_VERSION_VERSIONKEY .'="'.$ver.'"'),
            'RESOURCE_VERSION/' . $pkey.'_'.$ver);

    return $rval;
}



function do_insert($inserts, $name, $type, $vars) {
    global $MYSQL_ERRORS;

    if ($type == 'RESOURCE')
        return do_insert_resource($inserts, $name, $vars);

    global $MAINTABLES, $TABLE_TYPE_TO_KEY, $OWNER_FIELD, $MODDATE_FIELD, $INIDATE_FIELD;

    if (@$inserts['-file']) {
        $file = $inserts['-file'];
        unset($inserts['-file']);
    }

    if (@$LINK_KEYS[$type]) {
        // this is a link type object:
        // check if an isomorphic link exists already, and delete it if it does.
        $query = 'delete from '.$MAINTABLES[$type].' where 1 ';
        foreach ($LINK_KEYS[$type] as $varname => $field)
            $query .= ' and '. $varname .' = "'.mysql_real_escape_string($inserts[$field]).'"';
        mysql_query($query);
    }

    if (@$OWNER_FIELD[$type]) $inserts[$OWNER_FIELD[$type]] = get_user_id();
    if (@$MODDATE_FIELD[$type]) $inserts[$MODDATE_FIELD[$type]] = date('Y-m-d H:i:s');
    if (@$INIDATE_FIELD[$type]) $inserts[$INIDATE_FIELD[$type]] = date('Y-m-d H:i:s');

    if (! mysql__insert($MAINTABLES[$type], $inserts))
        array_push($MYSQL_ERRORS, mysql_error());
    else {
        $pkey = mysql_insert_id();
        if ($file) do_file_update($file, $type, ($TABLE_TYPE_TO_KEY[$type].'="'.$pkey.'"'), $type . '/' . $pkey);
    }

    return $pkey;
}



function do_insert_resource($inserts, $name, $vars) {

    global $OWNER_FIELD, $MODDATE_FIELD, $MAINTABLES, $PKEY;
    global $MYSQL_ERRORS;

    if (@$inserts['-file']) {
        $file = $inserts['-file'];
        unset($inserts['-file']);
    }

    $is_ver_field = get_lvalues('RESOURCE_VERSION');

    $res_fields = array();
    $ver_fields = array();
    // separate out the stuff that goes in the RESOURCE table from the stuff that goes in RESOURCE_VERSION
    foreach ($inserts as $field => $val) {
        if (@$is_ver_field[$field])
            $ver_fields[$field] = $val;
        else
            $res_fields[$field] = $val;
    }
    if (count($res_fields) == 0) {
        // this is necessary: there could be resources that are entirely in their versions
        $res_fields[RESOURCE_KEY] = NULL;
    }
    if (mysql__insert($MAINTABLES['RESOURCE'], $res_fields))
        $pkey = mysql_insert_id();
    else
        array_push($MYSQL_ERRORS, mysql_error());

    $ver_fields[RESOURCE_VERSION_RESOURCEKEY] = $pkey;
    if (@$INIDATE_FIELD['RESOURCE_VERSION']) $ver_fields[$INIDATE_FIELD['RESOURCE_VERSION']] = date('Y-m-d H:i:s');
    if (@$MODDATE_FIELD['RESOURCE_VERSION']) $ver_fields[$MODDATE_FIELD['RESOURCE_VERSION']] = date('Y-m-d H:i:s');
    if (@$OWNER_FIELD['RESOURCE_VERSION']) $ver_fields[$OWNER_FIELD['RESOURCE_VERSION']] = get_user_id();

    if (mysql__insert($MAINTABLES['RESOURCE_VERSION'], $ver_fields))
        $ver = mysql_insert_id();
    else
        array_push($MYSQL_ERRORS, mysql_error());

    if ($file  &&  $pkey  &&  $ver)
        do_file_update($file, 'RESOURCE_VERSION', (RESOURCE_VERSION_RESOURCEKEY.'="'.$pkey.'" and '
            . RESOURCE_VERSION_VERSIONKEY .'="'.$ver.'"'),
            'RESOURCE_VERSION/' . $pkey.'_'.$ver);

    return $pkey;
}



function do_file_update(&$file, $type, $condition, $new_filename) {

    global $FILES, $MAINTABLES;
    global $MYSQL_ERRORS;

    $updates = array();
    if (! @$FILES[$type])
        fatal("$type objects aren't associated with a file upload");
    if (! @$file['filename'])
        fatal("non-file passed through to do_file_update");

    if (@$FILES[$type]['name']) $updates[$FILES[$type]['name']] = $file['filename'];
    if (@$FILES[$type]['size']) $updates[$FILES[$type]['size']] = $file['size'];
    if (@$FILES[$type]['type']) $updates[$FILES[$type]['type']] = $file['filetype'];
    $updates[$FILES[$type]['path']] = $new_filename;

    if (! move_uploaded_file($file['localpath'], HEURIST_FILESTORE_DIR . '/' . $new_filename))
        fatal("desperate failure while handling uploaded file");

    if (! mysql__update($MAINTABLES[$type], $condition, $updates))
        array_push($MYSQL_ERRORS, mysql_error());
}

/* END non-OO stuff */



function fatal($msg) { die($msg); }


class Lexer {

    var $pre;	// text of the input template between the end of the last tag and the beginning of the current tag
    var $tagtext;	// text of the current tag including the enclosing square brackets
    var $post;	// text of the input template from the end of the current tag onwards (i.e. everything still unparsed)

    var $current_tagtype;	// select, update, dropdown etc
    var $current_tagwords;	// array of all the words (i.e. anything separated by whitespace) before the first colon
    var $current_tagparams;	// array of the remaining type-specific parameters:
    //  [0] contains everything between the first and second colons,
    //  [1] contains everything between the second and third colons, ... etc


    function Lexer($template) {
        // construct a Lexer to process the string $template

        if ($template == '') {
            print "<!-- empty template -->";
            error_log("warning: empty template specified ... perhaps file_get_contents is reading the wrong file");
        }

        /* very simple macro pre-processor */
        /* any strings at the beginning of the template:
        [#define IDENTIFIER string]
        will be used to interpolate the rest of the template; IDENTIFIER will be replaced by string.
        string may contain \, [ and ]; these are expressed as \\, \[ and \] respectively
        */
        while (preg_match('/^\s*\\[#define\s+([-a-z_]+)\s+((?:[^\\]\\[\\\\]|[\\\\][[]|[\\\\][]]|[\\\\][\\\\])*)\\]/is', $template, $matches)) {
            $replacement = str_replace(array('\\[', '\\]', '\\\\'), array('[', ']', '\\'), $matches[2]);
            /* remove the matching part of the template */
            $template = substr($template, strlen($matches[0]));
            /* do the substitutions */
            $template = str_replace($matches[1], $replacement, $template);
        }

        $this->pre = NULL;
        $this->tagtext = NULL;
        $this->post = $template;

        $this->current_tagtype = NULL;
        $this->current_tagwords = NULL;
        $this->current_tagparams = NULL;
    }


    function next() {
        // move on to the next tag in the template
        // return true while there is such a tag found
        // 2005/10/17 (a MONDAY MORNING): added alternative syntax  <!--[[blah]]--> == [blah]

        $altpos = strpos($this->post, '<!--[[');
        $pos = strpos($this->post, '[');

        if ($altpos !== FALSE  &&  ($pos === FALSE  ||  $altpos < $pos)) {
            $tag_start_pos = $start_pos = $altpos;	/* alternative syntax */
            $alt_syntax = 1;
        } else {
            $tag_start_pos = $start_pos = $pos;
            $alt_syntax = 0;
        }
        if ($pos === FALSE  &&  $altpos === FALSE) {
            $this->pre = $this->post;
            $this->tagtext = NULL;
            return false;
        }

        // look for the first unescaped close-square-bracket (or alt syntax bracket) that isn't inside a /* .. */ comment
        if (! $alt_syntax) {
            while (($com_start_pos=strpos($this->post, '/*', $start_pos)) < ($end_pos=strpos($this->post, ']', $start_pos))) {
                if ($com_start_pos === FALSE) break;
                $start_pos = strpos($this->post, '*/', $com_start_pos);
            }
        } else {
            while (($com_start_pos=strpos($this->post, '/*', $start_pos)) < ($end_pos=strpos($this->post, ']]-->', $start_pos))) {
                if ($com_start_pos === FALSE) break;
                $start_pos = strpos($this->post, '*/', $com_start_pos);
            }
        }

        $this->pre = substr($this->post, 0, $tag_start_pos);
        if (! $alt_syntax) {
            $this->tagtext = str_replace(array('\\[', '\\]'), array('[', ']'),
                substr($this->post, $tag_start_pos, $end_pos-$tag_start_pos+1));
            $this->post = substr($this->post, $end_pos+1);
        } else {
            $this->tagtext = str_replace(array('\\[', '\\]'), array('[', ']'),
                substr($this->post, $tag_start_pos, $end_pos-$tag_start_pos+5));
            /* alternative tag starts with a whopping six characters (and ends with five!) */
            $this->post = substr($this->post, $end_pos+5);
        }

        // chunk up the tag
        if (! $alt_syntax)
            $tag = substr($this->tagtext, 1, strlen($this->tagtext)-2);	// lose the square brackets
        else
            $tag = substr($this->tagtext, 6, strlen($this->tagtext)-11);	// lose the alt syntax brackets
        $tag = preg_replace('!/\*.*?\*/!s', '', $tag);			// remove comments /* .. */
        $bits = explode(':', $tag);

        // The only tricky case we care about is if one of the tag parameters contains a string that contains a colon:
        // in this case we have to glue some bits together.
        $bits2 = array();
        for ($i=0; $i < count($bits); $i++) {
            //if (preg_match('/^((([^"\\]|\\\\|\\")*"){2})*([^"\\]|\\\\|\\")*"([^"\\]|\\\\|\\")*$/s', $bits[$i])) {
            if (preg_match('/^([^"]*"[^"]*")*[^"]*"[^"]*$/s', $bits[$i])) {
                // the pattern contains an odd number of (unescaped) quote marks
                if ($i != count($bits) - 1) {
                    $bits[$i+1] = $bits[$i] . ':' . $bits[$i+1];
                    continue;
                } else {
                    error_log("warning: [$tag] contains malformed string");
                    // we'll use it anyway, but they have been warned
                }
            }
            array_push($bits2, trim($bits[$i]));
        }

        $tag0 = array_shift($bits2);	// Clean up the whitespace in the first entry
        $tag0 = trim(preg_replace('/\s+/s', ' ', $tag0));

        $this->current_tagwords = explode(' ', $tag0);
        $this->current_tagtype = strtolower($this->current_tagwords[0]);
        $this->current_tagparams = $bits2;

        return true;
    }
}



class Component {

    var $container;
    var $required;		// boolean: whether or not this component is REQUIRED
    var $versioned;		// boolean: whether or not this component is VERSIONED
    var $satisfied;


    function Component(&$lexer, &$container) { $this->container = &$container; $this->required = $this->satisfied = false; }
    // parse this component as a child of the provided container using the provided lexer


    function verify($vars, $auto_vars) { }
    // check the syntax of the component and all sub-components


    function input_check($vars) { $this->satisfied = true; return array(); }
    // returns an array of errors (i.e. required elements that weren't satisfied)
    // and sets the $satisfied property for each Component.
    // The list of errors is non-empty if and only if the component is not satisfied
    // (note that a component is not satisfied if and only if it is REQUIRED and has a non-true value)


    function execute($vars, $auto_vars) { return true; }
    // perform SQL inserts and updates, and side-effects like file uploads


    function render($vars) { }
    // render HTML to represent this component


    function component_description() { return "unknown component"; }
    // return a terse human-readable description of this component for debugging purposes
}



class Scope extends Component {

    var $type;	// object type (ENTRY, RESOURCE etc)
    var $name;	// user-specified name for the scope
    var $params;	// extra parameters passed to the scope

    var $children;	// components contained within this one
    var $_END_TAG;	// ooh ... dirty hack.  This must be initialised in subclasses before they call the Scope constructor.
    var $readonly;


    function Scope(&$lexer, &$container) {
        parent::Component($lexer, $container);

        // find the outermost scope -- necessarily the BodyScope
        $_top_scope =& $this;
        while ($_top_scope->container) $_top_scope =& $_top_scope->container;


        $this->children = array();
        $this->readonly = true;

        if (! is_a($this, 'InputScope')) {
            $this->required = (strtoupper(@$lexer->current_tagwords[1]) == 'REQUIRED');
            $this->versioned = (strtoupper(@$lexer->current_tagwords[1]) == 'VERSIONED');
            if ($this->required  or  $this->versioned) {
                $this->type = @$lexer->current_tagwords[2];
                $this->name = @$lexer->current_tagwords[3];
            } else {
                $this->type = @$lexer->current_tagwords[1];
                $this->name = @$lexer->current_tagwords[2];
            }
            $this->params = $lexer->current_tagparams;
        } else {
            $this->required = (strtoupper($lexer->current_tagwords[1]) == 'REQUIRED');
            if ($this->required) {
                $this->name = $lexer->current_tagwords[2];
            } else {
                $this->name = $lexer->current_tagwords[1];
            }
            $this->params = $lexer->current_tagparams;
        }

        if ($container  and  $this->name) {	// obviously can't apply if $this isa BodyScope
            // fill in the BodyScope's descendants:
            // each entry should be a reference to a unique object with that name,
            // or, if there are multiple objects with that name, then an array of those objects.
            if (@$_top_scope->descendants[$this->name]) {
                if (is_array($_top_scope->descendants[$this->name]))
                    $_top_scope->descendants[$this->name][] = &$this;
                else {
                    $desc0 =& $_top_scope->descendants[$this->name];
                    unset($_top_scope->descendants[$this->name]);
                    $_top_scope->descendants[$this->name] = array($desc0, $this);
                    unset($desc0);
                }
            } else {
                $_top_scope->descendants[$this->name]= &$this;
            }
        }

        while ($lexer->next()) {
            if (strlen($lexer->pre) > 0)
                $this->addChild(new PlainText($lexer, $this));

            if ( $lexer->current_tagtype === $this->_END_TAG
            or ($lexer->current_tagtype == 'end-extended-search'  and  $this->_END_TAG == 'end-search')) {
                return;
            }

            switch ($lexer->current_tagtype) {
                case '':	// empty tags are fair play (e.g. could contain comments), just ignore them
                    break;
                case 'select':
                    $this->addChild(new SelectScope($lexer, $this)); break;
                case 'search':
                    $this->addChild(new SearchScope($lexer, $this)); break;
                case 'extended-search':
                    $child =& new SearchScope($lexer, $this);
                    $child->extended = true;
                    $this->addChild($child);
                    unset($child);
                    break;
                case 'input':
                    $this->addChild(new InputScope($lexer, $this)); break;
                case 'update':
                    $this->addChild(new UpdateScope($lexer, $this)); break;
                case 'insert':
                    $this->addChild(new InsertScope($lexer, $this)); break;
                case 'delete':
                    $this->addChild(new DeleteScope($lexer, $this)); break;
                case 'foreach':
                    $this->addChild(new ForeachScope($lexer, $this)); break;
                case 'not-found':
                    $this->addChild(new NotFoundScope($lexer, $this)); break;
                case 'literal':
                    $this->addChild(new LiteralScope($lexer, $this)); break;

                case 'if':
                    $this->addChild(new IfScope($lexer, $this)); break;
                case 'if-eq':
                    $this->addChild(new IfEqScope($lexer, $this)); break;
                case 'else':
                    if (is_a($this, 'IfScope')  ||  is_a($this, 'IfEqScope'))
                        $this->addChild(new ElseHack($lexer, $this));
                    else
                        fatal($this->component_description() . ' cannot contain an [else]');
                    break;

                case 'textbox':
                    $this->addChild(new Textbox($lexer, $this)); break;
                case 'datebox':
                    $this->addChild(new Datebox($lexer, $this)); break;
                case 'password':
                    $this->addChild(new Password($lexer, $this)); break;
                case 'hidden':
                    $this->addChild(new Hidden($lexer, $this)); break;
                case 'textarea':
                    $this->addChild(new Textarea($lexer, $this)); break;
                case 'dropdown':
                    $this->addChild(new Dropdown($lexer, $this)); break;
                case 'checkbox':
                    $this->addChild(new Checkbox($lexer, $this)); break;
                case 'radio':
                    $this->addChild(new Radio($lexer, $this)); break;
                case 'file':
                    $this->addChild(new File($lexer, $this)); break;

                case 'auto':
                    $this->addChild(new Auto($lexer, $this)); break;

                case 'errors':
                    $this->addChild(new Errors($lexer, $this)); break;

                case 'output-flush':
                    $this->addChild(new FlushComponent($lexer, $this)); break;

                case 'evaluate':
                    $this->addChild(new Evaluate($lexer, $this)); break;

                default:
                    // if this isn't a variable reference then it shouldn't be here
                    if (count($lexer->current_tagwords) > 1  or  count($lexer->current_tagparams) > 0)
                        fatal("Don't understand ".$lexer->tagtext." in scope ".$this->name);

                    // assume it's a variable substitution!
                    $this->addChild(new Variable($lexer, $this));
            }
        }

        if ($this->_END_TAG) fatal("Scope ".$this->name." must be terminated by ".$this->_END_TAG);
    }


    function addChild(&$component) {
        $this->children[] = &$component;
    }


    function verify($vars, $auto_vars) {

        global $MAINTABLES;

        if (! @$MAINTABLES[$this->type])
            fatal($this->component_description() . " must have a type");

        if ($this->versioned  and  ! is_a($this, 'UpdateScope'))
            fatal($this->component_description() . " cannot be versioned: it's not an [update] scope");

        $fields = get_fields($this->type);
        foreach ($fields as $field => $dummy)
            $vars[$field] = $vars[$this->name . '.' . $field] = true;

        foreach ($this->children as $child) {
            $child->verify($vars, $auto_vars);
        }
    }


    function input_check($vars) {

        $my_errors = array();

        $all_children_satisfied = true;
        $any_children_satisfied = false;
        $any_children_required = false;
        foreach ($this->children as $child) {
            $errors = $child->input_check($vars);

            if ((is_a($child, 'Scope') or $child->required)  and  $errors) {
                foreach ($errors as $error) array_push($my_errors, $error);
                $all_children_satisfied = false;
                if (defined('T1000_DEBUG')) error_log($this->component_description() . ' had a child FAIL: ' . $child->component_description());
            } else {
                if ($child->satisfied and !is_a($child, 'Auto')) {
                    $any_children_satisfied = true;
                    if (defined('T1000_DEBUG')) error_log($this->component_description() . ' had a child SATISFIED: ' . $child->component_description());
                } else {

                    if (defined('T1000_DEBUG')) if (! is_a($child, 'PlainText')) error_log($this->component_description() . ' had a non-required child not satisfied: ' . $child->component_description());
                }
            }

            if ($child->required) $any_children_required = true;
        }
        if (($any_children_required or $any_children_satisfied)  and  $all_children_satisfied)
            $this->satisfied = true;

        if (defined('T1000_DEBUG')) error_log($this->component_description() . ": any children required ($any_children_required), any children satisfied ($any_children_satisfied), all children satisfied ($all_children_satisfied), this->satisfied (" . $this->satisfied . ")");

        if ($any_children_satisfied  and  ($any_children_required  or  $this->required))
            return $my_errors;
    }


    function execute($vars, $auto_vars) {
        $rval = true;
        foreach ($this->children as $child)
            $rval = $rval and $child->execute($vars, $auto_vars);
        return $rval;
    }


    function render($vars) {
        foreach ($this->children as $child)
            $child->render($vars);
    }


    function add_vars(&$src_vars, &$dst_vars) {
        if (is_a($this->container, 'BodyScope'))
            if ($this->type != 'RESOURCE_VERSION'  and  ! array_key_exists($this->type.'-ID', $src_vars)) {
                global $not_found;
                $not_found = 1;
                return;
            }
            add_vars($this->type, $this->name, $src_vars, $dst_vars);
    }
}



class BodyScope extends Scope {

    var $global_vars;
    var $descendants;


    function BodyScope(&$lexer) {
        global $KEY_TO_TABLE_TYPE;
        $dummy = NULL;
        $this->descendants = array();

        parent::Scope($lexer, $dummy);
        if (strlen($lexer->post) > 0) // parent::Scope swallows all tags, leaving only (possibly) some trailing text
            $this->addChild(new PlainText($lexer, $this));

        $this->global_vars = array();
        foreach ($KEY_TO_TABLE_TYPE as $key => $table_type) {
            // e.g. 'res_id' => RESOURCE
            if (array_key_exists($key, $_REQUEST))
                $this->global_vars["$table_type-ID"] = $_REQUEST[$key];
        }
        $this->global_vars['this-url'] = preg_replace('/&chop&.*/', '', $_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'].'?'.$_SERVER['QUERY_STRING']);
        $this->global_vars['ref-url'] = preg_replace('/&chop&.*/', '', @$_SERVER['HTTP_REFERER']);

        if (get_user_id()) {
            $this->global_vars["logged-in-user-id"] = get_user_id();
            $this->global_vars["logged-in-user-name"] = get_user_name();
            $this->global_vars["logged-in-user-username"] = get_user_username();

            $access = get_user_access();
            if ($access) {
                $this->global_vars["logged-in-user-access"] = get_user_access();
                //$this->global_vars["logged-in-is-administrator"] = is_administrator();
                //$this->global_vars["logged-in-is-editor"] = is_editor();

                $levels = get_access_levels();
                foreach ($levels as $accesslevel => $enabled)
                    $this->global_vars["logged-in-is-".$accesslevel] = $enabled;
            }
        }
    }

    function component_description() { return "global scope"; }


    function verify($vars=NULL, $auto_vars=NULL) {
        foreach ($this->children as $child)
            $child->verify($this->global_vars, $auto_vars);
    }


    function input_check($vars=array()) {
        if (defined('T1000_DEBUG')) error_log("checking body scope");

        global $not_found;

        $my_errors = array();

        $all_children_satisfied = true;
        $any_children_satisfied = false;
        foreach ($this->children as $child) {
            if (defined('T1000_DEBUG')) error_log("checking " . $child->component_description());
            $errors = $child->input_check($this->global_vars);
            if (defined('T1000_DEBUG')) error_log("finished checking " . $child->component_description());

            if (is_a($child, 'UpdateScope')  or  is_a($child, 'SelectScope')) {
                if (! array_key_exists($child->type.'-ID', $this->global_vars)) {
                    $not_found = true;
                    if (defined('T1000_DEBUG')) error_log("skipping scope " . $child->component_description() . " because " . $child->type . "-ID is not set");
                    continue;
                }
            }

            if ((is_a($child, 'Scope') or $child->required)  and  $errors) {
                foreach ($errors as $error) array_push($my_errors, $error);
                $all_children_satisfied = false;
                if (defined('T1000_DEBUG')) error_log($this->component_description() . ' had a child FAIL: ' . $child->component_description());
            } else {
                if ($child->satisfied and !is_a($child, 'Auto')) {
                    $any_children_satisfied = true;
                    if (defined('T1000_DEBUG')) error_log($this->component_description() . ' had a child SATISFIED: ' . $child->component_description());
                } else {
                    if (defined('T1000_DEBUG')) if (! is_a($child, 'PlainText')) error_log($this->component_description() . ' had a non-required child not satisfied: ' . $child->component_description());
                }
            }
            if (is_a($child, 'Scope')) $not_found = false;
        }
        if (defined('T1000_DEBUG')) error_log("fin");
        if ($any_children_satisfied  and  $all_children_satisfied)
            $this->satisfied = true;
        if (defined('T1000_DEBUG')) error_log($this->component_description() . ": any children required ($any_children_required), any children satisfied ($any_children_satisfied), all children satisfied ($all_children_satisfied), this->satisfied (" . $this->satisfied . ")");

        $this->global_vars['-ERRORS'] = $my_errors;
        return $my_errors;
    }


    /* Global scope treats execute() specially:
    * it returns the last value that isn't the default value true.
    * This means we can retrieve the primary key associated with a big ol' INSERT,
    * or test for unsuccessful execution.
    */
    function execute($vars=array(), $auto_vars=array()) {
        global $not_found;

        $rval = true;
        foreach ($this->children as $child) {
            if (is_a($child, 'UpdateScope')  or  is_a($child, 'SelectScope')) {
                if (! array_key_exists($child->type.'-ID', $this->global_vars)) {
                    $not_found = true;
                    continue;
                }
            }

            $val = $child->execute($this->global_vars, @$auto_vars);
            if ($val !== true) $rval = $val;
        }

        return $rval;
    }


    function render($vars=NULL) {
        global $not_found;
        foreach ($this->children as $child) {
            if (is_a($child, 'UpdateScope')  or  is_a($child, 'SelectScope')) {
                if (! array_key_exists($child->type.'-ID', $this->global_vars)) {
                    $not_found = true;
                    continue;
                }
                $child->render($this->global_vars);

            } else {
                $child->render($this->global_vars);
                if (is_a($child, 'Scope')  and  ! is_a($child, 'ForeachScope')) $not_found = false;
            }
        }
    }


    function add_vars() { fatal("called BodyScope::add_vars()"); }
}



class InputScope extends Scope {

    var $execute_hook;

    function InputScope(&$lexer, &$container) {
        $this->_END_TAG = 'end-input';
        parent::Scope($lexer, $container);
    }


    function component_description() { return "[input] scope \"".$this->name."\""; }


    function verify($vars, $auto_vars) {

        if (! $this->name)
            fatal("[input] scope must have a name");

        foreach ($this->children as $child) {
            if (is_a($child, 'InputComponent') or is_a($child, 'Auto')) continue;
            $child->verify($vars, $auto_vars);
        }
    }


    function execute($vars, $auto_vars) {
        if ($this->execute_hook) {
            $sat_vars = array();
            foreach ($this->children as $child) {
                if (is_a($child, 'InputComponent')  or  is_a($child, 'Auto')) {
                    $value = $child->value($vars, $auto_vars);
                    if ($value) $sat_vars[$child->name] = $value;
                }
            }

            $executehook =& $this->execute_hook;
            $executehook($this, $sat_vars);
        }
        return parent::execute($vars, $auto_vars);
    }


    function render($vars) {
        global $submit_written;

        if (! $submit_written) {
            if (! defined('T1000_SUPPRESS_HIDDEN_FIELDS')) print '<input type="hidden" name="_submit" value="1">';
            $submit_written = 1;
        }

        if (! defined('T1000_SUPPRESS_HIDDEN_FIELDS')) print '<input type="hidden" name="_'.$this->name.'_submit" value="1">';
        $vars[$this->name.'-submitted'] = $_REQUEST['_'.$this->name.'_submit'];	// were we submitted last time?
        return parent::render($vars);
    }


    function has_lvalue($name) { return true; }
}



class SelectScope extends Scope {

    function SelectScope(&$lexer, &$container) {
        $this->_END_TAG = 'end-select';
        parent::Scope($lexer, $container);
    }


    function component_description() { return "[select] scope \"".$this->name."\""; }


    function verify($vars, $auto_vars) {

        global $MAINTABLES;

        if (! $this->name)
            fatal("[select] scope must have a name");
        if (! $this->type)
            fatal($this->component_description() . " must have a type");
        if (! @$MAINTABLES[$this->type])
            fatal($this->component_description() . " has unknown type");

        $fields = get_fields($this->type);
        foreach ($fields as $field => $dummy)
            $vars[$field] = $vars[$this->name . '.' . $field] = true;
        if ($this->type == 'RESOURCE') $vars['rv_nicesize'] = $vars[$this->name . '.rv_nicesize'] = true;
        $vars[$this->type.'-ID'] = true;

        foreach ($this->children as $child) {
            if (is_a($child, 'InputComponent') or is_a($child, 'Auto'))
                // input components (textbox etc) don't make sense in a select scope
                fatal($child->component_description() . " not allowed in " . $this->component_description());

            $child->verify($vars, $auto_vars);
        }
    }


    function input_check($vars) {
        $this->add_vars($vars, $vars);
        return parent::input_check($vars);
    }


    function execute($vars, $auto_vars) {
        global $not_found;
        $this->add_vars($vars, $vars);
        if ($not_found) return false;
        return parent::execute($vars, $auto_vars);
    }


    function render($vars) {
        global $not_found;
        $this->add_vars($vars, $vars);
        if ($not_found) return;
        parent::render($vars);
    }
}



class SearchScope extends Scope {

    var $search_clause;
    var $order_col;
    var $offset;
    var $extended;


    function SearchScope(&$lexer, &$container) {
        $this->_END_TAG = 'end-search';
        parent::Scope($lexer, $container);
        $this->search_clause = NULL;
        $this->order_col = defined($this->name . '-order')? constant($this->name . '-order') : '';
        $this->offset = intval(@$_REQUEST['_' . $this->name . '_offset']);
    }


    function component_description() { return "[search] scope \"".$this->name."\""; }


    function verify($vars, $auto_vars) {
        global $SEARCHES;

        if (! $this->name) fatal("[search] scope must have a name");
        $vars[$this->name.'-searched'] = true;	// dummy variable

        // dummy values that will be filled-in inside the scope
        $vars['result-count'] = true;		// total number of results
        $vars['result-page-count'] = true;	// total number of results *on this page*
        $vars['result-first'] = true;		// index of first result (1 for page 1, e.g. 21 for page 2 ...)
        $vars['result-last'] = true;		// index of last result (20 for page 1, e.g. 40 for page 2 ...)
        $vars['result-pages-index'] = true;
        $vars['result-pages-total'] = true;
        $vars['next-url'] = true;		// URL to give the next page of results (or empty if no next page)
        $vars['prev-url'] = true;		// URL to give the previous page of results (or empty on first page)
        $vars['this-url'] = true;		// URL to give the current page of results

        if (! @$SEARCHES[$this->name]) {
            parent::verify($vars, $auto_vars);
        } else {
            preg_match_all('/\[%?([^]%]+)%?\]/', $SEARCHES[$this->name], $matches);
            $col_refs = array();
            foreach ($matches[1] as $match) {
                if (substr($match, 0, 10) == 'word-match') continue;
                $col_refs[$match] = true;
            }

            foreach ($this->children as $child) {
                if (! is_a($child, 'InputComponent')  and  ! is_a($child, 'Auto'))
                    $child->verify($vars, $auto_vars);
                else if (! isset($child->name, $col_refs))
                    error_log('warning: ' . $this->component_description()
                        . ' does not use ' . $child->component_description());
                    else unset($col_refs[$child->name]);
            }

            unset($col_refs['logged-in-user-id']);
            unset($col_refs['logged-in-user-name']);
            unset($col_refs['logged-in-user-username']);
            unset($col_refs['logged-in-user-access']);
            if ($col_refs)
                fatal('Search term for ' . $this->component_description() .
                    ' refers to unknown input(s): ' . join(',', array_keys($col_refs)));
        }
    }


    function input_check($vars) {
        $vars[$this->name.'-searched'] = $_REQUEST['_'.$this->name.'_search'];
        // FIXME: should this be restricted to matching results?
        return parent::input_check($vars);
    }


    function decode_search_stmt(&$vars, &$auto_vars, $return_query=0) {
        global $SEARCHES, $MAINTABLES;

        if (! @$vars[$this->name.'-searched']) {
            // didn't search, don't get any results
            $this->search_clause = '0 ';
            return 0;
        }

        $values = array();
        foreach ($this->children as $child) {
            if (! is_a($child, 'InputComponent')  and  ! is_a($child, 'Auto'))
                continue;

            if (is_a($child, 'Radio')  and  @$values[$child->name]) {
                /* leave existing value */
            } else {
                $values[$child->name] = $child->value($vars, $auto_vars);
            }
        }
        $values['logged-in-user-id'] = $vars['logged-in-user-id'];
        $values['logged-in-user-name'] = $vars['logged-in-user-name'];
        $values['logged-in-user-username'] = $vars['logged-in-user-username'];
        $values['logged-in-user-access'] = $vars['logged-in-user-access'];

        $query = $SEARCHES[$this->name];
        $query = preg_replace('/\[\s*word-match\s*:\s*([^:]+)\s*:([^:\s]+)\s*\]/es',
            'decode_word_match(\'\1\', $values[\'\2\'])', $query);
        $query = preg_replace('/\[(%?)([^]%]+)(%?)\]/es', '"\'\\1".mysql_real_escape_string($values[\'\\2\'])."\\3\'"', $query);
        if ($return_query) return $query;

        $this->internal_search_stuff($vars, $auto_vars, $query);
    }


    function create_adhoc_search_stmt(&$vars, &$auto_vars, $return_query=0) {
        global $MAINTABLES, $TABLE_TYPE_TO_KEY, $DELETED_FIELD;

        if (! @$vars[$this->name.'-searched']) {
            // didn't search, don't get any results
            $this->search_clause = '0 ';
            return 0;
        }

        $clauses = array();
        foreach ($this->children as $child) {
            if (! is_a($child, 'InputComponent')  and  ! is_a($child, 'Auto'))
                continue;

            $clause = $child->sql_snippet($vars, $auto_vars);
            if ($clause)
                array_push($clauses, $clause);
        }

        if ($clauses) $search_clause = '(' . join(' and ', $clauses) . ')';
        else {
            if (@$vars[$this->name.'-searched']) $search_clause = ' 1 ';
            else $search_clause = ' 0 ';
        }

        $query = 'select '.$TABLE_TYPE_TO_KEY[$this->type].' from '.$MAINTABLES[$this->type].' where '.$search_clause;

        if (@$DELETED_FIELD[$this->type])
            $query .= ' and (!'.$DELETED_FIELD[$this->type].' or '.$DELETED_FIELD[$this->type].' is null) ';

        if ($return_query) return $query;

        $this->internal_search_stuff($vars, $auto_vars, $query);
    }


    function create_extended_adhoc_search_stmt(&$vars, &$auto_vars, $return_query=0) {
        global $MAINTABLES, $TABLE_TYPE_TO_KEY, $DELETED_FIELD;

        if (! @$vars[$this->name.'-searched']) {
            // didn't search, don't get any results
            $this->search_clause = '0 ';
            return 0;
        }

        $clauses = array();
        foreach ($this->children as $child) {
            if (! is_a($child, 'InputComponent')  and  ! is_a($child, 'Auto'))
                continue;

            if (is_a($child, 'Dropdown')  or  is_a($child, 'Checkbox')  or  is_a($child, 'Radio')  or  is_a($child, 'File')) {
                $clause = $child->sql_snippet($vars, $auto_vars);
            } else {
                $clause = make_extended_sql_snippet($child->name, $child->value($vars, $auto_vars));
            }
            if ($clause)
                array_push($clauses, $clause);
        }

        if ($clauses) $search_clause = '(' . join(' and ', $clauses) . ')';
        else {
            if (@$vars[$this->name.'-searched']) $search_clause = ' 1 ';
            else $search_clause = ' 0 ';
        }

        $query = 'select '.$TABLE_TYPE_TO_KEY[$this->type].' from '.$MAINTABLES[$this->type].' where '.$search_clause;
        if (defined('T1000_DEBUG')) print("<!-- QUERY: $query -->");

        if (@$DELETED_FIELD[$this->type])
            $query .= ' and (!'.$DELETED_FIELD[$this->type].' or '.$DELETED_FIELD[$this->type].' is null) ';

        if ($return_query) return $query;

        $this->internal_search_stuff($vars, $auto_vars, $query);
    }


    function internal_search_stuff(&$vars, &$auto_vars, $query) {
        global $TABLE_TYPE_TO_KEY;

        $RESULTS_PER_PAGE = defined($this->name . '-RESULTS_PER_PAGE')? constant($this->name . '-RESULTS_PER_PAGE')
        : RESULTS_PER_PAGE;

        if ($this->order_col) $query .= ' order by ' . $this->order_col;
        // grab all the results, and only keep the ones that will appear on this page
        if (defined('T1000_DEBUG')) print "<!-- final query: $query -->";
        $res = mysql_query($query);
        $matches = array();
        $vars['result-count'] = mysql_num_rows($res);
        $res_num = 0;
        while (($row = mysql_fetch_row($res))) {
            if ($res_num++ >= $this->offset  &&  $res_num <= $this->offset+$RESULTS_PER_PAGE)
                array_push($matches, "'" . addslashes($row[0]) . "'");
        }
        $vars['result-page-count'] = count($matches);

        if ($matches) $this->search_clause = $TABLE_TYPE_TO_KEY[$this->type] . ' in (' . join(',', $matches) . ')';
        else { $this->search_clause = ' 0 '; return; }

        $vars['result-first'] = $this->offset + 1;
        $vars['result-last'] = $this->offset + $vars['result-page-count'];
        $vars['result-pages-index'] = intval($this->offset / $RESULTS_PER_PAGE) + 1;
        $vars['result-pages-total'] = intval(($vars['result-count']-1) / $RESULTS_PER_PAGE) + 1;

        if (! preg_match('/\??&?_'.$this->name.'_offset=\d+/', $_SERVER['REQUEST_URI'])) {
            if (strpos($_SERVER['REQUEST_URI'], '&chop&'))
                $url = preg_replace('/&chop&/', '&_'.$this->name.'_offset=0&chop&', $_SERVER['REQUEST_URI']);
            else if (strpos($_SERVER['REQUEST_URI'], '?'))
                $url = $_SERVER['REQUEST_URI'] . '&_'.$this->name.'_offset=0';
                else
                    $url = $_SERVER['REQUEST_URI'] . '?_'.$this->name.'_offset=0';
        } else {
            $url = $_SERVER['REQUEST_URI'];
        }
        if ($this->offset != 0) {
            $vars['prev-url'] =
            preg_replace('/(\??)(&?)(_'.$this->name.'_offset=\d+)/',
                '$1$2'.'_'.$this->name.'_offset='.max($this->offset-$RESULTS_PER_PAGE, 0),
                $url);
        } else	$vars['prev-url'] = '';
        if ($vars['result-last'] < $vars['result-count']) {
            $vars['next-url'] =
            preg_replace('/(\??)(&?)(_'.$this->name.'_offset=\d+)/',
                '$1$2'.'_'.$this->name.'_offset='.($this->offset+$RESULTS_PER_PAGE),
                $url);
        } else	$vars['next-url'] = '';

        // $vars['this-url'] = preg_replace('/&chop&.*/', '', $_SERVER['REQUEST_URI']);
    }


    function execute($vars, $auto_vars) {

        global $SEARCHES;

        $vars[$this->name.'-searched'] = $_REQUEST['_'.$this->name.'_search'];

        if (@$SEARCHES[$this->name])
            $this->decode_search_stmt($vars, $auto_vars);
        else if ($this->extended)
            $this->create_extended_adhoc_search_stmt($vars, $auto_vars);
            else
                $this->create_adhoc_search_stmt($vars, $auto_vars);

        $rval = true;
        foreach ($this->children as $child) {
            if (! is_a($child, 'Scope')) continue;
            if (is_a($child, 'ForeachScope')  and  $child->type == $this->type) {
                $child->search_clause = $this->search_clause;
                $child->order_col = $this->order_col;
            }

            // a cheat: also look inside any IfScope inside the SearchScope,
            // to see if there is a ForeachScope inside that
            if (is_a($child, 'IfScope')  ||  is_a($child, 'IfEqScope')) {
                foreach ($child->children as $grandchild) {
                    if (is_a($grandchild, 'ForeachScope')  and  $grandchild->type == $this->type) {
                        $grandchild->search_clause = $this->search_clause;
                        $grandchild->order_col = $this->order_col;
                    }
                }
            }
            $rval = $rval and $child->execute($vars, $auto_vars);
        }
    }


    function render($vars) {

        global $SEARCHES;

        if (! defined('T1000_SUPPRESS_HIDDEN_FIELDS')) print '<input type="hidden" name="_'.$this->name.'_search" value="1">';
        $vars[$this->name.'-searched'] = @$_REQUEST['_'.$this->name.'_search'];	// were we searched last time?

        if (@$SEARCHES[$this->name])
            $this->decode_search_stmt($vars, $auto_vars);
        else if ($this->extended)
            $this->create_extended_adhoc_search_stmt($vars, $auto_vars);
            else
                $this->create_adhoc_search_stmt($vars, $auto_vars);

        foreach ($this->children as $child) {
            if (is_a($child, 'ForeachScope')) {
                $child->search_clause = $this->search_clause;
                $child->order_col = $this->order_col;
            }

            // a cheat: also look inside any IfScope inside the SearchScope,
            // to see if there is a ForeachScope inside that
            if (is_a($child, 'IfScope')  ||  is_a($child, 'IfEqScope')) {
                foreach ($child->children as $grandchild) {
                    if (is_a($grandchild, 'ForeachScope')  and  $grandchild->type == $this->type) {
                        $grandchild->search_clause = $this->search_clause;
                        $grandchild->order_col = $this->order_col;
                    }
                }
            }
            $rval = @$rval and $child->execute($vars, $auto_vars);
            $child->render($vars);
        }
    }


    function get_search_results() {
        // Get the results of this search scope as an array of primary keys of the appropriate type

        global $SEARCHES, $TABLE_TYPE_TO_KEY;


        // Need to fill in all the scoped variables
        $ancestors = array();
        $ancestor = $this->container;
        while ($ancestor) {
            $ancestors[] =& $ancestor;
            $ancestor =& $ancestor->container;
        }

        // Last ancestor is the BodyScope, which doesn't have a TYPE (or an add_vars)
        // so it's treated specially.  Let's watch ...
        $ancestor =& array_pop($ancestors);
        $vars = $ancestor->global_vars;
        $vars[$this->name.'-searched'] = $_REQUEST['_'.$this->name.'_search'];

        while ($ancestor =& array_pop($ancestors)) {
            if (! is_a($ancestor, 'InsertScope')
            and  ! is_a($ancestor, 'SearchScope')  and  ! is_a($ancestor, 'ForeachScope')) {
                $ancestor->add_vars($vars, $vars);
            }
        }


        if (@$SEARCHES[$this->name])
            $query = $this->decode_search_stmt($vars, $vars, 1);
        else if ($this->extended)
            $query = $this->create_extended_adhoc_search_stmt($vars, $vars, 1);
            else
                $query = $this->create_adhoc_search_stmt($vars, $vars, 1);
        if ($this->order_col) $query .= ' order by ' . $this->order_col;


        $res = mysql_query($query);
        $results = array();
        while ($row = mysql_fetch_row($res)) array_push($results, $row[0]);

        return $results;
    }


    function getClause() { return $this->search_clause; }
}



class UpdateScope extends Scope {

    var $lvalues;


    function UpdateScope(&$lexer, &$container) {
        $this->_END_TAG = 'end-update';
        parent::Scope($lexer, $container);
        $this->readonly = false;
    }


    function component_description() { return "[update] scope \"".$this->name."\""; }


    function verify($vars, $auto_vars) {
        global $MAINTABLES;

        if (! $this->name)
            fatal("[update] scope must have a name");
        if (! $this->type)
            fatal($this->component_description() . " must have a type");
        if (! @$MAINTABLES[$this->type])
            fatal($this->component_description() . " has unknown type");

        $vars[$this->name.'-submitted'] = @$_REQUEST['_'.$this->name.'_submit'];
        if ($this->type == 'RESOURCE') $vars['rv_nicesize'] = $vars[$this->name . '.rv_nicesize'] = true;
        $vars[$this->type.'-ID'] = true;
        parent::verify($vars, $auto_vars);
    }


    function input_check($vars) {
        global $not_found;
        $this->add_vars($vars, $vars);
        if ($not_found) return array();

        $vars[$this->name.'-submitted'] = $_REQUEST['_'.$this->name.'_submit'];
        if (! @$vars[$this->name.'-submitted']) return array();

        return parent::input_check($vars);
    }


    function execute($vars, $auto_vars) {
        global $not_found;

        $vars[$this->name.'-submitted'] = @$_REQUEST['_'.$this->name.'_submit'];
        $updates = array();
        if ($this->versioned) {
            // check to see if any of the specified fields have actually changed
            $new_vars = $vars;
            $this->add_vars($vars, $new_vars);
            if ($not_found) return false;
            $changed = false;
            foreach ($this->children as $child) {
                if (is_a($child, 'File')  and  $child->size > 0) {
                    $changed = true;
                    //print "<!-- updating because FILE has changed -->\n";
                } else if (is_a($child, 'Radio')  and  $child->value($vars)  and  $child->value($vars) != $new_vars[$child->name]) {
                    $changed = true;
                    //print "<!-- updating because radio " . $child->name . " has changed -->\n";
                } else if (is_a($child, 'InputComponent')  and  $child->value($vars) != $new_vars[$child->name]) {
                    $changed = true;
                    //print "<!-- updating because " . $child->name . " has changed -->\n";
                } else if (is_a($child, 'Auto')  and  $child->value($vars) != $new_vars[$child->name]) {
                    $changed = true;
                    //print "<!-- updating because " . $child->name . " has changed -->\n";
                }
            }
        } else {
            $dummy = array();
            $this->add_vars($vars, $dummy);
            if ($not_found) return false;
        }

        foreach ($this->children as $child) {
            if (is_a($child, 'Auto'))
                $updates[$child->name] = $child->value($vars, $auto_vars);
            else if (is_a($child, 'File')) {
                if (! $child->execute($vars, $auto_vars)) {
                    if ($child->required) return false;
                } else {
                    $updates['-file'] = array('filename'=>$child->filename,
                        'size'=>$child->size,
                        'filetype'=>$child->filetype,
                        'localpath'=>$child->localpath);
                }
            } else if (is_a($child, 'Password')) {
                $value = $child->value($vars);
                if (! $value  and  $child->required) return false;
                if ($value) $updates[$child->name] = $value;
                // don't update the password if it isn't set
            } else if (is_a($child, 'Radio')) {
                $value = $child->value($vars);
                if ($value !== NULL)
                    $updates[$child->name] = $value;
                // nb: we don't enforce required radio button here, only in input_check()
            } else if (is_a($child, 'InputComponent')) {
                $value = $child->value($vars);
                if (! $value  and  $child->required) return false;
                $updates[$child->name] = $value;
            }
        }

        if (! $this->versioned  or  $changed) {
            if (! do_update($updates, $this->name, $this->type, $vars))
                return false;

            // remove values from the $_REQUEST once they've been successfully updated
            foreach ($this->children as $child) {
                if (is_a($child, 'InputComponent')) {
                    $param_name = $this->name . '_' . $child->name;
                    unset($_REQUEST[$param_name]);
                }
            }
        }
        $this->add_vars($vars, $vars);

        $rval = true;
        foreach ($this->children as $child) {
            if (is_a($child, 'Scope'))
                $rval = $rval and $child->execute($vars, $auto_vars);
        }
        return $rval;
    }


    function render($vars) {
        global $not_found, $submit_written, $PKEY;
        add_vars($this->type, $this->name, $vars, $vars, true);
        if ($not_found) return;

        if (! $submit_written) {
            if (! defined('T1000_SUPPRESS_HIDDEN_FIELDS')) print '<input type="hidden" name="_submit" value="1">';
            $submit_written = 1;
        }

        if (is_a($this->container, 'BodyScope'))
            if (! defined('T1000_SUPPRESS_HIDDEN_FIELDS')) print '<input type="hidden" name="'. $PKEY[$this->type] . '" value="'.addslashes($vars[$this->type.'-ID']).'">';
            if (! defined('T1000_SUPPRESS_HIDDEN_FIELDS')) print '<input type="hidden" name="_'.$this->name.'_submit" value="1">';
        $vars[$this->name.'-submitted'] = @$_REQUEST['_'.$this->name.'_submit'];	// were we submitted last time?
        return parent::render($vars);
    }


    function has_lvalue($name) {
        if (! $this->lvalues) $this->lvalues = get_lvalues($this->type);
        return $this->lvalues[$name];
    }
}



class InsertScope extends Scope {

    var $lvalues;


    function InsertScope(&$lexer, &$container) {
        $this->_END_TAG = 'end-insert';
        parent::Scope($lexer, $container);
        $this->readonly = false;
    }


    function component_description() { return "[insert] scope \"".$this->name."\""; }


    function verify($vars, $auto_vars) {

        global $MAINTABLES;

        if (! $this->name)
            fatal("[insert] scope must have a name");
        if (! $this->type)
            fatal($this->component_description() . " must have a type");
        if (! @$MAINTABLES[$this->type])
            fatal($this->component_description() . " has unknown type");

        // augment the variables available to an [auto] with the fields in this table
        $fields = get_fields($this->type);
        foreach ($fields as $field => $dummy)
            $auto_vars[$field] = $auto_vars[$this->name . ".$field"] = true;	// just a dummy value
        $vars[$this->name.'-submitted'] = true;
        if ($this->type == 'RESOURCE') $auto_vars['rv_nicesize'] = $auto_vars[$this->name . '.rv_nicesize'] = true;

        foreach ($this->children as $child)
            $child->verify($vars, $auto_vars);
    }


    function input_check($vars) {
        $vars[$this->name.'-submitted'] = $_REQUEST['_'.$this->name.'_submit'];

        if (! @$vars[$this->name.'-submitted']) return array();
        $rval = parent::input_check($vars);
        if (defined('T1000_DEBUG')) error_log("################ " . print_r($rval, 1) . " ################");
        return $rval;
    }


    function execute($vars, $auto_vars) {
        $vars[$this->name.'-submitted'] = $_REQUEST['_'.$this->name.'_submit'];
        $inserts = array();
        foreach ($this->children as $child) {
            //print "processing " . $this->component_description() . "<br>";
            if (is_a($child, 'Auto'))
                $inserts[$child->name] = $child->value($vars, $auto_vars);
            else if (is_a($child, 'File')) {
                if (! $child->execute($vars, $auto_vars)) {	// required parameter is missing
                    if ($child->required) return false;
                } else {
                    $inserts['-file'] = array('filename'=>$child->filename,
                        'size'=>$child->size,
                        'filetype'=>$child->filetype,
                        'localpath'=>$child->localpath);
                }
            } else if (is_a($child, 'Radio')) {
                $value = $child->value($vars);
                if ($value !== NULL) $inserts[$child->name] = $value;
                // nb: we don't enforce required radio button here, only in input_check()
            } else if (is_a($child, 'InputComponent')) {
                $value = $child->value($vars);
                if (! $value  and  $child->required)	// a required parameter is missing: back out
                    return true;
                if ($value != NULL) $inserts[$child->name] = $value;
            }
            //print "processed " . $this->component_description() . "<br>";
        }

        if (! ($pkey = do_insert($inserts, $this->name, $this->type, $vars))) {
            return false;
        }
        $vars[$this->type."-ID"] = $pkey;
        $this->add_vars($vars, $auto_vars);

        $rval = true;
        foreach ($this->children as $child) {
            if (is_a($child, 'Scope'))
                $rval = $rval and $child->execute($vars, $auto_vars);
            else if (is_a($child, 'InputComponent')) {
                // remove values from the $_REQUEST once they've been successfully inserted
                $param_name = $this->name . '_' . $child->name;
                unset($_REQUEST[$param_name]);
            }
        }

        if ($rval)
            return $pkey;
        else
            return false;
    }


    function has_lvalue($name) {
        global $PKEY;

        if (! $this->lvalues) $this->lvalues = get_lvalues($this->type);
        $this->lvalues[$PKEY[$this->type]] = true;
        return @$this->lvalues[$name];
    }


    function render($vars) {
        global $submit_written;

        if (! $submit_written) {
            if (! defined('T1000_SUPPRESS_HIDDEN_FIELDS')) print '<input type="hidden" name="_submit" value="1">';
            $submit_written = 1;
        }

        if (! defined('T1000_SUPPRESS_HIDDEN_FIELDS')) print '<input type="hidden" name="_'.$this->name.'_submit" value="1">';
        $vars[$this->name.'-submitted'] = @$_REQUEST['_'.$this->name.'_submit'];
        return parent::render($vars);
    }
}



class DeleteScope extends Scope {

    function DeleteScope(&$lexer, &$container) {
        $this->_END_TAG = 'end-delete';
        parent::Scope($lexer, $container);
        $this->readonly = false;
    }


    function component_description() { return "[delete] scope \"".$this->name."\""; }


    function verify($vars, $auto_vars) {

        global $MAINTABLES;

        if (! $this->name)
            fatal("[delete] scope must have a name");
        if (! $this->type)
            fatal($this->component_description() . " must have a type");
        if (! @$MAINTABLES[$this->type])
            fatal($this->component_description() . " has unknown type");
        if (! array_key_exists($this->type.'-ID', $vars))
            fatal($this->component_description() . " not available - ".$this->type."-ID not defined");

        $non_empty = false;
        foreach ($this->children as $child) {
            if (is_a($child, 'Auto')) {
                $child->verify($vars, $auto_vars);
                $non_empty = true;
            } else if (is_a($child, 'InputComponent')) {
                $non_empty = true;
            } else if (! is_a($child, 'PlainText')) {
                fatal($this->component_description() . " may not contain " . $child->component_description());
            }
        }
    }


    function input_check($vars) {
        // nothing to check.  No variables are introduced, and the InputComponents don't refer to actual database fields

        // do nothing unless all child values are non-false
        foreach ($this->children as $child) {
            if (is_a($child, 'PlainText')) continue;
            if (! $child->value($vars, @$auto_vars)) return;
        }

        $this->satisfied = true;
    }


    function execute($vars, $auto_vars) {
        global $not_found;
        global $MAINTABLES, $TABLE_TYPE_TO_KEY;

        // do nothing unless all child values are non-false
        foreach ($this->children as $child) {
            if (is_a($child, 'PlainText')) continue;
            if (! $child->value($vars, $auto_vars)) return true;
        }

        // only made it here if ALL child values are non-false
        $pkey = mysql_real_escape_string($vars[$this->type."-ID"]);
        if (defined('T1000_DEBUG')) print('<!-- delete from '.$MAINTABLES[$this->type].' where '.$TABLE_TYPE_TO_KEY[$this->type].'="'.$pkey.'" -->');
        mysql_query('delete from '.$MAINTABLES[$this->type].' where '.$TABLE_TYPE_TO_KEY[$this->type].'="'.$pkey.'"');
    }


    function render($vars) {
        global $not_found, $submit_written;
        if ($not_found) return;

        if (! $submit_written) {
            if (! defined('T1000_SUPPRESS_HIDDEN_FIELDS')) print '<input type="hidden" name="_submit" value="1">';
            $submit_written = 1;
        }

        if (! defined('T1000_SUPPRESS_HIDDEN_FIELDS')) print '<input type="hidden" name="_'.$this->name.'_submit" value="1">';
        $vars[$this->name.'-submitted'] = @$_REQUEST['_'.$this->name.'_submit'];	// were we submitted last time?
        return parent::render($vars);
    }
}



class ForeachScope extends Scope {

    var $sql_clause;
    var $search_clause;
    var $order_col;
    var $lvalues;


    function ForeachScope(&$lexer, &$container) {
        $this->_END_TAG = 'end-foreach';
        $this->sql_clause = @$lexer->current_tagparams[0];
        $this->order_col = '';
        parent::Scope($lexer, $container);
    }


    function component_description() { return ($this->required? "required " : "") . "[foreach] scope \"".$this->name."\""; }


    function verify($vars, $auto_vars) {

        if (! $this->name)
            fatal("[foreach] scope must have a name");

        $fields = get_fields($this->type);
        $vars[$this->type.'-ID'] = true;
        foreach ($fields as $field => $dummy)
            $vars[$field] = $vars[$this->name . '.' . $field] = true;
        if ($this->type == 'RESOURCE') $vars['rv_nicesize'] = $vars[$this->name . '.rv_nicesize'] = true;

        $vars['result-number'] = true;
        parent::verify($vars, $auto_vars);
    }


    // need to add in per-SUFFIX success/failure
    function input_check($vars) {

        global $TABLE_TYPE_TO_KEY, $not_found;

        $my_errors = array();

        $parent_vars = $vars;

        list($stmt, $iters) = get_iter_statement($this->type, $vars);
        if ($this->sql_clause) $stmt .= ' and ' . $this->sql_clause;

        $all_children_satisfied = true;
        $any_children_satisfied = false;

        $result_number = @$vars['result-first']? $vars['result-first'] : 1;
        $res = mysql_query($stmt)  or  fatal(mysql_error());
        if (mysql_num_rows($res) > 0) {
            while (($cols = mysql_fetch_assoc($res))) {
                $vars = $parent_vars;
                //				foreach ($cols as $key => $value) $vars[$key] = $vars[$this->name . ".$key"] = $value;

                // define a primary key in the scoped namespace (nb: could be multiple columns in the primary key)
                foreach ($iters as $iter_field => $iter_key)
                    $vars[$iter_key] = $cols[$iter_field];
                add_vars($this->type, $this->name, $vars, $vars);
                @$vars['-SUFFIX'] .= '_' . @$vars[$TABLE_TYPE_TO_KEY[$this->type]];

                $vars['result-number'] = $result_number++;
                foreach ($this->children as $child) {
                    $errors = $child->input_check($vars);

                    if ((is_a($child, 'Scope') or $child->required)  and  $errors) {
                        foreach ($errors as $error) array_push($my_errors, $error);
                        $all_children_satisfied = false;
                    } else if ($child->satisfied and !is_a($child, 'Auto')) {
                        $any_children_satisfied = true;
                    }
                    unset($child);
                }
            }
            $not_found = false;
        } else {
            $not_found = true;
        }

        if ($all_children_satisfied and $any_children_satisfied)
            $this->satisfied = true;

        return $my_errors;
    }


    function execute($vars, $auto_vars) {

        global $TABLE_TYPE_TO_KEY, $not_found;
        $parent_vars = $vars;

        list($stmt, $iters) = get_iter_statement($this->type, $vars);

        /*
        if (is_a($this->container, 'SearchScope')
        and  $this->container->type == $this->type
        and  $this->container->search_clause)
        $stmt .= ' and ' . $this->container->search_clause;
        */

        if ($this->search_clause) $stmt .= ' and ' . $this->search_clause;
        if ($this->sql_clause) $stmt .= ' and ' . $this->sql_clause;
        else if ($this->order_col) $stmt .= ' order by ' . $this->order_col;

            $rval = true;
        $result_number = @$vars['result-first']? $vars['result-first'] : 1;
        $res = mysql_query($stmt)  or  fatal(mysql_error());
        if (mysql_num_rows($res) > 0) {
            while (($cols = mysql_fetch_assoc($res))) {
                $vars = $parent_vars;
                //		foreach ($cols as $key => $value) $vars[$key] = $vars[$this->name . ".$key"] = $value;

                foreach ($iters as $iter_field => $iter_key) {
                    $vars[$iter_key] = $cols[$iter_field];
                }
                add_vars($this->type, $this->name, $vars, $vars);
                @$vars['-SUFFIX'] .= '_' . @$vars[$TABLE_TYPE_TO_KEY[$this->type]];

                $vars['result-number'] = $result_number++;
                foreach ($this->children as $child) {
                    $rval = $rval and $child->execute($vars, $auto_vars);
                    unset($child);
                }
            }
            $not_found = false;
        } else {
            $not_found = true;
        }

        return $rval;
    }


    function render($vars) {

        global $TABLE_TYPE_TO_KEY, $not_found;

        $parent_vars = $vars;

        list($stmt, $iters) = get_iter_statement($this->type, $vars);

        /*
        if (is_a($this->container, 'SearchScope')
        and  $this->container->type == $this->type
        and  $this->container->search_clause)
        $stmt .= ' and ' . $this->container->search_clause;
        */

        if ($this->search_clause) $stmt .= ' and ' . $this->search_clause;
        if ($this->sql_clause) $stmt .= ' and ' . $this->sql_clause;
        else if ($this->order_col) $stmt .= ' order by ' . $this->order_col;

            if (defined('T1000_DEBUG')) print "<!-- foreach: $stmt -->";
        $result_number = @$vars['result-first']? $vars['result-first'] : 1;
        $res = mysql_query($stmt)  or  fatal(mysql_error());
        if (mysql_num_rows($res) > 0) {
            $not_found = false;

            while (($cols = mysql_fetch_assoc($res))) {
                $vars = $parent_vars;
                //				foreach ($cols as $key => $value) $vars[$key] = $vars[$this->name . ".$key"] = $value;

                foreach ($iters as $iter_field => $iter_key) {
                    $vars[$iter_key] = $cols[$iter_field];
                }
                add_vars($this->type, $this->name, $vars, $vars);
                //		if (@$vars['-SUFFIX'])	-- VERY BAD KIM, GO TO JAIL YOU STUPID LITTLE BOY
                @$vars['-SUFFIX'] .= '_' . @$vars[$TABLE_TYPE_TO_KEY[$this->type]];

                $vars['result-number'] = $result_number++;
                foreach ($this->children as $child) {
                    $child->render($vars);
                    unset($child);
                }
            }

            $not_found = false;
        } else {
            $not_found = true;
        }
    }


    function has_lvalue($name) {
        if (! $this->lvalues) $this->lvalues = get_lvalues($this->type);
        return $this->lvalues[$name];
    }
}



class NotFoundScope extends Scope {

    function NotFoundScope(&$lexer, &$container) {
        $this->_END_TAG = 'end-not-found';
        parent::Scope($lexer, $container);
    }


    function component_description() { return "[not-found] scope"; }


    function verify($vars, $auto_vars) {
        foreach ($this->children as $child)
            $child->verify($vars, $auto_vars);
    }


    function input_check($vars) {
        global $not_found;

        $my_errors = array();
        if ($not_found) {
            foreach ($this->children as $child) {
                $errors = $child->input_check($vars);
                foreach ($errors as $error)
                    array_push($my_errors, $error);
            }
        }

        $this->satisfied = $my_errors? false : true;
        return $my_errors;
    }


    function execute($vars, $auto_vars) {
        global $not_found;
        if ($not_found) { $not_found = false; return parent::execute($vars, $auto_vars); }
        return true;
    }


    function render($vars) {
        global $not_found;
        if ($not_found) { $not_found = false; parent::render($vars); }
    }
}



class LiteralScope extends Scope {

    var $text;

    function LiteralScope(&$lexer, &$container) {	// not literally a scope ...
        $tag_end_pos = strpos($lexer->post, '[end-literal]');
        if ($tag_end_pos === FALSE)
            fatal("[literal] section must be closed by [end-literal]");
        $this->text = substr($lexer->post, 0, $tag_end_pos);
        $lexer->post = substr($lexer->post, $tag_end_pos + 13);
    }


    function component_description() { return "[literal] section"; }


    function verify($vars, $auto_vars) { }


    function input_check($vars) { $this->satisfied = true; return array(); }


    function execute($vars, $auto_vars) { return true; }


    function render($vars) {
        print $this->text;
    }
}



class IfScope extends Scope {

    var $condition_var;


    function IfScope(&$lexer, &$container) {
        $this->_END_TAG = 'end-if';
        $this->condition_var = $lexer->current_tagparams[0];
        parent::Scope($lexer, $container);
    }


    function component_description() { return "[if] scope"; }


    function verify($vars, $auto_vars) {
        if (! $this->condition_var)
            fatal('[if] scope must specify a condition variable');
        if (! array_key_exists($this->condition_var, $vars))
            fatal("[if] scope specifies non-existent variable " . $this->condition_var);
        foreach ($this->children as $child)
            $child->verify($vars, $auto_vars);
    }


    function input_check($vars) {
        $my_errors = array();

        $condition = $vars[$this->condition_var];
        foreach ($this->children as $child) {
            if (is_a($child, 'ElseHack')) $condition = !$condition;
            if ($condition) {
                $errors = $child->input_check($vars);
                if ($errors) {
                    foreach ($errors as $error)
                        array_push($my_errors, $error);
                }
            }
        }

        $this->satisfied = $my_errors? false : true;
        return $my_errors;
    }


    function execute($vars, $auto_vars) {
        $rval = true;
        $condition = $vars[$this->condition_var];
        foreach ($this->children as $child) {
            if (is_a($child, 'ElseHack')) $condition = !$condition;
            if ($condition)
                $rval = $rval and $child->execute($vars, $auto_vars);
        }
        return $rval;
    }


    function render($vars) {
        $condition = $vars[$this->condition_var];
        foreach ($this->children as $child) {
            if (is_a($child, 'ElseHack')) $condition = !$condition;
            if ($condition)
                $child->render($vars);
        }
    }


    function has_lvalue($name) {
        return $this->container->has_lvalue($name);
    }
}



class IfEqScope extends Scope {

    var $condition_var;

    var $rtype;	// [if-eq] component is either a LITERAL, a MAGICAL value or a FIELD from an outer (or the current) scope
    var $raw;	// the text of the rvalue parameter as provided by the user

    var $_value;	// the processed rvalue (internal use only)


    function IfEqScope(&$lexer, &$container) {
        $this->_END_TAG = 'end-if-eq';
        $this->condition_var = $lexer->current_tagparams[0];

        $this->raw = $lexer->current_tagparams[1];

        if ($this->raw[0] == '"') {
            if ($this->raw[strlen($this->raw)-1] != '"')
                fatal("Improperly formatted string in ".$lexer->tagtext);
            $this->_value = str_replace(array('\\"', '\\\\'), array('"', '\\'),
                substr($this->raw, 1, strlen($this->raw)-2));
            $this->rtype = 'literal';

        } else {
            switch ($this->raw) {
                case 'LOGGED_IN_USR_ID':
                    $this->_value = get_user_id(); $this->rtype = 'literal'; break;
                case 'LOGGED_IN_USR_NAME':
                    $this->_value = get_user_name(); $this->rtype = 'literal'; break;
                case 'LOGGED_IN_USR_USERNAME':
                    $this->_value = get_user_username(); $this->rtype = 'literal'; break;
                case 'CURRENT_TIME':
                    $this->_value = date('Y-m-d H:i:s'); $this->rtype = 'literal'; break;

                default:
                    $this->rtype = 'field';
            }
        }

        parent::Scope($lexer, $container);
    }


    function component_description() { return "[if-eq] scope"; }


    function verify($vars, $auto_vars) {
        if (! $this->condition_var)
            fatal('[if-eq] scope must specify a condition variable');
        if (! array_key_exists($this->condition_var, $vars))
            fatal("[if-eq] scope specifies non-existent variable " . $this->condition_var);
        foreach ($this->children as $child)
            $child->verify($vars, $auto_vars);

        if ($this->rtype == 'field'  and
            ! array_key_exists($this->raw, $vars))
            fatal($this->component_description() . " references unknown field " . $this->raw);
    }


    function input_check($vars) {
        $my_errors = array();

        if ($this->rtype == 'literal') $value = $this->_value;
        else $value = $vars[$this->raw];

        $condition = ($vars[$this->condition_var] == $value);
        foreach ($this->children as $child) {
            if (is_a($child, 'ElseHack')) $condition = !$condition;
            if ($condition) {
                $errors = $child->input_check($vars);
                if (@$errors) {
                    foreach ($errors as $error)
                        array_push($my_errors, $error);
                }
            }
        }

        $this->satisfied = $my_errors? false : true;
        return $my_errors;
    }


    function execute($vars, $auto_vars) {
        $rval = true;

        if ($this->rtype == 'literal') $value = $this->_value;
        else $value = $vars[$this->raw];

        $condition = ($vars[$this->condition_var] == $value);
        foreach ($this->children as $child) {
            if (is_a($child, 'ElseHack')) $condition = !$condition;
            if ($condition)
                $rval = $rval and $child->execute($vars, $auto_vars);
        }
        return $rval;
    }


    function render($vars) {
        if ($this->rtype == 'literal') $value = $this->_value;
        else $value = $vars[$this->raw];

        $condition = ($vars[$this->condition_var] == $value);
        foreach ($this->children as $child) {
            if (is_a($child, 'ElseHack')) $condition = !$condition;
            if ($condition)
                $child->render($vars);
        }
    }


    function has_lvalue($name) {
        return $this->container->has_lvalue($name);
    }
}



class ElseHack extends Component {

    function ElseHack(&$lexer, &$container) {
        parent::Component($lexer, $container);
    }


    function component_description() { return "[else] component"; }


    function verify($vars, $auto_vars) { }


    function input_check($vars) { }


    function render($vars) { }
}



class PlainText extends Component {

    var $value;


    function PlainText(&$lexer, &$container) {
        parent::Component($lexer, $container);
        $this->value = $lexer->pre;
    }


    function component_description() { return "text <<".strlen($this->value)." chars>>"; }


    function verify($vars, $auto_vars) { /* nothing can go wrong with a slab of plain text ... OR CAN IT??? */ }


    function input_check($vars) { }


    function render($vars) {
        print $this->value;
    }
}



class InputComponent extends Component {

    var $name;		// SQL field that this component refers to

    var $description;	// human-readable description of this component
    var $extra_html;	// any additional HTML parameters to be rendered with this component


    function InputComponent(&$lexer, &$container) {
        parent::Component($lexer, $container);

        $this->name = $lexer->current_tagparams[0];
        $this->required = (strtoupper(@$lexer->current_tagwords[1]) == 'REQUIRED');

        $this->description = preg_replace('/[ \t]+/', ' ', @$lexer->current_tagparams[1]);
        $this->extra_html = @$lexer->current_tagparams[2];
    }


    function verify($vars, $auto_vars) {
        if (! $this->name)
            fatal($this->component_description() . " must have a name");

        if (! $this->container->readonly  and  ! $this->container->has_lvalue($this->name))
            fatal($this->component_description() . " tries to set unknown field");
    }


    function input_check($vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (! @$_REQUEST[$param_name]  &&  @$_REQUEST[$param_name] !== "0") {
            if ($this->required)
                return array("must provide " . $this->description);
        } else {
            $this->satisfied = true;
        }
    }


    function value($vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        return @$_REQUEST[$param_name];
    }


    function sql_snippet($vars, $auto_vars) { return NULL; }
}



class Textbox extends InputComponent {

    function Textbox(&$lexer, &$container) {
        parent::InputComponent($lexer, $container);
    }


    function component_description() { return "[textbox:".$this->name."]"; }


    function render($vars) {
        $var_name = $this->container->name . '.' . $this->name;
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (array_key_exists($param_name, $_REQUEST))
            $value = $this->value($vars);
        else
            $value = @$vars["-nodups-$var_name"];

        print '<input type="text" name="'.$param_name.'" value="'.htmlspecialchars($value).'" '.
        'title="'.htmlspecialchars($this->description).'" '.$this->extra_html.'>';
    }


    function sql_snippet($vars, $auto_vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];

        if ($_REQUEST[$param_name]  or  $_REQUEST[$param_name] === "0") {
            $value = mysql_real_escape_string($_REQUEST[$param_name]);
            $value = str_replace(array('%', '_'), array('\\%', '\\_'), $value);
            return $this->name . ' like "%' . $value . '%"';
        }
        return NULL;
    }
}



$_small_integers = array('one'=>1, 'two'=>2, 'three'=>3, 'four'=>4, 'five'=>5, 'six'=>6, 'seven'=>7, 'eight'=>8, 'nine'=>9);

class Datebox extends Textbox {

    function Datebox(&$lexer, &$container) {
        parent::Textbox($lexer, $container);
    }


    function component_description() { return "[datebox:".$this->name."]"; }


    function parseDate($value) {
        global $_small_integers;


        $orig_value = $value;
        if (trim($value) == '//') // FileMaker Pro uses this to mean "today"
            return date('Y-m-d', strtotime('today'));

        $value = trim($value);
        $value = preg_replace('/^(one|two|three|four|five|six|seven|eight|nine)/ei', '$_small_integers[\\1]', $value);

        // If user specifies (we assume) DD-MM-YYYY, then strtotime will assume MM-DD-YYYY.
        // Do a switcheroo.
        $value = preg_replace('!^(\d{1,2})[-/](\d{1,2})[-/](\d{1,4})$!', '$2/$1/$3', $value);
        if (($parsed_value = strtotime($value)) !== -1)
            return date('Y-m-d', $parsed_value);
        if (strstr($orig_value, " (can't parse date)"))
            return $orig_value;
        else
            return $orig_value . " (can't parse date)";
    }


    function value(&$vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (@$_REQUEST[$param_name]) {
            $value = $_REQUEST[$param_name];

            // do parsing of date and preserve FMPro-style modifiers even if they won't be used
            // (see EXTENDED attribute for SearchScope)
            // Also accept "date1 to date2", and "date1 - date2" as long as date1 and date2 don't contain -
            if (preg_match('/^(.*)(?:\.\.\.|to)(.*)$/', $value, $matches)) {
                return $this->parseDate($matches[1]) . '...' . $this->parseDate($matches[2]);
            } else if (preg_match('/^([^-]*)-([^-]*)$/', $value, $matches)) {
                return $this->parseDate($matches[1]) . '...' . $this->parseDate($matches[2]);
            } else if (preg_match('/^\s*([<>]?=?)\s*(.*)\s*$/', $value, $matches)) {
                return $matches[1] . $this->parseDate($matches[2]);
            }

            return $this->parseDate($value);
        }
        return NULL;
    }


    function input_check($vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (! @$_REQUEST[$param_name]) {
            if ($this->required)
                return array("must provide " . $this->description);
        } else {
            $value = $this->value($vars);
            if (! $value) return array('don\'t understand date "'.$value.'" for ' . $this->description);

            $this->satisfied = true;
        }
    }


    function sql_snippet($vars, $auto_vars) {
        $value = $this->value($vars);
        if ($value)
            return $this->name . '= "'.mysql_real_escape_string($value).'"';
        else
            return NULL;
    }
}



class Password extends InputComponent {

    function Password(&$lexer, &$container) {
        parent::InputComponent($lexer, $container);
    }


    function component_description() { return "[password:".$this->name."]"; }


    function render($vars) {
        $var_name = $this->container->name . '.' . $this->name;
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (array_key_exists($param_name, $_REQUEST))
            $value = $_REQUEST[$param_name];	//saw TODO: why do we set $value when it's not used.
        else
            $value = @$vars["-nodups-$var_name"];

        print '<input type="password" name="'.$param_name.'" '.'title="'.htmlspecialchars($this->description).'" '.$this->extra_html.'>';
    }


    function sql_snippet($vars, $auto_vars) {
        return NULL;
    }
}



class Hidden extends InputComponent {

    function Hidden(&$lexer, &$container) {
        parent::InputComponent($lexer, $container);
    }


    function component_description() { return "[hidden:".$this->name."]"; }


    function render($vars) {
        $var_name = $this->container->name . '.' . $this->name;
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (array_key_exists($param_name, $_REQUEST))
            $value = $_REQUEST[$param_name];
        else
            $value = @$vars["-nodups-$var_name"];

        if (! defined('T1000_SUPPRESS_HIDDEN_FIELDS')) print '<input type="hidden" name="'.$param_name.'" value="'.htmlspecialchars($value).'" '.$this->extra_html.'>';
    }


    function sql_snippet($vars, $auto_vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];

        if ($_REQUEST[$param_name]  or  $_REQUEST[$param_name] == "0") {
            $value = mysql_real_escape_string($_REQUEST[$param_name]);
            $value = str_replace(array('%', '_'), array('\\%', '\\_'), $value);
            return $this->name . ' like "%' . $value . '%"';
        }
        return NULL;
    }
}



class Textarea extends InputComponent {

    function Textarea(&$lexer, &$container) {
        parent::InputComponent($lexer, $container);
    }


    function component_description() { return "[textarea:".$this->name."]"; }


    function input_check($vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if ((! $_REQUEST[$param_name]  and  $_REQUEST[$param_name] != "0")  or  $_REQUEST[$param_name] == '('.$this->description.')') {
            $_REQUEST[$param_name] = '';
            if ($this->required)
                return array("must provide " . $this->description);
        } else {
            $this->satisfied = true;
        }
    }


    function value($vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if ($_REQUEST[$param_name] == '('.$this->description.')') return '';
        return $_REQUEST[$param_name];
    }


    function render($vars) {
        $var_name = $this->container->name . '.' . $this->name;
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (array_key_exists($param_name, $_REQUEST))
            $value = ($_REQUEST[$param_name] != '('.$this->description.')')? $_REQUEST[$param_name] : '';
        else
            $value = $vars["-nodups-$var_name"];

        if ($value  or  !$this->description  or  strpos(strtolower($this->extra_html), 'onfocus')  or  defined('T1000_NO_TEXTAREA_MAGIC'))
            print '<textarea name="'.$param_name.'" '.'title="'.htmlspecialchars($this->description).'" '.$this->extra_html.'>'.htmlspecialchars($value).'</textarea>';
        else {
            print '<textarea name="'.$param_name.
            '" onFocus="if (this.value==\'('.mysql_real_escape_string($this->description).')\') '.
            'this.value=\'\';" '.'title="'.htmlspecialchars($this->description).'" '.
            $this->extra_html.'>('.htmlspecialchars($this->description).')</textarea>';
        }
    }


    function sql_snippet($vars, $auto_vars) {
        $my_value = $this->value($vars);

        if ($my_value  or  $my_value == "0") {
            $value = mysql_real_escape_string($my_value);
            $value = str_replace(array('%', '_'), array('\\%', '\\_'), $value);
            return $this->name . ' like "%' . $value . '%"';
        }
        return NULL;
    }
}



class Dropdown extends InputComponent {

    var $enum_values;
    var $select_stmt;
    var $default_text;


    function Dropdown(&$lexer, &$container) {
        parent::InputComponent($lexer, $container);

        // we have an extra parameter before the HTML parameter
        $this->extra_html = @$lexer->current_tagparams[3];
        $this->default_text = @$lexer->current_tagparams[4];

        $this->enum_values = NULL;
        $this->select_stmt = NULL;

        $val = strtolower(trim($lexer->current_tagparams[2]));
        if (substr($val, 0, 5) == 'enum('  and  $val[strlen($val)-1] == ')') {
            $val = substr($val, 5, strlen($val)-6);

            if (preg_match_all('/\G\s*"([^"]*)"\s*(?:,|$)/', $val, $matches))
                $this->enum_values = $matches[1];
            else
                fatal('Malformed enum(...) in [dropdown:' . $this->name . ']');
        } else {
            $this->select_stmt = $lexer->current_tagparams[2];
        }
    }


    function component_description() { return "[dropdown:".$this->name."]"; }


    function verify($vars, $auto_vars) {
        if (! $this->name)
            fatal($this->component_description() . " must have a name");

        if (! is_a($this->container, 'SearchScope') and ! $this->container->has_lvalue($this->name))
            fatal($this->component_description() . " tries to set unknown field");

        if (! $this->select_stmt and ! $this->enum_values)
            fatal($this->component_description() . " must be populated by an SQL query or enum(...)");
    }


    function render($vars) {
        $var_name = $this->container->name . '.' . $this->name;
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (array_key_exists($param_name, $_REQUEST))
            $user_key = $_REQUEST[$param_name];
        else
            $user_key = @$vars["-nodups-$var_name"];

        $options = array();
        $have_nontrue = false;
        if ($this->select_stmt) {
            $res = mysql_query($this->select_stmt)  or  fatal(mysql_error());
            while (($row = mysql_fetch_row($res))) {
                $options[$row[0]] = $row[1];
                if (! @$row[0]) $have_nontrue = true;
            }

        } else {
            foreach ($this->enum_values as $val) {
                $options[$val] = $val;
                if (! $val) $have_nontrue = true;
            }
        }

        if (defined('T1000_DEBUG')) print "<!-- $user_key -->";
        print '<select name="' . $param_name . '" ' . 'title="'.htmlspecialchars($this->description).'" '.$this->extra_html . ">\n";
        if (! $have_nontrue) {	// no zero / null / empty option
            if ($this->required) {
                // print an empty option as disabled (but maybe selected)
                print '<option value="" disabled';
            } else {
                print '<option value=""';
            }
            if (! $user_key) print ' selected';
            if (! $this->default_text)
                print '>Select ...</option>'."\n";
            else
                print '>'.$this->default_text.'</option>'."\n";
        }
        foreach ($options as $key => $value) {
            $selected = ($key == $user_key  or  (!$key and !$user_key))? ' selected' : '';
            $key = htmlspecialchars($key);
            $value = htmlspecialchars($value);
            $value = str_replace(' ', '&nbsp;', $value);
            print "<option value=\"$key\"$selected>$value</option>\n";
        }
        print '</select>';
    }


    function sql_snippet($vars, $auto_vars) {
        $param_name = $this->container->name . '_' . $this->name . $vars['-SUFFIX'];

        if (@$_REQUEST[$param_name]) {
            $value = mysql_real_escape_string($_REQUEST[$param_name]);
            return $this->name . ' = "' . $value . '"';
        }
        return NULL;
    }
}



class Checkbox extends InputComponent {

    var $inverted;	// is the sense of this checkbox inverted (non-false when NOT checked)
    var $checked_value;


    function Checkbox(&$lexer, &$container) {
        parent::InputComponent($lexer, $container);

        $this->inverted = (strtoupper(@$lexer->current_tagwords[1]) == 'INVERTED'
            or  strtoupper(@$lexer->current_tagwords[2]) == 'INVERTED');

        if (count($lexer->current_tagparams) > 3)
            $this->checked_value = $lexer->current_tagparams[3];
        else
            $this->checked_value = 1;
    }


    function component_description() { return "[checkbox:".$this->name."]"; }


    function input_check($vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (! array_key_exists($param_name, $_REQUEST) or ($this->inverted and array_key_exists($param_name, $_REQUEST))) {
            if ($this->required)
                return array("must check " . $this->description);
        } else {
            $this->satisfied = true;
        }
    }


    function value($vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (array_key_exists($param_name, $_REQUEST)  or  ($this->inverted and ! array_key_exists($param_name, $_REQUEST)))
            return $this->checked_value;
        else
            return 0; // NULL; CHANGED BY IAN TO FIX PROBLEM WITH Database > Properties
    }


    function render($vars) {
        $var_name = $this->container->name . '.' . $this->name;
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (array_key_exists($param_name, $_REQUEST))
            $value = $_REQUEST[$param_name];
        else
            $value = $vars["-nodups-$var_name"];
        $checked = ($value == $this->checked_value)? (!$this->inverted) : $this->inverted;

        if ($checked)
            print '<input type="checkbox" name="'.$param_name.'" value="'.$this->checked_value.'" checked '.'title="'.htmlspecialchars($this->description).'" '.$this->extra_html.'>';
        else
            print '<input type="checkbox" name="'.$param_name.'" value="'.$this->checked_value.'" '.'title="'.htmlspecialchars($this->description).'" '.$this->extra_html.'>';
    }


    function sql_snippet($vars, $auto_vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];

        if (array_key_exists($param_name, $_REQUEST) or ($this->inverted and ! array_key_exists($param_name, $_REQUEST))) {
            $value = mysql_real_escape_string($this->checked_value);
            return $this->name . ' = "' . $value . '"';
        }
        return NULL;
    }
}


class Radio extends InputComponent {

    var $selected_value;


    function Radio(&$lexer, &$container) {
        parent::InputComponent($lexer, $container);

        if (count($lexer->current_tagparams) > 3)
            $this->selected_value = $lexer->current_tagparams[3];
        else
            $this->selected_value = '';
    }


    function component_description() { return "[radio:".$this->name."=".$this->selected_value."]"; }


    function input_check($vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (! array_key_exists($param_name, $_REQUEST)) {
            if ($this->required)
                return array("must choose at least one " . $this->description);
        } else if ($_REQUEST[$param_name] == $this->selected_value) {
            $this->satisfied = true;
        }
    }


    function value($vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];

        if (array_key_exists($param_name, $_REQUEST)  and  $_REQUEST[$param_name] == $this->selected_value)
            return $this->selected_value;
        else
            return NULL;
    }


    function render($vars) {
        $var_name = $this->container->name . '.' . $this->name;
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (array_key_exists($param_name, $_REQUEST))
            $value = $_REQUEST[$param_name];
        else
            $value = $vars["-nodups-$var_name"];
        $checked = ($value == $this->selected_value)? 1 : 0;

        if ($checked)
            print '<input type="radio" name="'.$param_name.'" value="'.$this->selected_value.'" checked '.'title="'.htmlspecialchars($this->description).'" '.$this->extra_html.'>';
        else
            print '<input type="radio" name="'.$param_name.'" value="'.$this->selected_value.'" '.'title="'.htmlspecialchars($this->description).'" '.$this->extra_html.'>';
    }


    function sql_snippet($vars, $auto_vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];

        $value = $this->value($vars);
        if (defined('T1000_DEBUG')) error_log('radio val: ' . $value);

        if ($value !== NULL)
            return $this->name . ' = "' . mysql_real_escape_string($value) . '"';

        return NULL;
    }
}

class File extends InputComponent {

    var $filename;
    var $size;
    var $localpath;
    var $filetype;


    function File(&$lexer, &$container) {
        parent::InputComponent($lexer, $container);
    }


    function component_description() { return "[file:".$this->name."]"; }


    function verify($vars, $auto_vars) {
        if (! $this->name)
            fatal($this->component_description() . " must have a name");
    }


    function input_check($vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];
        if (! (@$_FILES[$param_name]  and  @$_FILES[$param_name]['name'])) {
            if ($this->required)
                return array("must select a file for " . $this->description);
        } else if (@$_FILES[$param_name]['error']) {
            switch ($_FILES[$param_name]['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return array("file supplied for " . $this->description . " is too large");
                case UPLOAD_ERR_PARTIAL:
                    return array("file supplied for " . $this->description . " was not fully uploaded");
                default:
                    return array("file supplied for " . $this->description . " was not successfully uploaded");
            }
            $this->satisfied = true;
        } else {
            $this->satisfied = true;
        }
    }


    function execute($vars, $auto_vars) {
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];

        if (! @$_FILES[$param_name]  or  ! @$_FILES[$param_name]['size'])
            return false;
        $this->filename = $_FILES[$param_name]['name'];
        $this->size = $_FILES[$param_name]['size'];
        $this->filetype = decode_extension($_FILES[$param_name]['name']);
        $this->localpath = $_FILES[$param_name]['tmp_name'];
        return true;
    }


    function render($vars) {
        $var_name = $this->container->name . '.' . $this->name;
        $param_name = $this->container->name . '_' . $this->name . @$vars['-SUFFIX'];

        print '<input type="file" name="'.$param_name.'" '.'title="'.htmlspecialchars($this->description).'" '.$this->extra_html.'>';
    }
}



class Auto extends Component {

    var $name;	// SQL field that this component refers to
    var $type;	// an auto component is either a LITERAL, a MAGICAL value, or a FIELD from an outer scope
    var $raw;	// the text of the rvalue parameter as provided by the user

    var $_value;	// the processed rvalue (internal use only)


    function Auto(&$lexer, &$container) {
        parent::Component($lexer, $container);

        $this->name = $lexer->current_tagparams[0];
        $this->raw = $lexer->current_tagparams[1];

        if ($this->raw[0] == '"') {
            if ($this->raw[strlen($this->raw)-1] != '"')
                fatal("Improperly formatted string in ".$lexer->tagtext);
            $this->_value = str_replace(array('\\"', '\\\\'), array('"', '\\'),
                substr($this->raw, 1, strlen($this->raw)-2));
            $this->type = 'literal';

        } else {
            switch ($this->raw) {
                /* NB: we're taking a gamble here that these values won't change between parsing and updating ^_^ */
                case 'LOGGED_IN_USR_ID':
                    $this->_value = get_user_id(); $this->type = 'literal'; break;
                case 'LOGGED_IN_USR_NAME':
                    $this->_value = get_user_name(); $this->type = 'literal'; break;
                case 'LOGGED_IN_USR_USERNAME':
                    $this->_value = get_user_username(); $this->type = 'literal'; break;
                case 'CURRENT_TIME':
                    $this->_value = date('Y-m-d H:i:s'); $this->type = 'literal'; break;

                default:
                    $this->type = 'field';
            }
        }
    }


    function component_description() { return "[auto:".$this->name."]"; }


    function verify($vars, $auto_vars) {
        if (! $this->name)
            fatal("[auto] field must have a name");

        if (! is_a($this->container, 'SearchScope') and ! $this->container->has_lvalue($this->name))
            fatal($this->component_description() . " tries to set unknown field");

        if ($this->type == 'field'  and
            ! array_key_exists($this->raw, $vars)  and  ! array_key_exists($this->raw, $auto_vars))
            fatal($this->component_description() . " references unknown field " . $this->raw);
    }


    function render($vars) { }


    function input_check($vars) {
        $this->satisfied = $this->required;
        return array();
    }


    function value($vars, $auto_vars) {
        if ($this->type == 'literal') return $this->_value;
        if (@array_key_exists($this->raw, $auto_vars)) return $auto_vars[$this->raw];
        return $vars[$this->raw];
    }


    function sql_snippet($vars, $auto_vars) {
        if ($this->type == 'field')
            return $this->name . ' = "' . $this->value($vars, $auto_vars) . '"';
        else if ($this->type == 'literal')
            return $this->name . ' = "' . mysql_real_escape_string($this->_value) . '"';
            else
                return '0';
    }
}



class Errors extends Component {

    function Errors(&$lexer, &$container) {
        parent::Component($lexer, $container);
    }


    function component_description() { return "[errors] component"; }


    function render($vars) {
        global $MYSQL_ERRORS;

        if ($MYSQL_ERRORS) {
            print '<p><b>There may be a configuration error:</b></p>';
            print '<ul class="error_list">';
            foreach ($MYSQL_ERRORS as $error)
                print '<li>' . htmlspecialchars($error) . '</li>';
            print '</ul>';
            print '<p><b>Please notify the maintainer.</b><br>';
            print "<font color=\"red\">No changes have been made to the database.</font></b></p>";
            print "<hr class=\"error_hr\">";
        }

        if (@$vars['-ERRORS']) {
            if (count($vars['-ERRORS']) > 1)
                print '<p><b>There were problems with the values you provided:</b></p>';
            else
                print '<p><b>There was a problem with the values you provided:</b></p>';
            print '<ul class="error_list">';
            foreach ($vars['-ERRORS'] as $error)
                print '<li>' . htmlspecialchars($error) . '</li>';
            print '</ul>';
            if (count($vars['-ERRORS']) > 1)
                print "<p><b>Please address these issues and resubmit.<br>";
            else
                print "<p><b>Please address this issue and resubmit.<br>";

            print "<font color=\"red\">No changes have been made to the database.</font></b></p>";
            print "<hr class=\"error_hr\">";
        }
    }
}



class Variable extends Component {

    var $name;
    var $escape;
    var $nltobr;


    function Variable(&$lexer, &$container) {
        parent::Component($lexer, $container);

        $this->escape = 1;
        $this->nltobr = 0;
        $this->name = $lexer->current_tagwords[0];
        if (substr($this->name, strlen($this->name)-1) == '?') {
            $this->nltobr = 1;
            $this->name = substr($this->name, 0, strlen($this->name)-1);
        }
        if (substr($this->name, strlen($this->name)-1) == '!') {
            $this->escape = 0;
            $this->name = substr($this->name, 0, strlen($this->name)-1);
        }
    }


    function component_description() { return "[".$this->name."] substitution"; }


    function verify($vars, $auto_vars) {
        if ($this->name == 'dump-vars') return;
        if ($this->name == '-SUFFIX') return;
        if ($this->name == '-SUCCESS') return;
        if (! array_key_exists($this->name, $vars))
            fatal($this->component_description() . " references unknown field");
    }


    function input_check($vars) { }


    function render($vars) {
        if ($this->name == 'dump-vars') { print "<!-- "; print_r($vars); print " -->\n"; }
        if ($this->escape) {
            $val = htmlspecialchars($vars[$this->name]);

            //if (defined('T1000_XML')) $val = str_replace(array("\01", "\02", "\03", "\04", "\05", "\06"), array('&#01;', '&#02;', '&#03;', '&#04;', '&#05;', '&#06;'), $val);
            if (defined('T1000_XML')) {
                $val = preg_replace("/([\001-\010\013\014\016-\037])/e", "sprintf('&#%02X;', ord('\\1'))", $val);
                //$val = str_replace(array("\01", "\02", "\03", "\04", "\05", "\06"), array('&#01;', '&#02;', '&#03;', '&#04;', '&#05;', '&#06;'), $val);
            }
        } else
            $val = $vars[$this->name];
        if ($this->nltobr)
            print nl2br($val);
        else if (defined('T1000_CSV'))
            print str_replace('"', '""', $val);
            else
                print $val;
    }
}


class FlushComponent extends Component {
    function component_description() { return "[flush]"; }

    function render($vars) {
        ob_flush();
        flush();
    }
}



class Evaluate extends Component {

    var $text;
    var $type;


    function Evaluate(&$lexer, &$container) {
        parent::Component($lexer, $container);
        $this->text = $lexer->current_tagparams[0];
        if (@$lexer->current_tagparams[1])
            $this->type = $lexer->current_tagparams[1];
        else
            $this->type = $container->type;
    }


    function component_description() { return "[evaluate:".$this->text."]"; }


    function verify($vars, $auto_vars) {
        if (! $this->type)
            fatal($this->component_description() . " must have type");

        list($stmt, $dummy) = get_iter_statement($this->type, $vars);
        $stmt = preg_replace('/^select (.+) from /', 'select '.$this->text.' from ', $stmt);
        $res = mysql_query($stmt);
        if (! $res)
            fatal($this->component_description() . " not executable: " . mysql_error());
        if (mysql_num_rows($res) != 1)
            error_log('warning: ' . $this->component_description() . ' should return a single result');
    }


    function input_check($vars) { }


    function render($vars) {
        list($stmt, $dummy) = get_iter_statement($this->type, $vars);
        $stmt = preg_replace('/^select (\S+) from /', 'select '.$this->text.' from ', $stmt);
        $res = mysql_query($stmt);
        $row = mysql_fetch_row($res);
        print htmlspecialchars($row[0]);
    }
}



function decode_extension($filename) {
    // return a best guess of the MIME type associated with this file, based on its extension

    $extension = substr(strtolower(strrchr($filename, '.')), 1);
    $res = mysql_query('select * from ' . MIMETYPE_TABLE . ' where ' . MIMETYPE_EXTENSION_FIELD . ' = "'.mysql_real_escape_string($extension).'"');
    $filetype = mysql_fetch_assoc($res);
    if (! @$filetype[MIMETYPE_TYPE_FIELD])
        return 'application/octet-stream';
    else
        return $filetype[MIMETYPE_TYPE_FIELD];
}


function decode_word_match($col_name, $var_value) {
    if (! $var_value) return "1";

    if (! preg_match_all('/[^\s"]+|"[^"]+"/s', $var_value, $matches)) return "1";

    $subclauses = array();
    foreach ($matches[0] as $word) {
        if ($word[0] == '"') {
            $word = preg_replace('/\s+/s', '_', $word);
            $word = substr($word, 1, strlen($word)-2);
        }
        array_push($subclauses, $col_name . ' like "%'. mysql_real_escape_string($word) .'%"');
    }
    return '(' . join(' and ', $subclauses) . ')';
}


function make_extended_sql_snippet($name, $value) {

    /* Create an SQL snippet based on the extended operators available in FileMaker Pro:

    <     less than the following value
    <=    less than or equal to the following value
    >     greater than the following value
    >=    greater than the following value
    =     an exact match for the following value (or an exact match for an empty field if nothing follows)
    ...   within the specified range (everything between the values on either side of the dots)
    //    today's date (note that the Datebox object is quite flexible in this respect)
    @     matches any one character
    *     matches any zero or more characters
    " "   everything between the quotes is treated as a single word (can also be used to nullify the other operators)

    In the absence of any of these operators, the standard t-1000 matching behaviour occurs:
    words are intersection-matched against the field, with words as groups of consecutive
    non-whitespace characters, or explicitly grouped with double-quotes.
    */

    if (preg_match('/^\s*(.*)\s*\.\.\.\s*(.*)\s*/', $value, $matches)) {
        if ($matches[1] < $matches[2]) {
            return '(' . $name . ' >= "'.mysql_real_escape_string($matches[1]).'"'
            .' and ' . $name . ' <= "'.mysql_real_escape_string($matches[2]).'")';
        } else {
            return '(' . $name . ' >= "'.mysql_real_escape_string($matches[2]).'"'
            .' and ' . $name . ' <= "'.mysql_real_escape_string($matches[1]).'")';
        }
    } else {
        preg_match('/^\s*([<>]?=?)\s*(.*)\s*/', $value, $matches);

        if ($matches[1] == '') {
            $match_value = preg_replace('/"([^"]*)"|([^"@*]*)(@)([^"@*]*)|([^"@*]*)(\*)([^"@*]*)/es',
                "('\\1'?'\"\\1\"':'').('\\3'?'\\2_\\4':'').('\\6'?'\\5%\\7':'')", $matches[2]);
            return decode_word_match($name, $match_value);
        } else {
            return '(' . $name . $matches[1] . '"'.mysql_real_escape_string($matches[2]).'")';
        }
    }
}

?>
