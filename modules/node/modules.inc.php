<?php

/**
 * Implementation of hook_theme()
 */
function _real_node_theme() {
  return array(
    'node' => array(
      'arguments' => array('node' => NULL, 'teaser' => FALSE, 'page' => FALSE),
      'template' => 'node',
    ),
    'node_list' => array(
      'arguments' => array('items' => NULL, 'title' => NULL),
    ),
    'node_search_admin' => array(
      'arguments' => array('form' => NULL),
    ),
    'node_filter_form' => array(
      'arguments' => array('form' => NULL),
      'file' => 'node.admin.inc',
    ),
    'node_filters' => array(
      'arguments' => array('form' => NULL),
      'file' => 'node.admin.inc',
    ),
    'node_admin_nodes' => array(
      'arguments' => array('form' => NULL),
      'file' => 'node.admin.inc',
    ),
    'node_add_list' => array(
      'arguments' => array('content' => NULL),
      'file' => 'node.pages.inc',
    ),
    'node_form' => array(
      'arguments' => array('form' => NULL),
      'file' => 'node.pages.inc',
    ),
    'node_preview' => array(
      'arguments' => array('node' => NULL),
      'file' => 'node.pages.inc',
    ),
    'node_log_message' => array(
      'arguments' => array('log' => NULL),
    ),
    'node_submitted' => array(
      'arguments' => array('node' => NULL),
    ),
  );
}

/**
 * Resets the database cache of node types, and saves all new or non-modified
 * module-defined node types to the database.
 */
function _real_node_types_rebuild() {
  _node_types_build();

  $node_types = node_get_types('types', NULL, TRUE);

  foreach ($node_types as $type => $info) {
    if (!empty($info->is_new)) {
      node_type_save($info);
    }
    if (!empty($info->disabled)) {
      node_type_delete($info->type);
    }
  }

  _node_types_build();
}

/**
 * Implementation of hook_menu().
 */
