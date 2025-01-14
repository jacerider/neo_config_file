<?php

namespace Drupal\neo_config_file;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;

/**
 * Storage handler for field config.
 */
class ConfigFileStorage extends ConfigEntityStorage implements ConfigFileStorageInterface {

  /**
   * {@inheritDoc}
   */
  public function loadByUri($uri) {
    $entities = $this->loadByProperties([
      'uri' => $uri,
    ]);
    return !empty($entities) ? reset($entities) : NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function loadByFile(FileInterface $file) {
    return $this->loadByUri($file->getFileUri());
  }

  /**
   * {@inheritDoc}
   */
  public function loadByFileId($fid) {
    $file = File::load($fid);
    if ($file) {
      return $this->loadByFile($file);
    }
    return NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function createFromFile(FileInterface $file) {
    return $this->create([
      'id' => \Drupal::service('uuid')->generate(),
      'filename' => basename($file->getFileUri()),
      'uri' => $file->getFileUri(),
      'uid' => $file->getOwnerId(),
    ]);
  }

}
