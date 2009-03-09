<?php

/**
 * Gather a listing of links to nodes.
 *
 * @param $result
 *   A DB result object from a query to fetch node objects. If your query
 *   joins the <code>node_comment_statistics</code> table so that the
 *   <code>comment_count</code> field is available, a title attribute will
 *   be added to show the number of comments.
 * @param $title
 *   A heading for the resulting list.
 *
 * @return
 *   An HTML list suitable as content for a block, or FALSE if no result can
 *   fetch from DB result object.
 */
function _real_node_title_list($result, $title = NULL) {
  $items = array();
  $num_rows = FALSE;
  while ($node = db_fetch_object($result)) {
    $items[] = l($node->title, 'node/'. $node->nid, !empty($node->comment_count) ? array('attributes' => array('title' => format_plural($node->comment_count, '1 comment', '@count comments'))) : array());
    $num_rows = TRUE;
  }

  return $num_rows ? theme('node_list', $items, $title) : FALSE;
}

/**
 * Generate a teaser for a node body.
 *
 * If the end of the teaser is not indicated using the <!--break--> delimiter
 * then we generate the teaser automatically, trying to end it at a sensible
 * place such as the end of a paragraph, a line break, or the end of a
 * sentence (in that order of preference).
 *
 * @param $body
 *   The content for which a teaser will be generated.
 * @param $format
 *   The format of the content. If the content contains PHP code, we do not
 *   split it up to prevent parse errors. If the line break filter is present
 *   then we treat newlines embedded in $body as line breaks.
 * @param $size
 *   The desired character length of the teaser. If omitted, the default
 *   value will be used. Ignored if the special delimiter is present
 *   in $body.
 * @return
 *   The generated teaser.
 */
function _real_node_teaser($body, $format = NULL, $size = NULL) {

  if (!isset($size)) {
    $size = variable_get('teaser_length', 600);
  }

  // Find where the delimiter is in the body
  $delimiter = strpos($body, '<!--break-->');

  // If the size is zero, and there is no delimiter, the entire body is the teaser.
  if ($size == 0 && $delimiter === FALSE) {
    return $body;
  }

  // If a valid delimiter has been specified, use it to chop off the teaser.
  if ($delimiter !== FALSE) {
    return substr($body, 0, $delimiter);
  }

  // We check for the presence of the PHP evaluator filter in the current
  // format. If the body contains PHP code, we do not split it up to prevent
  // parse errors.
  if (isset($format)) {
    $filters = filter_list_format($format);
    if (isset($filters['php/0']) && strpos($body, '<?') !== FALSE) {
      return $body;
    }
  }

  // If we have a short body, the entire body is the teaser.
  if (drupal_strlen($body) <= $size) {
    return $body;
  }

  // If the delimiter has not been specified, try to split at paragraph or
  // sentence boundaries.

  // The teaser may not be longer than maximum length specified. Initial slice.
  $teaser = truncate_utf8($body, $size);

  // Store the actual length of the UTF8 string -- which might not be the same
  // as $size.
  $max_rpos = strlen($teaser);

  // How much to cut off the end of the teaser so that it doesn't end in the
  // middle of a paragraph, sentence, or word.
  // Initialize it to maximum in order to find the minimum.
  $min_rpos = $max_rpos;

  // Store the reverse of the teaser.  We use strpos on the reversed needle and
  // haystack for speed and convenience.
  $reversed = strrev($teaser);

  // Build an array of arrays of break points grouped by preference.
  $break_points = array();

  // A paragraph near the end of sliced teaser is most preferable.
  $break_points[] = array('</p>' => 0);

  // If no complete paragraph then treat line breaks as paragraphs.
  $line_breaks = array('<br />' => 6, '<br>' => 4);
  // Newline only indicates a line break if line break converter
  // filter is present.
  if (isset($filters['filter/1'])) {
    $line_breaks["\n"] = 1;
  }
  $break_points[] = $line_breaks;

  // If the first paragraph is too long, split at the end of a sentence.
  $break_points[] = array('. ' => 1, '! ' => 1, '? ' => 1, '。' => 0, '؟ ' => 1);

  // Iterate over the groups of break points until a break point is found.
  foreach ($break_points as $points) {
    // Look for each break point, starting at the end of the teaser.
    foreach ($points as $point => $offset) {
      // The teaser is already reversed, but the break point isn't.
      $rpos = strpos($reversed, strrev($point));
      if ($rpos !== FALSE) {
        $min_rpos = min($rpos + $offset, $min_rpos);
      }
    }

    // If a break point was found in this group, slice and return the teaser.
    if ($min_rpos !== $max_rpos) {
      // Don't slice with length 0.  Length must be <0 to slice from RHS.
      return ($min_rpos === 0) ? $teaser : substr($teaser, 0, 0 - $min_rpos);
    }
  }

  // If a break point was not found, still return a teaser.
  return $teaser;
}

