<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_config_file\Unit;

use Drupal\Core\File\FileSystemInterface;
use Drupal\neo_config_file\Exception\ExtractionRefusedException;
use Drupal\neo_config_file\ZipExtractor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Drives the zip extractor against archives the test builds for itself.
 *
 * This is the first test this module has ever had, and the seam it covers is
 * the one Drupal 12's removal of `Drupal\Core\Archiver` left uncovered: two
 * consuming modules each unpacked a config file's payload through the removed
 * manager, and nothing anywhere asserted what happened when an archive would
 * not open.
 *
 * Every archive is written at run time with `ZipArchive` in write mode and
 * removed again in tear-down. A committed binary fixture would put the thing
 * under test into a file nobody can read in a diff, and the extension is
 * already a hard requirement of this module, so writing one costs nothing.
 *
 * The destination is a real temporary directory rather than a virtual one.
 * `ZipArchive::extractTo()` writes through PHP's own stream layer and the
 * extractor swaps directories with `rename()`, so a virtual filesystem would
 * be asserting the harness rather than the code. The single injected
 * dependency is stubbed, and its three methods are wired to the real
 * operations they name so the stub does not quietly become the subject.
 */
#[Group('neo_config_file')]
final class ZipExtractorTest extends UnitTestCase {

  /**
   * A real temporary directory holding every path a test touches.
   */
  private string $workspace;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->workspace = sys_get_temp_dir() . '/neo-config-file-zip-' . bin2hex(random_bytes(6));
    mkdir($this->workspace, 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->removeRecursive($this->workspace);
    parent::tearDown();
  }

  /**
   * Entries land beneath the destination, nested ones included.
   *
   * Covers: it writes every entry of an archive beneath the destination,
   * preserving nested directories.
   */
  public function testWritesEveryEntryBeneathTheDestinationPreservingNestedDirectories(): void {
    $archive = $this->archive('library.zip', [
      'style.css' => '.icon { display: inline-block; }',
      'fonts/icons.woff' => 'woff-bytes',
      'selection/nested/definitions.json' => '{"name":"library"}',
    ]);
    $destination = $this->workspace . '/destination';

    $this->extractor()->extract($archive, $destination);

    $this->assertStringEqualsFile($destination . '/style.css', '.icon { display: inline-block; }');
    $this->assertStringEqualsFile($destination . '/fonts/icons.woff', 'woff-bytes');
    $this->assertStringEqualsFile($destination . '/selection/nested/definitions.json', '{"name":"library"}');
  }

  /**
   * A destination that is not there yet is the install case.
   *
   * Covers: it creates the destination directory when it does not exist.
   */
  public function testCreatesTheDestinationDirectoryWhenItDoesNotExist(): void {
    $archive = $this->archive('library.zip', ['style.css' => 'css']);
    $destination = $this->workspace . '/not/there/yet';
    $this->assertDirectoryDoesNotExist($destination);

    $this->extractor()->extract($archive, $destination);

    $this->assertDirectoryExists($destination);
    $this->assertStringEqualsFile($destination . '/style.css', 'css');
  }

  /**
   * A second archive leaves nothing of the first behind.
   *
   * Covers: it replaces the destination's previous contents on a successful
   * extraction.
   */
  public function testReplacesThePreviousContentsOnSuccessfulExtraction(): void {
    $destination = $this->workspace . '/destination';
    mkdir($destination . '/retired', 0777, TRUE);
    file_put_contents($destination . '/retired/old.css', 'old');
    $archive = $this->archive('library.zip', ['new.css' => 'new']);

    $this->extractor()->extract($archive, $destination);

    $this->assertStringEqualsFile($destination . '/new.css', 'new');
    $this->assertDirectoryDoesNotExist($destination . '/retired');
    $this->assertFileDoesNotExist($destination . '/retired/old.css');
  }

  /**
   * A refused archive costs a site nothing it already had.
   *
   * The sequence this replaces deleted the destination and then extracted
   * into it, so an archive that would not open cost a site every favicon it
   * had, or a whole icon library, for a log line.
   *
   * Covers: it leaves an existing destination's contents intact when the
   * archive is refused.
   */
  public function testLeavesAnExistingDestinationIntactWhenTheArchiveIsRefused(): void {
    $destination = $this->workspace . '/destination';
    mkdir($destination . '/fonts', 0777, TRUE);
    file_put_contents($destination . '/style.css', 'installed');
    file_put_contents($destination . '/fonts/icons.woff', 'installed-woff');
    $archive = $this->workspace . '/corrupt.zip';
    file_put_contents($archive, 'these bytes are not a zip');

    try {
      $this->extractor()->extract($archive, $destination);
      $this->fail('The extractor accepted an archive it cannot open.');
    }
    catch (ExtractionRefusedException) {
      // The refusal itself is asserted by its own criterion below; what this
      // one is about is what the destination looks like afterwards.
    }

    $this->assertStringEqualsFile($destination . '/style.css', 'installed');
    $this->assertStringEqualsFile($destination . '/fonts/icons.woff', 'installed-woff');
  }

