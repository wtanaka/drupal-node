<?php

/**
 * Implementation of hook_search().
 */
function _real_node_search($op = 'search', $keys = NULL) {
  switch ($op) {
    case 'name':
      return t('Content');

    case 'reset':
      db_query("UPDATE {search_dataset} SET reindex = %d WHERE type = 'node'", time());
      return;

    case 'status':
      $total = db_result(db_query('SELECT COUNT(*) FROM {node} WHERE status = 1'));
      $remaining = db_result(db_query("SELECT COUNT(*) FROM {node} n LEFT JOIN {search_dataset} d ON d.type = 'node' AND d.sid = n.nid WHERE n.status = 1 AND (d.sid IS NULL OR d.reindex <> 0)"));
      return array('remaining' => $remaining, 'total' => $total);

    case 'admin':
      $form = array();
      // Output form for defining rank factor weights.
      $form['content_ranking'] = array(
        '#type' => 'fieldset',
        '#title' => t('Content ranking'),
      );
      $form['content_ranking']['#theme'] = 'node_search_admin';
      $form['content_ranking']['info'] = array(
        '#value' => '<em>'. t('The following numbers control which properties the content search should favor when ordering the results. Higher numbers mean more influence, zero means the property is ignored. Changing these numbers does not require the search index to be rebuilt. Changes take effect immediately.') .'</em>'
      );

      $ranking = array('node_rank_relevance' => t('Keyword relevance'),
                       'node_rank_recent' => t('Recently posted'));
      if (module_exists('comment')) {
        $ranking['node_rank_comments'] = t('Number of comments');
      }
      if (module_exists('statistics') && variable_get('statistics_count_content_views', 0)) {
        $ranking['node_rank_views'] = t('Number of views');
      }

      // Note: reversed to reflect that higher number = higher ranking.
      $options = drupal_map_assoc(range(0, 10));
      foreach ($ranking as $var => $title) {
        $form['content_ranking']['factors'][$var] = array(
          '#title' => $title,
          '#type' => 'select',
          '#options' => $options,
          '#default_value' => variable_get($var, 5),
        );
      }
      return $form;

    case 'search':
      // Build matching conditions
      list($join1, $where1) = _db_rewrite_sql();
      $arguments1 = array();
      $conditions1 = 'n.status = 1';

      if ($type = search_query_extract($keys, 'type')) {
        $types = array();
        foreach (explode(',', $type) as $t) {
          $types[] = "n.type = '%s'";
          $arguments1[] = $t;
        }
        $conditions1 .= ' AND ('. implode(' OR ', $types) .')';
        $keys = search_query_insert($keys, 'type');
      }

      if ($category = search_query_extract($keys, 'category')) {
        $categories = array();
        foreach (explode(',', $category) as $c) {
          $categories[] = "tn.tid = %d";
          $arguments1[] = $c;
        }
        $conditions1 .= ' AND ('. implode(' OR ', $categories) .')';
        $join1 .= ' INNER JOIN {term_node} tn ON n.vid = tn.vid';
        $keys = search_query_insert($keys, 'category');
      }

      // Build ranking expression (we try to map each parameter to a
      // uniform distribution in the range 0..1).
      $ranking = array();
      $arguments2 = array();
      $join2 = '';
      // Used to avoid joining on node_comment_statistics twice
      $stats_join = FALSE;
      $total = 0;
      if ($weight = (int)variable_get('node_rank_relevance', 5)) {
        // Average relevance values hover around 0.15
        $ranking[] = '%d * i.relevance';
        $arguments2[] = $weight;
        $total += $weight;
      }
      if ($weight = (int)variable_get('node_rank_recent', 5)) {
        // Exponential decay with half-life of 6 months, starting at last indexed node
        $ranking[] = '%d * POW(2, (GREATEST(MAX(n.created), MAX(n.changed), MAX(c.last_comment_timestamp)) - %d) * 6.43e-8)';
        $arguments2[] = $weight;
        $arguments2[] = (int)variable_get('node_cron_last', 0);
        $join2 .= ' LEFT JOIN {node_comment_statistics} c ON c.nid = i.sid';
        $stats_join = TRUE;
        $total += $weight;
      }
      if (module_exists('comment') && $weight = (int)variable_get('node_rank_comments', 5)) {
        // Inverse law that maps the highest reply count on the site to 1 and 0 to 0.
        $scale = variable_get('node_cron_comments_scale', 0.0);
        $ranking[] = '%d * (2.0 - 2.0 / (1.0 + MAX(c.comment_count) * %f))';
        $arguments2[] = $weight;
        $arguments2[] = $scale;
        if (!$stats_join) {
          $join2 .= ' LEFT JOIN {node_comment_statistics} c ON c.nid = i.sid';
        }
        $total += $weight;
      }
      if (module_exists('statistics') && variable_get('statistics_count_content_views', 0) &&
          $weight = (int)variable_get('node_rank_views', 5)) {
        // Inverse law that maps the highest view count on the site to 1 and 0 to 0.
        $scale = variable_get('node_cron_views_scale', 0.0);
        $ranking[] = '%d * (2.0 - 2.0 / (1.0 + MAX(nc.totalcount) * %f))';
        $arguments2[] = $weight;
        $arguments2[] = $scale;
        $join2 .= ' LEFT JOIN {node_counter} nc ON nc.nid = i.sid';
        $total += $weight;
      }
      
      // When all search factors are disabled (ie they have a weight of zero), 
      // the default score is based only on keyword relevance and there is no need to 
      // adjust the score of each item. 
      if ($total == 0) {
        $select2 = 'i.relevance AS score';
        $total = 1;
      }
      else {
        $select2 = implode(' + ', $ranking) . ' AS score';
      }
      
      // Do search.
      $find = do_search($keys, 'node', 'INNER JOIN {node} n ON n.nid = i.sid '. $join1, $conditions1 . (empty($where1) ? '' : ' AND '. $where1), $arguments1, $select2, $join2, $arguments2);

      // Load results.
      $results = array();
      foreach ($find as $item) {
        // Build the node body.
        $node = node_load($item->sid);
        $node->build_mode = NODE_BUILD_SEARCH_RESULT;
        $node = node_build_content($node, FALSE, FALSE);
        $node->body = drupal_render($node->content);

        // Fetch comments for snippet.
        $node->body .= module_invoke('comment', 'nodeapi', $node, 'update index');
        // Fetch terms for snippet.
        $node->body .= module_invoke('taxonomy', 'nodeapi', $node, 'update index');

        $extra = node_invoke_nodeapi($node, 'search result');
        $results[] = array(
          'link' => url('node/'. $item->sid, array('absolute' => TRUE)),
          'type' => check_plain(node_get_types('name', $node)),
          'title' => $node->title,
          'user' => theme('username', $node),
          'date' => $node->changed,
          'node' => $node,
          'extra' => $extra,
          'score' => $item->score / $total,
          'snippet' => search_excerpt($keys, $node->body),
        );
      }
      return $results;
  }
}