/**
 * Theme a log message.
 *
 * @ingroup themeable
 */
function _real_theme_node_log_message($log) {
  return '<div class="log"><div class="title">'. t('Log') .':</div>'. $log .'</div>';
}

/**
 * Return a list of all the existing revision numbers.
 */
function _real_node_revision_list($node) {
  $revisions = array();
  $result = db_query('SELECT r.vid, r.title, r.log, r.uid, n.vid AS current_vid, r.timestamp, u.name FROM {node_revisions} r LEFT JOIN {node} n ON n.vid = r.vid INNER JOIN {users} u ON u.uid = r.uid WHERE r.nid = %d ORDER BY r.timestamp DESC', $node->nid);
  while ($revision = db_fetch_object($result)) {
    $revisions[$revision->vid] = $revision;
  }

  return $revisions;
}

/**
 * Implementation of hook_block().
 */
function _real_node_block($op = 'list', $delta = 0) {
  if ($op == 'list') {
    $blocks[0]['info'] = t('Syndicate');
    // Not worth caching.
    $blocks[0]['cache'] = BLOCK_NO_CACHE;
    return $blocks;
  }
  else if ($op == 'view') {
    $block['subject'] = t('Syndicate');
    $block['content'] = theme('feed_icon', url('rss.xml'), t('Syndicate'));

    return $block;
  }
}

/**
 * A generic function for generating RSS feeds from a set of nodes.
 *
 * @param $nids
 *   An array of node IDs (nid). Defaults to FALSE so empty feeds can be
 *   generated with passing an empty array, if no items are to be added
 *   to the feed.
 * @param $channel
 *   An associative array containing title, link, description and other keys.
 *   The link should be an absolute URL.
 */
function _real_node_feed($nids = FALSE, $channel = array()) {
  global $base_url, $language;

  if ($nids === FALSE) {
    $nids = array();
    $result = db_query_range(db_rewrite_sql('SELECT n.nid, n.created FROM {node} n WHERE n.promote = 1 AND n.status = 1 ORDER BY n.created DESC'), 0, variable_get('feed_default_items', 10));
    while ($row = db_fetch_object($result)) {
      $nids[] = $row->nid;
    }
  }

  $item_length = variable_get('feed_item_length', 'teaser');
  $namespaces = array('xmlns:dc' => 'http://purl.org/dc/elements/1.1/');

  $items = '';
  foreach ($nids as $nid) {
    // Load the specified node:
    $item = node_load($nid);
    $item->build_mode = NODE_BUILD_RSS;
    $item->link = url("node/$nid", array('absolute' => TRUE));

    if ($item_length != 'title') {
      $teaser = ($item_length == 'teaser') ? TRUE : FALSE;

      // Filter and prepare node teaser
      if (node_hook($item, 'view')) {
        $item = node_invoke($item, 'view', $teaser, FALSE);
      }
      else {
        $item = node_prepare($item, $teaser);
      }

      // Allow modules to change $node->content before the node is rendered.
      node_invoke_nodeapi($item, 'view', $teaser, FALSE);

      // Set the proper node property, then unset unused $node property so that a
      // bad theme can not open a security hole.
      $content = drupal_render($item->content);
      if ($teaser) {
        $item->teaser = $content;
        unset($item->body);
      }
      else {
        $item->body = $content;
        unset($item->teaser);
      }
    
      // Allow modules to modify the fully-built node.
      node_invoke_nodeapi($item, 'alter', $teaser, FALSE);
    }

    // Allow modules to add additional item fields and/or modify $item
    $extra = node_invoke_nodeapi($item, 'rss item');
    $extra = array_merge($extra, array(array('key' => 'pubDate', 'value' => gmdate('r', $item->created)), array('key' => 'dc:creator', 'value' => $item->name), array('key' => 'guid', 'value' => $item->nid .' at '. $base_url, 'attributes' => array('isPermaLink' => 'false'))));
    foreach ($extra as $element) {
      if (isset($element['namespace'])) {
        $namespaces = array_merge($namespaces, $element['namespace']);
      }
    }

    // Prepare the item description
    switch ($item_length) {
      case 'fulltext':
        $item_text = $item->body;
        break;
      case 'teaser':
        $item_text = $item->teaser;
        if (!empty($item->readmore)) {
          $item_text .= '<p>'. l(t('read more'), 'node/'. $item->nid, array('absolute' => TRUE, 'attributes' => array('target' => '_blank'))) .'</p>';
        }
        break;
      case 'title':
        $item_text = '';
        break;
    }

    $items .= format_rss_item($item->title, $item->link, $item_text, $extra);
  }

  $channel_defaults = array(
    'version'     => '2.0',
    'title'       => variable_get('site_name', 'Drupal'),
    'link'        => $base_url,
    'description' => variable_get('site_mission', ''),
    'language'    => $language->language
  );
  $channel = array_merge($channel_defaults, $channel);

  $output = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
  $output .= "<rss version=\"". $channel["version"] ."\" xml:base=\"". $base_url ."\" ". drupal_attributes($namespaces) .">\n";
  $output .= format_rss_channel($channel['title'], $channel['link'], $channel['description'], $items, $channel['language']);
  $output .= "</rss>\n";

  drupal_set_header('Content-Type: application/rss+xml; charset=utf-8');
  print $output;
}

