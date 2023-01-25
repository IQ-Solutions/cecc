<?php

namespace Drupal\cecc\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderTotalSummaryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\BareHtmlPageRenderer;
use Drupal\Core\Render\Renderer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays a commerce order receipt.
 */
class CeccOrderReceipt extends ControllerBase {

  /**
   * The profile view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  public $profileViewBuilder;

  /**
   * The order total summary service.
   *
   * @var \Drupal\commerce_order\OrderTotalSummaryInterface
   */
  public $orderTotalSummary;

  /**
   * The order total summary service.
   *
   * @var \Drupal\Core\Render\AttachmentsResponseProcessorInterface
   */
  public $attachmentsProcessor;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  public $renderer;

  /**
   * View order receipt controller construct.
   *
   * @param Drupal\commerce_order\OrderTotalSummaryInterface $order_total_summary
   *   The order total summary service.
   * @param Drupal\Core\Render\AttachmentsResponseProcessorInterface $attachments_processor
   *   The HTML response attachments processor.
   * @param Drupal\Core\Render\Renderer $renderer
   *   The render service.
   */
  public function __construct(
    OrderTotalSummaryInterface $order_total_summary,
    AttachmentsResponseProcessorInterface $attachments_processor,
    Renderer $renderer
  ) {
    $this->profileViewBuilder = $this->entityTypeManager()->getViewBuilder('profile');
    $this->orderTotalSummary = $order_total_summary;
    $this->attachmentsProcessor = $attachments_processor;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_order.order_total_summary'),
      $container->get('html_response.attachments_processor'),
      $container->get('renderer')
    );
  }

  /**
   * View order receipt.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function viewReceipt(OrderInterface $order) {
    $build = [
      '#theme' => 'commerce_order_receipt',
      '#order_entity' => $order,
      '#totals' => $this->orderTotalSummary->buildTotals($order),
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    if ($billing_profile = $order->getBillingProfile()) {
      $build['#billing_information'] = $this->profileViewBuilder->view($billing_profile);
    }

    $bareHtmlPageRenderer = new BareHtmlPageRenderer($this->renderer, $this->attachmentsProcessor);

    $response = $bareHtmlPageRenderer->renderBarePage($build, "Order Receipt", 'markup');

    return $response;
  }

}
