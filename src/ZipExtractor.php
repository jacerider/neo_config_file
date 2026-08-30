<?php

declare(strict_types=1);

namespace Drupal\neo_config_file;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\File\FileSystemInterface;
use Drupal\neo_config_file\Exception\ExtractionRefusedException;

/**
 * Unpacks a zip into a directory, replacing whatever was there.
 *
 * Drupal 12 removes the whole `Drupal\Core\Archiver` namespace with no
 * replacement, and the modules that unpack a config file's payload — an icon
 * library's IcoMoon package, a favicon package — both went through it. This is
 * the one implementation they share now. It lives here, in a module that never
 * unpacks anything itself, because it is the only package both consumers
 * already require directly and because the zip is a config file's own payload;
 * ADR 0001 records why every other home is worse.
 *
 * It only ever unpacks. It does not create an archive, add to one or list one,
 * because no consumer has ever asked it to and a public service id is
 * permanent.
 *
 * `final` with no interface is the shape the stack settled for a small service
 * of this kind — `neo`'s Linkit resolver, `neo_toolbar`'s access gate — and a
 * site that needs another implementation swaps it by service override.
 * Removing `final` later breaks nothing; adding it does.
 */
final class ZipExtractor {

  /**
   * Constructs a ZipExtractor object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service, which resolves the archive's stream URI to a
   *   path on disk and creates and removes the directories either side of the
   *   swap below.
   */
  public function __construct(
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Extracts an archive's entries into a destination directory.
   *
   * The destination is replaced, never emptied first. Everything is unpacked
   * into a temporary sibling directory, and only once the archive has fully
   * extracted is the old directory removed and the new one moved into place.
   * The sequence this replaces deleted the destination and then extracted into
   * it, so an archive that would not open cost a site its installed icon
   * library, or every favicon it had, for a log line.
   *
   * The archive is resolved to a real path because both callers pass a stream
   * URI and `ZipArchive::open()` reads through the filesystem. The destination
   * is deliberately not resolved: `ZipArchive::extractTo()` writes through
   * PHP's stream layer, which is what has always let a stream URI destination
   * work, and it may not exist yet — creating it is this method's job.
   *
   * The archive opening is the only guard. There is no extension check: the
   * removed manager matched a file name against a plugin's extensions, which
   * is why a file named `.zip` that was not a zip sailed straight past it, and
   * both consumers' upload elements already restrict to `.zip`.
   *
   * @param string $archive
   *   The archive's location, which may be a stream URI.
   * @param string $destination
   *   The directory to unpack into, which may be a stream URI and need not
   *   exist yet. Whatever it holds is replaced on success and untouched
   *   otherwise.
   *
   * @throws \Drupal\neo_config_file\Exception\ExtractionRefusedException
   *   When the archive does not resolve to a path on disk, or will not open.
   */
  public function extract(string $archive, string $destination): void {
    $path = $this->fileSystem->realpath($archive);
    if ($path === FALSE) {
      throw new ExtractionRefusedException(sprintf('Cannot extract %s: it does not resolve to a file on disk.', $archive));
    }

    $zip = new \ZipArchive();
    $status = $zip->open($path);
    if ($status !== TRUE) {
      throw new ExtractionRefusedException(sprintf('Cannot extract %s: it is not a valid archive.', $archive), (int) $status);
    }

    // Normalised once: a trailing separator would make the rename below ask
    // for a directory that is not there yet.
    $destination = rtrim($destination, '/\\');

    // A sibling of the destination rather than a temporary-directory path, so
    // the swap below is a rename within one filesystem — and within one stream
    // wrapper — rather than a copy across two. The suffix is random because
    // two requests may unpack into the same destination at once.
    $staging = $destination . '.neo-zip-' . Crypt::randomBytesBase64(8);
    $this->fileSystem->prepareDirectory($staging, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $zip->extractTo($staging);
    $zip->close();

    // Only now, with the whole archive on disk, does the destination change.
    // `rename()` rather than the file system service's move: that one is for
    // files, and it renames around a collision rather than replacing.
    $this->fileSystem->deleteRecursive($destination);
    rename($staging, $destination);
  }

}
