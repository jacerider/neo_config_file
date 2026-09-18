<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_config_file\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Extraction onto a mount that cannot rename a directory.
 *
 * The extractor unpacks beside the destination and renames the result into
 * place. Pantheon's file system cannot rename a directory, and the extractor
 * used to delete the destination and ignore the failed rename, leaving an
 * unread staging directory and no destination at all. The test module's
 * `norename://` wrapper is the public file system with rename() failing.
 */
#[Group('neo_config_file')]
final class ZipExtractorRenameFallbackTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'file', 'neo_config_file', 'neo_config_file_test'];

  /**
   * {@inheritdoc}
   *
   * A real site directory, as in ZipExtractorContainerTest: ZipArchive needs
   * a path realpath() can answer.
   */
  protected function setUpFilesystem(): void {
    $files = $this->siteDirectory . '/files';
    mkdir($files, 0775, TRUE);
    mkdir($this->siteDirectory . '/config/sync', 0775, TRUE);
    $this->setSetting('file_public_path', $files);
    $this->setSetting('config_sync_directory', $this->siteDirectory . '/config/sync');
  }

  /**
   * The package still lands in place when the rename fails.
   */
  public function testCopiesIntoPlaceWhenTheMountCannotRename(): void {
    $this->zip();

    $this->container->get('neo_config_file.zip_extractor')->extract('public://library.zip', 'norename://library');

    $this->assertStringEqualsFile('norename://library/style.css', '.icon {}');
    $this->assertStringEqualsFile('norename://library/fonts/icons.woff', 'woff-bytes');
    $this->assertSame([], $this->staging());
  }

  /**
   * What the destination held before is replaced, not merged.
   */
  public function testReplacesTheDestinationWhenTheMountCannotRename(): void {
    $this->zip();
    mkdir($this->siteDirectory . '/files/library', 0775, TRUE);
    file_put_contents($this->siteDirectory . '/files/library/old.css', 'old');

    $this->container->get('neo_config_file.zip_extractor')->extract('public://library.zip', 'norename://library');

    $this->assertFileDoesNotExist('norename://library/old.css');
    $this->assertStringEqualsFile('norename://library/style.css', '.icon {}');
    $this->assertSame([], $this->staging());
  }

  /**
   * Writes the package the tests extract.
   */
  private function zip(): void {
    $zip = new \ZipArchive();
    $zip->open($this->siteDirectory . '/files/library.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip->addFromString('style.css', '.icon {}');
    $zip->addFromString('fonts/icons.woff', 'woff-bytes');
    $zip->close();
  }

  /**
   * Staging directories left beside the destination.
   *
   * @return list<string>
   */
  private function staging(): array {
    return array_values(array_filter(scandir($this->siteDirectory . '/files'), static fn ($name) => str_contains($name, '.neo-zip-')));
  }

}
