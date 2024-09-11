<?php

namespace Drupal\neo_config_file;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a config file entity type.
 */
interface ConfigFileInterface extends ConfigEntityInterface {

  /**
   * The public URI where the files will be stored.
   */
  const PUBLIC_URI = 'public://neo-file';

  /**
   * The config URI where the files will be stored.
   */
  const CONFIG_URI = 'config://files';

  /**
   * Get file entity associated with this entity.
   *
   * @return \Drupal\file\FileInterface|false
   *   The file entity.
   */
  public function getFile();

  /**
   * Get the config uri to the file.
   *
   * @return string
   *   The config uri.
   */
  public function getConfigUri();

  /**
   * Check if config exists locally.
   *
   * @return bool
   *   Returns TRUE if config exists locally.
   */
  public function hasConfig();

  /**
   * Move a config to file.
   *
   * @return \Drupal\file\FileInterface|false
   *   The file entity.
   */
  public function toFile();

  /**
   * Move a file to config.
   *
   * @return string|false
   *   The URI to the config file.
   */
  public function toConfig();

  /**
   * Move a file to cache.
   *
   * @return bool
   *   Returns TRUE if file was cached.
   */
  public function toCache();

  /**
   * Get the raw file from cache.
   *
   * @return string|null
   *   Returns the raw file data.
   */
  public function getCache();

  /**
   * Remove a file from config.
   *
   * @return bool
   *   Returns TRUE if file was removed.
   */
  public function removeCache();

  /**
   * Get parent form id.
   *
   * @return string
   *   The parent form id.
   */
  public function getParentFormId();

  /**
   * Sets the parent entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The parent entity.
   *
   * @return $this
   */
  public function setParentEntity(ConfigEntityInterface $entity);

  /**
   * Get the parent entity.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The parent entity.
   */
  public function getParentEntity();

  /**
   * Get the parent entity type.
   *
   * @return string
   *   The parent entity type id.
   */
  public function getParentEntityType();

  /**
   * Get the parent entity id.
   *
   * @return string
   *   The parent entity id.
   */
  public function getParentEntityId();

  /**
   * Get the parent entity field name.
   *
   * @return string
   *   The parent entity field name.
   */
  public function getParentEntityField();

  /**
   * Adds a custom dependency.
   *
   * @param string $type
   *   Type of dependency being added: 'module', 'theme', 'config', 'content'.
   * @param string $name
   *   If $type is 'module' or 'theme', the name of the module or theme. If
   *   $type is 'config' or 'content', the result of
   *   EntityInterface::getConfigDependencyName().
   *
   * @see \Drupal\Core\Entity\EntityInterface::getConfigDependencyName()
   *
   * @return $this
   */
  public function addDependent($type, $name);

}
