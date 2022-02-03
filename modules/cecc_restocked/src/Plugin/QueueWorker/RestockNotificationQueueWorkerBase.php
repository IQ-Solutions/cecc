<?php

namespace Drupal\cecc_restocked\Plugin\QueueWorker;

use Drupal\cecc_restocked\Mail\RestockMail;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Drupal config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * The restock notification service.
   *
   * @var \Drupal\cecc_restocked\Mail\RestockMail
   */
  private $restockMail;

  /**
   * Queueworker Construct.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Drupal logger service.
   * @param \Drupal\cecc_restocked\Mail\RestockMail $restock_mail
   *   Drupal mail manager service.
   * @param \Drupal\flag\FlagServiceInterface $flag
   *   The flag service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $loggerFactory,
    RestockMail $restock_mail,
    FlagServiceInterface $flag,
    ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $loggerFactory->get('cecc_restocked');
    $this->restockMail = $restock_mail;
    $this->flag = $flag;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {

    $instance = new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('cecc_restocked.restock_mail'),
      $container->get('flag'),
      $container->get('config.factory')
    );

    return $instance;
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
      $this->logger->warning('Attempted to send restock notification for product that does not exist: @id', [
        '@id',
        $item['product'],
      ]);
      return FALSE;
    }

    if (is_null($user)) {
      $this->logger->warning('Attempted to send restock notification to user that does not exist: @id', [
        '@id',
        $item['user'],
      ]);

      return FALSE;
    }

    $result = $this->restockMail->send($product, $user);

    if (!$result) {
      $this->logger->error('The message could not be sent');
      throw new RequeueException('The message could not be sent');
    }
    else {
      $this->logger->info('Restock notification sent to %user for %product.', [
        '%user' => $user->getAccountName(),
        '%product' => $product->getTitle(),
      ]);
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
