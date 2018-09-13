<?php

namespace Drupal\adobe_analytics;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider to register tagged services for tracking matchers.
 */
class AdobeAnalyticsServiceProvider implements ServiceModifierInterface {

  /**
   * Modifies existing service definitions.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The ContainerBuilder whose service definitions can be altered.
   */
  public function alter(ContainerBuilder $container) {
    if (!$container->has('adobe_analytics.variable_formatter')) {
      return;
    }

    $definition = $container->findDefinition('adobe_analytics.variable_formatter');

    $taggedServices = $container->findTaggedServiceIds('adobe_analytics_tracking_matcher');

    foreach ($taggedServices as $id => $tags) {
      $definition->addMethodCall('addTrackingMatcher', [new Reference($id)]);
    }
  }

}
