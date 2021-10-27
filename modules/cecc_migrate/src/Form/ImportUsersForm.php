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
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrates orders from IQ Legacy systems.
 */
class ImportUsersForm extends FormBase {
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
    return 'cecc_import_users_form';
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
      'title' => $this->t('Import Users'),
      'operations' => [
        [
          '\Drupal\cecc_migrate\Form\ImportUsersForm::processCsv',
          [
            $skipFirstLine,
            $sourceFile,
          ],
        ],
      ],
      'finished' => '\Drupal\cecc_migrate\Form\ImportUsersForm::finishedImporting',
      'init_message' => $this->t('Loading CSV'),
      'progress_message' => $this->t('Processing users. Time remaining: @estimate'),
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
      self::importUser($line);

      $context['message'] = t('Processing customer (@username). Total users: @users | Processed: @current', [
        '@username' => $line[0],
        '@users' => $context['sandbox']['max'],
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
  public static function importUser(array $data) {
    $entityTypeManager = \Drupal::entityTypeManager();

    $customerId = $data[0];
    $username = $data[2];
    $email = $data[11];

    if (empty($username) || $username == 'guest') {
      return;
    }

    /** @var \Drupal\user\Entity\User[] $users */
    $users = $entityTypeManager->getStorage('user')->loadByProperties([
      'mail' => $email,
    ]);

    if (empty($users)) {
      $user = User::create();
      /** @var Drupal\Core\Password\PasswordGeneratorInterface $passwordGenerator */
      $passwordGenerator = \Drupal::service('password_generator');
      $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();

      $user->set('field_customer_id_legacy', $customerId);
      $user->set('name', $email);
      $user->setEmail($email);
      $user->setUsername($email);
      $user->setPassword($passwordGenerator->generate(12));
      $user->set('init', $email);
      $user->set('langcode', $lang);
      $user->set("preferred_langcode", $lang);
      $user->set("preferred_admin_langcode", $lang);
      $user->set('status', 1);
      $user->enforceIsNew();

      try {
        $user->save();
        \Drupal::logger('cecc_migrate')->info('Created user @user', [
          '@user' => $user->getAccountName(),
        ]);
      }
      catch (EntityStorageException $e) {
        \Drupal::logger('cecc_migrate')->warning($e->getMessage());
        return NULL;
      }
    }
    else {
      $user = reset($users);
      \Drupal::logger('cecc_migrate')->info('Loaded user @user', [
        '@user' => $user->getAccountName(),
      ]);
    }

    return $user;
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
