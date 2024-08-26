<?php

declare(strict_types = 1);

namespace Drupal\neo_config_file\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\neo_config_file\ConfigFileEntityEventInterface;
use Drupal\neo_config_file\ConfigFileInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;

/**
 * Defines the config file entity type.
 *
 * @ConfigEntityType(
 *   id = "neo_config_file",
 *   label = @Translation("Config File"),
 *   label_collection = @Translation("Config Files"),
 *   label_singular = @Translation("config file"),
 *   label_plural = @Translation("config files"),
 *   label_count = @PluralTranslation(
 *     singular = "@count config file",
 *     plural = "@count config files",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\neo_config_file\ConfigFileListBuilder",
 *     "storage" = "Drupal\neo_config_file\ConfigFileStorage",
 *     "form" = {
 *       "add" = "Drupal\neo_config_file\Form\ConfigFileForm",
 *       "edit" = "Drupal\neo_config_file\Form\ConfigFileForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "neo_config_file",
 *   admin_permission = "administer neo_config_file",
 *   links = {
 *     "collection" = "/admin/structure/config-file",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "filename",
 *     "uid" = "uid",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "uid",
 *     "filename",
 *     "uri",
 *     "parent_type",
 *     "parent_id",
 *     "parent_field",
 *     "changed",
 *     "dependents",
 *   }
 * )
 */
class ConfigFile extends ConfigEntityBase implements ConfigFileInterface {

  /**
   * The config file ID.
   *
   * @var string
   */
  protected $id;
  /**
   * The config file name.
   *
   * @var string
   */
  protected $filename;

  /**
   * The config file uri.
   *
   * @var string
   */
  protected $uri;

  /**
   * The user id.
   *
   * @var string
   */
  protected $uid;

  /**
   * The parent entity.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityInterface
   */
  protected $parentEntity;

  /**
   * Dependents.
   *
   * @var array
   */
  protected $dependents = [];

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // If we do not have a file entity, we want to take our config file and
    // convert it to an actual file entity.
    if (!$this->getFile()) {
      $this->toFile();
    }
    // If we are not syncing, we want to make sure we have a copy of this file
    // in cache.
    else {
      $this->toCache();
    }

    // Allow parent entity to interact with this update.
    if (($parent_entity = $this->getParentEntity()) && $parent_entity instanceof ConfigFileEntityEventInterface) {
      $parent_entity->neoConfigFileUpdate($this);
    }

    // Keep track of the file's changed time so that this entity will be resaved
    // whenever the file is changed.
    $this->set('changed', $this->getFile()->getChangedTime());
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    /** @var \Drupal\neo_config_file\ConfigFileInterface[] $entities */
    parent::preDelete($storage, $entities);

