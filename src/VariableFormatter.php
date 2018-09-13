<?php

namespace Drupal\adobe_analytics;

use Drupal\adobe_analytics\TrackingMatcher\TrackingMatcherInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;

/**
 * Format analytics variables into arrays or strings.
 */
class VariableFormatter {

  /**
   * The Token replacement service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The current route.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPathStack;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The variables to render.
   *
   * @var \Drupal\adobe_analytics\VariablesInterface
   */
  protected $variables;

  /**
   * The tracking matchers used to determine if tracking should be skipped.
   *
   * @var \Drupal\adobe_analytics\TrackingMatcher\TrackingMatcherInterface[]
   */
  protected $trackingMatchers = [];

  /**
   * An array of objects to override token replacements.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface[]
   */
  private $tokenDataOverrides = [];

  /**
   * VariableFormatter constructor.
   *
   * Since we use a lazy builder for the markup, we can only specify scalars in
   * the callback to renderMarkup(). However, we can use factories to construct
   * and inject a Variables instance. This ensures that the hook to generate
   * variables has data from the subrequest and not the parent request.
   *
   * @todo
   *   This is a ton of dependencies. If we split the Token rendering into it's
   *   own class the formatter should become simpler.
   *
   * @param \Drupal\adobe_analytics\VariablesInterface $variables
   *   The variables to format.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPathStack
   *   The current path.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(VariablesInterface $variables, EntityFieldManagerInterface $entityFieldManager, RouteMatchInterface $routeMatch, CurrentPathStack $currentPathStack, EntityTypeManagerInterface $entityTypeManager, Token $token) {
    $this->variables = $variables;
    $this->entityFieldManager = $entityFieldManager;
    $this->routeMatch = $routeMatch;
    $this->currentPathStack = $currentPathStack;
    $this->entityTypeManager = $entityTypeManager;
    $this->token = $token;
  }

  /**
   * Lazy builder callback to render markup.
   *
   * @return array
   *   Build array.
   */
  public function renderMarkup() {
    if ($this->variables instanceof ModuleNotConfiguredVariables) {
      return [];
    }

    if ($this->access()->isForbidden()) {
      return [];
    }

    $build = [
      '#theme' => 'analytics_code',
      '#js_file_location' => $this->variables->getJsFileLocation(),
      '#version' => $this->variables->getVersion(),
      '#image_location' => $this->variables->getImageFileLocation(),
      '#formatted_vars' => $this->getFormattedVariables(),
    ];

    return $build;
  }

  /**
   * Determine if tracking should be skipped.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The result of checking for analytics access.
   */
  protected function access() {
    // We grant access to the entity if both of these conditions are met:
    // - No modules say to deny access.
    // - At least one module says to grant access.
    $access = [];
    foreach ($this->trackingMatchers as $matcher) {
      $access[] = $matcher->access();
    }

    // No results means no opinion.
    if (empty($access)) {
      return AccessResult::neutral();
    }

    /** @var \Drupal\Core\Access\AccessResultInterface $result */
    $result = array_shift($access);
    foreach ($access as $other) {
      $result = $result->orIf($other);
    }
    return $result;
  }

  /**
   * Get the formatted variables as a string.
   *
   * @return string
   *   The formatted variables with all sections rendered.
   */
  protected function getFormattedVariables(): string {
    // Extract entity overrides.
    list($include_main_codesnippet, $include_custom_variables, $entity_snippet) = $this->extractEntityOverrides();

    // Format and combine variables in the "right" order
    // Right order is the code file (list likely to be maintained)
    // Then admin settings with codesnippet first and finally taxonomy->vars.
    $formatted_vars = '';

    // Load variables implemented by modules.
    $rendered = $this->render()->getVariables();

    // Append header variables.
    if ($include_custom_variables && !empty($rendered[Variables::HEADER_SECTION])) {
      $formatted_vars = $this->formatVariables($rendered[Variables::HEADER_SECTION]);
    }

    // Append main JavaScript snippet.
    if ($include_main_codesnippet) {
      $formatted_vars .= $this->formatJsSnippet($this->variables->getCodeSnippet());
    }

    // Append main variables.
    if ($include_custom_variables && !empty($rendered[Variables::VARIABLES_SECTION])) {
      $formatted_vars .= $this->formatVariables($rendered[Variables::VARIABLES_SECTION]);
    }

    // Append footer variables.
    if ($include_custom_variables && !empty($rendered[Variables::FOOTER_SECTION])) {
      $formatted_vars .= $this->formatVariables($rendered[Variables::FOOTER_SECTION]);
    }

    // Append entity's custom snippet.
    if (!empty($entity_snippet)) {
      $formatted_vars .= $this->formatJsSnippet($entity_snippet);
    }
    return $formatted_vars;
  }

  /**
   * Extracts entity overrides when the entity has an Adobe Analytics field.
   *
   * @return array
   *   An array containing:
   *     * A flag for whether to include the global custom JavaScript snippet.
   *     * A flag for whether to include the global custom variables.
   *     * A string with a custom JavaScript snippet, or an empty string.
   */
  protected function extractEntityOverrides() {
    // Check if we are viewing an entity containing field overrides.
    $entity_field_manager = $this->entityFieldManager;
    $route_match = $this->routeMatch;
    $entity = NULL;
    $field_name = NULL;
    foreach ($entity_field_manager->getFieldMapByFieldType('adobe_analytics') as $entity_type => $field_config) {
      if ($entity = $route_match->getParameter($entity_type)) {
        $field_name = key($field_config);
        break;
      }
    }

    $include_main_codesnippet = TRUE;
    $include_custom_variables = TRUE;
    $entity_snippet = '';
    if (!empty($entity) && !$entity->{$field_name}->isEmpty()) {
      $entity_values = $entity->{$field_name}->first()->getValue();
      $include_main_codesnippet = $entity_values['include_main_codesnippet'];
      $include_custom_variables = $entity_values['include_custom_variables'];
      $entity_snippet = $entity_values['codesnippet'];
    }

    return [
      $include_main_codesnippet,
      $include_custom_variables,
      $entity_snippet,
    ];
  }

