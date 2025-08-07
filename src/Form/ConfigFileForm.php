<?php

namespace Drupal\neo_config_file\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Config File form.
 *
 * @property \Drupal\neo_config_file\ConfigFileInterface $entity
 */
class ConfigFileForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\neo_config_file\ConfigFileInterface $entity */
    $entity = $this->entity;

    $form = parent::form($form, $form_state);

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File'),
      '#upload_location' => 'temporary://neo-file',
      '#default_value' => [$entity->getFile()->id()],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\neo_config_file\ConfigFileInterface $entity */
    $entity = $this->entity;

    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
    $fileSystem = \Drupal::service('file_system');
    $originalFile = $entity->getFile();
    $fid = $form_state->getValue('file')[0] ?? NULL;
    if ($fid) {
      /** @var \Drupal\file\FileInterface $replacementFile */
      $replacementFile = $this->entityTypeManager->getStorage('file')->load($form_state->getValue('file')[0]);
      $replacementFileUri = $replacementFile->getFileUri();
      $originalFileUri = $originalFile->getFileUri();
      if ($replacementFileUri !== $originalFileUri) {
        $destination = $fileSystem->dirname($originalFileUri);
        $fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);
        if (!$fileSystem->copy($replacementFileUri, $originalFileUri, FileExists::Replace)) {
          \Drupal::messenger()->addError(t('Unable to overwrite original file with the replacement.'));
          return;
        }

        // The file entity must be saved to force it to recalculate metadata
        // about the file (like size).
        $originalFile->save();

        // Delete image style derivatives for this file. If it's not an image,
        // this is harmless.
        image_path_flush($originalFileUri);
      }
    }

    // The replacement file is marked as temporary and will typically be
    // automatically deleted on cron after a certain period of time, but
    // lets just do it now to avoid any potential confusion of the file
    // remaining on the filesystem and in the managed files table.
    $replacementFile->delete();
    $entity->save();

    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus($this->t('Updated config file %label.', $message_args));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
  }

}
