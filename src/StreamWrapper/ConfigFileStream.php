<?php

namespace Drupal\neo_config_file\StreamWrapper;

use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\Url;

/**
 * Defines a Drupal public (config://) stream wrapper class.
 *
 * Provides support for storing publicly accessible files with the Drupal file
 * interface.
 */
class ConfigFileStream extends LocalStream {

  /**
   * {@inheritdoc}
   */
  public static function getType() {
    return StreamWrapperInterface::LOCAL_HIDDEN;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return t('Config files');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('Config files stored in the config sync directory.');
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectoryPath() {
    return Settings::get('config_sync_directory');
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    $path = str_replace('\\', '/', $this->getTarget());
    return Url::fromRoute('system.config', [], [
      'absolute' => TRUE,
      'query' => ['file' => $path],
    ])->toString();
  }

}
