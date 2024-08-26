<?php

namespace Drupal\neo_config_file\Cache;

use Drupal\Core\Cache\DatabaseBackend;

/**
 * Provides a cache database backend.
 */
class CacheDatabaseBackend extends DatabaseBackend {

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    // This method is called during a normal Drupal cache clear. We absolutely
    // do not want to flush the caches so we do nothing.
  }

  /**
   * This method will call the original deleteAll() method.
   *
   * Calling this method will permanently delete the caches for this cache bin.
   *
   * @throws \Exception
   */
  public function purgeAll() {
    parent::deleteAll();
  }

}