  /**
   * Return a new Variables instance with tokens replaced and keys sanitized.
   *
   * @return \Drupal\adobe_analytics\Variables
   *   The new variables instance.
   */
  public function render() {
    if ($this->variables instanceof Variables) {
      $sections = [];
      foreach ($this->variables->getVariables() as $key => $value) {
        $sections[$key] = $this->renderVariables($value);
      }

      return Variables::fromVariables($this->variables, $sections);
    }

    throw new \RuntimeException('Adobe Analytics is not configured so variables can not be returned.');
  }

  /**
   * Render variables so they are suitable for display.
   *
   * @param array $variables
   *   The array of variables to render.
   *
   * @return array
   *   An array of rendered variables. Empty variable values are removed.
   */
  protected function renderVariables(array $variables): array {
    $rendered = [];
    foreach ($variables as $key => $value) {
      if (is_array($value)) {
        // Use the last element.
        $value = end($value);
      }

      $key = htmlspecialchars($key, ENT_NOQUOTES, 'UTF-8');
      $rendered[$key] = $this->tokenReplace($value, [
        'clear' => TRUE,
        'sanitize' => TRUE,
      ]);
    }

    // Remove empty strings but not zeros as they could be valid data.
    $rendered = array_filter($rendered, function ($value, $key) {
      return trim($value) !== '';
    }, ARRAY_FILTER_USE_BOTH);

    return $rendered;
  }

  /**
   * Replace tokens.
   *
   * @param string $text
   *   The text to process.
   * @param array $options
   *   (optional) An array of token options.
   *
   * @return string
   *   The replaced value.
   */
  protected function tokenReplace(string $text, array $options = []) {
    $data = $this->tokenDataOverrides;
    $token_type = $this->getEntityTypeFromText($text);

    if (!empty($token_type) && !isset($data[$token_type])) {
      $entity = $this->extractTokenEntityFromPath($token_type);
      if ($entity) {
        $data[$token_type] = $entity;
      }
    }

    return $this->token->replace($text, $data, $options);
  }

  /**
   * Return entity type of token.
   *
   * @param string $text
   *   The token to extract the entity type from.
   *
   * @return string|null
   *   The entity type, or NULL if an entity type could not be extracted.
   */
  private function getEntityTypeFromText(string $text) {
    $matches = [];
    preg_match('/\[([^\s\[\]:]*):([^\s\[\]]*)\]/x', $text, $matches);

    if (!empty($matches[1])) {
      // Deal with issue of term entity name.
      return preg_replace('/term/', 'taxonomy_term', $matches[1]);
    }

    return NULL;
  }

  /**
   * Format an array of variables, split by semicolons.
   *
   * @param array $variables
   *   The array of variables.
   *
   * @return string
   *   The formatted string.
   */
  protected function formatVariables(array $variables = []) {
    $variables_formatted = '';
    foreach ($this->renderVariables($variables) as $key => $value) {
      $variables_formatted .= "{$key}=\"{$value}\";\n";
    }
    return $variables_formatted;
  }

  /**
   * Processes tokens and formats a JavaScript snippet.
   *
   * @param string $raw_snippet
   *   The raw snippet.
   *
   * @return string
   *   The processed snippet.
   */
  protected function formatJsSnippet(string $raw_snippet) {
    // Add any custom code snippets if specified and replace any tokens.
    $snippet = $this->tokenReplace(
        $raw_snippet, [
          'clear' => TRUE,
          'sanitize' => TRUE,
        ]
      ) . "\n";
    return $snippet;
  }

  /**
   * Add data to be used when rendering tokens.
   *
   * This data will override any data from the request.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to add.
   * @param string $type
   *   The type of the entity's tokens, such as 'node'.
   */
  public function addTokenContext(ContentEntityInterface $entity, string $type) {
    $this->tokenDataOverrides[$type] = $entity;
  }

  /**
   * Add a matcher for determining if a request should be tracked.
   *
   * @param \Drupal\adobe_analytics\TrackingMatcher\TrackingMatcherInterface $trackingMatcher
   *   The matcher to add.
   */
  public function addTrackingMatcher(TrackingMatcherInterface $trackingMatcher) {
    $this->trackingMatchers[] = $trackingMatcher;
  }

  /**
   * Extract an entity context for a token from the current request path.
   *
   * @param string $entity_type
   *   The entity type to extract.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The extracted entity, or FALSE if one could not be loaded.
   */
  private function extractTokenEntityFromPath(string $entity_type) {
    // Return current entity path and parameters.
    $path = $this->currentPathStack->getPath();

    $params = Url::fromUserInput($path)->getRouteParameters();
    $entity = FALSE;

    // Set token through token replace.
    if (!empty($params[$entity_type]) && $this->entityTypeManager->hasHandler($entity_type, 'storage')) {
      // Return entity data.
      $entity = $this->entityTypeManager->getStorage($entity_type)
        ->load($params[$entity_type]);
    }

    return $entity;
  }

}
