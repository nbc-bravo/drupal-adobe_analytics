<?php

namespace Drupal\adobe_analytics\TrackingMatcher;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\AdminContext as RoutingAdminContext;

/**
 * Skip tracking based on the admin route.
 */
class AdminContext implements TrackingMatcherInterface {

  /**
   * The admin route context.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * AdminContext constructor.
   *
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   The admin route context.
   */
  public function __construct(RoutingAdminContext $adminContext) {
    $this->adminContext = $adminContext;
  }

  /**
   * {@inheritdoc}
   */
  public function access() {
    if ($this->adminContext->isAdminRoute()) {
      return AccessResult::forbidden('This is an administration page.');
    }

    return AccessResult::neutral();
  }

}