  /**
   * An archive that resolves to nothing never reaches `ZipArchive`.
   *
   * Both callers hand the extractor a stream URI, so resolution through the
   * file system service is the first thing that can fail and the refusal has
   * no open status to carry.
   *
   * Covers: it refuses an archive that cannot be resolved to a real path.
   */
  public function testRefusesAnArchiveThatCannotBeResolvedToRealPath(): void {
    $missing = $this->workspace . '/never-uploaded.zip';

    try {
      $this->extractor()->extract($missing, $this->workspace . '/destination');
      $this->fail('The extractor accepted an archive that resolves to nothing.');
    }
    catch (ExtractionRefusedException $refusal) {
      $this->assertStringContainsString($missing, $refusal->getMessage());
      $this->assertNull($refusal->getOpenStatus());
    }

    $this->assertDirectoryDoesNotExist($this->workspace . '/destination');
  }

  /**
   * The guard is the archive, not the file name.
   *
   * The removed manager chose a plugin by matching the file name's extension,
   * so its "not a valid archive" answer only ever meant "no plugin handles
   * this extension" and a file named `.zip` that was not one sailed past it.
   * The open status rides on the refusal so a consuming module can say which
   * kind of refusal it was.
   *
   * Covers: it refuses a file whose name ends in `.zip` but whose bytes are
   * not a zip, carrying the open status.
   */
  public function testRefusesFileNamedZipWhoseBytesAreNotZipCarryingTheOpenStatus(): void {
    $archive = $this->workspace . '/looks-like.zip';
    file_put_contents($archive, 'these bytes are not a zip');

    try {
      $this->extractor()->extract($archive, $this->workspace . '/destination');
      $this->fail('The extractor accepted a file that only looks like a zip.');
    }
    catch (ExtractionRefusedException $refusal) {
      $this->assertSame(\ZipArchive::ER_NOZIP, $refusal->getOpenStatus());
      $this->assertStringContainsString($archive, $refusal->getMessage());
    }
  }

  /**
   * Builds a zip archive on disk from a map of entry name to contents.
   *
   * @param string $name
   *   The archive's file name, created inside the workspace.
   * @param array<string, string> $entries
   *   Entry names, which may contain directory separators, keyed to contents.
   *
   * @return string
   *   The archive's path.
   */
  private function archive(string $name, array $entries): string {
    $path = $this->workspace . '/' . $name;
    $zip = new \ZipArchive();
    $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    foreach ($entries as $entry => $contents) {
      $zip->addFromString($entry, $contents);
    }
    $zip->close();
    return $path;
  }

  /**
   * Builds an extractor over a stub wired to the real file operations.
   *
   * The extractor's one dependency is core's file system service. Only three
   * of its methods are reached, and each is answered by the operation it
   * names, because the point of a real temporary destination is lost if the
   * writes never happen.
   *
   * @return \Drupal\neo_config_file\ZipExtractor
   *   The extractor under test.
   */
  private function extractor(): ZipExtractor {
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturnCallback(
      static fn ($uri) => realpath((string) $uri)
    );
    $fileSystem->method('prepareDirectory')->willReturnCallback(
      static fn ($directory, $options = NULL) => is_dir((string) $directory) || mkdir((string) $directory, 0777, TRUE)
    );
    $fileSystem->method('deleteRecursive')->willReturnCallback(
      fn ($path, $callback = NULL) => $this->removeRecursive((string) $path)
    );
    return new ZipExtractor($fileSystem);
  }

  /**
   * Removes a directory tree, or a single file, if it is there.
   *
   * @param string $path
   *   The path to remove.
   *
   * @return bool
   *   TRUE once the path is gone.
   */
  private function removeRecursive(string $path): bool {
    if (is_dir($path) && !is_link($path)) {
      foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
          $this->removeRecursive($path . '/' . $entry);
        }
      }
      return rmdir($path);
    }
    if (file_exists($path) || is_link($path)) {
      return unlink($path);
    }
    return TRUE;
  }

}
