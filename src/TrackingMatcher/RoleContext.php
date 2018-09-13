<?php

namespace Drupal\adobe_analytics\TrackingMatcher;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Skip tracking based on configured roles.
 */
class RoleContext implements TrackingMatcherInterface {

  /**
   * The Adobe Analytics settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * RoleContext constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountProxyInterface $current_user) {
    $this->config = $config_factory->get('adobe_analytics.settings');
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function access() {
    // Check if we should track the currently active user's role.
    $tracking_type = $this->config->get('role_tracking_type');
    $stored_roles = $this->config->get('track_roles');

    $selected_roles = [];
    if ($stored_roles) {
      $selected_roles = array_filter($stored_roles);
    }

    // Compare the roles with current user.
    $union = array_intersect($selected_roles, $this->currentUser->getRoles());
    if (($tracking_type == 'inclusive' && !empty($union)) || empty($union)) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden('The current user does not belong to a tracked role.');
  }

}
