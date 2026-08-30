<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_config_file\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_config_file\ZipExtractor;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves the two things a unit test of the extractor cannot.
 *
 * The first is registration: the extractor is a public service from the moment
 * it ships, so a consuming module fetches it by id rather than constructing
 * it, and a unit test that constructs the class asserts nothing about the
 * services file.
 *
 * The second is the destination being a stream URI. Both consumers hand the
 * extractor one — `public://neo-favicon` and an icon library's own URI — and
 * the reason that works is that `ZipArchive::extractTo()` writes through PHP's
 * stream layer rather than through a resolved path. Only a booted container
 * has a registered stream wrapper to prove it against.
 *
 * The boot list is enumerated rather than inherited because kernel tests do
 * not resolve info.yml dependencies. It is exactly three modules, and it boots
 * because the extractor's only dependency is core's file system service and
 * the wrapper it writes through is core's public one.
 */
#[Group('neo_config_file')]
final class ZipExtractorContainerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'file', 'neo_config_file'];

  /**
   * {@inheritdoc}
   *
   * The inherited set-up maps the public stream onto a virtual filesystem, on
   * which `realpath()` answers nothing, so `public://` resolves to FALSE and
   * `ZipArchive` has nowhere to write. A real site directory is what core's
   * own file tests use for the same reason.
   */
  protected function setUpFilesystem(): void {
    $files = $this->siteDirectory . '/files';
    mkdir($files, 0775, TRUE);
    mkdir($this->siteDirectory . '/config/sync', 0775, TRUE);
    $this->setSetting('file_public_path', $files);
    $this->setSetting('config_sync_directory', $this->siteDirectory . '/config/sync');
  }

  /**
   * The service is public, and a stream URI is a destination it can write.
   *
   * Covers: booted with exactly `['system', 'file', 'neo_config_file']`, it
   * resolves from the container as a public service and extracts into a
   * destination given as a stream URI.
   */
  public function testResolvesAsPublicServiceAndExtractsIntoStreamUri(): void {
    $extractor = $this->container->get('neo_config_file.zip_extractor');
    $this->assertInstanceOf(ZipExtractor::class, $extractor);

    $zip = new \ZipArchive();
    $zip->open($this->siteDirectory . '/files/library.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip->addFromString('style.css', '.icon {}');
    $zip->addFromString('fonts/icons.woff', 'woff-bytes');
    $zip->close();

    $extractor->extract('public://library.zip', 'public://neo-config-file-library');

    $this->assertStringEqualsFile('public://neo-config-file-library/style.css', '.icon {}');
    $this->assertStringEqualsFile('public://neo-config-file-library/fonts/icons.woff', 'woff-bytes');
  }

}
