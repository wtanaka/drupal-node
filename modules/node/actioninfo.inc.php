<?php

/**
 * Implementation of hook_action_info().
 */
function _real_node_action_info() {
  return array(
    'node_publish_action' => array(
      'type' => 'node',
      'description' => t('Publish post'),
      'configurable' => FALSE,
      'behavior' => array('changes_node_property'),
      'hooks' => array(
        'nodeapi' => array('presave'),
        'comment' => array('insert', 'update'),
      ),
    ),
    'node_unpublish_action' => array(
      'type' => 'node',
      'description' => t('Unpublish post'),
      'configurable' => FALSE,
      'behavior' => array('changes_node_property'),
      'hooks' => array(
        'nodeapi' => array('presave'),
        'comment' => array('delete', 'insert', 'update'),
      ),
    ),
    'node_make_sticky_action' => array(
      'type' => 'node',
      'description' => t('Make post sticky'),
      'configurable' => FALSE,
      'behavior' => array('changes_node_property'),
      'hooks' => array(
        'nodeapi' => array('presave'),
        'comment' => array('insert', 'update'),
      ),
    ),
    'node_make_unsticky_action' => array(
      'type' => 'node',
      'description' => t('Make post unsticky'),
      'configurable' => FALSE,
      'behavior' => array('changes_node_property'),
      'hooks' => array(
        'nodeapi' => array('presave'),
        'comment' => array('delete', 'insert', 'update'),
      ),
    ),
    'node_promote_action' => array(
      'type' => 'node',
      'description' => t('Promote post to front page'),
      'configurable' => FALSE,
      'behavior' => array('changes_node_property'),
      'hooks' => array(
        'nodeapi' => array('presave'),
        'comment' => array('insert', 'update'),
      ),
    ),
    'node_unpromote_action' => array(
      'type' => 'node',
      'description' => t('Remove post from front page'),
      'configurable' => FALSE,
      'behavior' => array('changes_node_property'),
      'hooks' => array(
        'nodeapi' => array('presave'),
        'comment' => array('delete', 'insert', 'update'),
      ),
    ),
    'node_assign_owner_action' => array(
      'type' => 'node',
      'description' => t('Change the author of a post'),
      'configurable' => TRUE,
      'behavior' => array('changes_node_property'),
      'hooks' => array(
        'any' => TRUE,
        'nodeapi' => array('presave'),
        'comment' => array('delete', 'insert', 'update'),
      ),
    ),
    'node_save_action' => array(
      'type' => 'node',
      'description' => t('Save post'),
      'configurable' => FALSE,
      'hooks' => array(
        'comment' => array('delete', 'insert', 'update'),
      ),
    ),
    'node_unpublish_by_keyword_action' => array(
      'type' => 'node',
      'description' => t('Unpublish post containing keyword(s)'),
      'configurable' => TRUE,
      'hooks' => array(
        'nodeapi' => array('presave', 'insert', 'update'),
      ),
    ),
  );
}