/**
 * Implementation of hook_update_index().
 */
function _real_node_update_index() {
  $limit = (int)variable_get('search_cron_limit', 100);

  // Store the maximum possible comments per thread (used for ranking by reply count)
  variable_set('node_cron_comments_scale', 1.0 / max(1, db_result(db_query('SELECT MAX(comment_count) FROM {node_comment_statistics}'))));
  variable_set('node_cron_views_scale', 1.0 / max(1, db_result(db_query('SELECT MAX(totalcount) FROM {node_counter}'))));

  $result = db_query_range("SELECT n.nid FROM {node} n LEFT JOIN {search_dataset} d ON d.type = 'node' AND d.sid = n.nid WHERE d.sid IS NULL OR d.reindex <> 0 ORDER BY d.reindex ASC, n.nid ASC", 0, $limit);

  while ($node = db_fetch_object($result)) {
    _node_index_node($node);
  }
}

/**
 * Index a single node.
 *
 * @param $node
 *   The node to index.
 */
function _real__node_index_node($node) {
  $node = node_load($node->nid);

  // save the changed time of the most recent indexed node, for the search results half-life calculation
  variable_set('node_cron_last', $node->changed);

  // Build the node body.
  $node->build_mode = NODE_BUILD_SEARCH_INDEX;
  $node = node_build_content($node, FALSE, FALSE);
  $node->body = drupal_render($node->content);

  $text = '<h1>'. check_plain($node->title) .'</h1>'. $node->body;

  // Fetch extra data normally not visible
  $extra = node_invoke_nodeapi($node, 'update index');
  foreach ($extra as $t) {
    $text .= $t;
  }

  // Update index
  search_index($node->nid, 'node', $text);
}

