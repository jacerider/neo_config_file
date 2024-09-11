<?php

declare(strict_types = 1);

namespace Drupal\neo_config_file\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\neo_config_file\ConfigFileInterface;

/**
 * Event that is fired when a user logs in.
 */
class ConfigFilePreSaveEvent extends Event {

  // This makes it easier for subscribers to reliably use our event name.
  const EVENT_NAME = 'neo_config_file_pre_save';

  /**
   * The config file.
   *
   * @var \Drupal\neo_config_file\ConfigFileInterface
   */
  public $configFile;

  /**
   * Constructs the object.
   *
   * @param \Drupal\neo_config_file\ConfigFileInterface $configFile
   *   The config file.
   */
  public function __construct(ConfigFileInterface $configFile) {
    $this->configFile = $configFile;
  }

  /**
   * Gets the config file.
   *
   * @return \Drupal\neo_config_file\ConfigFileInterface
   *   The config file.
   */
  public function getConfigFile(): ConfigFileInterface {
    return $this->configFile;
  }

}
