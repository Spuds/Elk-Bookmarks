<?php

/**
 * @package "Bookmarks" Addon for Elkarte
 * @author Aaron
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0.0
 *
 */

use ElkArte\User;

/**
 * Adds a bookmark for a certain topic / user.
 *
 * @param int $id_member
 * @param int $id_topic
 */
function addBookmark($id_member, $id_topic = null)
{
	$db = database();

	$id_topic = $id_topic ?? (int) $_GET['topic'];

	// Add a bookmark for this user and topic
	$result = $db->insert('replace', '
		{db_prefix}bookmarks',
		[
			'id_member' => 'int',
			'id_topic' => 'int',
		],
		[
			'id_member' => $id_member,
			'id_topic' => $id_topic,
		],
		[]
	);

	return $result->affected_rows();
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
		[
			'id_member' => $id_member,
			'topics' => $topic_ids,
		]
	);

	// Return the number of deleted bookmarks, unless an error occurred.
	return $result ? $result->affected_rows() : false;
}

function getCountBookmarks($id_member)
{
	global $modSettings;

	$db = database();

	$request = $db->query('substring', '
		SELECT 
			COUNT(t.id_topic)
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
		[
			'current_member' => $id_member,
			'is_approved' => 1,
		]
	);
	$result = $request->fetch_row();
	$total = empty($result) ? 0 : (int) $result[0];
	$request->free_result();

	return $total;
}

/**
 * Gathers a list of all of this user's bookmarks.
 *
 * @param int $id_member
 */
function getBookmarks($id_member, $offset, $limit)
{
	global $settings, $scripturl, $modSettings, $txt, $context;

	$db = database();

	$bookmarks = [];
	$db->fetchQuery('
		SELECT
			t.id_topic, t.num_replies, t.locked, t.is_sticky, t.num_views, t.id_board, t.id_last_msg, t.id_first_msg, t.num_likes,
			b.name AS board_name,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from,
			ml.poster_time AS last_poster_time, ml.id_msg_modified, ml.subject AS last_subject,
			ml.icon AS last_icon, ml.poster_name AS last_member_name, ml.id_member AS last_id_member,
			IFNULL(meml.real_name, ml.poster_name) AS last_display_name,
			mf.poster_time AS first_poster_time, mf.subject AS first_subject, mf.icon AS first_icon,
			mf.poster_name AS first_member_name, mf.id_member AS first_id_member,
			IFNULL(memf.real_name, mf.poster_name) AS first_display_name,
			meml.avatar, COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, meml.email_address
		FROM {db_prefix}bookmarks AS bm
			INNER JOIN {db_prefix}topics AS t ON (bm.id_topic = t.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = ml.id_member AND a.id_member != 0)
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
		[
			'current_member' => $id_member,
			'is_approved' => 1,
			'offset' => $offset,
			'limit' => $limit,
		]
	)->fetch_callback(
		function ($row) use (&$bookmarks, $txt, $settings, $scripturl) {
			$row['first_subject'] = censor($row['first_subject']);
			$row['last_subject'] = censor($row['last_subject']);
			$row['num_replies'] = (int) $row['num_replies'];
			$url = generateTopicUrl($row);

			$bookmarks[$row['id_topic']] = [
				'id' => $row['id_topic'],
				'board' => [
					'id' => $row['id_board'],
					'name' => $row['board_name'],
					'href' => getUrl('board', ['board' => $row['id_board'], 'start' => '0']),
					'link' => '<a href="' . getUrl('board', ['board' => $row['id_board'], 'start' => '0']) . '">' . $row['board_name'] . '</a>'
				],
				'first_post' => [
					'id' => $row['id_first_msg'],
					'member' => [
						'username' => $row['first_member_name'],
						'name' => $row['first_display_name'],
						'id' => $row['first_id_member'],
						'href' => !empty($row['first_id_member']) ? getUrl('profile', ['action' => 'profile', 'u' => $row['first_id_member']]) : '',
						'link' => !empty($row['first_id_member']) ? '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $row['first_id_member']]) . '" title="' . $txt['profile_of'] . ' ' . $row['first_display_name'] . '">' . $row['first_display_name'] . '</a>' : $row['first_display_name']
					],
					'time' => standardTime($row['first_poster_time']),
					'timestamp' => forum_time(true, $row['first_poster_time']),
					'html_time' => htmlTime($row['first_poster_time']),
					'subject' => $row['first_subject'],
					'icon' => $row['first_icon'],
					'icon_url' => $settings['images_url'] . '/post/' . $row['first_icon'] . '.png',
					'href' => getUrl('topic',['topic' => $row['id_topic'], 'start' => '0']),
					'link' => '<a href="' . getUrl('topic',['topic' => $row['id_topic'], 'start' => '0']) .'">' . $row['first_subject'] . '</a>'
				],
				'last_post' => [
					'id' => $row['id_last_msg'],
					'member' => [
						'username' => $row['last_member_name'],
						'name' => $row['last_display_name'],
						'id' => $row['last_id_member'],
						'href' => !empty($row['last_id_member']) ? getUrl('profile', ['action' => 'profile','u' => $row['last_id_member']]) : '',
						'link' => !empty($row['last_id_member']) ? '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $row['last_id_member']]) . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name']
					],
					'time' => standardTime($row['last_poster_time']),
					'timestamp' => forum_time(true, $row['last_poster_time']),
					'html_time' => htmlTime($row['last_poster_time']),
					'subject' => $row['last_subject'],
					'icon' => $row['last_icon'],
					'icon_url' => $settings['images_url'] . '/post/' . $row['last_icon'] . '.png',
					'href' => $url,
					'link' => '<a href="' . $url . '"' . ($row['num_replies'] === 0 ? '' : ' rel="nofollow"') . '>' . $row['last_subject'] . '</a>'
				],
				'icon' => $row['first_icon'],
				'icon_url' => $settings['theme_url'] . '/post/' . $row['first_icon'] . '.png',
				'subject' => $row['first_subject'],
				'new' => $row['new_from'] <= $row['id_msg_modified'],
				'new_from' => $row['new_from'],
				'newtime' => $row['new_from'],
				'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
				'replies' => comma_format($row['num_replies']),
				'views' => comma_format($row['num_views']),
				'likes' => comma_format($row['num_likes']),
				'is_sticky' => !empty($row['is_sticky']),
				'is_locked' => !empty($row['locked']),
			];

			if (isset($row['avatar']) || !empty($row['id_attach']))
			{
				$bookmarks[$row['id_topic']]['last_post']['member']['avatar'] = determineAvatar($row);
			}
		}
	);

	return bookmark_topics_participating($bookmarks);
}

