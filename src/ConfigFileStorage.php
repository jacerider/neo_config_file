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
    $filename = basename($file->getFileUri());
    $id = $filename;
    $id = strtolower($id);
    $id = preg_replace('/[^a-z0-9_]+/', '_', $id);
    $id = preg_replace('/_+/', '_', $id);
    if (strlen($id) > 238) {
      $first = substr($id, 0, 200);
      $second = substr(hash('sha256', $id), 0, 10);
      $id = $first . '__' . $second;
    }
    $config_file = $this->create([
      'id' => $id,
      'filename' => $filename,
      'uri' => $file->getFileUri(),
      'uid' => $file->getOwnerId(),
    ]);
    return $config_file;
  }

}
