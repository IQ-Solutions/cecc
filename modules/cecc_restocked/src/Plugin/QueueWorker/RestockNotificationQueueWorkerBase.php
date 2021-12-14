<?php

namespace Drupal\cecc_restocked\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes items for updating stocking value.
 */
class RestockNotificationQueueWorkerBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * Drupal logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Data storage query.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flag;

  /**
   * Queueworker Construct.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Drupal logger service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   Drupal mail manager service.
   * @param \Drupal\flag\FlagServiceInterface $flag
   *   The flag service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $loggerFactory,
    MailManagerInterface $mailManager,
    FlagServiceInterface $flag) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $loggerFactory->get('cecc_restocked');
    $this->mailManager = $mailManager;
    $this->flag = $flag;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('plugin.manager.mail'),
      $container->get('flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    /**
     * @var \Drupal\commerce_product\Entity\ProductInterface $product
     */
    $product = $this->entityTypeManager->getStorage('commerce_product')
      ->load($item['product']);
    /** @var \Drupal\user\Entity\User $user */
    $user = $this->entityTypeManager->getStorage('user')
      ->load($item['user']);

    if (is_null($product)) {
      $this->logger->warning('Product does not exist: @id', [
        '@id',
        $item['product'],
      ]);
      return FALSE;
    }

    if (is_null($user)) {
      $this->logger->warning('User does not exist: @id', [
        '@id',
        $item['user'],
      ]);
      return FALSE;
    }

    $module = 'cecc_restocked';
    $key = 'restock_notification';
    $to = $user->getEmail();
    $params = [
      'message' => $this->t('@product has been restocked.', [
        '@product' => $product->getTitle(),
      ]),
      'product_title' => $product->getTitle(),
    ];

    $langcode = $user->getPreferredLangcode();
    $send = TRUE;

    $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

    if ($result['result'] !== TRUE) {
      $this->logger->error('The message could not be sent');
      throw new RequeueException('The message could not be sent');
    }
    else {
      $flag = $this->flag->getFlagById('cecc_request_restock');
      try {
        $this->flag->unflag($flag, $product, $user);
      }
      catch (\LogicException $e) {
        $this->logger->error($e->getMessage());
      }
    }
  }

}
