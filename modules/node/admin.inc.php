<?php

/**
 * Saves a node type to the database.
 *
 * @param $info
 *   The node type to save, as an object.
 *
 * @return
 *   Status flag indicating outcome of the operation.
 */
function _real_node_type_save($info) {
  $is_existing = FALSE;
  $existing_type = !empty($info->old_type) ? $info->old_type : $info->type;
  $is_existing = db_result(db_query("SELECT COUNT(*) FROM {node_type} WHERE type = '%s'", $existing_type));
  if (!isset($info->help)) {
    $info->help = '';
  }
  if (!isset($info->min_word_count)) {
    $info->min_word_count = 0;
  }
  if (!isset($info->body_label)) {
    $info->body_label = '';
  }

  if ($is_existing) {
    db_query("UPDATE {node_type} SET type = '%s', name = '%s', module = '%s', has_title = %d, title_label = '%s', has_body = %d, body_label = '%s', description = '%s', help = '%s', min_word_count = %d, custom = %d, modified = %d, locked = %d WHERE type = '%s'", $info->type, $info->name, $info->module, $info->has_title, $info->title_label, $info->has_body, $info->body_label, $info->description, $info->help, $info->min_word_count, $info->custom, $info->modified, $info->locked, $existing_type);

    module_invoke_all('node_type', 'update', $info);
    return SAVED_UPDATED;
  }
  else {
    db_query("INSERT INTO {node_type} (type, name, module, has_title, title_label, has_body, body_label, description, help, min_word_count, custom, modified, locked, orig_type) VALUES ('%s', '%s', '%s', %d, '%s', %d, '%s', '%s', '%s', %d, %d, %d, %d, '%s')", $info->type, $info->name, $info->module, $info->has_title, $info->title_label, $info->has_body, $info->body_label, $info->description, $info->help, $info->min_word_count, $info->custom, $info->modified, $info->locked, $info->orig_type);

    module_invoke_all('node_type', 'insert', $info);
    return SAVED_NEW;
  }
}

/**
 * Deletes a node type from the database.
 *
 * @param $type
 *   The machine-readable name of the node type to be deleted.
 */
function _real_node_type_delete($type) {
  $info = node_get_types('type', $type);
  db_query("DELETE FROM {node_type} WHERE type = '%s'", $type);
  module_invoke_all('node_type', 'delete', $info);
}

/**
 * Updates all nodes of one type to be of another type.
 *
 * @param $old_type
 *   The current node type of the nodes.
 * @param $type
 *   The new node type of the nodes.
 *
 * @return
 *   The number of nodes whose node type field was modified.
 */
function _real_node_type_update_nodes($old_type, $type) {
  db_query("UPDATE {node} SET type = '%s' WHERE type = '%s'", $type, $old_type);
  return db_affected_rows();
}

/**
 * Theme the content ranking part of the search settings admin page.
 *
 * @ingroup themeable
 */
function _real_theme_node_search_admin($form) {
  $output = drupal_render($form['info']);

  $header = array(t('Factor'), t('Weight'));
  foreach (element_children($form['factors']) as $key) {
    $row = array();
    $row[] = $form['factors'][$key]['#title'];
    unset($form['factors'][$key]['#title']);
    $row[] = drupal_render($form['factors'][$key]);
    $rows[] = $row;
  }
  $output .= theme('table', $header, $rows);

  $output .= drupal_render($form);
  return $output;
}

