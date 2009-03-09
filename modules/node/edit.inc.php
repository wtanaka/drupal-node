<?php

/**
 * Perform validation checks on the given node.
 */
function _real_node_validate($node, $form = array()) {
  // Convert the node to an object, if necessary.
  $node = (object)$node;
  $type = node_get_types('type', $node);

  // Make sure the body has the minimum number of words.
  // TODO : use a better word counting algorithm that will work in other languages
  if (!empty($type->min_word_count) && isset($node->body) && count(explode(' ', $node->body)) < $type->min_word_count) {
    form_set_error('body', t('The body of your @type is too short. You need at least %words words.', array('%words' => $type->min_word_count, '@type' => $type->name)));
  }

  if (isset($node->nid) && (node_last_changed($node->nid) > $node->changed)) {
    form_set_error('changed', t('This content has been modified by another user, changes cannot be saved.'));
  }

  if (user_access('administer nodes')) {
    // Validate the "authored by" field.
    if (!empty($node->name) && !($account = user_load(array('name' => $node->name)))) {
      // The use of empty() is mandatory in the context of usernames
      // as the empty string denotes the anonymous user. In case we
      // are dealing with an anonymous user we set the user ID to 0.
      form_set_error('name', t('The username %name does not exist.', array('%name' => $node->name)));
    }

    // Validate the "authored on" field. As of PHP 5.1.0, strtotime returns FALSE instead of -1 upon failure.
    if (!empty($node->date) && strtotime($node->date) <= 0) {
      form_set_error('date', t('You have to specify a valid date.'));
    }
  }

  // Do node-type-specific validation checks.
  node_invoke($node, 'validate', $form);
  node_invoke_nodeapi($node, 'validate', $form);
}

/**
 * Prepare node for save and allow modules to make changes.
 */
function _real_node_submit($node) {
  global $user;

  // Convert the node to an object, if necessary.
  $node = (object)$node;

  // Generate the teaser, but only if it hasn't been set (e.g. by a
  // module-provided 'teaser' form item).
  if (!isset($node->teaser)) {
    if (isset($node->body)) {
      $node->teaser = node_teaser($node->body, isset($node->format) ? $node->format : NULL);
      // Chop off the teaser from the body if needed. The teaser_include
      // property might not be set (eg. in Blog API postings), so only act on
      // it, if it was set with a given value.
      if (isset($node->teaser_include) && !$node->teaser_include && $node->teaser == substr($node->body, 0, strlen($node->teaser))) {
        $node->body = substr($node->body, strlen($node->teaser));
      }
    }
    else {
      $node->teaser = '';
      $node->format = 0;
    }
  }

  if (user_access('administer nodes')) {
    // Populate the "authored by" field.
    if ($account = user_load(array('name' => $node->name))) {
      $node->uid = $account->uid;
    }
    else {
      $node->uid = 0;
    }
  }
  $node->created = !empty($node->date) ? strtotime($node->date) : time();
  $node->validated = TRUE;

  return $node;
}

/**
 * Save a node object into the database.
 */
function _real_node_save(&$node) {
  // Let modules modify the node before it is saved to the database.
  node_invoke_nodeapi($node, 'presave');
  global $user;

  $node->is_new = FALSE;

  // Apply filters to some default node fields:
  if (empty($node->nid)) {
    // Insert a new node.
    $node->is_new = TRUE;

    // When inserting a node, $node->log must be set because
    // {node_revisions}.log does not (and cannot) have a default
    // value.  If the user does not have permission to create
    // revisions, however, the form will not contain an element for
    // log so $node->log will be unset at this point.
    if (!isset($node->log)) {
      $node->log = '';
    }

    // For the same reasons, make sure we have $node->teaser and
    // $node->body.  We should consider making these fields nullable
    // in a future version since node types are not required to use them.
    if (!isset($node->teaser)) {
      $node->teaser = '';
    }
    if (!isset($node->body)) {
      $node->body = '';
    }
  }
  elseif (!empty($node->revision)) {
    $node->old_vid = $node->vid;
  }
  else {
    // When updating a node, avoid clobberring an existing log entry with an empty one.
    if (empty($node->log)) {
      unset($node->log);
    }
  }

  // Set some required fields:
  if (empty($node->created)) {
    $node->created = time();
  }
  // The changed timestamp is always updated for bookkeeping purposes (revisions, searching, ...)
  $node->changed = time();

  $node->timestamp = time();
  $node->format = isset($node->format) ? $node->format : FILTER_FORMAT_DEFAULT;

  // Generate the node table query and the node_revisions table query.
  if ($node->is_new) {
    _node_save_revision($node, $user->uid);
    drupal_write_record('node', $node);
    db_query('UPDATE {node_revisions} SET nid = %d WHERE vid = %d', $node->nid, $node->vid);
    $op = 'insert';
  }
  else {
    drupal_write_record('node', $node, 'nid');
    if (!empty($node->revision)) {
      _node_save_revision($node, $user->uid);
      db_query('UPDATE {node} SET vid = %d WHERE nid = %d', $node->vid, $node->nid);
    }
    else {
      _node_save_revision($node, $user->uid, 'vid');
    }
    $op = 'update';
  }

  // Call the node specific callback (if any).
  node_invoke($node, $op);
  node_invoke_nodeapi($node, $op);

  // Update the node access table for this node.
  node_access_acquire_grants($node);

  // Clear the page and block caches.
  cache_clear_all();
}

