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
    if (version_compare(\Drupal::VERSION, '11.0.0', '<')) {
      return new CacheDatabaseBackend($this->connection, $this->checksumProvider, $bin, $max_rows);
    }
    return new CacheDatabaseBackend($this->connection, $this->checksumProvider, $bin, $this->serializer, $this->time, $max_rows);
  }

}
