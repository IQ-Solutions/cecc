<?php

namespace Drupal\cecc_migrate\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\profile\Entity\Profile;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrates users from IQ Legacy systems.
 */
class MigrateUsersForm extends FormBase {
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
   * Undocumented function.
   *
   * @param Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param Drupal\Core\Password\PasswordGeneratorInterface $password_generator
   *   The password generator service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    PasswordGeneratorInterface $password_generator
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordGenerator = $password_generator;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('password_generator')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'cecc_migrate_users_form';
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
    $fileId = reset($form_state->getValue('csv_file'));
    $skipFirstLine = $form_state->getValue('skip_first_line');

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($fileId);
    $file->setFilename('user_migration.csv');
    $file->save();

    $filePath = $file->createFileUrl();

    $fileObj = new \SplFileObject(DRUPAL_ROOT . $filePath);
    $fileObj->setFlags($fileObj::READ_CSV);

    $operations = [];

    foreach ($fileObj as $index => $line) {
      if ($skipFirstLine == 1 && $index == 0) {
        continue;
      }

      $operations[] = [
        '\Drupal\cecc_migrate\Form\MigrateUsersForm::createUserProfile',
        [
          $line,
        ],
      ];
    }

    $batch = [
      'title' => $this->t('Import Users from CSV'),
      'operations' => $operations,
      'finished' => '\Drupal\cecc_migrate\Form\MigrateUsersForm::finishedImportingUsers',
      'init_message' => $this->t('Importing Users'),
      'progress_message' => $this->t('Processed @current out of @total. Estimated: @estimate'),
      'error_message' => $this->t('The import process has encountered an error.'),
    ];

    batch_set($batch);

  }

  public static function createUserProfile($data, array &$context) {
    $entityTypeManager = \Drupal::entityTypeManager();
    $user = reset($entityTypeManager->getStorage('user')->loadByProperties([
      'mail' => $data[11],
    ]));
    /** @var Drupal\Core\Password\PasswordGeneratorInterface $passwordGenerator */
    $passwordGenerator = \Drupal::service('password_generator');
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();

    if (empty($user)) {
      $user = User::create();

      $user->set('name', $data[11]);
      $user->uid = $data[0];
      $user->setEmail($data[11]);
      $user->setUsername($data[11]);
      $user->setPassword($passwordGenerator->generate(12));
      $user->set('init', $data[11]);
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
        $context['finished'] = TRUE;
        return;
      }
    }

    $profileIds = $entityTypeManager->getStorage('profile')->getQuery()
      ->condition('field_customer_id_legacy', $data[0])
      ->execute();

    if (empty($profileIds)) {
      self::createProfile($data, $lang, $user);
    }

    $context['finished'] = TRUE;
  }

  /**
   * Create a new customer profile.
   *
   * @param array $data
   *   The profile data.
   * @param string $lang
   *   The current language id.
   * @param \Drupal\user\Entity\User $user
   *   The user the profile is for.
   */
  public static function createProfile(array $data, $lang, User $user) {
    $profile = Profile::create([
      'type' => 'customer',
      'uid' => $user->id(),
      'status' => 1,
      'field_organization' => Xss::filter($data[5]),
      'field_first_name' => Xss::filter($data[3]),
      'field_last_name' => Xss::filter($data[4]),
      'address' => [
        "langcode" => $lang,
        "country_code" => Xss::filter($data[10]),
        "administrative_area" => Xss::filter($data[8]),
        "locality" => NULL,
        "dependent_locality" => NULL,
        "postal_code" => Xss::filter($data[9]),
        "sorting_code" => NULL,
        "address_line1" => Xss::filter($data[6]),
        "address_line2" => Xss::filter($data[7]),
      ],
    ]);

    $profile->set('field_customer_id_legacy', $data[0]);

    try {
      $profile->save();
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('cecc_migrate')->warning($e->getMessage());
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
  public static function finishedImportingUsers(bool $success, array $results, array $operations) {
    $status = Messenger::TYPE_STATUS;

    if ($success) {
      $message = t('Users imported.');

      \Drupal::logger('cecc_migrate')->info($message);
    }
    else {
      $message = t('Failed to import users.');

      $status = Messenger::TYPE_ERROR;
      \Drupal::logger('cecc_migrate')->error($message);
    }

    \Drupal::messenger()->addMessage($message, $status);

  }

}
