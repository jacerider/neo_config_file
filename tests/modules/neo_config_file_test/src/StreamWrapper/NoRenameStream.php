<?php

declare(strict_types=1);

namespace Drupal\neo_config_file_test\StreamWrapper;

use Drupal\Core\StreamWrapper\PublicStream;

/**
 * The public files, on a mount that cannot rename anything.
 *
 * Stands in for Pantheon's file system, where rename() of a directory fails.
 */
final class NoRenameStream extends PublicStream {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'Public files that cannot be renamed';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return 'Public files on a mount that cannot rename.';
  }

  /**
   * {@inheritdoc}
   */
  public function rename($from_uri, $to_uri) {
    return FALSE;
  }

}
