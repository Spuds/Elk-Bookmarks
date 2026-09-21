<?php

/**
 * @package "Bookmarks" Addon for Elkarte
 * @author Aaron
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0.0
 *
 */

namespace Addons\Bookmarks;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Languages\Txt;
use ElkArte\User;

class Bookmarks extends AbstractController
{
	/** @var string Holds the results of our add/delete actions */
	protected $_result;

	/**
	 * Default action method, if a specific bookmark method wasn't
	 * directly called. Simply forwards to action_bookmarks_main.
	 */
	public function action_index()
	{
		$this->action_bookmarks_main();
	}

	/**
	 * Entry point for all bookmark actions
	 */
	public function action_bookmarks_main()
	{
		global $txt, $context;

		// All we know
		$subActions = [
			'main' => [$this, 'action_bookmarks_get', 'permission' => 'make_bookmarks'],
			'add' => [$this, 'action_bookmarks_add', 'permission' => 'make_bookmarks'],
			'delete' => [$this, 'action_bookmarks_delete', 'permission' => 'make_bookmarks'],
			'remove' => [$this, 'action_bookmark_remove', 'permission' => 'make_bookmarks'],
		];

		// Your bookmark activity will end here if you don't have permission.
		$action = new Action();

		// Load the template and language
		theme()->getTemplates()->load('Bookmarks');
		Txt::load('Bookmarks');

		// db help is here
		require_once('Bookmarks.subs.php');

		// Set the page title
		$context['page_title'] = $txt['bookmarks'];

		// Add it to the linktree
		$context['breadcrumbs'][] = [
			'url' => getUrl('action', ['action' => 'bookmarks']),
			'name' => $txt['bookmarks'],
		];

		// Default to sub-action 'main' if they have asked for something odd
		$subAction = $action->initialize($subActions, 'main');
		$context['sub_action'] = $subAction;

		// Call the right function
		$action->dispatch($subAction);

		// Set any messages
		if (!empty($this->_result))
		{
			$context['bookmark_result'] = is_array($this->_result) ? sprintf($txt[$this->_result[0]], $this->_result[1]) : $txt[$this->_result];
		}
	}

	/**
	 * Load bookmarks in to context for display
	 */
	public function action_bookmarks_get()
	{
		global $context, $scripturl;

		$total = getCountBookmarks(User::$info->id);
		if (!$total)
		{
			$context['bookmarks'] = [];

			return;
		}

		$offset = $this->_req->getQuery('start', 'intval', 0);
		$limit = 25;

		$context['page_index'] = constructPageIndex($scripturl . '?action=bookmarks', $offset, $total, $limit);
		$context['page_info'] = [
			'current_page' => $offset / $limit + 1,
			'num_pages' => floor(($total - 1) / $limit) + 1,
		];

		// Load this user's bookmarks
		$context['bookmarks'] = getBookmarks(User::$info->id, $offset, $limit);
	}

	/**
	 * Adds a bookmark for a certain topic for a certain user.
	 */
	public function action_bookmarks_add()
	{
		checkSession('get');

		// No topic, can't add a bookmark then
		if (empty($_GET['topic']))
		{
			$this->_result = 'bookmark_add_failed';
		}
		else
		{
			$id_topic = $this->_req->getQuery('topic', 'intval');

			// Add a bookmark for this user and topic
			$result = addBookmark(User::$info->id, $id_topic);
			$this->_result = $result === 0 ? 'bookmark_add_failed' : 'bookmark_add_success';
		}

		// reLoad this user's bookmarks
		$this->action_bookmarks_get();
	}

	/**
	 * Delete bookmarks for a certain user.
	 */
	public function action_bookmarks_delete()
	{
		checkSession();

		// None to remove, what are you doing?
		if (empty($this->_req->getPost('remove_bookmarks')))
		{
			$this->_result = 'bookmark_delete_failure';
		}
		else
		{
			// Make sure we have valid id's here.
			$topic_ids = array_map(static function ($id) {
				return (int) $id;
			}, $this->_req->getPost('remove_bookmarks'));

			// Remove what we can
			$result = deleteBookmarks(User::$info->id, $topic_ids);

			// Return the amount of deleted bookmarks, unless an error occurred.
			$this->_result = $result ? ['bookmark_delete_success', $result] : 'bookmark_delete_failure';
		}

		// reLoad this user's bookmarks
		$this->action_bookmarks_get();
	}

	/**
	 * Delete a single bookmark for a specific user.
	 */
	public function action_bookmark_remove()
	{
		checkSession('get');

		$topic_ids = $this->_req->getQuery('remove_bookmark', 'intval');

		// None to remove, what are you doing?
		if (empty($topic_ids))
		{
			$this->_result = 'bookmark_delete_failure';
		}
		else
		{
			// Remove it
			$result = deleteBookmarks(User::$info->id, (array) $topic_ids);

			// Return success or failure.
			$this->_result = $result ? ['bookmark_delete_success', $result] : 'bookmark_delete_failure';
		}

		// ReLoad this user's bookmarks
		$this->action_bookmarks_get();
	}
}
