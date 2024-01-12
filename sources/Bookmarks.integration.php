<?php

/**
 * @package "Bookmarks" Addon for Elkarte
 * @author Aaron
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0.1
 *
 */

/**
 * Not a lot of settings for this addon so we add them under the predefined
 * Miscellaneous area of the forum
 *
 * @param array $config_vars
 */
function bmks_integrate_general_mod_settings(&$config_vars)
{
	loadLanguage('Bookmarks');

	$config_vars = array_merge($config_vars, array(
		array('check', 'bookmarks_enabled'),
		'',
	));
}

/**
 * Permissions hook, called from ManagePermissions.php
 *
 * - Used to add new permissions
 *
 * @param array $permissionGroups
 * @param array $permissionList
 * @param array $leftPermissionGroups
 * @param array $hiddenPermissions
 * @param array $relabelPermissions
 */
function bmks_integrate_load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	global $context;

	loadLanguage('Bookmarks');

	// Guests should never be able to make bookmarks
	$context['non_guest_permissions'][] = 'bookmarks';

	// Allow admins to grant users the ability to create bookmarks
	$permissionList['membergroup']['make_bookmarks'] = array(false, 'general', 'view_basic_info');
}

/**
 * integrate_remove_topics, used to remove information when a topic is being removed
 *
 * @param int[] $topics
 */
function bmks_integrate_remove_topics($topics)
{
	require_once(SUBSDIR . '/Bookmarks.subs.php');
	delete_topic_bookmark($topics);
}

/**
 * integrate_topic_query, called from Display.controller
 *
 * @param array $topic_selects
 * @param array $topic_tables
 * @param array $topic_parameters
 */
function bmks_integrate_topic_query(&$topic_selects, &$topic_tables, &$topic_parameters)
{
	global $modSettings, $context;

	$context['can_make_bookmarks'] = !empty($modSettings['bookmarks_enabled']) && allowedTo('make_bookmarks');

	if (!$context['can_make_bookmarks'])
	{
		return;
	}

	$topic_selects[] = 'bmks.id_topic AS bookmark';
	$topic_tables[] = 'LEFT JOIN {db_prefix}bookmarks AS bmks ON (bmks.id_member = {int:member} AND bmks.id_topic = {int:topic})';
}

/**
 * integrate_display_topic, called from Display.controller
 *
 * @param array $topicinfo
 */
function bmks_integrate_display_topic($topicinfo)
{
	global $context;

	if (!$context['can_make_bookmarks'])
	{
		return;
	}

	$context['has_bookmark'] = !empty($topicinfo['bookmark']);
}

/**
 * integrate_display_buttons, called from Display.controller
 *
 * - Used to add additional buttons to topic views
 */
function bmks_integrate_display_buttons()
{
	global $context, $scripturl;

	if (!$context['can_make_bookmarks'])
	{
		return;
	}

	loadLanguage('Bookmarks');

	if ($context['has_bookmark'])
	{
		$url = $scripturl . '?action=bookmarks;sa=remove;remove_bookmark=' . $context['current_topic'] . ';' . $context['session_var'] . '=' . $context['session_id'];
	}
	else
	{
		$url = $scripturl . '?action=bookmarks;sa=add;topic=' . $context['current_topic'] . ';' . $context['session_var'] . '=' . $context['session_id'];
	}

	// Define the new button
	$bookmarks = array('bookmarks' => array(
		'test' => 'can_make_bookmarks',
		'text' => $context['has_bookmark'] ? 'bookmark_exists' : 'bookmark',
		'image' => 'bookmark.png',
		'lang' => true,
		'url' => $url
	));

	// Add bookmark to the normal button array
	$context['normal_buttons'] = elk_array_insert($context['normal_buttons'], 'reply', $bookmarks, 'after');
}

/**
 * integrate_menu_buttons hook, called from Subs.php
 *
 * - Used to add top menu buttons
 *
 * @param mixed[] $buttons
 */
function bmks_integrate_menu_buttons(&$buttons)
{
	global $scripturl, $txt, $modSettings;

	// Not enable or not allowed
	if (empty($modSettings['bookmarks_enabled']) || !allowedTo('make_bookmarks'))
	{
		return;
	}

	loadLanguage('Bookmarks');

	// Where do we want to place the My Bookmarks button
	$insert_after = 'theme';

	// Define the new menu item(s), this will call for GoogleMap.controller
	$new_menu = array(
		'bookmarks' => array(
			'title' => $txt['bookmarks'],
			'href' => $scripturl . '?action=bookmarks',
			'show' => true,
		)
	);

	$buttons['profile']['sub_buttons'] = elk_array_insert($buttons['profile']['sub_buttons'], $insert_after, $new_menu, 'after');
}
