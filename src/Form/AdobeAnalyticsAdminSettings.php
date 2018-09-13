<?php

namespace Drupal\adobe_analytics\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Build the configuration form.
 */
class AdobeAnalyticsAdminSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {

    return 'adobe_analytics_settings';
  }

  /**
   * Get Editable configuratons.
   *
   * @return array
   *   Gets the configuration names that will be editable
   */
  protected function getEditableConfigNames() {
    return ['adobe_analytics.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('adobe_analytics.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
      '#weight' => '-10',
    ];

    $form['general']['js_file_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Complete path to Adobe Analytics Javascript file'),
      '#default_value' => $config->get('js_file_location'),
      '#required' => TRUE,
    ];

    $form['general']['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Adobe Analytics version (used by adobe_analytics for debugging)'),
      '#default_value' => $config->get('version'),
      '#required' => TRUE,
    ];

    $form['general']['image_file_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Complete path to Adobe Analytics Image file'),
      '#default_value' => $config->get('image_file_location'),
    ];

    $form['general']['token_cache_lifetime'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token cache lifetime'),
      '#default_value' => $config->get('token_cache_lifetime'),
      '#description' => $this->t(
        'The time, in seconds, that the Adobe Analytics token
         cache will be valid for. The token cache will always be cleared at the
         next system cron run after this time period, or when this form is saved.'
      ),
    ];

    $form['roles'] = [
      '#type' => 'details',
      '#title' => $this->t('User role tracking'),
      '#open' => TRUE,
      '#description' => $this->t('Define which user roles should, or should not be tracked by AdobeAnalytics.'),
      '#weight' => '-6',
    ];

    $default_value = ($config->get("role_tracking_type")) ? $config->get("role_tracking_type") : 'inclusive';
    $form['roles']['role_tracking_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Add tracking for specific roles'),
      '#options' => [
        'exclusive' => $this->t('Add to all roles except the ones selected'),
        'inclusive' => $this->t('Add to the selected roles only'),
      ],
      '#default_value' => $default_value,
    ];

    $roles = [];
    foreach (user_roles() as $role) {
      $roles[$role->id()] = $role->label();
    }

    $form['roles']['track_roles'] = [
      '#type' => 'checkboxes',
      '#options' => $roles,
      '#default_value' => $config->get('track_roles'),
    ];

    $form['variables'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom Variables'),
      '#open' => FALSE,
      '#description' => $this->t('You can define tracking variables here.'),
      '#weight' => '-3',
      '#prefix' => '<div id="variables-details-wrapper">',
      '#suffix' => '</div>',
    ];
    $this->adobeAnalyticsExtraVariablesForm($form, $form_state);

    $form['variables']['actions'] = [
      '#type' => 'actions',
    ];
    $form['variables']['actions']['add_variable'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add variable'),
      '#submit' => ['::addVariable'],
      '#ajax' => [
        'callback' => '::addVariableCallback',
        'wrapper' => 'variables-details-wrapper',
      ],
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
      '#description' => $this->t('You can add custom AdobeAnalytics code here.'),
      '#open' => FALSE,
      '#weight' => '-2',
    ];

    $description = 'Example : <br/> - if ([current-date:custom:N] >= 6) { s.prop5
         = "weekend"; }<br/>';
    $description .= '- if ("[current-page:url:path]" == "node") {s.prop9 = "homep
        age";} else {s.prop9 = "[current-page:title]";}';
    $form['advanced']['codesnippet'] = [
      '#type' => 'textarea',
      '#title' => $this->t('JavaScript Code'),
      '#default_value' => $config->get('codesnippet'),
      '#rows' => 15,
      '#description' => $description,
    ];

    $form['advanced']['tokens'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['node', 'menu', 'term', 'user'],
      '#global_types' => TRUE,
      '#click_insert' => TRUE,
      '#dialog' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Form for getting extra variables.
   */
  public function adobeAnalyticsExtraVariablesForm(&$form, FormStateInterface $form_state) {

    $config = $this->config('adobe_analytics.settings');
    $existing_vars = $config->get('extra_variables');

    if (empty($existing_vars)) {
      $existing_vars = [];
    }

    $values = $form_state->get('variables');
    $existing_variables = isset($values) ? $values : $existing_vars;

    $headers = [$this->t('Name'), $this->t('Value')];

    $form['variables']['variables'] = [
      '#type' => 'table',
      '#header' => $headers,
    ];

    foreach ($existing_variables as $key => $data) {
      $form = $this->adobeAnalyticsExtraVariableInputs($form, $key, $data);
    }

    // Always add a blank line at the end.
    $form = $this->adobeAnalyticsExtraVariableInputs($form, count($existing_variables));

    $form['variables']['tokens'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['node', 'menu', 'term', 'user'],
      '#global_types' => TRUE,
      '#click_insert' => TRUE,
      '#dialog' => TRUE,
    ];
  }

  /**
   * Get inputs in the extra variables form.
   */
  public function adobeAnalyticsExtraVariableInputs($form, $index, $data = []) {

    $form['variables']['variables'][$index]['name'] = [
      '#type' => 'textfield',
      '#size' => 40,
      '#maxlength' => 40,
      '#title_display' => 'invisible',
      '#title' => $this->t('Name'),
      '#default_value' => isset($data['name']) ? $data['name'] : '',
      '#attributes' => ['class' => ['field-variable-name']],
      '#element_validate' => [[$this, 'validateVariableName']],
    ];
    $form['variables']['variables'][$index]['value'] = [
      '#type' => 'textfield',
      '#size' => 40,
      '#maxlength' => 40,
      '#title_display' => 'invisible',
      '#title' => $this->t('Value'),
      '#default_value' => isset($data['value']) ? $data['value'] : '',
      '#attributes' => ['class' => ['field-variable-value']],
    ];

    if (empty($data)) {
      $form['variables']['variables'][$index]['name']['#description'] = $this->t('Example: prop1');
      $form['variables']['variables'][$index]['value']['#description'] = $this->t('Example: [current-page:title]');
    }
    return $form;
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addVariableCallback(array &$form, FormStateInterface $form_state) {

    // Leave the fieldset open.
    $form['variables']['#open'] = TRUE;
    return $form['variables'];
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addVariable(array &$form, FormStateInterface $form_state) {

    $input = $form_state->getUserInput();
    $form_state->set('variables', $input['variables']);
    $form_state->setRebuild();
  }

  /**
   * Element validate callback to ensure that variable names are valid.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateVariableName(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $variable_name = $element['#value'];

    // Variable names must follow the rules defined by javascript syntax.
    if (!empty($variable_name) && !preg_match("/^[A-Za-z_$]{1}\S*$/", $variable_name)) {
      $form_state->setError($element, $this->t('This is not a valid variable name. It must start with a letter, $ or _ and cannot contain spaces.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('adobe_analytics.settings');

    // Save extra variables.
    $extra_vars = [];
    foreach ($form_state->getValue('variables') as $variable) {
      if (!empty($variable['name']) && !empty($variable['value'])) {
        $extra_vars[] = ['name' => $variable['name'], 'value' => $variable['value']];
      }
    }

    // Save all the config variables.
    $config->set('extra_variables', $extra_vars)
      ->set('js_file_location', $form_state->getValue('js_file_location'))
      ->set('image_file_location', $form_state->getValue('image_file_location'))
      ->set('version', $form_state->getValue('version'))
      ->set('token_cache_lifetime', $form_state->getValue('token_cache_lifetime'))
      ->set('codesnippet', $form_state->getValue('codesnippet'))
      ->set('role_tracking_type', $form_state->getValue('role_tracking_type'))
      ->set('track_roles', $form_state->getValue('track_roles'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
