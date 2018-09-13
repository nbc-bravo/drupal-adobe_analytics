<?php

namespace Drupal\adobe_analytics\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'adobe_analytics' field type.
 *
 * @FieldType(
 *   id = "adobe_analytics",
 *   label = @Translation("Adobe Analytics"),
 *   category = @Translation("General"),
 *   default_widget = "adobe_analytics",
 *   default_formatter = "adobe_analytics",
 *   cardinality = 1
 * )
 */
class AdobeAnalyticsItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return ($this->get('include_custom_variables') == NULL) &&
      ($this->get('include_main_codesnippet') == NULL) &&
        empty($this->get('codesnippet')->getValue());
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['include_custom_variables'] = DataDefinition::create('boolean')
      ->setLabel(t('Include custom variables'))
      ->setRequired(TRUE);
    $properties['include_main_codesnippet'] = DataDefinition::create('boolean')
      ->setLabel(t('JavaScript Code'))
      ->setRequired(TRUE);
    $properties['codesnippet'] = DataDefinition::create('string')
      ->setLabel(t('JavaScript Code'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'include_custom_variables' => [
        'description' => 'Include custom variables',
        'type' => 'int',
        'size' => 'tiny',
        'unsigned' => TRUE,
        'default' => 1,
      ],
      'include_main_codesnippet' => [
        'description' => 'JavaScript Code',
        'type' => 'int',
        'size' => 'tiny',
        'unsigned' => TRUE,
        'default' => 1,
      ],
      'codesnippet' => [
        'description' => 'JavaScript Code',
        'type' => 'text',
        'not null' => FALSE,
      ],
    ];

    $schema = [
      'columns' => $columns,
    ];

    return $schema;
  }

}
