<?php

/**
 * Implementation of a Drupal action.
 * Sets the status of a node to 1, meaning published.
 */
function _real_node_publish_action(&$node, $context = array()) {
  $node->status = 1;
  watchdog('action', 'Set @type %title to published.', array('@type' => node_get_types('name', $node), '%title' => $node->title));
}

/**
 * Implementation of a Drupal action.
 * Sets the status of a node to 0, meaning unpublished.
 */
function _real_node_unpublish_action(&$node, $context = array()) {
  $node->status = 0;
  watchdog('action', 'Set @type %title to unpublished.', array('@type' => node_get_types('name', $node), '%title' => $node->title));
}

/**
 * Implementation of a Drupal action.
 * Sets the sticky-at-top-of-list property of a node to 1.
 */
function _real_node_make_sticky_action(&$node, $context = array()) {
  $node->sticky = 1;
  watchdog('action', 'Set @type %title to sticky.', array('@type' => node_get_types('name', $node), '%title' => $node->title));
}

/**
 * Implementation of a Drupal action.
 * Sets the sticky-at-top-of-list property of a node to 0.
 */
function _real_node_make_unsticky_action(&$node, $context = array()) {
  $node->sticky = 0;
  watchdog('action', 'Set @type %title to unsticky.', array('@type' => node_get_types('name', $node), '%title' => $node->title));
}

/**
 * Implementation of a Drupal action.
 * Sets the promote property of a node to 1.
 */
function _real_node_promote_action(&$node, $context = array()) {
  $node->promote = 1;
  watchdog('action', 'Promoted @type %title to front page.', array('@type' => node_get_types('name', $node), '%title' => $node->title));
}

/**
 * Implementation of a Drupal action.
 * Sets the promote property of a node to 0.
 */
function _real_node_unpromote_action(&$node, $context = array()) {
  $node->promote = 0;
  watchdog('action', 'Removed @type %title from front page.', array('@type' => node_get_types('name', $node), '%title' => $node->title));
}

/**
 * Implementation of a Drupal action.
 * Saves a node.
 */
function _real_node_save_action($node) {
  node_save($node);
  watchdog('action', 'Saved @type %title', array('@type' => node_get_types('name', $node), '%title' => $node->title));
}

/**
 * Implementation of a configurable Drupal action.
 * Assigns ownership of a node to a user.
 */
function _real_node_assign_owner_action(&$node, $context) {
  $node->uid = $context['owner_uid'];
  $owner_name = db_result(db_query("SELECT name FROM {users} WHERE uid = %d", $context['owner_uid']));
  watchdog('action', 'Changed owner of @type %title to uid %name.', array('@type' => node_get_types('type', $node), '%title' => $node->title, '%name' => $owner_name));
}

function _real_node_assign_owner_action_form($context) {
  $description = t('The username of the user to which you would like to assign ownership.');
  $count = db_result(db_query("SELECT COUNT(*) FROM {users}"));
  $owner_name = '';
  if (isset($context['owner_uid'])) {
    $owner_name = db_result(db_query("SELECT name FROM {users} WHERE uid = %d", $context['owner_uid']));
  }

  // Use dropdown for fewer than 200 users; textbox for more than that.
  if (intval($count) < 200) {
    $options = array();
    $result = db_query("SELECT uid, name FROM {users} WHERE uid > 0 ORDER BY name");
    while ($data = db_fetch_object($result)) {
      $options[$data->name] = $data->name;
    }
    $form['owner_name'] = array(
      '#type' => 'select',
      '#title' => t('Username'),
      '#default_value' => $owner_name,
      '#options' => $options,
      '#description' => $description,
    );
  }
  else {
    $form['owner_name'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => $owner_name,
      '#autocomplete_path' => 'user/autocomplete',
      '#size' => '6',
      '#maxlength' => '7',
      '#description' => $description,
    );
  }
  return $form;
}

function _real_node_assign_owner_action_validate($form, $form_state) {
  $count = db_result(db_query("SELECT COUNT(*) FROM {users} WHERE name = '%s'", $form_state['values']['owner_name']));
  if (intval($count) != 1) {
    form_set_error('owner_name', t('Please enter a valid username.'));
  }
}

function _real_node_assign_owner_action_submit($form, $form_state) {
  // Username can change, so we need to store the ID, not the username.
  $uid = db_result(db_query("SELECT uid from {users} WHERE name = '%s'", $form_state['values']['owner_name']));
  return array('owner_uid' => $uid);
}

function _real_node_unpublish_by_keyword_action_form($context) {
  $form['keywords'] = array(
    '#title' => t('Keywords'),
    '#type' => 'textarea',
    '#description' => t('The post will be unpublished if it contains any of the character sequences above. Use a comma-separated list of character sequences. Example: funny, bungee jumping, "Company, Inc.". Character sequences are case-sensitive.'),
    '#default_value' => isset($context['keywords']) ? drupal_implode_tags($context['keywords']) : '',
  );
  return $form;
}

function _real_node_unpublish_by_keyword_action_submit($form, $form_state) {
  return array('keywords' => drupal_explode_tags($form_state['values']['keywords']));
}

/**
 * Implementation of a configurable Drupal action.
 * Unpublish a node if it contains a certain string.
 *
 * @param $context
 *   An array providing more information about the context of the call to this action.
 * @param $comment
 *   A node object.
 */
function _real_node_unpublish_by_keyword_action($node, $context) {
  foreach ($context['keywords'] as $keyword) {
    if (strstr(node_view(drupal_clone($node)), $keyword) || strstr($node->title, $keyword)) {
      $node->status = 0;
      watchdog('action', 'Set @type %title to unpublished.', array('@type' => node_get_types('name', $node), '%title' => $node->title));
      break;
    }
  }
}
