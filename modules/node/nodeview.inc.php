<?php

/**
 * Update the 'last viewed' timestamp of the specified node for current user.
 */
function _real_node_tag_new($nid) {
  global $user;

  if ($user->uid) {
    if (node_last_viewed($nid)) {
      db_query('UPDATE {history} SET timestamp = %d WHERE uid = %d AND nid = %d', time(), $user->uid, $nid);
    }
    else {
      @db_query('INSERT INTO {history} (uid, nid, timestamp) VALUES (%d, %d, %d)', $user->uid, $nid, time());
    }
  }
}

/**
 * Retrieves the timestamp at which the current user last viewed the
 * specified node.
 */
function _real_node_last_viewed($nid) {
  global $user;
  static $history;

  if (!isset($history[$nid])) {
    $history[$nid] = db_fetch_object(db_query("SELECT timestamp FROM {history} WHERE uid = %d AND nid = %d", $user->uid, $nid));
  }

  return (isset($history[$nid]->timestamp) ? $history[$nid]->timestamp : 0);
}

/**
 * Decide on the type of marker to be displayed for a given node.
 *
 * @param $nid
 *   Node ID whose history supplies the "last viewed" timestamp.
 * @param $timestamp
 *   Time which is compared against node's "last viewed" timestamp.
 * @return
 *   One of the MARK constants.
 */
function _real_node_mark($nid, $timestamp) {
  global $user;
  static $cache;

  if (!$user->uid) {
    return MARK_READ;
  }
  if (!isset($cache[$nid])) {
    $cache[$nid] = node_last_viewed($nid);
  }
  if ($cache[$nid] == 0 && $timestamp > NODE_NEW_LIMIT) {
    return MARK_NEW;
  }
  elseif ($timestamp > $cache[$nid] && $timestamp > NODE_NEW_LIMIT) {
    return MARK_UPDATED;
  }
  return MARK_READ;
}

/**
 * Invoke a node hook.
 *
 * @param &$node
 *   Either a node object, node array, or a string containing the node type.
 * @param $hook
 *   A string containing the name of the hook.
 * @param $a2, $a3, $a4
 *   Arguments to pass on to the hook, after the $node argument.
 * @return
 *   The returned value of the invoked hook.
 */
function _real_node_invoke(&$node, $hook, $a2 = NULL, $a3 = NULL, $a4 = NULL) {
  if (node_hook($node, $hook)) {
    $module = node_get_types('module', $node);
    if ($module == 'node') {
      $module = 'node_content'; // Avoid function name collisions.
    }
    $function = $module .'_'. $hook;
    return ($function($node, $a2, $a3, $a4));
  }
}

/**
 * Invoke a hook_nodeapi() operation in all modules.
 *
 * @param &$node
 *   A node object.
 * @param $op
 *   A string containing the name of the nodeapi operation.
 * @param $a3, $a4
 *   Arguments to pass on to the hook, after the $node and $op arguments.
 * @return
 *   The returned value of the invoked hooks.
 */
function _real_node_invoke_nodeapi(&$node, $op, $a3 = NULL, $a4 = NULL) {
  $return = array();
  foreach (module_implements('nodeapi') as $name) {
    $function = $name .'_nodeapi';
    $result = $function($node, $op, $a3, $a4);
    if (isset($result) && is_array($result)) {
      $return = array_merge($return, $result);
    }
    else if (isset($result)) {
      $return[] = $result;
    }
  }
  return $return;
}

/**
 * Generate a display of the given node.
 *
 * @param $node
 *   A node array or node object.
 * @param $teaser
 *   Whether to display the teaser only or the full form.
 * @param $page
 *   Whether the node is being displayed by itself as a page.
 * @param $links
 *   Whether or not to display node links. Links are omitted for node previews.
 *
 * @return
 *   An HTML representation of the themed node.
 */
function _real_node_view($node, $teaser = FALSE, $page = FALSE, $links = TRUE) {
  $node = (object)$node;

  $node = node_build_content($node, $teaser, $page);

  if ($links) {
    $node->links = module_invoke_all('link', 'node', $node, $teaser);
    drupal_alter('link', $node->links, $node);
  }

  // Set the proper node part, then unset unused $node part so that a bad
  // theme can not open a security hole.
  $content = drupal_render($node->content);
  if ($teaser) {
    $node->teaser = $content;
    unset($node->body);
  }
  else {
    $node->body = $content;
    unset($node->teaser);
  }

  // Allow modules to modify the fully-built node.
  node_invoke_nodeapi($node, 'alter', $teaser, $page);

  return theme('node', $node, $teaser, $page);
}

