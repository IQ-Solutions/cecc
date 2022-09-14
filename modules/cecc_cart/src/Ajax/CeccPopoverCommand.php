<?php

namespace Drupal\cecc_cart\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Tippy Popover Ajax Command.
 */
class CeccPopoverCommand implements CommandInterface {

  /**
   * The triggering element id.
   *
   * @var string
   */
  protected $selector;

  /**
   * The popover type either. Either success or danger.
   *
   * @var string
   */
  protected $type;

  /**
   * Tippy options.
   *
   * @var array
   */
  protected $options;

  /**
   * The popover render array.
   *
   * @var array
   */
  protected $content;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  public function __construct(
    $selector,
    $type,
    $content,
    array $options) {
    $this->selector = $selector;
    $this->type = $type;
    $this->options = $options;
    $this->content = $content;
    $this->renderer = \Drupal::service('renderer');
  }

  /**
   * {@inheritDoc}
   */
  public function render() {
    return [
      'command' => 'ceccPopover',
      'options' => $this->options,
      'content' => $this->renderer->render($this->content),
      'type' => $this->type,
    ];
  }

}