/**
 * Generate an SQL join clause for use in fetching a node listing.
 *
 * @param $node_alias
 *   If the node table has been given an SQL alias other than the default
 *   "n", that must be passed here.
 * @param $node_access_alias
 *   If the node_access table has been given an SQL alias other than the default
 *   "na", that must be passed here.
 * @return
 *   An SQL join clause.
 */
function _real__node_access_join_sql($node_alias = 'n', $node_access_alias = 'na') {
  if (user_access('administer nodes')) {
    return '';
  }

  return 'INNER JOIN {node_access} '. $node_access_alias .' ON '. $node_access_alias .'.nid = '. $node_alias .'.nid';
}

/**
 * Generate an SQL where clause for use in fetching a node listing.
 *
 * @param $op
 *   The operation that must be allowed to return a node.
 * @param $node_access_alias
 *   If the node_access table has been given an SQL alias other than the default
 *   "na", that must be passed here.
 * @param $account
 *   The user object for the user performing the operation. If omitted, the
 *   current user is used.
 * @return
 *   An SQL where clause.
 */
function _real__node_access_where_sql($op = 'view', $node_access_alias = 'na', $account = NULL) {
  if (user_access('administer nodes')) {
    return;
  }

  $grants = array();
  foreach (node_access_grants($op, $account) as $realm => $gids) {
    foreach ($gids as $gid) {
      $grants[] = "($node_access_alias.gid = $gid AND $node_access_alias.realm = '$realm')";
    }
  }

  $grants_sql = '';
  if (count($grants)) {
    $grants_sql = 'AND ('. implode(' OR ', $grants) .')';
  }

  $sql = "$node_access_alias.grant_$op >= 1 $grants_sql";
  return $sql;
}

/**
 * Format the "Submitted by username on date/time" for each node
 *
 * @ingroup themeable
 */
function _real_theme_node_submitted($node) {
  return t('Submitted by !username on @datetime',
    array(
      '!username' => theme('username', $node),
      '@datetime' => format_date($node->created),
    ));
}

/**
 * Implementation of hook_hook_info().
 */
function _real_node_hook_info() {
  return array(
    'node' => array(
      'nodeapi' => array(
        'presave' => array(
          'runs when' => t('When either saving a new post or updating an existing post'),
        ),
        'insert' => array(
          'runs when' => t('After saving a new post'),
        ),
        'update' => array(
          'runs when' => t('After saving an updated post'),
        ),
        'delete' => array(
          'runs when' => t('After deleting a post')
        ),
        'view' => array(
          'runs when' => t('When content is viewed by an authenticated user')
        ),
      ),
    ),
  );
}
