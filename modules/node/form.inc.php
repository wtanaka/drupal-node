<?php

/**
 * See if the user used JS to submit a teaser.
 */
function _real_node_teaser_js(&$form, &$form_state) {
  if (isset($form['#post']['teaser_js'])) {
    // Glue the teaser to the body.
    if (trim($form_state['values']['teaser_js'])) {
      // Space the teaser from the body
      $body = trim($form_state['values']['teaser_js']) ."\r\n<!--break-->\r\n". trim($form_state['values']['body']);
    }
    else {
      // Empty teaser, no spaces.
      $body = '<!--break-->'. $form_state['values']['body'];
    }
    // Pass updated body value on to preview/submit form processing.
    form_set_value($form['body'], $body, $form_state);
    // Pass updated body value back onto form for those cases
    // in which the form is redisplayed.
    $form['body']['#value'] = $body;
  }
  return $form;
}

/**
 * Ensure value of "teaser_include" checkbox is consistent with other form data.
 *
 * This handles two situations in which an unchecked checkbox is rejected:
 *
 *   1. The user defines a teaser (summary) but it is empty;
 *   2. The user does not define a teaser (summary) (in this case an
 *      unchecked checkbox would cause the body to be empty, or missing
 *      the auto-generated teaser).
 *
 * If JavaScript is active then it is used to force the checkbox to be
 * checked when hidden, and so the second case will not arise.
 *
 * In either case a warning message is output.
 */
function _real_node_teaser_include_verify(&$form, &$form_state) {
  $message = '';

  // $form['#post'] is set only when the form is built for preview/submit.
  if (isset($form['#post']['body']) && isset($form_state['values']['teaser_include']) && !$form_state['values']['teaser_include']) {
    // "teaser_include" checkbox is present and unchecked.
    if (strpos($form_state['values']['body'], '<!--break-->') === 0) {
      // Teaser is empty string.
      $message = t('You specified that the summary should not be shown when this post is displayed in full view. This setting is ignored when the summary is empty.');
    }
    elseif (strpos($form_state['values']['body'], '<!--break-->') === FALSE) {
      // Teaser delimiter is not present in the body.
      $message = t('You specified that the summary should not be shown when this post is displayed in full view. This setting has been ignored since you have not defined a summary for the post. (To define a summary, insert the delimiter "&lt;!--break--&gt;" (without the quotes) in the Body of the post to indicate the end of the summary and the start of the main content.)');
    }

    if (!empty($message)) {
      drupal_set_message($message, 'warning');
      // Pass new checkbox value on to preview/submit form processing.
      form_set_value($form['teaser_include'], 1, $form_state);
      // Pass new checkbox value back onto form for those cases
      // in which form is redisplayed.
      $form['teaser_include']['#value'] = 1;
    }
  }

  return $form;
}

/**
 * Implementation of hook_form().
 */
function _real_node_content_form($node, $form_state) {
  $type = node_get_types('type', $node);
  $form = array();

  if ($type->has_title) {
    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => check_plain($type->title_label),
      '#required' => TRUE,
      '#default_value' => $node->title,
      '#maxlength' => 255,
      '#weight' => -5,
    );
  }

  if ($type->has_body) {
    $form['body_field'] = node_body_field($node, $type->body_label, $type->min_word_count);
  }

  return $form;
}

