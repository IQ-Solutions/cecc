<?php

namespace Drupal\cecc_restocked\EventSubscriber;

use Drupal\cecc_stock\Event\RestockEvent;
use Drupal\cecc_stock\Service\StockValidation;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles product variation restock updates.
 */
class RestockSubscriber implements EventSubscriberInterface {

  /**
   * Drupal logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Stock Validation Service.
   *
   * @var \Drupal\cecc_stock\Service\StockValidation
   */
  protected $stockValidation;

  /**
   * Stock the state service.
   *
   * @var \Drupal\core\State\StateInterface
   */
  protected $state;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flag;

  /**
   * The Queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Contstructs a new order event subscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger Channel Factory.
   * @param \Drupal\cecc_stock\Service\StockValidation $stockValidation
   *   Logger Channel Factory.
   * @param \Drupal\core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\flag\FlagServiceInterface $flag
   *   The flag service.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue Factory service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    StockValidation $stockValidation,
    StateInterface $state,
    FlagServiceInterface $flag,
    QueueFactory $queueFactory
  ) {
    $this->logger = $loggerFactory->get('cecc');
    $this->stockValidation = $stockValidation;
    $this->state = $state;
    $this->flag = $flag;
    $this->queueFactory = $queueFactory;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      RestockEvent::CECC_PRODUCT_VARIATION_RESTOCK => [
        'onRestock',
        -50,
      ],
    ];

    return $events;
  }

  /**
   * Fires on product restocked.
   *
   * @param Drupal\cecc_stock\Event\RestockEvent $event
   *   The event.
   */
  public function onRestock(RestockEvent $event) {
    $productVariation = $event->productVariation;
    $product = $productVariation->getProduct();
    /** @var \Drupal\user\Entity\User[] $flagginUsers */
    $flaggingUsers = $this->flag->getFlaggingUsers($product);

    if ($flaggingUsers) {
      $queue = $this->queueFactory->get('cecc_restock_notification');

      foreach ($flaggingUsers as $user) {
        $item = [
          'user' => $user->id(),
          'product' => $product->id(),
        ];

        $queue->createItem($item);
      }
    }
  }

}
