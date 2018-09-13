<?php

namespace Drupal\Tests\adobe_analytics\Unit;

use Drupal\adobe_analytics\TrackingMatcher\TrackingMatcherInterface;
use Drupal\adobe_analytics\VariableFormatter;
use Drupal\adobe_analytics\Variables;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Utility\Token;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Variable Formatter.
 *
 * @group adobe_analytics
 * @coversDefaultClass \Drupal\adobe_analytics\VariableFormatter
 */
class VariableFormatterTest extends UnitTestCase {

  /**
   * The mock entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  private $entityFieldManager;

  /**
   * The mock for the current route.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  private $routeMatch;

  /**
   * The mock for the current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack|\PHPUnit_Framework_MockObject_MockObject
   */
  private $currentPath;

  /**
   * The mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  private $entityTypeManager;

  /**
   * The mock token service.
   *
   * @var \Drupal\Core\Utility\Token|\PHPUnit_Framework_MockObject_MockObject
   */
  private $token;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
    $this->currentPath = $this->createMock(CurrentPathStack::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->token = $this->createMock(Token::class);
    $this->token->method('replace')
      ->willReturnArgument(0);
  }

  /**
   * Tests markup rendering when it is not allowed.
   *
   * @covers ::renderMarkup
   * @covers ::access
   * @covers ::addTrackingMatcher
   */
  public function testRenderMarkupForbidden() {
    $variables = new Variables('https://www.example.com/js', '1.23');
    $variables->setAllSections([
      Variables::HEADER_SECTION => [
        's_account' => 'account',
      ],
      Variables::VARIABLES_SECTION => [
        's_page' => 'https://www.example.com/page',
        's_title' => '[node:title]',
      ],
      Variables::FOOTER_SECTION => [
        's_section' => 'kittens',
      ],
    ]);

    $formatter = new VariableFormatter($variables, $this->entityFieldManager, $this->routeMatch, $this->currentPath, $this->entityTypeManager, $this->token);

    /** @var \Drupal\adobe_analytics\TrackingMatcher\TrackingMatcherInterface|\PHPUnit_Framework_MockObject_MockObject $forbid_tracking */
    $forbid_tracking = $this->createMock(TrackingMatcherInterface::class);
    $forbid_tracking->expects($this->once())->method('access')
      ->willReturn(new AccessResultForbidden());
    $formatter->addTrackingMatcher($forbid_tracking);

    $this->assertEquals([], $formatter->renderMarkup());
  }

  /**
   * Tests rendering variables where no replacements are required.
   *
   * @covers ::render
   * @covers ::renderVariables
   * @covers ::__construct
   */
  public function testRender() {
    $variables = new Variables('https://www.example.com/js', '1.23');
    $variables->setAllSections([
      Variables::HEADER_SECTION => [
        's_account' => 'account',
      ],
      Variables::VARIABLES_SECTION => [
        's_page' => 'https://www.example.com/page',
      ],
      Variables::FOOTER_SECTION => [
        's_section' => 'kittens',
      ],
    ]);

    $formatter = new VariableFormatter($variables, $this->entityFieldManager, $this->routeMatch, $this->currentPath, $this->entityTypeManager, $this->token);

    $rendered = $formatter->render();
    $this->assertNotSame($variables, $rendered);
    $this->assertEquals($variables->getVariables(), $rendered->getVariables());
  }

}
