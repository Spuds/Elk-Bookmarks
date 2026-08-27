<?php

/**
 * @package "Bookmarks" Addon for Elkarte
 * @author Aaron
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0.0
 *
 */

use ElkArte\Languages\Txt;

/**
 * Integrates bookmark-related settings into the general moderation settings configuration.
 *
 * Called via integrate_general_mod_settings hook.
 *
 * @param array &$config_vars A reference to the array of configuration variables for the general moderation settings.
 * @return void
 */
function bmks_integrate_general_mod_settings(&$config_vars)
{
	Txt::load('Bookmarks');

	$config_vars = array_merge($config_vars, [
		['check', 'bookmarks_enabled'],
		'',
	]);
}

/**
 * Integrates the permissions for bookmarks into the forum's permission management system.
 *
 * Called via integrate_load_permissions hook, used to define and modify custom permissions.
 *
 * @param array $permissionGroups A reference to the array of permission groups categorized by type.
 * @param array $permissionList A reference to the list of permissions, specifying where they belong.
 * @param array $leftPermissionGroups A reference to the array of permissions to be displayed on the left-hand side.
 * @param array $hiddenPermissions A reference to the list of permissions that are hidden from the interface.
 * @param array $relabelPermissions A reference to the array for relabeling permissions within the interface.
 * @return void
 */
function bmks_integrate_load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	global $context;

	Txt::load('Bookmarks');

	// Guests should never be able to make bookmarks
	$context['non_guest_permissions'][] = 'bookmarks';

	// Allow admins to grant users the ability to create bookmarks
	$permissionList['membergroup']['make_bookmarks'] = [false, 'general'];
}

/**
 * Handles the removal of bookmarks associated with specific topics.
 *
 * This function is triggered via the integrate_remove_topics hook
 * and ensures that bookmarks linked to the provided topics are deleted.
 *
 * @param array|int $topics A single topic ID or an array of topic IDs to remove bookmarks for.
 * @return void
 */
function bmks_integrate_remove_topics($topics)
{
	require_once('Bookmarks.subs.php');

	delete_topic_bookmark($topics);
}

/**
 * Modifies the topic query to integrate bookmark functionality.
 *
 * Called via integrate_topic_query hook, typically invoked during topic queries.
 *
 * @param array $topic_selects An array of topic select query fragments, modified to include bookmark-related fields.
 * @param array $topic_tables An array of topic table query fragments, modified to include bookmark-related joins.
 * @return void
 */
function bmks_integrate_topic_query(&$topic_selects, &$topic_tables)
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
 * Integrates bookmark functionality into the display of a topic.
 *
 * Called via integrate_display_topic hook, called from Display.php
 *
 * @param array $topicinfo An associative array containing information about the current topic, including bookmark status.
 * @return void
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
 * Integrates bookmark functionality into the normal button array for topic display.
 *
 * This method determines if the current topic has been bookmarked by the user and dynamically adjusts
 * the display button options to show either a "Bookmark" or "Remove Bookmark" button.
 *
 * Called via integrate_display_buttons hook, called from Display.php
 *
 * @return void
 */
function bmks_integrate_display_buttons()
{
	global $context;

	if (!$context['can_make_bookmarks'])
	{
		return;
	}

	Txt::load('Bookmarks');

	if ($context['has_bookmark'])
	{
		$url = getUrl('action', ['action' => 'bookmarks', 'sa' => 'remove', 'remove_bookmark' => $context['current_topic'], '{session_data}']);
	}
	else
	{
		$url = getUrl('action', ['action' => 'bookmarks', 'sa' => 'add', 'topic' => $context['current_topic'], '{session_data}']);
	}

	// Define the new button
	$bookmarks = ['bookmarks' => [
		'test' => 'can_make_bookmarks',
		'text' => $context['has_bookmark'] ? 'bookmark_exists' : 'bookmark',
		'icon' => 'i-bookmark',
		'linkclass' => 'buttonlist_icon',
		'lang' => true,
		'url' => $url
	]];

	// Add bookmark to the normal button array
	$context['normal_buttons'] = elk_array_insert($context['normal_buttons'], 'reply', $bookmarks, 'after');
}

/**
 * Adds a "My Bookmarks" button to the menu if bookmarks are enabled and the user has permission.
 *
 * Called via integrate_menu_buttons hook, modifies the menu buttons array.
 *
 * @param array $buttons Reference to the global array of menu buttons to be modified.
 * @return void
 */
function bmks_integrate_menu_buttons(&$buttons)
{
	global $scripturl, $txt, $modSettings;

	// Not enable or not allowed
	if (empty($modSettings['bookmarks_enabled']) || !allowedTo('make_bookmarks'))
	{
		return;
	}

	Txt::load('Bookmarks');

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
