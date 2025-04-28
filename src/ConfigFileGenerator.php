<?php

declare(strict_types=1);

namespace Drupal\neo_config_file;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Generates config file entities.
 */
final class ConfigFileGenerator {

  /**
   * Constructs a ConfigFileGenerator object.
   */
  public function __construct(
    private readonly AccountProxyInterface $account,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Creates a new config file entity from a base64 encoded string.
   *
   * @param string $base64
   *   The base64 encoded string.
   * @param string $filename
   *   The name of the file to be created.
   * @param int $maxWidth
   *   The maximum width of the resized image.
   * @param int $maxHeight
   *   The maximum height of the resized image.
   * @param int|null $canvasWidth
   *   The width of the canvas (optional).
   * @param int|null $canvasHeight
   *   The height of the canvas (optional).
   *
   * @return \Drupal\file\FileInterface
   *   The created file entity.
   */
  public function createFromBase64(string $base64, string $filename, $maxWidth = 800, $maxHeight = 600, $canvasWidth = NULL, $canvasHeight = NULL): ?ConfigFileInterface {
    $destination = ConfigFileInterface::PUBLIC_URI . '/' . $filename;
    $directory = dirname($destination);
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData(base64_decode($base64), $destination, FileExists::Replace);
    $this->resizeImage($destination, $maxWidth, $maxHeight, $canvasWidth, $canvasHeight);
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entityTypeManager->getStorage('file')->create([
      'filename' => basename($destination),
      'uri' => $destination,
      'status' => 1,
      'uid' => $this->account->id(),
    ]);
    $file->save();
    /** @var \Drupal\neo_config_file\ConfigFileStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('neo_config_file');
    $configFile = $storage->loadByFile($file);
    if ($configFile) {
      $file->setPermanent();
      $file->save();
    }
    return $configFile;
  }

  /**
   * Resizes an image to fit within the specified dimensions.
   *
   * @param string $imageFile
   *   The path to the image file.
   * @param int $maxWidth
   *   The maximum width of the resized image.
   * @param int $maxHeight
   *   The maximum height of the resized image.
   * @param int|null $canvasWidth
   *   The width of the canvas (optional).
   * @param int|null $canvasHeight
   *   The height of the canvas (optional).
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  protected function resizeImage(
    $imageFile,
    $maxWidth = 800,
    $maxHeight = 600,
    $canvasWidth = NULL,
    $canvasHeight = NULL,
  ) {
    // Check if file exists.
    if (!file_exists($imageFile)) {
      return FALSE;
    }

    // Verify file is an image.
    $imageInfo = @getimagesize($imageFile);
    if ($imageInfo === FALSE) {
      // Not an image or cannot be read as an image.
      return FALSE;
    }

    $backgroundColor = [
      255,
      255,
      255,
    ];

    // Get image dimensions and type.
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    $imageType = $imageInfo[2];

    // Check if image type is supported.
    if (!in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) {
      // Unsupported image type.
      return FALSE;
    }

    // Calculate new dimensions for the image itself.
    $widthRatio = $maxWidth / $originalWidth;
    $heightRatio = $maxHeight / $originalHeight;

    // Use the smaller ratio to ensure the image fits within both constraints.
    $ratio = min($widthRatio, $heightRatio);

    // Calculate new dimensions.
    $newWidth = round($originalWidth * $ratio);
    $newHeight = round($originalHeight * $ratio);

    // If canvas dimensions aren't provided, use the new image dimensions.
    $canvasWidth = $canvasWidth ?: $newWidth;
    $canvasHeight = $canvasHeight ?: $newHeight;

    // Create the appropriate image resource based on file type.
    $sourceImage = NULL;
    switch ($imageType) {
      case IMAGETYPE_JPEG:
        $sourceImage = @imagecreatefromjpeg($imageFile);
        break;

      case IMAGETYPE_PNG:
        $sourceImage = @imagecreatefrompng($imageFile);
        break;

      case IMAGETYPE_GIF:
        $sourceImage = @imagecreatefromgif($imageFile);
        break;
    }

    // Check if image resource was created successfully.
    if (!$sourceImage) {
      return FALSE;
    }

    // Create the canvas with the specified dimensions.
    $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);

    // Set background color.
    $bgColor = imagecolorallocate($canvas, $backgroundColor[0], $backgroundColor[1], $backgroundColor[2]);
    imagefill($canvas, 0, 0, $bgColor);

    // Handle transparency for PNG.
    if ($imageType == IMAGETYPE_PNG) {
      imagealphablending($canvas, FALSE);
      imagesavealpha($canvas, TRUE);

      // If we need a transparent background.
      if ($backgroundColor[0] == 0 && $backgroundColor[1] == 0 && $backgroundColor[2] == 0 && isset($backgroundColor[3]) && $backgroundColor[3] == 127) {
        // Create a transparent color.
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
      }
    }

    // Calculate position to center the image on the canvas.
    $xPos = ($canvasWidth - $newWidth) / 2;
    $yPos = ($canvasHeight - $newHeight) / 2;

    // Copy and resize the source image to the canvas.
    imagecopyresampled(
        $canvas, $sourceImage,
        (int) $xPos, (int) $yPos, 0, 0,
        (int) $newWidth, (int) $newHeight, $originalWidth, $originalHeight
    );

    // Save the resized image back to the original file.
    $result = FALSE;
    switch ($imageType) {
      case IMAGETYPE_JPEG:
        // 90 is quality (0-100)
        $result = imagejpeg($canvas, $imageFile, 90);
        break;

      case IMAGETYPE_PNG:
        // 9 is compression level (0-9)
        $result = imagepng($canvas, $imageFile, 9);
        break;

      case IMAGETYPE_GIF:
        $result = imagegif($canvas, $imageFile);
        break;
    }

    // Free up memory.
    imagedestroy($sourceImage);
    imagedestroy($canvas);

    return $result;
  }

}