/**
 * Apply filters and build the node's standard elements.
 */
function _real_node_prepare($node, $teaser = FALSE) {
  // First we'll overwrite the existing node teaser and body with
  // the filtered copies! Then, we'll stick those into the content
  // array and set the read more flag if appropriate.
  $node->readmore = $node->teaser != $node->body;

  if ($teaser == FALSE) {
    $node->body = check_markup($node->body, $node->format, FALSE);
  }
  else {
    $node->teaser = check_markup($node->teaser, $node->format, FALSE);
  }

  $node->content['body'] = array(
    '#value' => $teaser ? $node->teaser : $node->body,
    '#weight' => 0,
  );

  return $node;
}

/**
 * Builds a structured array representing the node's content.
 *
 * @param $node
 *   A node object.
 * @param $teaser
 *   Whether to display the teaser only, as on the main page.
 * @param $page
 *   Whether the node is being displayed by itself as a page.
 *
 * @return
 *   An structured array containing the individual elements
 *   of the node's body.
 */
function _real_node_build_content($node, $teaser = FALSE, $page = FALSE) {

  // The build mode identifies the target for which the node is built.
  if (!isset($node->build_mode)) {
    $node->build_mode = NODE_BUILD_NORMAL;
  }

  // Remove the delimiter (if any) that separates the teaser from the body.
  $node->body = isset($node->body) ? str_replace('<!--break-->', '', $node->body) : '';

  // The 'view' hook can be implemented to overwrite the default function
  // to display nodes.
  if (node_hook($node, 'view')) {
    $node = node_invoke($node, 'view', $teaser, $page);
  }
  else {
    $node = node_prepare($node, $teaser);
  }

  // Allow modules to make their own additions to the node.
  node_invoke_nodeapi($node, 'view', $teaser, $page);

  return $node;
}

/**
 * Generate a page displaying a single node, along with its comments.
 */
function _real_node_show($node, $cid, $message = FALSE) {
  if ($message) {
    drupal_set_title(t('Revision of %title from %date', array('%title' => $node->title, '%date' => format_date($node->revision_timestamp))));
  }
  $output = node_view($node, FALSE, TRUE);

  if (function_exists('comment_render') && $node->comment) {
    $output .= comment_render($node, $cid);
  }

  // Update the history table, stating that this user viewed this node.
  node_tag_new($node->nid);

  return $output;
}

/**
 * Retrieve the comment mode for the given node ID (none, read, or read/write).
 */
function _real_node_comment_mode($nid) {
  static $comment_mode;
  if (!isset($comment_mode[$nid])) {
    $comment_mode[$nid] = db_result(db_query('SELECT comment FROM {node} WHERE nid = %d', $nid));
  }
  return $comment_mode[$nid];
}

/**
 * Implementation of hook_link().
 */
function _real_node_link($type, $node = NULL, $teaser = FALSE) {
  $links = array();

  if ($type == 'node') {
    if ($teaser == 1 && $node->teaser && !empty($node->readmore)) {
      $links['node_read_more'] = array(
        'title' => t('Read more'),
        'href' => "node/$node->nid",
        // The title attribute gets escaped when the links are processed, so
        // there is no need to escape here.
        'attributes' => array('title' => t('Read the rest of !title.', array('!title' => $node->title)))
      );
    }
  }

  return $links;
}

function _real__node_revision_access($node, $op = 'view') {
  static $access = array();
  if (!isset($access[$node->vid])) {
    $node_current_revision = node_load($node->nid);
    $is_current_revision = $node_current_revision->vid == $node->vid;
    // There should be at least two revisions. If the vid of the given node
    // and the vid of the current revision differs, then we already have two
    // different revisions so there is no need for a separate database check.
    // Also, if you try to revert to or delete the current revision, that's
    // not good.
    if ($is_current_revision && (db_result(db_query('SELECT COUNT(vid) FROM {node_revisions} WHERE nid = %d', $node->nid)) == 1 || $op == 'update' || $op == 'delete')) {
      $access[$node->vid] = FALSE;
    }
    elseif (user_access('administer nodes')) {
      $access[$node->vid] = TRUE;
    }
    else {
      $map = array('view' => 'view revisions', 'update' => 'revert revisions', 'delete' => 'delete revisions');
      // First check the user permission, second check the access to the
      // current revision and finally, if the node passed in is not the current
      // revision then access to that, too.
      $access[$node->vid] = isset($map[$op]) && user_access($map[$op]) && node_access($op, $node_current_revision) && ($is_current_revision || node_access($op, $node));
    }
  }
  return $access[$node->vid];
}