/**
 * Implementation of hook_form_alter().
 */
function _real_node_form_alter(&$form, $form_state, $form_id) {
  // Advanced node search form
  if ($form_id == 'search_form' && $form['module']['#value'] == 'node' && user_access('use advanced search')) {
    // Keyword boxes:
    $form['advanced'] = array(
      '#type' => 'fieldset',
      '#title' => t('Advanced search'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#attributes' => array('class' => 'search-advanced'),
    );
    $form['advanced']['keywords'] = array(
      '#prefix' => '<div class="criterion">',
      '#suffix' => '</div>',
    );
    $form['advanced']['keywords']['or'] = array(
      '#type' => 'textfield',
      '#title' => t('Containing any of the words'),
      '#size' => 30,
      '#maxlength' => 255,
    );
    $form['advanced']['keywords']['phrase'] = array(
      '#type' => 'textfield',
      '#title' => t('Containing the phrase'),
      '#size' => 30,
      '#maxlength' => 255,
    );
    $form['advanced']['keywords']['negative'] = array(
      '#type' => 'textfield',
      '#title' => t('Containing none of the words'),
      '#size' => 30,
      '#maxlength' => 255,
    );

    // Taxonomy box:
    if ($taxonomy = module_invoke('taxonomy', 'form_all', 1)) {
      $form['advanced']['category'] = array(
        '#type' => 'select',
        '#title' => t('Only in the category(s)'),
        '#prefix' => '<div class="criterion">',
        '#size' => 10,
        '#suffix' => '</div>',
        '#options' => $taxonomy,
        '#multiple' => TRUE,
      );
    }

    // Node types:
    $types = array_map('check_plain', node_get_types('names'));
    $form['advanced']['type'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Only of the type(s)'),
      '#prefix' => '<div class="criterion">',
      '#suffix' => '</div>',
      '#options' => $types,
    );
    $form['advanced']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Advanced search'),
      '#prefix' => '<div class="action">',
      '#suffix' => '</div>',
    );

    $form['#validate'][] = 'node_search_validate';
  }
}

/**
 * Form API callback for the search form. Registered in node_form_alter().
 */
function _real_node_search_validate($form, &$form_state) {
  // Initialise using any existing basic search keywords.
  $keys = $form_state['values']['processed_keys'];

  // Insert extra restrictions into the search keywords string.
  if (isset($form_state['values']['type']) && is_array($form_state['values']['type'])) {
    // Retrieve selected types - Forms API sets the value of unselected checkboxes to 0.
    $form_state['values']['type'] = array_filter($form_state['values']['type']);
    if (count($form_state['values']['type'])) {
      $keys = search_query_insert($keys, 'type', implode(',', array_keys($form_state['values']['type'])));
    }
  }

  if (isset($form_state['values']['category']) && is_array($form_state['values']['category'])) {
    $keys = search_query_insert($keys, 'category', implode(',', $form_state['values']['category']));
  }
  if ($form_state['values']['or'] != '') {
    if (preg_match_all('/ ("[^"]+"|[^" ]+)/i', ' '. $form_state['values']['or'], $matches)) {
      $keys .= ' '. implode(' OR ', $matches[1]);
    }
  }
  if ($form_state['values']['negative'] != '') {
    if (preg_match_all('/ ("[^"]+"|[^" ]+)/i', ' '. $form_state['values']['negative'], $matches)) {
      $keys .= ' -'. implode(' -', $matches[1]);
    }
  }
  if ($form_state['values']['phrase'] != '') {
    $keys .= ' "'. str_replace('"', ' ', $form_state['values']['phrase']) .'"';
  }
  if (!empty($keys)) {
    form_set_value($form['basic']['inline']['processed_keys'], trim($keys), $form_state);
  }
}
