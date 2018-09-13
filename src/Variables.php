<?php

namespace Drupal\adobe_analytics;

/**
 * Represents a set of Adobe Analytics variables.
 */
class Variables implements VariablesInterface {

  /**
   * Array of variables.
   *
   * @var array
   */
  protected $variables = [];

  /**
   * The URL of the tracking JavaScript.
   *
   * @var string
   */
  protected $jsFileLocation;

  /**
   * The version of the tracking JavaScript.
   *
   * @var string
   */
  protected $version;

  /**
   * A custom JavaScript code snippet.
   *
   * @var string
   */
  protected $codeSnippet = '';

  /**
   * The location of an image for no-JavaScript tracking.
   *
   * @var string
   */
  protected $imageFileLocation = '';

  /**
   * Variables constructor.
   *
   * @param string $jsFileLocation
   *   The URL of the tracking JavaScript.
   * @param string $version
   *   The version of the tracking JavaScript.
   */
  public function __construct(string $jsFileLocation, string $version) {
    $this->jsFileLocation = $jsFileLocation;
    $this->version = $version;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsFileLocation() {
    return $this->jsFileLocation;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion() {
    return $this->version;
  }

  /**
   * {@inheritdoc}
   */
  public function getCodeSnippet() {
    return $this->codeSnippet;
  }

  /**
   * Set the code snippet.
   *
   * @param string $codeSnippet
   *   The JavaScript code snippet.
   */
  public function setCodeSnippet(string $codeSnippet) {
    $this->codeSnippet = $codeSnippet;
  }

  /**
   * {@inheritdoc}
   */
  public function getImageFileLocation() {
    return $this->imageFileLocation;
  }

  /**
   * Set the URL of the no-js image tracker.
   *
   * @param string $imageFileLocation
   *   The URL to the image.
   */
  public function setNoJs(string $imageFileLocation) {
    $this->imageFileLocation = $imageFileLocation;
  }

  /**
   * Set all variable sections.
   *
   * @param array $sections
   *   The array of variables, keyed by section.
   *
   * @see \Drupal\adobe_analytics\VariablesInterface::VALID_SECTIONS
   */
  public function setAllSections(array $sections) {
    foreach ($sections as $section => $variables) {
      $this->setSection($section, $variables);
    }
  }

  /**
   * Set a single section of variables.
   *
   * @param string $section
   *   The name of the section.
   * @param array $variables
   *   The variables of the section.
   *
   * @see \Drupal\adobe_analytics\VariablesInterface::VALID_SECTIONS
   */
  public function setSection(string $section, array $variables) {
    $this->variables[$section] = [];
    foreach ($variables as $name => $value) {
      $this->setVariable($section, $name, $value);
    }
  }

  /**
   * Set a single variable.
   *
   * @param string $section
   *   The name of the section.
   * @param string $name
   *   The name of the variable.
   * @param string $value
   *   Value of the variable.
   *
   * @see \Drupal\adobe_analytics\VariablesInterface::VALID_SECTIONS
   */
  public function setVariable(string $section, string $name, $value) {
    if (!in_array($section, self::VALID_SECTIONS)) {
      throw new \InvalidArgumentException(sprintf('%s is not a valid section defined in VALID_SECTIONS.', $section));
    }
    $this->variables[$section][$name] = $value;
  }

  /**
   * Return all variables, grouped by section.
   *
   * @return array
   *   The array of variables.
   */
  public function getVariables() {
    return $this->variables;
  }

  /**
   * Create a new Variables object with the settings from an existing object.
   *
   * @param \Drupal\adobe_analytics\Variables $variables
   *   The Variables object to copy the configuration from.
   * @param array $sections
   *   The data to set on the new variables object.
   *
   * @return \Drupal\adobe_analytics\Variables
   *   A new variables object with the specified sections.
   */
  public static function fromVariables(Variables $variables, array $sections) {
    $new = clone $variables;
    $new->setAllSections($sections);
    return $new;
  }

}
