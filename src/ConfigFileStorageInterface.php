<?php

namespace Drupal\neo_config_file;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Config\Entity\ImportableEntityStorageInterface;
use Drupal\file\FileInterface;

/**
 * Provides an interface defining a config file entity storage.
 */
interface ConfigFileStorageInterface extends ConfigEntityStorageInterface, ImportableEntityStorageInterface {

  /**
   * Loads one entity.
   *
   * @param mixed $id
   *   The ID of the entity to load.
   *
   * @return \Drupal\neo_config_file\ConfigFileInterface|null
   *   An entity object. NULL if no matching entity is found.
   */
  public function load($id);

  /**
   * Load by URI.
   *
   * @param string $uri
   *   The uri.
   *
   * @return \Drupal\neo_config_file\ConfigFileInterface|null
   *   The config file entity.
   */
  public function loadByUri($uri);

  /**
   * Load from file entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   *
   * @return \Drupal\neo_config_file\ConfigFileInterface|null
   *   The config file entity.
   */
  public function loadByFile(FileInterface $file);

  /**
   * Load from file entity id.
   *
   * @param string $fid
   *   The file entity id.
   *
   * @return \Drupal\neo_config_file\ConfigFileInterface|null
   *   The config file entity.
   */
  public function loadByFileId($fid);

  /**
   * Create from file entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   *
   * @return \Drupal\neo_config_file\ConfigFileInterface|null
   *   The config file entity.
   */
  public function createFromFile(FileInterface $file);

}
