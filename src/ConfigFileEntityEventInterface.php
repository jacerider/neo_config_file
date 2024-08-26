<?php

namespace Drupal\neo_config_file;

/**
 * Provides an interface defining a config file entity type.
 */
interface ConfigFileEntityEventInterface {

  /**
   * Called when a config file entity is updated.
   *
   * @param ConfigFileInterface $config_file
   *   The config file entity.
   */
  public function neoConfigFileUpdate(ConfigFileInterface $config_file);

  /**
   * Called when a config file entity is deleted.
   *
   * @param ConfigFileInterface $config_file
   *   The config file entity.
   */
  public function neoConfigFileDelete(ConfigFileInterface $config_file);

}
