<?php

namespace Drupal\adobe_analytics\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines the 'adobe_analytics' field widget.
 *
 * @FieldWidget(
 *   id = "adobe_analytics",
 *   label = @Translation("Adobe Analytics"),
 *   field_types = {"adobe_analytics"},
 * )
 */
class AdobeAnalyticsWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['adobe_analytics'] = [
      '#type' => 'details',
      '#title' => $this->t('Adobe Analytics'),
      '#description' => $this->t('Adjust the following settings to override the <a href="@adobe_analytics_form" target="_blank">Adobe Analytics\' settings form</a> configuration.', [
        '@adobe_analytics_form' => Url::fromRoute('adobe_analytics.settings')->toString(),
      ]),
      '#open' => FALSE,
      '#weight' => '-2',
    ];

    $element['adobe_analytics']['include_custom_variables'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include variables'),
      '#default_value' => isset($items[$delta]->include_custom_variables) ? $items[$delta]->include_custom_variables : TRUE,
    ];

    $element['adobe_analytics']['include_main_codesnippet'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include main JavaScript code'),
      '#default_value' => isset($items[$delta]->include_main_codesnippet) ? $items[$delta]->include_main_codesnippet : TRUE,
    ];

    $description = 'Example : <br/> - if ([current-date:custom:N] >= 6) { s.prop5
         = "weekend"; }<br/>';
    $description .= '- if ("[current-page:url:path]" == "node") {s.prop9 = "homep
        age";} else {s.prop9 = "[current-page:title]";}';
    $element['adobe_analytics']['codesnippet'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom JavaScript Code for the current entity'),
      '#default_value' => isset($items[$delta]->codesnippet) ? $items[$delta]->codesnippet : '',
      '#rows' => 15,
      '#description' => $description,
    ];

    $element['adobe_analytics']['tokens'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['node', 'menu', 'term', 'user'],
      '#global_types' => TRUE,
      '#click_insert' => TRUE,
      '#dialog' => TRUE,
    ];

    return $element;
  }

  /**
   * @inheritDoc
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Extract values from the fieldset.
    foreach ($values as $key => $value) {
      $values[$key] += $value['adobe_analytics'];
      unset($values[$key]['adobe_analytics']);
    }

    return $values;
  }

}
