<?php

/**
 * Implementation of hook_perm().
 */
function _real_node_perm() {
  $perms = array('administer content types', 'administer nodes', 'access content', 'view revisions', 'revert revisions', 'delete revisions');

  foreach (node_get_types() as $type) {
    if ($type->module == 'node') {
      $name = check_plain($type->type);
      $perms[] = 'create '. $name .' content';
      $perms[] = 'delete own '. $name .' content';
      $perms[] = 'delete any '. $name .' content';
      $perms[] = 'edit own '. $name .' content';
      $perms[] = 'edit any '. $name .' content';
    }
  }

  return $perms;
}