function _real_node_menu() {
  $items['admin/content/node'] = array(
    'title' => 'Content',
    'description' => "View, edit, and delete your site's content.",
    'page callback' => 'drupal_get_form',
    'page arguments' => array('node_admin_content'),
    'access arguments' => array('administer nodes'),
    'file' => 'node.admin.inc',
  );

  $items['admin/content/node/overview'] = array(
    'title' => 'List',
    'type' => MENU_DEFAULT_LOCAL_TASK,
    'weight' => -10,
  );

  $items['admin/content/node-settings'] = array(
    'title' => 'Post settings',
    'description' => 'Control posting behavior, such as teaser length, requiring previews before posting, and the number of posts on the front page.',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('node_configure'),
    'access arguments' => array('administer nodes'),
    'file' => 'node.admin.inc',
  );
  $items['admin/content/node-settings/rebuild'] = array(
    'title' => 'Rebuild permissions',
    'page arguments' => array('node_configure_rebuild_confirm'),
    'file' => 'node.admin.inc',
    // Any user than can potentially trigger a node_acess_needs_rebuild(TRUE)
    // has to be allowed access to the 'node access rebuild' confirm form.
    'access arguments' => array('access administration pages'),
    'type' => MENU_CALLBACK,
  );

  $items['admin/content/types'] = array(
    'title' => 'Content types',
    'description' => 'Manage posts by content type, including default status, front page promotion, etc.',
    'page callback' => 'node_overview_types',
    'access arguments' => array('administer content types'),
    'file' => 'content_types.inc',
  );
  $items['admin/content/types/list'] = array(
    'title' => 'List',
    'type' => MENU_DEFAULT_LOCAL_TASK,
    'weight' => -10,
  );
  $items['admin/content/types/add'] = array(
    'title' => 'Add content type',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('node_type_form'),
    'access arguments' => array('administer content types'),
    'file' => 'content_types.inc',
    'type' => MENU_LOCAL_TASK,
  );
  $items['node'] = array(
    'title' => 'Content',
    'page callback' => 'node_page_default',
    'access arguments' => array('access content'),
    'type' => MENU_CALLBACK,
  );
  $items['node/add'] = array(
    'title' => 'Create content',
    'page callback' => 'node_add_page',
    'access callback' => '_node_add_access',
    'weight' => 1,
    'file' => 'node.pages.inc',
  );
  $items['rss.xml'] = array(
    'title' => 'RSS feed',
    'page callback' => 'node_feed',
    'access arguments' => array('access content'),
    'type' => MENU_CALLBACK,
  );
  foreach (node_get_types('types', NULL, TRUE) as $type) {
    $type_url_str = str_replace('_', '-', $type->type);
    $items['node/add/'. $type_url_str] = array(
      'title' => drupal_ucfirst($type->name),
      'title callback' => 'check_plain',
      'page callback' => 'node_add',
      'page arguments' => array(2),
      'access callback' => 'node_access',
      'access arguments' => array('create', $type->type),
      'description' => $type->description,
      'file' => 'node.pages.inc',
    );
    $items['admin/content/node-type/'. $type_url_str] = array(
      'title' => $type->name,
      'page callback' => 'drupal_get_form',
      'page arguments' => array('node_type_form', $type),
      'access arguments' => array('administer content types'),
      'file' => 'content_types.inc',
      'type' => MENU_CALLBACK,
    );
    $items['admin/content/node-type/'. $type_url_str .'/edit'] = array(
      'title' => 'Edit',
      'type' => MENU_DEFAULT_LOCAL_TASK,
    );
    $items['admin/content/node-type/'. $type_url_str .'/delete'] = array(
      'title' => 'Delete',
      'page arguments' => array('node_type_delete_confirm', $type),
      'access arguments' => array('administer content types'),
      'file' => 'content_types.inc',
      'type' => MENU_CALLBACK,
    );
  }
  $items['node/%node'] = array(
    'title callback' => 'node_page_title',
    'title arguments' => array(1),
    'page callback' => 'node_page_view',
    'page arguments' => array(1),
    'access callback' => 'node_access',
    'access arguments' => array('view', 1),
    'type' => MENU_CALLBACK);
  $items['node/%node/view'] = array(
    'title' => 'View',
    'type' => MENU_DEFAULT_LOCAL_TASK,
    'weight' => -10);
  $items['node/%node/edit'] = array(
    'title' => 'Edit',
    'page callback' => 'node_page_edit',
    'page arguments' => array(1),
    'access callback' => 'node_access',
    'access arguments' => array('update', 1),
    'weight' => 1,
    'file' => 'node.pages.inc',
    'type' => MENU_LOCAL_TASK,
  );
  $items['node/%node/delete'] = array(
    'title' => 'Delete',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('node_delete_confirm', 1),
    'access callback' => 'node_access',
    'access arguments' => array('delete', 1),
    'file' => 'node.pages.inc',
    'weight' => 1,
    'type' => MENU_CALLBACK);
  $items['node/%node/revisions'] = array(
    'title' => 'Revisions',
    'page callback' => 'node_revision_overview',
    'page arguments' => array(1),
    'access callback' => '_node_revision_access',
    'access arguments' => array(1),
    'weight' => 2,
    'file' => 'node.pages.inc',
    'type' => MENU_LOCAL_TASK,
  );
  $items['node/%node/revisions/%/view'] = array(
    'title' => 'Revisions',
    'load arguments' => array(3),
    'page callback' => 'node_show',
    'page arguments' => array(1, NULL, TRUE),
    'access callback' => '_node_revision_access',
    'access arguments' => array(1),
    'type' => MENU_CALLBACK,
  );
  $items['node/%node/revisions/%/revert'] = array(
    'title' => 'Revert to earlier revision',
    'load arguments' => array(3),
    'page callback' => 'drupal_get_form',
    'page arguments' => array('node_revision_revert_confirm', 1),
    'access callback' => '_node_revision_access',
    'access arguments' => array(1, 'update'),
    'file' => 'node.pages.inc',
    'type' => MENU_CALLBACK,
  );
  $items['node/%node/revisions/%/delete'] = array(
    'title' => 'Delete earlier revision',
    'load arguments' => array(3),
    'page callback' => 'drupal_get_form',
    'page arguments' => array('node_revision_delete_confirm', 1),
    'access callback' => '_node_revision_access',
    'access arguments' => array(1, 'delete'),
    'file' => 'node.pages.inc',
    'type' => MENU_CALLBACK,
  );
  return $items;
}