/**
 * Generates a URL for a specific topic, customized based on user status and preferences.
 *
 * @param array $row An associative array containing topic information. Expected keys:
 *                   - 'id_topic' (int): The ID of the topic.
 *                   - 'id_last_msg' (int): The ID of the last message in the topic.
 *                   - 'num_replies' (int): The number of replies in the topic.
 * @return string The generated topic URL.
 */
function generateTopicUrl($row)
{
	global $scripturl, $options, $context;

	$topicBase = $scripturl . '?topic=' . $row['id_topic'];

	if (User::$info->is_guest) {
		$page = !empty($options['view_newest_first'])
			? 0
			: ((int) (($row['num_replies']) / (int) $context['pageindex_multiplier'])) * (int) $context['pageindex_multiplier'];
		$anchor = '#msg' . $row['id_last_msg'];
		return $topicBase . '.' . $page . $anchor;
	}

	$suffix = $row['num_replies'] === 0 ? '.0' : '.msg' . $row['id_last_msg'];
	return $topicBase . $suffix . '#new';
}

/**
 * Updates the provided list of bookmarked topics to indicate whether the current user has participated in them.
 *
 * @param array $bookmarks An associative array of bookmarked topics, where the keys are topic IDs and the values
 *                         contain topic details. Each topic may be updated with an 'is_posted_in' flag if
 *                         participation is detected.
 *
 * @return array The updated array of bookmarked topics with participation information added where applicable.
 */
function bookmark_topics_participating($bookmarks)
{
	global $modSettings;

	if (!empty($modSettings['enableParticipation']) && User::$info->is_guest === false && !empty($bookmarks))
	{
		require_once(SUBSDIR . '/MessageIndex.subs.php');
		$topics_participated_in = topicsParticipation(User::$info->id, array_keys($bookmarks));
		foreach ($topics_participated_in as $participated)
		{
			$bookmarks[$participated['id_topic']]['is_posted_in'] = true;
		}
	}

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
		$topics = [$topics];
	}

	$db->query('', '
		DELETE FROM {db_prefix}bookmarks
		WHERE id_topic IN ({array_int:topics})',
		[
			'topics' => $topics,
		]
	);
}
