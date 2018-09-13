<?php

namespace Drupal\Tests\adobe_analytics\Kernel;

use Drupal\adobe_analytics\TrackingMatcher\TrackingMatcherInterface;
use Drupal\adobe_analytics\VariableFormatter;
use Drupal\adobe_analytics\Variables;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests VariableFormatter.
 *
 * This class could almost be a unit test, but mocking the Token class is
 * complex to mock when replacements are required.
 *
 * @group adobe_analytics
 *
 * @coversDefaultClass \Drupal\adobe_analytics\VariableFormatter
 */
class VariableFormatterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'node',
    'adobe_analytics',
  ];

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
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  private $token;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
    $this->currentPath = $this->createMock(CurrentPathStack::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->token = $this->container->get('token');
    $this->installConfig('adobe_analytics');
  }

  /**
   * Tests rendering markup for the lazy builder.
   *
   * @covers ::renderMarkup
   * @covers ::access
   * @covers ::getFormattedVariables
   * @covers ::formatVariables
   * @covers ::formatJsSnippet
   */
  public function testRenderMarkup() {
    $variables = new Variables('http://www.example.com/js/s_code_remote_h.js', 'H.20.3.');
    $variables->setAllSections([
      Variables::HEADER_SECTION => [
        'boom' => 'pow',
      ],
      Variables::VARIABLES_SECTION => [
        'slap' => 'twist',
      ],
      Variables::FOOTER_SECTION => [
        'smash' => 'crash',
      ],
    ]);
    $variables->setCodeSnippet('foo="bar";');
    $variables->setNoJs('http://examplecom.112.2O7.net/b/ss/examplecom/1/H.20.3--NS/0');

    $this->entityFieldManager->method('getFieldMapByFieldType')
      ->willReturn([]);
    $formatter = new VariableFormatter($variables, $this->entityFieldManager, $this->routeMatch, $this->currentPath, $this->container->get('entity_type.manager'), $this->token);

    /** @var \Drupal\adobe_analytics\TrackingMatcher\TrackingMatcherInterface|\PHPUnit_Framework_MockObject_MockObject $allow_tracking */
    $allow_tracking = $this->createMock(TrackingMatcherInterface::class);
    $allow_tracking->expects($this->once())->method('access')
      ->willReturn(new AccessResultAllowed());
    $formatter->addTrackingMatcher($allow_tracking);

    $expected = [
      '#theme' => 'analytics_code',
      '#js_file_location' => 'http://www.example.com/js/s_code_remote_h.js',
      '#version' => 'H.20.3.',
      '#image_location' => 'http://examplecom.112.2O7.net/b/ss/examplecom/1/H.20.3--NS/0',
      '#formatted_vars' => "boom=\"pow\";\nfoo=\"bar\";\nslap=\"twist\";\nsmash=\"crash\";\n",
    ];
    $this->assertEquals($expected, $formatter->renderMarkup());
  }

  /**
   * Test rendering with replacing tokens from the current request.
   *
   * @covers ::render
   * @covers ::renderVariables
   * @covers ::tokenReplace
   */
  public function testRenderWithPathEntity() {
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

    $formatter = new VariableFormatter($variables, $this->entityFieldManager, $this->routeMatch, $this->currentPath, $this->container->get('entity_type.manager'), $this->token);

    $node = Node::create([
      'type' => 'article',
      'title' => 'the title',
    ]);
    $node->save();

    $this->currentPath->method('getPath')
      ->willReturn('/node/1');

    $rendered = $formatter->render();
    $this->assertNotSame($variables, $rendered);

    $expected = $variables->getVariables();
    $expected[Variables::VARIABLES_SECTION]['s_title'] = 'the title';
    $this->assertEquals($expected, $rendered->getVariables());
  }

  /**
   * Tests rendering with tokens from a specific entity.
   *
   * @covers ::render
   * @covers ::renderVariables
   * @covers ::tokenReplace
   * @covers ::addTokenContext
   */
  public function testRenderWithSpecificEntity() {
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

    /** @var \Drupal\node\NodeInterface|\PHPUnit_Framework_MockObject_MockObject $node */
    $node = $this->createMock(NodeInterface::class);
    $node->method('getTitle')->willReturn('the title');
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheTags')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(0);
    $formatter->addTokenContext($node, 'node');

    $rendered = $formatter->render();
    $this->assertNotSame($variables, $rendered);

    $expected = $variables->getVariables();
    $expected[Variables::VARIABLES_SECTION]['s_title'] = 'the title';
    $this->assertEquals($expected, $rendered->getVariables());
  }

  /**
   * Tests rendering with tokens from a specific entity.
   *
   * @covers ::render
   * @covers ::renderVariables
   * @covers ::tokenReplace
   * @covers ::addTokenContext
   */
  public function testReplaceEmptyTokens() {
    $variables = new Variables('https://www.example.com/js', '1.23');
    $variables->setAllSections([
      Variables::HEADER_SECTION => [
        's_account' => 'account',
      ],
      Variables::VARIABLES_SECTION => [
        's_page' => 'https://www.example.com/page',
        's_body' => '[node:body]',
      ],
      Variables::FOOTER_SECTION => [
        's_section' => 'kittens',
      ],
    ]);

    $formatter = new VariableFormatter($variables, $this->entityFieldManager, $this->routeMatch, $this->currentPath, $this->entityTypeManager, $this->token);

    /** @var \Drupal\node\NodeInterface|\PHPUnit_Framework_MockObject_MockObject $node */
    $node = $this->createMock(NodeInterface::class);
    $node->method('getTitle')->willReturn('the title');
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheTags')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(0);
    $formatter->addTokenContext($node, 'node');

    $rendered = $formatter->render();
    $this->assertNotSame($variables, $rendered);

    $expected = $variables->getVariables();
    unset($expected[Variables::VARIABLES_SECTION]['s_body']);
    $this->assertEquals($expected, $rendered->getVariables());
  }

}
