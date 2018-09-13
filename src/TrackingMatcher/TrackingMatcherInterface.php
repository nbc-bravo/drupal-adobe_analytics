<?php

namespace Drupal\adobe_analytics\TrackingMatcher;

/**
 * Interface for rules to determine if a request should be tracked.
 *
 * @todo
 *   This should probably use AccessResults and possibly have a full plugin
 *   manager instead of simple tagged services.
 */
interface TrackingMatcherInterface {

  /**
   * Determines whether or not to skip adding analytics code.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   */
  public function access();

}
