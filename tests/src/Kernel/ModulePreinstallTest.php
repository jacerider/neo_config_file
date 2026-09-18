<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_config_file\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\Group;

/**
 * The preinstall hook that copies a module's bundled files into config.
 *
 * It runs for every module install, including one that arrives through a
 * config import. There the files are already in the config directory —
 * exported and committed with the config — and on a read-only codebase the
 * directory cannot be written, which used to abort the import.
 *
 * A read-only codebase is simulated with a config files directory owned by
 * another user and not writable: unlike a plain chmod, prepareDirectory()
 * cannot undo that, just as it cannot on a read-only mount.
 *
 * neo_icon is the module under test because it ships its icon packages this
 * way; it only has to be present on disk, not installed.
 */
#[Group('neo_config_file')]
final class ModulePreinstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'file', 'neo_config_file'];

  /**
   * The bundled file the tests watch.
   */
  private string $bundled;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $source = $this->root . '/' . $this->container->get('extension.list.module')->getPath('neo_icon') . '/config/install/files';
    $files = glob($source . '/*.zip');
    if (!$files) {
      $this->markTestSkipped('neo_icon with bundled files is not present.');
    }
    $this->bundled = $files[0];
  }

  /**
   * A fresh install copies the bundled files into the config directory.
   */
  public function testCopiesBundledFiles(): void {
    neo_config_file_module_preinstall('neo_icon');

    $this->assertSame(hash_file('sha256', $this->bundled), hash_file('sha256', $this->copy()));
  }

  /**
   * A read-only config directory already holding the files is not an error.
   */
  public function testReadOnlyDirectoryWithFilesAlreadyThere(): void {
    neo_config_file_module_preinstall('neo_icon');
    $this->lockFilesDirectory();

    neo_config_file_module_preinstall('neo_icon');

    $this->assertSame(hash_file('sha256', $this->bundled), hash_file('sha256', $this->copy()));
  }

  /**
   * A read-only config directory missing a file skips it instead of failing.
   */
  public function testReadOnlyDirectoryMissingAFile(): void {
    mkdir($this->configDirectory() . '/files', 0775, TRUE);
    $this->lockFilesDirectory();

    neo_config_file_module_preinstall('neo_icon');

    $this->assertFileDoesNotExist($this->copy());
  }

  /**
   * The config sync directory the config:// wrapper writes into.
   */
  private function configDirectory(): string {
    return Settings::get('config_sync_directory');
  }

  /**
   * Where the watched bundled file lands in the config directory.
   */
  private function copy(): string {
    return $this->configDirectory() . '/files/' . basename($this->bundled);
  }

  /**
   * Makes the config files directory unwritable in a way chmod cannot undo.
   */
  private function lockFilesDirectory(): void {
    $path = substr($this->configDirectory(), strlen('vfs://root/')) . '/files';
    $this->vfsRoot->getChild($path)->chown(vfsStream::OWNER_ROOT)->chmod(0555);
  }

}
