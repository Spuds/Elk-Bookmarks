<?php

/**
 * @package "Bookmarks" Addon for Elkarte
 * @author Aaron
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0.2
 *
 */

class Bookmarks_Controller extends Action_Controller
{
	/**
	 * Holds the results of our add/delete actions
	 *
	 * @var string
	 */
	protected $_result;

	/**
	 * Default action method, if a specific bookmark method wasn't
	 * directly called. Simply forwards to main.
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
		global $txt, $context, $scripturl;

		// Actions here
		require_once(SUBSDIR . '/Action.class.php');

		// All we know
		$subActions = array(
			'main' => array($this, 'action_bookmarks_get', 'permission' => 'make_bookmarks'),
			'add' => array($this, 'action_bookmarks_add', 'permission' => 'make_bookmarks'),
			'delete' => array($this, 'action_bookmarks_delete', 'permission' => 'make_bookmarks'),
			'remove' => array($this, 'action_bookmark_remove', 'permission' => 'make_bookmarks'),
		);

		// Your bookmark activity will end here if you don't have permission.
		$action = new Action();

		// Load the template and language
		loadTemplate('Bookmarks');
		loadLanguage('Bookmarks');

		// db help is here
		require_once(SUBSDIR . '/Bookmarks.subs.php');

		// Set the page title
		$context['page_title'] = $txt['bookmarks'];

		// Add it to the linktree
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=bookmarks',
			'name' => $txt['bookmarks'],
		);

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
	 * Load users bookmarks in to context for display
	 */
	public function action_bookmarks_get()
	{
		global $user_info, $context, $scripturl;

		$total = getCountBookmarks($user_info['id']);
		if (!$total)
		{
			$context['bookmarks'] = array();

			return;
		}

		$offset = empty($_GET['start']) ? 0 : (int) $_GET['start'];
		$limit = 25;

		$context['page_index'] = constructPageIndex($scripturl . '?action=bookmarks', $offset, $total, $limit);
		$context['page_info'] = array(
			'current_page' => $offset / $limit + 1,
			'num_pages' => floor(($total - 1) / $limit) + 1,
		);

		// Load this user's bookmarks
		$context['bookmarks'] = getBookmarks($user_info['id'], $offset, $limit);
	}

	/**
	 * Adds a bookmark for a certain topic for a certain user.
	 */
	public function action_bookmarks_add()
	{
		global $user_info;

		checkSession('get');

		// No topic, can't add a bookmark then
		if (empty($_GET['topic']))
		{
			$this->_result = 'bookmark_add_failed';
		}
		else
		{
			$id_topic = (int) $_GET['topic'];

			// Add a bookmark for this user and topic
			$result = addBookmark($user_info['id'], $id_topic);
			$this->_result = $result == 0 ? 'bookmark_add_failed' : 'bookmark_add_success';
		}

		// reLoad this user's bookmarks
		$this->action_bookmarks_get();
	}

	/**
	 * Delete bookmarks for a certain user.
	 */
	public function action_bookmarks_delete()
	{
		global $user_info;

		checkSession();

		// None to remove, what are you doing?
		if (empty($_POST['remove_bookmarks']))
		{
			$this->_result = 'bookmark_delete_failure';
		}
		else
		{
			$topic_ids = array();

			// Make sure we have valid id's here.
			foreach ($_POST['remove_bookmarks'] as $index => $id)
			{
				$topic_ids[$index] = (int) $id;
			}

			// Remove what we can
			$result = deleteBookmarks($user_info['id'], $topic_ids);

			// Return the amount of deleted bookmarks, unless an error occurred.
			$this->_result = $result ? array('bookmark_delete_success', $result) : 'bookmark_delete_failure';
		}

		// reLoad this user's bookmarks
		$this->action_bookmarks_get();
	}

	/**
	 * Delete a single bookmark for a specific user.
	 */
	public function action_bookmark_remove()
	{
		global $user_info;

		checkSession('get');

		// None to remove, what are you doing?
		if (empty($_GET['remove_bookmark']))
		{
			$this->_result = 'bookmark_delete_failure';
		}
		else
		{
			$topic_ids = (int) $_GET['remove_bookmark'];

			// Remove it
			$result = deleteBookmarks($user_info['id'], (array) $topic_ids);

			// Return success or failure.
			$this->_result = $result ? array('bookmark_delete_success', $result) : 'bookmark_delete_failure';
		}

		// ReLoad this user's bookmarks
		$this->action_bookmarks_get();
	}
}