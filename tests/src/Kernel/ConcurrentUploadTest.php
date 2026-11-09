<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_config_file\Kernel;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\file\Entity\File;
use Drupal\neo_config_file\ConfigFileInterface;
use Drupal\neo_config_file\Element\ConfigFile;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Two requests carrying the same upload at the same moment.
 *
 * A form that refreshes on change posts the file a second time while the
 * managed-file upload is still being saved. Left alone, the two either both
 * save it, leaving a duplicate "_0" file and config file, or collide on core's
 * per-destination lock, and the upload fails if it is the one that loses.
 *
 * Real concurrency cannot be staged in a kernel test, so the lock backend plays
 * the other request: it refuses this one the lock and, during the wait, does
 * what the other request would have done.
 */
#[Group('neo_config_file')]
final class ConcurrentUploadTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'file', 'user', 'neo_config_file'];

  /**
   * The local path of the upload the request carries.
   */
  private string $upload;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system']);
    $this->setUpCurrentUser();

    $this->upload = tempnam(sys_get_temp_dir(), 'neo_config_file_upload');
    file_put_contents($this->upload, 'the same bytes in both requests');
    \Drupal::request()->files->set('files', [
      'image' => new UploadedFile($this->upload, 'bike.png', 'image/png', NULL, TRUE),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    @unlink($this->upload);
    parent::tearDown();
  }

  /**
   * The request that waits reuses the file the other request saved.
   */
  public function testWaitsForTheOtherRequestAndReusesItsFile(): void {
    $saved = NULL;
    $lock = $this->useLock(function () use (&$saved) {
      $saved = $this->saveFileAsOtherRequest();
    });

    // FormBuilder always sets the user input before processing a form.
    $form_state = (new FormState())->setUserInput([]);

    $value = $this->runValueCallback($form_state);

    $this->assertNotNull($saved, 'The request never waited for the other one.');
    $this->assertSame([$saved], array_map('intval', $value['fids']));
    $this->assertCount(1, File::loadMultiple());
    $this->assertCount(1, $value['cfids']);
    $this->assertSame([], $lock->held);

    // The form rebuilds after this pass and runs the callback again, with the
    // upload gone from the request. Losing the file here returns an empty
    // widget: the upload appears to have done nothing, with no error shown.
    $input = NestedArray::getValue($form_state->getUserInput(), ['image']);
    $rebuilt = $this->runValueCallback($form_state, $input);
    $this->assertSame([$saved], array_map('intval', $rebuilt['fids']));
    $this->assertCount(1, File::loadMultiple());
  }

  /**
   * A save that produces nothing records nothing and still frees the lock.
   *
   * The element rebuilds the upload as a real one, which is_uploaded_file()
   * rejects outside a web request, so this save fails the way an upload that
   * fails validation does.
   */
  public function testFailedSaveReleasesTheLock(): void {
    $lock = $this->useLock(NULL);

    $value = $this->runValueCallback(new FormState());

    $this->assertSame([], $value['fids']);
    $this->assertNotEmpty($lock->taken, 'The upload was not made under the lock.');
    $this->assertSame([], $lock->held);
    $this->assertNull($this->store()->get($this->dedupeKey()));
  }

  /**
   * Runs the element's value callback against the request's upload.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $input
   *   The element's submitted input.
   */
  private function runValueCallback(FormStateInterface $form_state, array $input = ['fids' => '']): array {
    $element = [
      '#parents' => ['image'],
      '#array_parents' => ['image'],
      '#filename' => 'bike',
      '#extensions' => ['png'],
      '#multiple' => FALSE,
      '#extended' => FALSE,
    ];
    return ConfigFile::valueCallback($element, $input, $form_state);
  }

  /**
   * Installs the lock backend that plays the other request.
   *
   * @param \Closure|null $otherRequest
   *   What the other request does while this one waits, or NULL when no other
   *   request is running and the lock is free.
   */
  private function useLock(?\Closure $otherRequest): NullLockBackend {
    $lock = new class($otherRequest) extends NullLockBackend {

      /**
       * The upload locks currently held.
       *
       * @var string[]
       */
      public array $held = [];

      /**
       * Every upload lock this request has been granted.
       *
       * @var string[]
       */
      public array $taken = [];

      public function __construct(private ?\Closure $otherRequest) {
      }

      /**
       * {@inheritdoc}
       */
      public function acquire($name, $timeout = 30.0) {
        if (!str_starts_with($name, 'neo_config_file:')) {
          return TRUE;
        }
        if ($this->otherRequest) {
          return FALSE;
        }
        $this->held[$name] = $name;
        $this->taken[] = $name;
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      public function wait($name, $delay = 30) {
        ($this->otherRequest)();
        $this->otherRequest = NULL;
        return FALSE;
      }

      /**
       * {@inheritdoc}
       */
      public function release($name) {
        unset($this->held[$name]);
      }

    };
    $this->container->set('lock', $lock);
    return $lock;
  }

  /**
   * Saves the upload the way the other request does, recording its file ID.
   */
  private function saveFileAsOtherRequest(): int {
    $uri = ConfigFileInterface::PUBLIC_URI . '/bike.png';
    $directory = ConfigFileInterface::PUBLIC_URI;
    \Drupal::service('file_system')->prepareDirectory($directory, 1);
    file_put_contents($uri, file_get_contents($this->upload));
    $file = File::create([
      'uri' => $uri,
      'filename' => 'bike.png',
      'uid' => \Drupal::currentUser()->id(),
    ]);
    $file->save();
    $this->store()->set($this->dedupeKey(), (int) $file->id());
    return (int) $file->id();
  }

  /**
   * The store the element records saved uploads in.
   */
  private function store() {
    return \Drupal::service('tempstore.private')->get('neo_config_file');
  }

  /**
   * The key the element records this upload under.
   */
  private function dedupeKey(): string {
    return 'upload:image:' . hash_file('sha256', $this->upload);
  }

}
