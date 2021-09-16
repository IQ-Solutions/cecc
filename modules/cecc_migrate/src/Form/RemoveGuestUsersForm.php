<?php

namespace Drupal\cecc_migrate\Form;

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
class RemoveGuestUsersForm extends FormBase {
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
    return 'cecc_remove_guest_users_form';
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
      'title' => $this->t('Remove Guest Users'),
      'operations' => [
        [
          '\Drupal\cecc_migrate\Form\RemoveGuestUsersForm::processCsv',
          [
            $skipFirstLine,
            $sourceFile,
          ],
        ],
      ],
      'finished' => '\Drupal\cecc_migrate\Form\RemoveGuestUsersForm::finishedImporting',
      'init_message' => $this->t('Loading CSV'),
      'progress_message' => $this->t('Processing users and orders. Time remaining: @estimate'),
      'error_message' => $this->t('The process has encountered an error.'),
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
      $context['sandbox']['progress'] = $skipFirstLine ? 1 : 0;
      $fileObj->seek(PHP_INT_MAX);
      $context['sandbox']['max'] = $fileObj->key();
      $fileObj->rewind();
    }

    $fileObj->seek($context['sandbox']['progress']);

    if ($fileObj->valid()) {
      $line = $fileObj->current();
      self::removeGuestsUsers($line);

      $context['message'] = t('Processing customer @order (@username). Total order items: @orderItems | Processed: @current', [
        '@username' => $line[0],
        '@type' => $line[2],
        '@orderItems' => $context['sandbox']['max'],
        '@current' => $context['sandbox']['progress'],
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
  public static function removeGuestsUsers(array $data) {
    $entityTypeManager = \Drupal::entityTypeManager();

    $customerId = $data[0];
    $username = $data[2];
    $email = $data[11];

    if (!empty($username) && $username != 'guest') {
      return;
    }

    $profileQuery = $entityTypeManager->getStorage('profile')->getQuery();
    $profileQuery->condition('field_customer_id_legacy', $customerId);
    $profileIds = $profileQuery->execute();

    $profiles = Profile::loadMultiple($profileIds);

    foreach ($profiles as $profile) {
      $profileOwner = $profile->getOwner();

      if (empty($profileOwner)) {
        continue;
      }

      if (is_a($profileOwner, 'Drupal\user\Entity\User')) {
        self::removeUserOrders($profileOwner, $profile);

        $profile->delete();
        user_cancel([], $profileOwner->id(), 'user_cancel_delete');
      }
    }

    $userQuery = $entityTypeManager->getStorage('user')->getQuery();
    $userQuery->condition('field_customer_id_legacy', $customerId);
    $userIds = $userQuery->execute();

    $users = User::loadMultiple($userIds);

    foreach ($users as $user) {
      self::removeUserOrders($user, $profile);

      user_cancel([], $user->id(), 'user_cancel_delete');
    }

    $users = $entityTypeManager->getStorage('user')->loadByProperties([
      'mail' => $email,
    ]);

    $users = User::loadMultiple($userIds);

    foreach ($users as $user) {
      self::removeUserOrders($user, $profile);

      user_cancel([], $user->id(), 'user_cancel_delete');
    }

  }

  /**
   * Removes orders for a user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The order user.
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The user profile.
   */
  public static function removeUserOrders(User $user, ProfileInterface $profile) {
    $entityTypeManager = \Drupal::entityTypeManager();

    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
    $orders = $entityTypeManager->getStorage('commerce_order')->loadByProperties([
      'uid' => $user->id(),
    ]);

    foreach ($orders as $order) {
      if ($order) {
        $orderProfile = $order->getBillingProfile();

        try {
          $order->delete();
        }
        catch (EntityStorageException $e) {
          \Drupal::logger('cecc_migrate')->error($e->getMessage());
        }

        if ($orderProfile && !$orderProfile->equalToProfile($profile)) {

          try {
            $orderProfile->delete();
          }
          catch (EntityStorageException $e) {
            \Drupal::logger('cecc_migrate')->error($e->getMessage());
          }
        }
      }
    }
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
