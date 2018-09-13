<?php

namespace Drupal\adobe_analytics;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;

/**
 * Null object representing Variables when the module has not been configured.
 */
class ModuleNotConfiguredVariables implements VariablesInterface {
  use StringTranslationTrait;

  /**
   * ModuleNotConfiguredVariables constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger used to warn the user that tracking is not functioning.
   */
  public function __construct(LoggerInterface $logger) {
    $logger->warning('Adobe Analytics is installed but missing required configuration settings.', [
      'link' => $this->t('<a href=":url">configure</a>', [':url' => Url::fromRoute('adobe_analytics.settings')->toString()]),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getJsFileLocation() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCodeSnippet() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getImageFileLocation() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getVariables() {
    return [];
  }

}
