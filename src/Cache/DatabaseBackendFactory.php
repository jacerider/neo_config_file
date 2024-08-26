<?php

namespace Drupal\neo_config_file\Cache;

use Drupal\Core\Cache\DatabaseBackendFactory as CoreDatabaseBackendFactory;

/**
 * Defines the cache database backend factory.
 */
class DatabaseBackendFactory extends CoreDatabaseBackendFactory {

  /**
   * {@inheritDoc}
   */
  public function get($bin) {
    $max_rows = $this->getMaxRowsForBin($bin);
    return new CacheDatabaseBackend($this->connection, $this->checksumProvider, $bin, $max_rows);
  }

}
