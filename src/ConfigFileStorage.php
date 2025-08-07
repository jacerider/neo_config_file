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
   * Static array to keep track of files being saved.
   *
   * @var array
   */
  protected static array $saving = [];

  /**
   * Set the config file as being saved.
   *
   * @param \Drupal\neo_config_file\ConfigFileInterface $configFile
   *   The config file entity being saved.
   */
  public static function flagAsSaving(ConfigFileInterface $configFile) {
    self::$saving[$configFile->id()] = $configFile->get('uri');
  }

  /**
   * Check if a config file is being saved.
   *
   * @param string $uri
   *   The URI of the file to check if it is being saved.
   *
   * @return bool
   *   TRUE if the file is being saved, FALSE otherwise.
   */
  public static function isSaving($uri) {
    return in_array($uri, self::$saving, TRUE);
  }

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
    $id = basename($file->getFileUri());
    $id = strtolower($id);
    $id = preg_replace('/[^a-z0-9_]+/', '_', $id);
    $id = preg_replace('/_+/', '_', $id);
    if (strlen($id) > 238) {
      $first = substr($id, 0, 200);
      $second = substr(hash('sha256', $id), 0, 10);
      $id = $first . '__' . $second;
    }
    return $this->create([
      'id' => $id,
      'filename' => basename($file->getFileUri()),
      'uri' => $file->getFileUri(),
      'uid' => $file->getOwnerId(),
    ]);
  }

}
