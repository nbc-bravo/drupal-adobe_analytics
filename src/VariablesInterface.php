<?php

namespace Drupal\adobe_analytics;

/**
 * Interface representing Adobe Analytics variables and their configuration.
 */
interface VariablesInterface {

  /**
   * The name of the header variables section.
   */
  const HEADER_SECTION = 'header';

  /**
   * The name of the primary variables section.
   */
  const VARIABLES_SECTION = 'variables';

  /**
   * The name of the footer variables section.
   */
  const FOOTER_SECTION = 'footer';

  /**
   * A list of all valid variables sections.
   */
  const VALID_SECTIONS = [
    self::HEADER_SECTION,
    self::VARIABLES_SECTION,
    self::FOOTER_SECTION,
  ];

  /**
   * To allow tracking by the Adobe Analytics package.
   */
  const ADOBEANALYTICS_TOKEN_CACHE = 'adobe_analytics:tag_token_results';

  /**
   * Return the URL of the tracking JavaScript.
   *
   * @return string
   *   The JavaScript URL.
   */
  public function getJsFileLocation();

  /**
   * Return the version of the tracking JavaScript.
   *
   * @return string
   *   The JavaScript version.
   */
  public function getVersion();

  /**
   * Return the JavaScript code snippet, if one is set.
   *
   * @return string
   *   The JavaScript code snippet.
   */
  public function getCodeSnippet();

  /**
   * Return the image used for no-JavaScript tracking.
   *
   * @return string
   */
  public function getImageFileLocation();

  /**
   * Return all variables, keyed by their section.
   *
   * @return array
   *   The array of variables, keyed by a valid section.
   */
  public function getVariables();

}