    foreach ($entities as $entity) {
      if ($entity->isSyncing() || !$entity->hasConfig()) {
        /** @var \Drupal\Core\File\FileSystemInterface $file_system */
        $file_system = \Drupal::service('file_system');
        $uri = $entity->getConfigUri();
        // Remove config file if it exists.
        if (file_exists($uri)) {
          $file_system->delete($uri);
        }
        $entity->removeCache();
      }

      // Allow parent entity to interact with this delete.
      if (($parent_entity = $entity->getParentEntity()) && $parent_entity instanceof ConfigFileEntityEventInterface) {
        $parent_entity->neoConfigFileDelete($entity);
      }

      // The file entity is always deleted when the neo config file entity
      // is deleted. If not done during a sync, the actual file will still
      // exist in the actual config directory and will be recreated if a
      // config import is ran.
      if (empty($entity->neoConfigFileDelete) && ($file = $entity->getFile())) {
        $file->neoConfigFileDelete = TRUE;
        $file->delete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    $files = $this->entityTypeManager()->getStorage('file')->loadByProperties([
      'uri' => $this->uri,
    ]);
    if (!empty($files)) {
      return reset($files);
    }
    return FALSE;
  }

  /**
   * Validate file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   */
  protected function validateFile(FileInterface $file) {
    $uri = $file->getFileUri();
    if (!file_exists($uri)) {
      // We have the file entity, but we don't have the actual file in our
      // file system. Try to re-create it from cache.
      // This can happen when a database is pulled down but the files are not
      // pulled down.
      if ($data = $this->getCache()) {
        /** @var \Drupal\Core\File\FileSystemInterface $file_system */
        $file_system = \Drupal::service('file_system');
        $file_system->prepareDirectory(dirname($uri), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $file_system->saveData($data, $uri);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigUri() {
    return str_replace(ConfigFileInterface::PUBLIC_URI, ConfigFileInterface::CONFIG_URI, $this->uri);
  }

  /**
   * {@inheritdoc}
   */
  public function hasConfig() {
    return file_exists(Settings::get('config_sync_directory') . '/neo_config_file.neo_config_file.' . $this->id . '.yml');
  }

  /**
   * {@inheritdoc}
   */
  public function toFile() {
    $file = $this->getFile();
    $destination = $this->uri;
    if ($file) {
      // Make sure the actual file exists.
      $this->validateFile($file);
      return FALSE;
    }
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->prepareDirectory(dirname($destination), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    try {
      $uri = $file_system->copy($this->getConfigUri(), $destination, FileExists::Replace);
      $file = File::create([
        'filename' => $this->filename,
        'uri' => $uri,
        'uid' => $this->uid,
        'status' => 1,
      ]);
      $file->save();
      return $file;
    }
    catch (FileException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function toConfig() {
    $file = $this->getFile();
    $destination = $this->getConfigUri();
    if (!$file) {
      return FALSE;
    }
    // Make sure the actual file exists.
    $this->validateFile($file);
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->prepareDirectory(dirname($destination), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    try {
      $uri = $file_system->copy($file->getFileUri(), $destination, FileExists::Replace);
      // We can remove from cache as we now have it in config.
      $this->removeCache();
      // When we move a file to config it should be permanent.
      if ($file = $this->getFile()) {
        $file->setPermanent();
        $file->save();
      }
      return $uri;
    }
    catch (FileException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function toCache() {
    $file = $this->getFile();
    if (!$file) {
      return FALSE;
    }
    $data = strtr(base64_encode(addslashes(gzcompress(serialize(file_get_contents($file->getFileUri())), 9))), '+/=', '-_,');
    /** @var \Drupal\Core\Cache\CacheBackendInterface $cache */
    $cache = \Drupal::service('cache.neo_config_file');
    $cache->set($this->id(), $data);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCache() {
    /** @var \Drupal\Core\Cache\CacheBackendInterface $cache */
    $cache = \Drupal::service('cache.neo_config_file');
    $data = $cache->get($this->id());
    if ($data) {
      return unserialize(gzuncompress(stripslashes(base64_decode(strtr($data->data, '-_,', '+/=')))));
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function removeCache() {
    $file = $this->getFile();
    if (!$file) {
      return FALSE;
    }
    /** @var \Drupal\Core\Cache\CacheBackendInterface $cache */
    $cache = \Drupal::service('cache.neo_config_file');
    $cache->delete($this->id());
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentEntity(ConfigEntityInterface $entity) {
    $this->parentEntity = $entity;
    $this->set('parent_type', $entity->getEntityTypeId());
    $this->set('parent_id', $entity->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntity() {
    if (!isset($this->parentEntity)) {
      $this->parentEntity = NULL;
      $parent_type = $this->getParentEntityType();
      $parent_id = $this->getParentEntityId();
      if ($parent_type && $parent_id) {
        $this->parentEntity = \Drupal::entityTypeManager()->getStorage($parent_type)->load($parent_id);
        // Return current translation of parent entity, if it exists.
        if ($this->parentEntity != NULL && ($this->parentEntity instanceof TranslatableInterface) && $this->parentEntity->hasTranslation($this->language()->getId())) {
          $this->parentEntity = $this->parentEntity->getTranslation($this->language()->getId());
        }
      }
    }
    return $this->parentEntity;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntityType() {
    return $this->get('parent_type');
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntityId() {
    return $this->get('parent_id');
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntityField() {
    return $this->get('parent_field');
  }

  /**
   * {@inheritdoc}
   */
  public function addDependent($type, $name) {
    if (empty($this->dependents[$type])) {
      $this->dependents[$type] = [$name];
      if (count($this->dependents) > 1) {
        // Ensure a consistent order of type keys.
        ksort($this->dependents);
      }
    }
    elseif (!in_array($name, $this->dependents[$type])) {
      $this->dependents[$type][] = $name;
      // Ensure a consistent order of dependency names.
      sort($this->dependents[$type], SORT_FLAG_CASE);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    foreach ($this->dependents as $type => $dependents) {
      foreach ($dependents as $name) {
        $this->addDependency($type, $name);
      }
    }
    return $this;
  }

}
