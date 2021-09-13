<?php

namespace Drupal\cecc_migrate\Form;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\profile\Entity\Profile;
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

    $form['site_module'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source file from Module'),
      '#description' => $this->t('Use a source file stored in a custom module directory. Enter the module machine name'),
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
    $modulePath = $this->fileSystem->realpath($this->moduleHandler->getModule('ninds')->getPath());
    $sourceFile = $modulePath . '/source_files/List_Customers_Orders_07312020_07312021.csv';
    $siteModule = $form_state->getValue('site_module');

    if (empty($siteModule)) {
      $fileId = reset($form_state->getValue('csv_file'));
      $skipFirstLine = $form_state->getValue('skip_first_line');

      /** @var \Drupal\file\Entity\File $file */
      $file = $this->entityTypeManager->getStorage('file')->load($fileId);
      $file->save();

      $sourceFile = $this->fileSystem->realpath($file->getFileUri());
    }

    $fileObj = new \SplFileObject($sourceFile);
    $fileObj->setFlags($fileObj::READ_CSV);

    $operations = [];

    foreach ($fileObj as $index => $line) {
      if ($skipFirstLine == 1 && $index == 0) {
        continue;
      }

      $operations[] = [
        '\Drupal\cecc_migrate\Form\MigrateOrdersForm::migrateOrders',
        [
          $line,
        ],
      ];
    }

    $batch = [
      'title' => $this->t('Import Orders from CSV'),
      'operations' => $operations,
      'finished' => '\Drupal\cecc_migrate\Form\MigrateUsersForm::finishedImportingOrders',
      'init_message' => $this->t('Importing Orders'),
      'progress_message' => $this->t('Processed @current order items of @total. Estimated: @estimate'),
      'error_message' => $this->t('The import process has encountered an error.'),
    ];

    batch_set($batch);

  }

  /**
   * Process order and order items.
   *
   * @param array $data
   *   The csv line data.
   * @param array $context
   *   The batch context.
   */
  public static function migrateOrders(array $data, array &$context) {
    $entityTypeManager = \Drupal::entityTypeManager();

    $title = $data[27];
    $quantity = (int) $data[18];
    $sku = $data[26];

    $user = self::getUser($data);

    if (!$user) {
      $context['finished'] = TRUE;
      return;
    }

    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = self::getProfile($data, $user);

    if (empty($profile)) {
      $context['finished'] = TRUE;
      return;
    }

    $orderItem = OrderItem::create([
      'type' => 'cecc_publication',
    ]);

    $orderItem->setQuantity($quantity);

    $productVariationIds = $entityTypeManager->getStorage('commerce_product_variation')->loadByProperties([
      'sku' => $sku,
    ]);

    if (!empty($productVariationIds)) {
      /** @var  \Drupal\commerce_product\Entity\ProductVariationInterface $productVariation */
      $productVariation = reset($productVariationIds);

      $orderItem->set('purchased_entity', $productVariation->id());

      $product = $productVariation->getProduct();

      if (!empty($product)) {
        $title = $product->getTitle();
      }
    }

    $orderItem->setTitle($title);

    $orderItem->save();
    $orderIds = $entityTypeManager->getStorage('commerce_order')->loadByProperties([
      'order_number' => $data[1],
    ]);

    if (empty($orderIds)) {
      $order = Order::create([
        'type' => 'cecc_publication',
        'state' => 'Completed',
        'mail' => $user->getEmail(),
        'uid' => $user->id(),
        'order_number' => $data[1],
        'billing_profile' => $profile,
        'store_id' => 1,
        'placed' => strtotime($data[3]),
      ]);
    }
    else {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = reset($orderIds);
    }

    if (!$order->hasItem($orderItem)) {
      $order = $order->addItem($orderItem);
    }

    $order->save();

    $context['finished'] = TRUE;
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

    $userIds = $entityTypeManager->getStorage('user')->loadByProperties([
      'mail' => $data[17],
    ]);

    if (empty($userIds)) {
      $user = User::create();
      /** @var Drupal\Core\Password\PasswordGeneratorInterface $passwordGenerator */
      $passwordGenerator = \Drupal::service('password_generator');
      $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();

      $user->set('field_customer_id_legacy', $data[4]);
      $user->set('name', $data[17]);
      $user->setEmail($data[17]);
      $user->setUsername($data[17]);
      $user->setPassword($passwordGenerator->generate(12));
      $user->set('init', $data[17]);
      $user->set('langcode', $lang);
      $user->set("preferred_langcode", $lang);
      $user->set("preferred_admin_langcode", $lang);
      $user->set('status', 1);
      $user->enforceIsNew();

      try {
        $user->save();
      }
      catch (EntityStorageException $e) {
        \Drupal::logger('cecc_migrate')->warning($e->getMessage());
        return NULL;
      }
    }
    else {
      $user = reset($userIds);
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
    $profileIds = $entityTypeManager->getStorage('profile')->getQuery()
      ->condition('field_customer_id_legacy', $data[4])
      ->execute();

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
  public static function finishedImportingOrders(bool $success, array $results, array $operations) {
    $status = Messenger::TYPE_STATUS;

    if ($success) {
      $message = t('Orders imported.');

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
