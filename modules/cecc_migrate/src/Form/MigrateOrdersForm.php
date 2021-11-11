<?php

namespace Drupal\cecc_migrate\Form;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrates orders from IQ Legacy systems.
 */
class MigrateOrdersForm extends FormBase {
  /**
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Password Generator service.
   *
   * @var \Drupal\Core\Password\PasswordGeneratorInterface
   */
  protected $passwordGenerator;

  /**
   * Password Generator service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Password Generator service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Undocumented function.
   *
   * @param Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param Drupal\Core\Password\PasswordGeneratorInterface $password_generator
   *   The password generator service.
   * @param Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param Drupal\Core\File\FileSystem $file_system
   *   The module handler service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    PasswordGeneratorInterface $password_generator,
    ModuleHandlerInterface $module_handler,
    FileSystem $file_system
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordGenerator = $password_generator;
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('password_generator'),
      $container->get('module_handler'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'cecc_migrate_orders_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#upload_location' => 'public://migrations/',
      '#upload_validators' => [
        'file_validate_extensions' => [
          'csv',
        ],
      ],
    ];

    $form['skip_first_line'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip the first line'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $skipFirstLine = $form_state->getValue('skip_first_line');
    $fileId = reset($form_state->getValue('csv_file'));

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($fileId);
    $file->save();

    $sourceFile = $this->fileSystem->realpath($file->getFileUri());

    $batch = [
      'title' => $this->t('Import Orders from CSV'),
      'operations' => [
        [
          '\Drupal\cecc_migrate\Form\MigrateOrdersForm::processCsv',
          [
            $skipFirstLine,
            $sourceFile,
          ],
        ],
      ],
      'finished' => '\Drupal\cecc_migrate\Form\MigrateOrdersForm::finishedImporting',
      'init_message' => $this->t('Importing Orders'),
      'progress_message' => $this->t('Processing order items. Time remaining: @estimate'),
      'error_message' => $this->t('The import process has encountered an error.'),
    ];

    batch_set($batch);

  }

  /**
   * Processes the csv file.
   *
   * @param int $skipFirstLine
   *   Skips the first line.
   * @param string $file
   *   The file path.
   * @param array $context
   *   The batch context.
   */
  public static function processCsv($skipFirstLine, $file, array &$context) {

    $fileObj = new \SplFileObject($file);
    $fileObj->setFlags($fileObj::READ_CSV);

    if (empty($context['sandbox'])) {
      $context['results']['processed'] = 0;
      $context['sandbox']['progress'] = $skipFirstLine ? 1 : 0;
      $fileObj->seek(PHP_INT_MAX);
      $context['sandbox']['max'] = $fileObj->key();
      $fileObj->rewind();
    }

    $fileObj->seek($context['sandbox']['progress']);

    if ($fileObj->valid()) {
      $line = $fileObj->current();
      if (!empty($line[1])) {
        $status = self::migrateOrders($line);

        if ($status) {
          $context['results']['processed']++;
        }
      }

      $context['message'] = t('Processing order item for order @order. @current/@orderItems | Updated: @updated', [
        '@order' => $line[1],
        '@orderItems' => $context['sandbox']['max'],
        '@current' => $context['sandbox']['progress'],
        '@updated' => $context['results']['processed'],
      ]);

      $context['sandbox']['progress']++;
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
    else {
      $context['finished'] = !$fileObj->valid();
    }
  }

  /**
   * Process order and order items.
   *
   * @param array $data
   *   The csv line data.
   */
  public static function migrateOrders(array $data) {

    $title = $data[27];
    $quantity = (int) $data[18];
    $sku = 'NINDS-' . $data[26];

    $user = self::getUser($data);

    if (!$user) {
      return FALSE;
    }

    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = self::getProfile($data, $user);

    if (empty($profile)) {
      return FALSE;
    }

    $order = self::getOrder($data, $user, $profile);
    $orderItem = self::getOrderItem($quantity, $sku, $title, $order);

    if (!$order->hasItem($orderItem)) {
      $order = $order->addItem($orderItem);
    }

    $order->save();

    return TRUE;
  }

  /**
   * Get order items if it exist.
   *
   * @param int $quantity
   *   The item quantity.
   * @param string $sku
   *   The item number.
   * @param string $title
   *   The item title.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order item.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface
   *   The order item.
   */
  public static function getOrderItem($quantity, $sku, $title, OrderInterface $order) {
    $entityTypeManager = \Drupal::entityTypeManager();
    $orderItem = NULL;
    $productVariation = NULL;

    /** @var  \Drupal\commerce_product\Entity\ProductVariationInterface[] $productVariations */
    $productVariations = $entityTypeManager->getStorage('commerce_product_variation')->loadByProperties([
      'sku' => $sku,
    ]);

    $orderItemProperties = [];

    if (!empty($productVariations)) {
      $productVariation = reset($productVariations);
      $orderItemProperties = [
        'purchased_entity' => $productVariation->id(),
        'order_id' => $order->id(),
      ];
    }

    /** @var \Drupal\commerce_order\Entity\OrderItem[] $orderItems */
    $orderItems = !empty($orderItemProperties) ?
      $entityTypeManager->getStorage('commerce_order_item')->loadByProperties($orderItemProperties)
      : NULL;

    if (empty($orderItems)) {
      $orderItem = OrderItem::create([
        'type' => 'cecc_publication',
      ]);

      $orderItem->setQuantity($quantity);

      if (!empty($productVariation)) {
        $orderItem->set('purchased_entity', $productVariation->id());

        $product = $productVariation->getProduct();

        if (!empty($product)) {
          $title = $product->getTitle();
        }
      }

      $orderItem->setTitle($title);

      $orderItem->save();
    }
    else {
      $orderItem = reset($orderItems);
    }

    return $orderItem;
  }

  /**
   * Get the order.
   *
   * @param array $data
   *   The csv data line.
   * @param \Drupal\user\Entity\User $user
   *   The drupal user.
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The Drupal user profile used.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   The order.
   */
  public static function getOrder(array $data, User $user, ProfileInterface $profile) {
    $entityTypeManager = \Drupal::entityTypeManager();

    $orderIds = $entityTypeManager->getStorage('commerce_order')->loadByProperties([
      'order_number' => $data[1],
    ]);

    $orderStates = [
      'InProcess' => 'fulfillment',
      'Closed' => 'completed',
      'OnHold' => 'fulfillment',
      'Submitted' => 'fulfillment',
      'Cancelled' => 'canceled',
    ];

    if (empty($orderIds)) {
      $order = Order::create([
        'type' => 'cecc_publication',
        'state' => $orderStates[$data[31]],
        'mail' => $user->getEmail(),
        'uid' => $user->id(),
        'order_number' => $data[1],
        'billing_profile' => $profile->createDuplicate(),
        'shipping_profile' => $profile->createDuplicate(),
        'store_id' => 1,
        'placed' => strtotime($data[3]),
      ]);
      $order->save();
    }
    else {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = reset($orderIds);

      foreach ($order->getItems() as $orderItem) {
        $order->removeItem($orderItem);
        $orderItem->delete();
      }
      $order->save();
    }

    return $order;
  }

  /**
   * Gets user. Creates user if it doesn't exist.
   *
   * @param array $data
   *   The user data array.
   *
   * @return \Drupal\user\Entity\User
   *   The user.
   */
  public static function getUser(array $data) {
    $entityTypeManager = \Drupal::entityTypeManager();

    if (empty($data[17])) {
      return FALSE;
    }

    /** @var \Drupal\user\Entity\User[] $users */
    $users = $entityTypeManager->getStorage('user')->loadByProperties([
      'mail' => $data[17],
    ]);
    $user = NULL;

    if (!empty($users)) {
      $user = reset($users);
    }

    return $user;
  }

  /**
   * Get or create a new customer profile.
   *
   * @param array $data
   *   The profile data.
   * @param \Drupal\user\Entity\User $user
   *   The user the profile is for.
   */
  public static function getProfile(array $data, User $user) {
    $entityTypeManager = \Drupal::entityTypeManager();
    $profileQuery = $entityTypeManager->getStorage('profile')->getQuery();
    $profileQuery->condition('field_customer_id_legacy', $data[4]);
    $profileQuery->condition('uid', $user->id());
    $profileIds = $profileQuery->execute();

    if (empty($profileIds)) {
      $profile = Profile::create([
        'type' => 'customer',
        'uid' => $user->id(),
        'status' => 1,
        'field_organization' => Xss::filter($data[8]),
        'field_first_name' => Xss::filter($data[5]),
        'field_last_name' => Xss::filter($data[6]),
        'field_phone_number' => str_replace('-', '', Xss::filter($data[15])),
        'address' => [
          "langcode" => $user->language()->getId(),
          "country_code" => Xss::filter($data[14]),
          "administrative_area" => Xss::filter($data[12]),
          "locality" => Xss::filter($data[11]),
          "dependent_locality" => NULL,
          "postal_code" => Xss::filter($data[13]),
          "sorting_code" => NULL,
          "address_line1" => Xss::filter($data[9]),
          "address_line2" => Xss::filter($data[10]),
        ],
      ]);

      $profile->set('field_customer_id_legacy', $data[4]);

      try {
        $profile->save();
      }
      catch (EntityStorageException $e) {
        \Drupal::logger('cecc_migrate')->warning($e->getMessage());
        return NULL;
      }
    }
    else {
      $profile = Profile::load(reset($profileIds));
    }

    return $profile;
  }

  /**
   * Batch process completion method.
   *
   * @param bool $success
   *   Boolean value that specifies batch success.
   * @param array $results
   *   Array that contains results from batch context.
   * @param array $operations
   *   Array that contains batch operations.
   */
  public static function finishedImporting(bool $success, array $results, array $operations) {
    $status = Messenger::TYPE_STATUS;

    if ($success) {
      $message = t('@count orders imported.', [
        'results' => $results['processed'],
      ]);

      \Drupal::logger('cecc_migrate')->info($message);
    }
    else {
      $message = t('Failed to import orders.');

      $status = Messenger::TYPE_ERROR;
      \Drupal::logger('cecc_migrate')->error($message);
    }

    \Drupal::messenger()->addMessage($message, $status);

  }

}
