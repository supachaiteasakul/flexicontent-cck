<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_content
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

JLoader::register('ContentHelper', JPATH_ADMINISTRATOR . '/components/com_content/helpers/content.php');
JLoader::register('CategoryHelperAssociation', JPATH_ADMINISTRATOR . '/components/com_categories/helpers/association.php');

/**
 * Content Component Association Helper
 *
 * @package     Joomla.Site
 * @subpackage  com_content
 * @since       3.0
 */
abstract class FlexicontentHelperAssociation extends CategoryHelperAssociation
{
	/**
	 * Method to get the associations for a given item
	 *
	 * @param   integer  $id    Id of the item
	 * @param   string   $view  Name of the view
	 *
	 * @return  array   Array of associations for the item
	 *
	 * @since  3.0
	 */

	public static function getAssociations($id = 0, $view = null)
	{
		jimport('helper.route', JPATH_COMPONENT_SITE);

		$app = JFactory::getApplication();
		$jinput = $app->input;
		$view = is_null($view) ? $jinput->get('view') : $view;
		$id = empty($id) ? $jinput->getInt('id') : $id;

		if ($view == 'item')
		{
			if ($id)
			{
				//$associations = JLanguageAssociations::getAssociations('com_content', '#__content', 'com_content.item', $id);
				$associations = FlexicontentHelperAssociation::getTranslations($id);
				
				$return = array();

				foreach ($associations as $tag => $item)
				{
					$return[$tag] = FlexicontentHelperRoute::getItemRoute($item->id, $item->catid, 0, $item);
				}

				return $return;
			}
		}

		if ($view == 'category')
		{
			$cid = $jinput->getInt('cid');
			return self::getCategoryAssociations($cid, 'com_content');
		}

		return array();

	}


	public static function getTranslations($item_id)
	{
		$db = JFactory::getDBO();
		$query = "SELECT i.language, ie.type_id, "
		. "   CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(':', i.id, i.alias) ELSE i.id END as id, "
		. "   CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(':', c.id, c.alias) ELSE c.id END as catid "
		. " FROM #__content AS i "
		. " LEFT JOIN #__categories AS c ON c.id = i.catid "
		. " LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id "
		. " WHERE "
		. " ie.lang_parent_id = (SELECT lang_parent_id FROM #__flexicontent_items_ext WHERE item_id=".(int) $item_id.")";
		;
		$db->setQuery($query);
		$translations = $db->loadObjectList('language');
		if( $db->getErrorNum() ) { $app->enqueueMessage( $db->getErrorMsg(), 'warning'); }
		return $translations;
	}		

}
