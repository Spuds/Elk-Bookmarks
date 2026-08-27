<?php

/**
 * @package "Bookmarks" Addon for Elkarte
 * @author Aaron
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0.0
 *
 */

function template_main()
{
	global $context, $scripturl, $txt, $modSettings;

	// Show the good or bad news, if any.
	if (isset($context['bookmark_result']))
	{
		echo '
			<div class="infobox" id="profile_success">
				', $context['bookmark_result'], '
			</div>';

		unset($context['bookmark_result']);
	}

	if (!empty($context['bookmarks']))
	{
		template_pagesection('normal_buttons', 'right');
	}

	echo '
	<h2 class="category_header hdicon i-bookmark">', $txt['bookmark_list'], '</h2>
	<form class="clear" action="', $scripturl, '?action=bookmarks;sa=delete" method="post" accept-charset="UTF-8" name="bookmarkForm" id="bookmarkForm">
		<main>
			<ul class="topic_listing" id="messageindex">';

	// Show a message saying there aren't any bookmarks yet
	if (empty($context['bookmarks']))
	{
		echo '
				<li class="basic_row">
					<div class="topic_info">
						<div class="topic_name">
							<h4>
								<strong>', $txt['bookmark_list_empty'], '</strong>
							</h4>
						</div>
					</div>
				</li>';
	}

	foreach ($context['bookmarks'] as $topic)
	{
		$color_class = 'basic_row';

		// We start with locked and sticky topics.
		if ($topic['is_sticky'] && $topic['is_locked'])
		{
			$color_class = 'locked_row sticky_row';
		}
		// Sticky topics should get a different color, too.
		elseif ($topic['is_sticky'])
		{
			$color_class = 'sticky_row';
		}
		// Locked topics get special treatment as well.
		elseif ($topic['is_locked'])
		{
			$color_class = 'locked_row';
		}

		// First up the message icon
		echo '
				<li class="' . $color_class . '">
					<div class="topic_icons', empty($modSettings['messageIcons_enable']) ? ' topicicon i-' . $topic['first_post']['icon'] : '', '">';

		if (!empty($modSettings['messageIcons_enable']))
		{
			echo '
						<img src="', $topic['first_post']['icon_url'], '" alt="" />';
		}

		echo '
						', !empty($topic['is_posted_in']) ? '<span class="fred topicicon i-profile"></span>' : '', '
					</div>';

		// The subject/poster section
		echo '
					<div class="topic_info">
						<div class="topic_name" ', (empty($topic['quick_mod']['modify']) ? '' : 'id="topic_' . $topic['first_post']['id'] . '"  ondblclick="oQuickModifyTopic.modify_topic(\'' . $topic['id'] . "', '" . $topic['first_post']['id'] . '\');"'), '>
							<h4>';

		// Is this topic new? (assuming they are logged in!)
		if ($topic['new'] && $context['user']['is_logged'])
		{
			echo '
								<a class="new_posts" href="', $topic['new_href'], '" id="newicon' . $topic['first_post']['id'] . '">' . $txt['new'] . '</a>';
		}

		echo '
								', $topic['is_sticky'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span>', $topic['is_sticky'] ? '</strong>' : '', '
							</h4>
						</div>
						<div class="topic_starter">
							', sprintf($txt['topic_started_by'], $topic['first_post']['member']['link']), empty($topic['pages']) ? '' : '
							<ul class="small_pagelinks" id="pages' . $topic['first_post']['id'] . '">' . $topic['pages'] . '</ul>', '
						</div>
					</div>';

		// The last poster info, avatar, when
		echo '
					<div class="topic_latest">';

		if (!empty($topic['last_post']['member']['avatar']))
		{
			echo '
						<span class="board_avatar">
							<a href="', $topic['last_post']['member']['href'], '">
								<img class="avatar" src="', $topic['last_post']['member']['avatar']['href'], '" alt="', $topic['last_post']['member']['name'], '" loading="lazy" />
							</a>
						</span>';
		}
		else
		{
			echo '
						<span class="board_avatar">
							<a href="#"></a>
						</span>';
		}

		echo '
					<span class="topic_latest_info">
						<span>', $topic['last_post']['html_time'], '</span>
						<span>', $txt['by'], ' ', $topic['last_post']['member']['link'], '</span>
					</span>
					<a class="topicicon i-last_post" href="', $topic['last_post']['href'], '" title="', $txt['last_post'], '"></a>
				</div>';

		// The stats section
		echo '
					<div class="topic_stats">
						', $topic['replies'], ' ', $txt['replies'], '<br />
						', $topic['views'], ' ', $txt['views'];

		echo '
					</div>
					<div class="topic_moderation">
						<input type="checkbox" name="remove_bookmarks[]" aria-label="check ', $topic['id'], '" value="', $topic['id'], '" />
					</div>
				</li>';
	}

	echo '
			</ul>
		</main>
		<div class="submitbutton">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="submit" name="send" value="', $txt['bookmark_delete'], '" />
		</div>
	</form>';

	if (!empty($context['bookmarks']))
	{
		template_pagesection('normal_buttons', 'right');
	}
}
