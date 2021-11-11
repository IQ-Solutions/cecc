<?php

namespace Drupal\cecc_migrate\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrates orders from IQ Legacy systems.
 */
class MigrateFavoritesForm extends FormBase {
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
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

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
   * @param Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    PasswordGeneratorInterface $password_generator,
    ModuleHandlerInterface $module_handler,
    FileSystem $file_system,
    FlagServiceInterface $flag_service
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordGenerator = $password_generator;
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
    $this->flagService = $flag_service;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('password_generator'),
      $container->get('module_handler'),
      $container->get('file_system'),
      $container->get('flag')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'cecc_migrate_favorites_form';
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
      'title' => $this->t('Import Favorites from CSV'),
      'operations' => [
        [
          '\Drupal\cecc_migrate\Form\MigrateFavoritesForm::processCsv',
          [
            $skipFirstLine,
            $sourceFile,
          ],
        ],
      ],
      'finished' => '\Drupal\cecc_migrate\Form\MigrateFavoritesForm::finishedImporting',
      'init_message' => $this->t('Importing Favorites'),
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
        $status = self::migrateFavorites($line);

        if ($status) {
          $context['results']['processed']++;
        }
      }

      $context['message'] = t('Processing favorites. @current/@orderItems | Updated: @updated', [
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
  public static function migrateFavorites(array $data) {
    $entityTypeManager = \Drupal::entityTypeManager();
    /** @var \Drupal\flag\FlagServiceInterface $flagService */
    $flagService = \Drupal::service('flag');
    $flag = $flagService->getFlagById('favorites');

    $customerId = $data[0];
    $sku = 'NINDS-' . trim($data[3]);

    $users = $entityTypeManager->getStorage('user')->getQuery()
      ->condition('field_customer_id_legacy', $customerId)
      ->execute();

    if (empty($users)) {
      return FALSE;
    }

    $user = User::load(reset($users));

    if (empty($user)) {
      return FALSE;
    }

    /** @var  \Drupal\commerce_product\Entity\ProductVariationInterface[] $productVariations */
    $productVariations = $entityTypeManager->getStorage('commerce_product_variation')->loadByProperties([
      'sku' => $sku,
    ]);

    if (!empty($productVariations)) {
      $productVariation = reset($productVariations);
      $product = $productVariation->getProduct();

      if ($product) {
        $flagging = $flagService->getFlagging($flag, $product, $user);

        if (!$flagging) {
          $flagService->flag($flag, $product, $user);

          return TRUE;
        }
      }
    }

    return FALSE;

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
      $message = t('Favorites imported.');

      \Drupal::logger('cecc_migrate')->info($message);
    }
    else {
      $message = t('Failed to import.');

      $status = Messenger::TYPE_ERROR;
      \Drupal::logger('cecc_migrate')->error($message);
    }

    \Drupal::messenger()->addMessage($message, $status);

  }

}
