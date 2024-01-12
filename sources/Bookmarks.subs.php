<?php

/**
 * @package "Bookmarks" Addon for Elkarte
 * @author Aaron
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0.2
 *
 */

/**
 * Adds a bookmark for a certain topic / user.
 *
 * @param int $id_member
 * @param int $id_topic
 */
function addBookmark($id_member, $id_topic = null)
{
	$db = database();

	$id_topic = isset($id_topic) ? (int) $id_topic : (int) $_GET['topic'];

	// Add a bookmark for this user and topic
	$result = $db->insert('replace', '
		{db_prefix}bookmarks',
		array(
			'id_member' => 'int',
			'id_topic' => 'int',
		),
		array(
			'id_member' => $id_member,
			'id_topic' => $id_topic,
		),
		array()
	);

	return $db->affected_rows($result);
}

/**
 * Delete bookmarks for a certain user.
 *
 * @param int $id_member
 * @param int[] $topic_ids
 */
function deleteBookmarks($id_member, $topic_ids)
{
	$db = database();

	// Remove what we can
	$result = $db->query('', '
		DELETE FROM {db_prefix}bookmarks
		WHERE
			id_topic IN({array_int:topics})
		AND
			id_member = {int:id_member}',
		array(
			'id_member' => $id_member,
			'topics' => $topic_ids,
		)
	);

	// Return the amount of deleted bookmarks, unless an error occurred.
	return $result ? $db->affected_rows() : false;
}

function getCountBookmarks($id_member)
{
	global $modSettings;

	$db = database();

	$request = $db->query('substring', '
		SELECT COUNT(t.id_topic)
		FROM {db_prefix}bookmarks AS bm
			INNER JOIN {db_prefix}topics AS t ON (bm.id_topic = t.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
		WHERE
			bm.id_member = {int:current_member}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND (t.approved = {int:is_approved} OR t.id_member_started = {int:current_member})') . '
			AND {query_see_board}
		LIMIT 1',
		array(
			'current_member' => $id_member,
			'is_approved' => 1,
		)
	);
	$result = $db->fetch_row($request);
	$total = empty($result) ? 0 : (int) $result[0];
	$db->free_result($request);

	return $total;
}

/**
 * Gathers a list of all of this user's bookmarks.
 *
 * @param int $id_member
 */
function getBookmarks($id_member, $offset, $limit)
{
	global $settings, $scripturl, $modSettings, $user_info, $txt, $context;

	$db = database();

	$request = $db->query('substring', '
		SELECT
			t.id_topic, t.num_replies, t.locked, t.num_views, t.id_board, t.id_last_msg, t.id_first_msg,
			b.name AS board_name,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from,
			ml.poster_time AS last_poster_time, ml.id_msg_modified, ml.subject AS last_subject,
			ml.icon AS last_icon, ml.poster_name AS last_member_name, ml.id_member AS last_id_member,
			IFNULL(meml.real_name, ml.poster_name) AS last_display_name,
			mf.poster_time AS first_poster_time, mf.subject AS first_subject, mf.icon AS first_icon,
			mf.poster_name AS first_member_name, mf.id_member AS first_id_member,
			IFNULL(memf.real_name, mf.poster_name) AS first_display_name
		FROM {db_prefix}bookmarks AS bm
			INNER JOIN {db_prefix}topics AS t ON (bm.id_topic = t.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
			LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
		WHERE
			bm.id_member = {int:current_member}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND (t.approved = {int:is_approved} OR t.id_member_started = {int:current_member})') . '
			AND {query_see_board}
		ORDER BY t.id_last_msg DESC
		LIMIT {int:offset}, {int:limit}',
		array(
			'current_member' => $id_member,
			'is_approved' => 1,
			'offset' => $offset,
			'limit' => $limit,
		)
	);
	$bookmarks = array();
	while ($row = $db->fetch_assoc($request))
	{
		$row['first_subject'] = censor($row['first_subject']);
		$row['last_subject'] = censor($row['last_subject']);

		$bookmarks[$row['id_topic']] = array(
			'id' => $row['id_topic'],
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>'
			),
			'first_post' => array(
				'id' => $row['id_first_msg'],
				'member' => array(
					'username' => $row['first_member_name'],
					'name' => $row['first_display_name'],
					'id' => $row['first_id_member'],
					'href' => !empty($row['first_id_member']) ? $scripturl . '?action=profile;u=' . $row['first_id_member'] : '',
					'link' => !empty($row['first_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . '" title="' . $txt['profile_of'] . ' ' . $row['first_display_name'] . '">' . $row['first_display_name'] . '</a>' : $row['first_display_name']
				),
				'time' => standardTime($row['first_poster_time']),
				'timestamp' => forum_time(true, $row['first_poster_time']),
				'subject' => $row['first_subject'],
				'icon' => $row['first_icon'],
				'icon_url' => $settings['images_url'] . '/post/' . $row['first_icon'] . '.png',
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['first_subject'] . '</a>'
			),
			'last_post' => array(
				'id' => $row['id_last_msg'],
				'member' => array(
					'username' => $row['last_member_name'],
					'name' => $row['last_display_name'],
					'id' => $row['last_id_member'],
					'href' => !empty($row['last_id_member']) ? $scripturl . '?action=profile;u=' . $row['last_id_member'] : '',
					'link' => !empty($row['last_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['last_id_member'] . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name']
				),
				'time' => standardTime($row['last_poster_time']),
				'timestamp' => forum_time(true, $row['last_poster_time']),
				'subject' => $row['last_subject'],
				'icon' => $row['last_icon'],
				'icon_url' => $settings['images_url'] . '/post/' . $row['last_icon'] . '.gif',
				'href' => $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')),
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')) . '" ' . ($row['num_replies'] == 0 ? '' : 'rel="nofollow"') . '>' . $row['last_subject'] . '</a>'
			),
			'icon' => $row['first_icon'],
			'icon_url' => $settings['theme_url'] . '/post/' . $row['first_icon'] . '.png',
			'subject' => $row['first_subject'],
			'new' => $row['new_from'] <= $row['id_msg_modified'],
			'new_from' => $row['new_from'],
			'newtime' => $row['new_from'],
			'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
			'replies' => $row['num_replies'],
			'views' => $row['num_views'],
		);
	}
	$db->free_result($request);

	return $bookmarks;
}

/**
 * Bookmarks should be removed if their respective topics are being removed
 *
 * @param int[]|int $topics
 */
function delete_topic_bookmark($topics)
{
	$db = database();

	if (!is_array($topics))
	{
		$topics = array($topics);
	}

	$db->query('', '
		DELETE FROM {db_prefix}bookmarks
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
}
