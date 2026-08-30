<?php

declare(strict_types=1);

namespace Drupal\neo_config_file\Exception;

/**
 * Thrown when the zip extractor refuses an archive.
 *
 * The single failure the extractor reports: the archive could not be resolved
 * to a real path, or it could not be opened as a zip. It exists as a type of
 * its own so a consuming module can catch extraction failures and only those —
 * `neo_icon` re-throws it in its own words to fail a form, `neo_favicon` logs
 * it and lets a settings save complete — which catching a broad runtime
 * exception inside an event subscriber could not do without swallowing
 * unrelated bugs along with it.
 */
class ExtractionRefusedException extends \RuntimeException {

  /**
   * Constructs an ExtractionRefusedException.
   *
   * @param string $message
   *   The refusal, naming the archive.
   * @param int|null $openStatus
   *   The status `ZipArchive::open()` answered with, or NULL where the archive
   *   never reached it because it did not resolve to a path on disk. It is
   *   carried rather than folded into the message so a consuming module can
   *   tell one kind of refusal from another — `\ZipArchive::ER_NOZIP` for a
   *   file named `.zip` whose bytes are not one — without parsing prose.
   * @param \Throwable|null $previous
   *   The previous exception, if any.
   */
  public function __construct(string $message, private readonly ?int $openStatus = NULL, ?\Throwable $previous = NULL) {
    parent::__construct($message, 0, $previous);
  }

  /**
   * The status the archive refused to open with.
   *
   * @return int|null
   *   One of `ZipArchive`'s `ER_*` constants, or NULL where the archive did
   *   not resolve to a path and so was never opened.
   */
  public function getOpenStatus(): ?int {
    return $this->openStatus;
  }

}
