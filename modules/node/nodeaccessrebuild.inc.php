<?php

/**
 * Rebuild the node access database. This is occasionally needed by modules
 * that make system-wide changes to access levels.
 *
 * When the rebuild is required by an admin-triggered action (e.g module
 * settings form), calling node_access_needs_rebuild(TRUE) instead of
 * node_access_rebuild() lets the user perform his changes and actually
 * rebuild only once he is done.
 *
 * Note : As of Drupal 6, node access modules are not required to (and actually
 * should not) call node_access_rebuild() in hook_enable/disable anymore.
 *
 * @see node_access_needs_rebuild()
 *
 * @param $batch_mode
 *   Set to TRUE to process in 'batch' mode, spawning processing over several
 *   HTTP requests (thus avoiding the risk of PHP timeout if the site has a
 *   large number of nodes).
 *   hook_update_N and any form submit handler are safe contexts to use the
 *   'batch mode'. Less decidable cases (such as calls from hook_user,
 *   hook_taxonomy, hook_node_type...) might consider using the non-batch mode.
 */
function _real_node_access_rebuild($batch_mode = FALSE) {
  db_query("DELETE FROM {node_access}");
  // Only recalculate if the site is using a node_access module.
  if (count(module_implements('node_grants'))) {
    if ($batch_mode) {
      $batch = array(
        'title' => t('Rebuilding content access permissions'),
        'operations' => array(
          array('_node_access_rebuild_batch_operation', array()),
        ),
        'finished' => '_node_access_rebuild_batch_finished'
      );
      batch_set($batch);
    }
    else {
      // If not in 'safe mode', increase the maximum execution time.
      if (!ini_get('safe_mode')) {
        set_time_limit(240);
      }
      $result = db_query("SELECT nid FROM {node}");
      while ($node = db_fetch_object($result)) {
        $loaded_node = node_load($node->nid, NULL, TRUE);
        // To preserve database integrity, only aquire grants if the node
        // loads successfully.
        if (!empty($loaded_node)) {
          node_access_acquire_grants($loaded_node);
        }
      }
    }
  }
  else {
    // Not using any node_access modules. Add the default grant.
    db_query("INSERT INTO {node_access} VALUES (0, 0, 'all', 1, 0, 0)");
  }

  if (!isset($batch)) {
    drupal_set_message(t('Content permissions have been rebuilt.'));
    node_access_needs_rebuild(FALSE);
    cache_clear_all();
  }
}

/**
 * Batch operation for node_access_rebuild_batch.
 *
 * This is a mutlistep operation : we go through all nodes by packs of 20.
 * The batch processing engine interrupts processing and sends progress
 * feedback after 1 second execution time.
 */
function _real__node_access_rebuild_batch_operation(&$context) {
  if (empty($context['sandbox'])) {
    // Initiate multistep processing.
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current_node'] = 0;
    $context['sandbox']['max'] = db_result(db_query('SELECT COUNT(DISTINCT nid) FROM {node}'));
  }

  // Process the next 20 nodes.
  $limit = 20;
  $result = db_query_range("SELECT nid FROM {node} WHERE nid > %d ORDER BY nid ASC", $context['sandbox']['current_node'], 0, $limit);
  while ($row = db_fetch_array($result)) {
    $loaded_node = node_load($row['nid'], NULL, TRUE);
    // To preserve database integrity, only aquire grants if the node
    // loads successfully.
    if (!empty($loaded_node)) {
      node_access_acquire_grants($loaded_node);
    }
    $context['sandbox']['progress']++;
    $context['sandbox']['current_node'] = $loaded_node->nid;
  }

  // Multistep processing : report progress.
  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
}

/**
 * Post-processing for node_access_rebuild_batch.
 */
function _real__node_access_rebuild_batch_finished($success, $results, $operations) {
  if ($success) {
    drupal_set_message(t('The content access permissions have been rebuilt.'));
    node_access_needs_rebuild(FALSE);
  }
  else {
    drupal_set_message(t('The content access permissions have not been properly rebuilt.'), 'error');
  }
  cache_clear_all();
}