/**
 * Helper function to save a revision with the uid of the current user.
 *
 * Node is taken by reference, becuse drupal_write_record() updates the
 * $node with the revision id, and we need to pass that back to the caller.
 */
function _real__node_save_revision(&$node, $uid, $update = NULL) {
  $temp_uid = $node->uid;
  $node->uid = $uid;
  if (isset($update)) {
    drupal_write_record('node_revisions', $node, $update);
  }
  else {
    drupal_write_record('node_revisions', $node);
  }
  $node->uid = $temp_uid;
}

/**
 * Delete a node.
 */
function _real_node_delete($nid) {

  $node = node_load($nid);

  if (node_access('delete', $node)) {
    db_query('DELETE FROM {node} WHERE nid = %d', $node->nid);
    db_query('DELETE FROM {node_revisions} WHERE nid = %d', $node->nid);

    // Call the node-specific callback (if any):
    node_invoke($node, 'delete');
    node_invoke_nodeapi($node, 'delete');

    // Clear the page and block caches.
    cache_clear_all();

    // Remove this node from the search index if needed.
    if (function_exists('search_wipe')) {
      search_wipe($node->nid, 'node');
    }
    watchdog('content', '@type: deleted %title.', array('@type' => $node->type, '%title' => $node->title));
    drupal_set_message(t('@type %title has been deleted.', array('@type' => node_get_types('name', $node), '%title' => $node->title)));
  }
}

function _real_node_last_changed($nid) {
  $node = db_fetch_object(db_query('SELECT changed FROM {node} WHERE nid = %d', $nid));
  return ($node->changed);
}

/**
 * This function will call module invoke to get a list of grants and then
 * write them to the database. It is called at node save, and should be
 * called by modules whenever something other than a node_save causes
 * the permissions on a node to change.
 *
 * This function is the only function that should write to the node_access
 * table.
 *
 * @param $node
 *   The $node to acquire grants for.
 */
function _real_node_access_acquire_grants($node) {
  $grants = module_invoke_all('node_access_records', $node);
  if (empty($grants)) {
    $grants[] = array('realm' => 'all', 'gid' => 0, 'grant_view' => 1, 'grant_update' => 0, 'grant_delete' => 0);
  }
  else {
    // retain grants by highest priority
    $grant_by_priority = array();
    foreach ($grants as $g) {
      $grant_by_priority[intval($g['priority'])][] = $g;
    }
    krsort($grant_by_priority);
    $grants = array_shift($grant_by_priority);
  }

  node_access_write_grants($node, $grants);
}

/**
 * This function will write a list of grants to the database, deleting
 * any pre-existing grants. If a realm is provided, it will only
 * delete grants from that realm, but it will always delete a grant
 * from the 'all' realm. Modules which utilize node_access can
 * use this function when doing mass updates due to widespread permission
 * changes.
 *
 * @param $node
 *   The $node being written to. All that is necessary is that it contain a nid.
 * @param $grants
 *   A list of grants to write. Each grant is an array that must contain the
 *   following keys: realm, gid, grant_view, grant_update, grant_delete.
 *   The realm is specified by a particular module; the gid is as well, and
 *   is a module-defined id to define grant privileges. each grant_* field
 *   is a boolean value.
 * @param $realm
 *   If provided, only read/write grants for that realm.
 * @param $delete
 *   If false, do not delete records. This is only for optimization purposes,
 *   and assumes the caller has already performed a mass delete of some form.
 */
function _real_node_access_write_grants($node, $grants, $realm = NULL, $delete = TRUE) {
  if ($delete) {
    $query = 'DELETE FROM {node_access} WHERE nid = %d';
    if ($realm) {
      $query .= " AND realm in ('%s', 'all')";
    }
    db_query($query, $node->nid, $realm);
  }

  // Only perform work when node_access modules are active.
  if (count(module_implements('node_grants'))) {
    foreach ($grants as $grant) {
      if ($realm && $realm != $grant['realm']) {
        continue;
      }
      // Only write grants; denies are implicit.
      if ($grant['grant_view'] || $grant['grant_update'] || $grant['grant_delete']) {
        db_query("INSERT INTO {node_access} (nid, realm, gid, grant_view, grant_update, grant_delete) VALUES (%d, '%s', %d, %d, %d, %d)", $node->nid, $grant['realm'], $grant['gid'], $grant['grant_view'], $grant['grant_update'], $grant['grant_delete']);
      }
    }
  }
}

