<?php

namespace Drupal\adobe_analytics;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Factory for loading analytics variables.
 */
class VariablesFactory {

  /**
   * The module handler used to call hook_adobe_analytics_variables().
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The system logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Adobe Analytics settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * VariablesFactory constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler used to call hook_adobe_analytics_variables().
   * @param \Psr\Log\LoggerInterface $logger
   *   The system logger.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler, LoggerInterface $logger) {
    $this->config = $configFactory->get('adobe_analytics.settings');
    $this->moduleHandler = $moduleHandler;
    $this->logger = $logger;
  }

  /**
   * Load the analytics variables.
   *
   * @return \Drupal\adobe_analytics\VariablesInterface
   *   A new variables instance. If variables cannot be loaded due to invalid
   *   configuration, a new \Drupal\adobe_analytics\ModuleNotConfiguredVariables
   *   is returned.
   */
  public function load() {
    // Extract module settings.
    $js_file_location = $this->config->get('js_file_location');
    $version = $this->config->get("version");

    if (!$js_file_location || !$version) {
      return new ModuleNotConfiguredVariables($this->logger);
    }

    $variables = new Variables($js_file_location, $version);

    if ($image_file_location = $this->config->get("image_file_location")) {
      $variables->setNoJs($image_file_location);
    }

    if ($codesnippet = $this->config->get('codesnippet')) {
      $variables->setCodeSnippet($codesnippet);
    }

    $data = $this->moduleHandler->invokeAll('adobe_analytics_variables', []);
    $variables->setAllSections($data);

    $settings_variables = $this->config->get('extra_variables');
    foreach ($settings_variables as $data) {
      $variables->setVariable('variables', $data['name'], $data['value']);
    }

    return $variables;
  }

}
