<?php
/**
 * @version 1.0 $Id: textarea.php 1937 2014-08-26 10:27:07Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.textarea
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.event.plugin');

class plgFlexicontent_fieldsTextarea extends JPlugin
{
	static $field_types = array('textarea', 'maintext');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsTextarea( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_textarea', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		
		
		// Create the editor object of editor prefered by the user,
		// this will also add the needed JS to the HTML head
		$editor_name = $user->getParam('editor', $app->getCfg('editor'));
		$editor  = JFactory::getEditor($editor_name);
		$editor_plg_params = array();  // Override parameters of the editor plugin, nothing yet
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		
		// **************
		// Value handling
		// **************
		
		// Default value
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($item->version == 0 || $value_usage > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_value = $default_value ? JText::_($default_value) : '';
		
		// Input field display size & max characters
		$maxlength = (int) $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced when using textarea, otherwise this will depend on the HTML editor (and only will be client size only)
		$use_html  = $field->field_type == 'maintext' ? !$field->parameters->get( 'hide_html', 0 ) : $field->parameters->get( 'use_html', 1 );  // load HTML editor
		
		// *** Simple Textarea configuration  ***
		$rows  = $field->parameters->get( 'rows', 6 ) ;
		$cols  = $field->parameters->get( 'cols', 80 ) ;
		
		// *** HTML Editor configuration  ***
		
		$height = $field->parameters->get( 'height', ($field->field_type == 'textarea') ? '300px' : '400px' ) ;
		if ($height != (int)$height) $height .= 'px';
		
		// Decide editor plugin buttons to SKIP
		$show_buttons = $field->parameters->get( 'show_buttons', 1 ) ;
		$skip_buttons = $field->parameters->get( 'skip_buttons', '' ) ;
		$skip_buttons = is_array($skip_buttons) ? $skip_buttons : explode('|',$skip_buttons);
		
		// Clear empty value
		if (empty($skip_buttons[0]))  unset($skip_buttons[0]);
		
		// Force skipping pagebreak and readmore for CUSTOM textarea fields
		if ($field->field_type == 'textarea') {
			if ( !in_array('pagebreak', $skip_buttons) ) $skip_buttons[] = 'pagebreak';
			if ( !in_array('readmore',  $skip_buttons) )  $skip_buttons[] = 'readmore';
		}
		$skip_buttons_arr = ($show_buttons && $editor_name=='jce' && count($skip_buttons)) ? $skip_buttons : (boolean) $show_buttons;   // JCE supports skipping buttons
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0] = $default_value;
		}
		
		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		
		// Field name and HTML TAG id
		if ($field->field_type == 'textarea')
		{
			$fieldname = 'custom['.$field->name.']';
			$elementid = 'custom_'.$field->name;
		}
		else if ($field->field_type == 'maintext')
		{
			if ( !is_array($field->name) ) {
				$fieldname = 'jform['.$field->name.']';
				$elementid = 'jform_'.$field->name;
			} else {
				foreach ( $field->name as $i => $ffname) {
					if ($i==0) {
						$fieldname = $elementid = $ffname;
					} else {
						$fieldname .= '['.$ffname.']';
						$elementid .= '_'.$ffname;
					}
				}
			}
			$required = '';
			$multiple = 0;
			if ($use_ingroup) { $field->html[0] ='This textarea field is being used to render the description field, which is not allowed to be grouped'; return; }
		}
		
		$js = "";
		$css = "";
		
		if ($multiple) // handle multiple records
		{
			// Add the drag and drop sorting feature
			if (!$use_ingroup) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
			});
			";
			
			if ($max_values) FLEXI_J16GE ? JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true) : fcjsJText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
			
			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;
				
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				";
			
			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			// Update the new textarea
			$js .= "
				var boxClass = 'txtarea';
				var container = newField.find('.fc_'+boxClass);
				container.after('<div class=\"fc_'+boxClass+'\"></div>');  // Append a new container box
				container.find('textarea').show().appendTo(container.next()); // Copy only the textarea (first make it visible) into the new container
				container.remove(); // Remove old (cloned) container box along with all the contents
				
				// Prepare the new textarea for attaching the HTML editor
				theArea = newField.find('.fc_'+boxClass).find('textarea');
				theArea.val('');
				theArea.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+']');
				theArea.attr('id','".$elementid."_'+uniqueRowNum".$field->id.");
				theArea.removeClass(); // Remove all classes from the textarea
				theArea.addClass(boxClass);
				";
			
			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();
				";
			
			// Attach a new JS HTML editor object
			if ($use_html) $js .= "
				if (typeof tinyMCE !== 'undefined') tinyMCE.execCommand('mceAddControl', false, '".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				";
			
			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .= "
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
				";
			
			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800);
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');
				
				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});
				
				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if(rowCount".$field->id." > 1) {
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ this.remove(); });
					rowCount".$field->id."--;
				}
			}
			";
			
			$css .= '';
			
			$remove_button = '<span class="fcfield-delvalue" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="fcfield-drag-handle" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="fcfield-insertvalue fc_before" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="fcfield-insertvalue fc_after"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		} else {
			$remove_button = '';
			$move2 = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		$field->html = array();
		$n = 0;
		//if ($use_ingroup) {print_r($field->value);}
		foreach ($field->value as $value)
		{
			// Special case TABULAR representation of single value textarea
			if ($n==0 && $field->parameters->get('editorarea_per_tab', 0)) {
				$this->parseTabs($field, $item);
				if ($field->tabs_detected) {
					$this->createTabs($field, $item, $fieldname, $elementid);
					return;
				}
			}
			if ( !strlen($value) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group
			
			$fieldname_n = $field->field_type == 'maintext' ? $fieldname : $fieldname.'['.$n.']';
			$elementid_n = $field->field_type == 'maintext' ? $elementid : $elementid.'_'.$n;
			
			// Normal textarea editting
			$field->tab_names[$n]  = $field->field_type == 'maintext' ? $fieldname : $fieldname_n;
			$field->tab_labels[$n] = $field->field_type == 'maintext' ? $field->label : $field->label." ".$n ;
			
			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			//display($name, $html, $width, $height, $col, $row, $buttons = true, $id = null, $asset = null, $author = null, $params = array())
			$txtarea = !$use_html ? '
				<textarea id="'.$elementid_n.'" name="' . $fieldname_n . '" style="width:auto;" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'" '.($maxlength ? 'maxlength="'.$maxlength.'"' : '').'>'
					.htmlspecialchars( $value, ENT_COMPAT, 'UTF-8' ).
				'</textarea>
				' : $editor->display(
					$fieldname_n, htmlspecialchars( $field->value[$n], ENT_COMPAT, 'UTF-8' ), '100%', $height, $cols, $rows,
					$skip_buttons_arr, $elementid_n, $_asset_ = null, $_author_ = null, $editor_plg_params
				);
			
			$txtarea = '
				<div class="fc_txtarea">
					'.$txtarea.'
				</div>
				';
			
			$field->html[] = '
				'.($use_ingroup ? '' : $move2).'
				'.($use_ingroup ? '' : $remove_button).'
				'.($use_ingroup || !$add_position ? '' : $add_here).'
				'.($use_ingroup ? '' : '<div class="fcclear"></div>').'
				'.$txtarea.'
				';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html =
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue fccleared" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$add_enclosers = !$use_ingroup || $field->parameters->get('add_enclosers_ingroup', 0);
		$view = JRequest::getVar('flexi_callview', JRequest::getVar('view', FLEXI_ITEMVIEW));
		
		// Value handling parameters
		$lang_filter_values = 0;//$field->parameters->get( 'lang_filter_values', 1);
		$clean_output = $field->parameters->get('clean_output', 0);
		$encode_output = $field->parameters->get('encode_output', 0);
		$multiple = $use_ingroup || $field->parameters->get( 'allow_multiple', 0 ) ;
		
		
		// Default value
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($value_usage == 2) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_value = $default_value ? JText::_($default_value) : '';
		
		// Get field values
		$values = $values ? $values : $field->value;
		
		// Load default value
		if ( empty($values) ) {
			if (!strlen($default_value)) {
				$field->{$prop} = $use_ingroup ? array() : '';
				return;
			}
			$values = array($default_value);
		}
		
		// Language filter, clean output, encode HTML
		if ($clean_output) {
			$ifilter = $clean_output == 1 ? JFilterInput::getInstance(null, null, 1, 1) : JFilterInput::getInstance();
		}
		if ($lang_filter_values || $clean_output || $encode_output)
		{
			// (* BECAUSE OF THIS, the value display loop expects unserialized values)
			foreach ($values as &$value)
			{
				if ( empty($value) ) continue;  // skip further actions
				
				if ($lang_filter_values) {
					$value = JText::_($value);
				}
				if ($clean_output) {
					$value = $ifilter->clean($value, 'string');
				}
				if ($encode_output) {
					$value = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
				}
			}
			unset($value); // Unset this or you are looking for trouble !!!, because it is a reference and reusing it will overwrite the pointed variable !!!
		}
		
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separatorf = ',&nbsp;';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			case 5:
			$separatorf = '';
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}
		
		// Initialise property with default value
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			if ( !strlen($value) && !$use_ingroup ) continue;
			if ( !strlen($value) ) {
				$field->{$prop}[$n++]	= '';
				continue;
			}
			
			// Add prefix / suffix
			$field->{$prop}[$n]	= !$add_enclosers ? $value : $pretext . $value . $posttext;
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if (!$use_ingroup)  // do not convert the array to string if field is in a group
		{
			// Apply separator and open/close tags
			$field->{$prop} = implode($separatorf, $field->{$prop});
			if ( $field->{$prop}!=='' ) {
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;
			} else {
				$field->{$prop} = '';
			}
		}
		
		
		// ************
		// Add OGP tags
		// ************
		if ($field->parameters->get('useogp', 0) && !empty($field->{$prop}))
		{
			// Get ogp configuration
			$ogpinview  = $field->parameters->get('ogpinview', array());
			$ogpinview  = FLEXIUtilities::paramToArray($ogpinview);
			$ogpmaxlen  = $field->parameters->get('ogpmaxlen', 300);
			$ogpusage   = $field->parameters->get('ogpusage', 0);
			
			if ( in_array($view, $ogpinview) ) {
				switch ($ogpusage)
				{
					case 1: $usagetype = 'title'; break;
					case 2: $usagetype = 'description'; break;
					default: $usagetype = ''; break;
				}
				if ($usagetype) {
					$content_val = !$use_ingroup ? flexicontent_html::striptagsandcut($field->{$prop}, $ogpmaxlen) :
						flexicontent_html::striptagsandcut($opentag.implode($separatorf, $field->{$prop}).$closetag, $ogpmaxlen) ;
					JFactory::getDocument()->addCustomTag('<meta property="og:'.$usagetype.'" content="'.$content_val.'" />');
				}
			}
		}
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;
		
		// Server side validation
		$validation = $field->parameters->get( 'validation', 2 ) ;
		$maxlength  = (int) $field->parameters->get( 'maxlength', 0 ) ;
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// Do server-side validation and skip empty values
			$post[$n] = flexicontent_html::dataFilter($post[$n], $maxlength, $validation, 0);
			
			if (!strlen($post[$n]) && !$use_ingroup) continue; // skip empty values
			
			$newpost[$new] = $post[$n];
			$new++;
		}
		$post = $newpost;
		
		// Reconstruct value if it has splitted up e.g. to tabs, MULTI-VALUE AND TAB-SPLIT not supported simutaneusly
		if ($field->parameters->get('editorarea_per_tab', 0) && count($post)>1)
		{
			$post = array(implode(' ', $post));
		}
		/*if ($use_ingroup) {
			$app = JFactory::getApplication();
			$app->enqueueMessage( print_r($post, true), 'warning');
		}*/
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
	}
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$field, $value)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($field, $value, $return_sql=true);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving 
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	// Method to parse a given text for tabbing code
	function parseTabs(&$field, &$item)
	{
		$start_of_tabs_pattern = $field->parameters->get('start_of_tabs_pattern');
		$end_of_tabs_pattern = $field->parameters->get('end_of_tabs_pattern');
		
		$title_tab_pattern = $field->parameters->get('title_tab_pattern');
		$start_of_tab_pattern = $field->parameters->get('start_of_tab_pattern');
		$end_of_tab_pattern = $field->parameters->get('end_of_tab_pattern');
		
		$field_value = & $field->value[0];
		$field->tabs_detected = false;
		
		// MAKE MAIN TEXT FIELD OR TEXTAREAS TABBED
		
		//echo 'tabs start: ' . preg_match_all('/'.$start_of_tabs_pattern.'/u', $field_value ,$matches) . "<br />";
		//print_r ($matches); echo "<br />";
		
		//echo 'tabs end: ' . preg_match_all('/'.$end_of_tabs_pattern.'/u', $field_value ,$matches) . "<br />";
		//print_r ($matches); echo "<br />";
		
		$field->tabs_detected = preg_match('/' .'(.*)('.$start_of_tabs_pattern .')(.*)(' .$end_of_tabs_pattern .')(.*)'. '/su', $field_value ,$matches);
		if (!$field->tabs_detected) return;
		
		$field->tab_info = new stdClass();
		$field->tab_info->beforetabs = $matches[1];
		$field->tab_info->tabs_start = $matches[2];
		$insidetabs = $matches[3];
		$field->tab_info->tabs_end   = $matches[4];
		$field->tab_info->aftertabs  = $matches[5];
		
		//echo 'tab start: ' . preg_match_all('/'.$start_of_tab_pattern.'/u', $insidetabs ,$matches) . "<br />";
		//echo "<pre>"; print_r ($matches); echo "</pre><br />";									
		
		//echo 'tab end: ' . preg_match_all('/'.$end_of_tab_pattern.'/u', $insidetabs ,$matches) . "<br />";
		//print_r ($matches); echo "<br />";
		
		$tabs_count = preg_match_all('/('.$start_of_tab_pattern .')(.*?)(' .$end_of_tab_pattern .')/su', $insidetabs ,$matches) . "<br />";
		
		if ($tabs_count) {
			$tab_startings = $matches[1];
			
			foreach ($tab_startings as $i => $v) {
				$title_matched = preg_match('/'.$title_tab_pattern.'/su', $tab_startings[$i] ,$title_matches) . "<br />";
				//echo "<pre>"; print_r($title_matches); echo "</pre>";
				$tab_titles[$i] = $title_matches[1];
			}
			
			$tab_contents = $matches[2];
			$tab_endings = $matches[3];
			//foreach ($tab_titles as $tab_title) echo "$tab_title &nbsp; &nbsp; &nbsp;";
		} else {
			echo "FALIED while parsing tabs<br />";
			$field->tabs_detected = 0;
		}
		
		$field->tab_info->tab_startings = & $tab_startings;
		$field->tab_info->tab_titles    = & $tab_titles;
		$field->tab_info->tab_contents  = & $tab_contents;
		$field->tab_info->tab_endings   = & $tab_endings;
	}
	
	
	
	// Method to create a tabular editting of the textarea value
	// this should be called after the tabbed content was parsed successfully
	function createTabs(&$field, &$item, $fieldname, $elementid)
	{
		// initialize framework objects and other variables
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		
		
		// Create the editor object of editor prefered by the user,
		// this will also add the needed JS to the HTML head
		$editor_name = $user->getParam('editor', $app->getCfg('editor'));
		$editor  = JFactory::getEditor($editor_name);
		$editor_plg_params = array();  // Override parameters of the editor plugin, nothing yet
		
		
		$required = $field->parameters->get( 'required', 0 ) ;
		$required = $required ? ' required' : '';
		
		// **************
		// Value handling
		// **************
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($item->version == 0 || $value_usage > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		
		// Input max characters & editing
		$maxlength = $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced when using textarea, otherwise this will depend on the HTML editor (and only will be client size only)
		$use_html  = $field->field_type == 'maintext' ? !$field->parameters->get( 'hide_html', 0 ) : $field->parameters->get( 'use_html', 1 );  // load HTML editor
		
		// *** Simple Textarea configuration  ***
		$rows  = $field->parameters->get( 'rows', 6 ) ;
		$cols  = $field->parameters->get( 'cols', 80 ) ;
		
		// *** HTML Editor configuration  ***
		
		$height = $field->parameters->get( 'height', ($field->field_type == 'textarea') ? '300px' : '400px' ) ;
		if ($height != (int)$height) $height .= 'px';
		
		$show_buttons = $field->parameters->get( 'show_buttons', 1 ) ;
		$skip_buttons = $field->parameters->get( 'skip_buttons', '' ) ;
		
		if (FLEXI_J16GE) {
			$skip_buttons = is_array($skip_buttons) ? $skip_buttons : explode('|',$skip_buttons);
		} else if ( !is_array($skip_buttons) ) {
			$skip_buttons = array($skip_buttons);
		}
		// Clear empty value
		if (empty($skip_buttons[0]))  unset($skip_buttons[0]);
		
		// Force skipping pagebreak and readmore for CUSTOM textarea fields
		if ($field->field_type == 'textarea') {
			if ( !in_array('pagebreak', $skip_buttons) ) $skip_buttons[] = 'pagebreak';
			if ( !in_array('readmore',  $skip_buttons) )  $skip_buttons[] = 'readmore';
		}
		
		$skip_buttons_arr = ($show_buttons && $editor_name=='jce' && count($skip_buttons)) ? $skip_buttons : (boolean) $show_buttons;   // JCE supports skipping buttons
		
		
		// ****************************************
		// Override parameters of the editor plugin
		// ****************************************
		
		
		// *********************
		// Tabbing configuration
		// *********************
		
		$editorarea_per_tab = $field->parameters->get('editorarea_per_tab', 0);
		$allow_tabs_code_editing = $field->parameters->get('allow_tabs_code_editing', 0);
		$merge_tabs_code_editor = $field->parameters->get('merge_tabs_code_editor', 1);
		$force_beforetabs = $field->parameters->get('force_beforetabs');
		$force_aftertabs = $field->parameters->get('force_aftertabs');
		
		$ta_count = 0;
		$ti = & $field->tab_info;
		
		
		// 1. BEFORE TABSET
		if ( $force_beforetabs == 1  ||  ($ti->beforetabs && trim(strip_tags($ti->beforetabs))) ) {
			$field->tab_names[$ta_count] = $fieldname.'['.($ta_count).']';
			$field->tab_labels[$ta_count] = /*$field->label.'<br />'.*/ 'Intro Text';
			
			if (!$use_html) {
				$field->html[$ta_count] = '
				<textarea id="'.$elementid.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'" '.($maxlength ? 'maxlength="'.$maxlength.'"' : '').'>'
					.htmlspecialchars( $ti->beforetabs, ENT_COMPAT, 'UTF-8' ).
				'</textarea>
				';
			} else {
				$field->html[$ta_count] = $editor->display( $field->tab_names[$ta_count], htmlspecialchars( $ti->beforetabs, ENT_COMPAT, 'UTF-8' ), '100%', $height, $cols, $rows, $skip_buttons_arr );
			}
			$ta_count++;
		}
		
		
		// 2. START OF TABSET
		$field->tab_names[$ta_count] = $fieldname.'['.($ta_count).']';
		if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] = !$merge_tabs_code_editor ? 'TabBegin' : 'T';
		if (!$merge_tabs_code_editor) {
			$field->html[$ta_count] = '<textarea id="'.$elementid.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $ti->tabs_start .'</textarea>'."\n";
			$ta_count++;
		} else {
			$field->html[$ta_count] = $ti->tabs_start;
		}
		
		foreach ($ti->tab_contents as $i => $tab_content) {
			// START OF TAB
			$field->tab_names[$ta_count] = $fieldname.'['.($ta_count).']';
			if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] = 'T';//'Start of tab: '. $ti->tab_titles[$i]; 
			$field->html[$ta_count] = '<textarea id="'.$elementid.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $field->html[$ta_count]."\n".$ti->tab_startings[$i] .'</textarea>'."\n";
			$ta_count++;

			$field->tab_names[$ta_count] = $fieldname.'['.($ta_count).']';
			$field->tab_labels[$ta_count] = /*$field->label.'<br />'.*/ $ti->tab_titles[$i]; 
			
			if (!$use_html) {
				$field->html[$ta_count] = '
				<textarea id="'.$elementid.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'" '.($maxlength ? 'maxlength="'.$maxlength.'"' : '').'>'
					.htmlspecialchars( $tab_content, ENT_COMPAT, 'UTF-8' ).
				'</textarea>
				';
			} else {
				$field->html[$ta_count] = $editor->display( $field->tab_names[$ta_count], htmlspecialchars( $tab_content, ENT_COMPAT, 'UTF-8' ), '100%', $height, $cols, $rows, $skip_buttons_arr );
			}
			$ta_count++;
			
			// END OF TAB
			$field->tab_names[$ta_count] = $fieldname.'['.($ta_count).']';
			if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] = 'T';//'End of tab: '. $ti->tab_titles[$i]; 
			if (!$merge_tabs_code_editor) {
				$field->html[$ta_count] = '<textarea id="'.$elementid.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $ti->tab_endings[$i] .'</textarea>'."\n";
				$ta_count++;
			} else {
				$field->html[$ta_count] = $ti->tab_endings[$i];
			}
		}
		
		
		// 3. END OF TABSET
		$field->tab_names[$ta_count] = $fieldname.'['.($ta_count).']';
		if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] =  !$merge_tabs_code_editor ? 'TabEnd' : 'T';
		$field->html[$ta_count] = '<textarea id="'.$elementid.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $field->html[$ta_count]."\n".$ti->tabs_end .'</textarea>'."\n";
		$ta_count++;
		
		
		// 4. AFTER TABSET
		if ( $force_aftertabs == 1  ||  ($ti->aftertabs && trim(strip_tags($ti->aftertabs))) ) {
			$field->tab_names[$ta_count] = $fieldname.'['.($ta_count).']';
			$field->tab_labels[$ta_count] = /*$field->label.'<br />'.*/ 'Foot Text' ;
			
			if (!$use_html) {
				$field->html[$ta_count]	 = '
				<textarea id="'.$elementid.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'" '.($maxlength ? 'maxlength="'.$maxlength.'"' : '').'>'
					.htmlspecialchars( $ti->aftertabs, ENT_COMPAT, 'UTF-8' ).
				'</textarea>
				';
			} else {
				$field->html[$ta_count] = $editor->display( $field->tab_names[$ta_count], htmlspecialchars( $ti->aftertabs, ENT_COMPAT, 'UTF-8' ), '100%', $height, $cols, $rows, $skip_buttons_arr );
			}
			$ta_count++;
		}
	}
}
