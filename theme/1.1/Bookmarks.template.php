<?php

function template_main()
{
	global $context, $scripturl, $txt;

	// Show the good or bad news, if any.
	if (isset($context['bookmark_result']))
	{
		echo '
			<div class="infobox" id="profile_success">
				', $context['bookmark_result'], '
			</div>';
	}

	// We know how to sprite these
	$message_icon_sprite = array('clip' => '', 'lamp' => '', 'poll' => '', 'question' => '', 'xx' => '', 'moved' => '', 'exclamation' => '', 'thumbup' => '', 'thumbdown' => '');

	$not_empty = !empty($context['bookmarks']);

	if ($not_empty)
	{
		template_pagesection('normal_buttons', 'right');
	}

	// Let's get the show moving.
	echo '
			<h3 class="category_header">', $txt['bookmark_list'], '</h3>';

	// Show the bookmarks, if any.
	if ($not_empty)
	{
		echo '
			<form class="generic_list_wrapper" action="', $scripturl, '?action=bookmarks;sa=delete" method="post">
				<table class="table_grid">
					<thead>
						<tr class="table_head">
							<th style="width:50px;"></th>
							<th class="grid33">', $txt['subject'], '</th>
							<th class="nowrap">', $txt['started_by'], '</th>
							<th class="centertext">', $txt['replies'], '</th>
							<th class="centertext">', $txt['views'], '</th>
							<th class="grid25">', $txt['latest_post'], '</th>
							<th class="centertext">
								<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />
							</th>
						</tr>
					</thead>
					<tbody>';

		foreach ($context['bookmarks'] as $topic)
		{
			// Show the topic's subject
			echo '
						<tr>
							<td>
								<p class="topic_icons', isset($message_icon_sprite[$topic['first_post']['icon']]) ? ' topicicon i-' . $topic['first_post']['icon'] : '', '">';

			if (!isset($message_icon_sprite[$topic['first_post']['icon']]))
			{
				echo '
									<img src="', $topic['first_post']['icon_url'], '" alt="" />';
			}

			echo '
								</p>
							</td>
							<td>';

			// Any new replies?
			if ($topic['new'])
			{
				echo '
								<a class="new_posts" href="', $topic['new_href'], '" id="newicon' . $topic['first_post']['id'] . '">' . $txt['new'] . '</a>';
			}

			// Show the board the topic was posted in, as well as a link to the profile of the topic starter
			echo
			$topic['first_post']['link'],
			'<br />
								<span class="smalltext"><i>', $txt['in'], ' ', $topic['board']['link'], '</i></span>
							</td>
							<td>', $topic['first_post']['member']['link'], '</td>
							<td class="centertext">', $topic['replies'], '</td>
							<td class="centertext">', $topic['views'], '</td>
							<td>
								<span class="smalltext">
									', $topic['last_post']['time'], '<br />
									', $txt['by'], ' ', $topic['last_post']['member']['link'], '
								</span>
								<a class="topicicon i-last_post" href="', $topic['last_post']['href'], '" title="', $txt['last_post'], '"></a>
							</td>
							<td class="centertext">
								<input type="checkbox" name="remove_bookmarks[]" value="', $topic['id'], '" class="input_check" />
							</td>
						</tr>';
		}

		echo '
					</tbody>
				</table>
				<div class="submitbutton">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="submit" name="send" value="', $txt['bookmark_delete'], '" />
				</div>
			</form>';
	}
	// Show a message saying there aren't any bookmarks yet
	else
	{
		echo '
			<div class="infobox">', $txt['bookmark_list_empty'], '</div>';
	}

	if ($not_empty)
	{
		template_pagesection('normal_buttons', 'right');
	}
}
