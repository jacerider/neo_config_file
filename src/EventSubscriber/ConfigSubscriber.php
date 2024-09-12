<?php

namespace Drupal\neo_config_file\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\neo_config_file\Cache\CacheDatabaseBackend;
use Drupal\neo_config_file\ConfigFileInterface;
use Drupal\neo_config_file\StreamWrapper\ConfigFileStream;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Deletes the container if default language has changed.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a ConfigSubscriber object.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly CacheDatabaseBackend $cache,
    protected readonly ConfigFileStream $streamWrapper,
    protected readonly FileSystemInterface $fileSystem,
  ) {
  }

  /**
   * On config save.
   *
   * @param \Drupal\Core\Config\ConfigImporterEvent $event
   *   The configuration event.
   */
  public function onImport(ConfigImporterEvent $event) {
    $this->cache->purgeAll();
  }

  /**
   * On config storage transform import.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The configuration event.
   */
  public function onStorageTransformImport(StorageTransformEvent $event) {
    $prefix = 'neo_config_file.neo_config_file.';
    $config_names = $event->getStorage()->listAll($prefix);
    if (!empty($config_names)) {
      // Loop through every neo config file about to be exported and move the
      // file associated with it to config.
      /** @var \Drupal\neo_config_file\ConfigFileStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('neo_config_file');
      foreach ($config_names as $config_name) {
        $config_file = $storage->load(substr($config_name, strlen($prefix)));
        // Always make sure we have a copy of the file in the files directory.
        if ($file = $config_file->getFile()) {
          $config_file->validateFile($file);
        }
      }
    }
  }

  /**
   * On config storage transform export.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The configuration event.
   */
  public function onStorageTransformExport(StorageTransformEvent $event) {
    $config_directory = $this->streamWrapper->getDirectoryPath();
    // We make sure the config destination is writable as this method is
    // called even when exporting to file via the Drupal UI.
    if (!is_writable($config_directory)) {
      return;
    }

    // Delete current config files.
    if (is_dir(ConfigFileInterface::CONFIG_URI)) {
      $this->fileSystem->deleteRecursive(ConfigFileInterface::CONFIG_URI);
    }

    $prefix = 'neo_config_file.neo_config_file.';
    $config_names = $event->getStorage()->listAll($prefix);
    if (!empty($config_names)) {
      // Loop through every neo config file about to be exported and move the
      // file associated with it to config.
      /** @var \Drupal\neo_config_file\ConfigFileStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('neo_config_file');
      foreach ($config_names as $config_name) {
        $config_file = $storage->load(substr($config_name, strlen($prefix)));
        // Always make sure we have a copy of the file in the config directory.
        $config_file->toConfig();
      }
    }
    $this->cache->purgeAll();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::IMPORT][] = ['onImport', 0];
    $events[ConfigEvents::STORAGE_TRANSFORM_IMPORT][] = [
      'onStorageTransformImport', 0,
    ];
    $events[ConfigEvents::STORAGE_TRANSFORM_EXPORT][] = [
      'onStorageTransformExport', 0,
    ];
    return $events;
  }

}
