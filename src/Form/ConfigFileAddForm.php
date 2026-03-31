<?php

declare(strict_types=1);

namespace Drupal\neo_config_file\Form;

use Drupal\Component\Utility\Bytes;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Neo | Config File form.
 */
final class ConfigFileAddForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LogCommentForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'neo_config_file_add';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['id'] = [
      '#type' => 'machine_name',
      '#description' => $this->t('This is the ID that will be used to identify the config file.'),
      '#machine_name' => [
        'exists' => '\Drupal\neo_config_file\Entity\ConfigFile::load',
      ],
    ];

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File'),
      '#upload_location' => 'temporary://neo-file',
      '#upload_validators' => [
        'FileSizeLimit' => ['fileLimit' => Bytes::toNumber('20MB')],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entityTypeManager->getStorage('file')->load($form_state->getValue('file')[0]);
    $id = $form_state->getValue('id');

    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
    $fileSystem = \Drupal::service('file_system');

    $newFileUri = $file->getFileUri();
    $info = pathinfo($newFileUri);
    $newFileUri = $info['dirname'] . DIRECTORY_SEPARATOR . $id . '.' . $info['extension'];
    $newFileUri = str_replace('temporary://', 'public://', $newFileUri);
    if (!$fileSystem->copy($file->getFileUri(), $newFileUri, FileExists::Replace)) {
      \Drupal::messenger()->addError(t('Unable to overwrite original file with the replacement.'));
      return;
    }
    $file->setFileUri($newFileUri);
    $file->save();

    /** @var \Drupal\neo_config_file\ConfigFileStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('neo_config_file');
    /** @var \Drupal\neo_config_file\ConfigFileInterface $configFile */
    $configFile = $storage->createFromFile($file);
    $configFile->set('id', $id);
    $configFile->set('parent_form_id', $this->getFormId());
    $configFile->save();

    $this->messenger()->addStatus($this->t('Config file added successfully.'));
    $form_state->setRedirect('entity.neo_config_file.collection');
  }

}
