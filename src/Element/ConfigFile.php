<?php

namespace Drupal\neo_config_file\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Render\Element;
use Drupal\neo_config_file\ConfigFileInterface;
use Drupal\file\Element\ManagedFile;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Provides a form element for uploading a config file.
 *
 * If you add this element to a form the enctype="multipart/form-data" attribute
 * will automatically be added to the form element.
 *
 * Properties:
 * - #multiple: A Boolean indicating whether multiple files may be uploaded.
 * - #size: The size of the file input element in characters.
 *
 * @FormElement("neo_config_file")
 */
class ConfigFile extends ManagedFile {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return parent::getInfo() + [
      '#after_build' => [[static::class, 'afterBuildManagedFile']],
      '#filename' => '',
      '#extensions' => ['txt'],
      '#dependencies' => [],
    ];
  }

  /**
   * Render API callback: Expands the managed_file element type.
   *
   * Expands the file type to include Upload and Remove buttons, as well as
   * support for a default value.
   */
  public static function processManagedFile(&$element, FormStateInterface $form_state, &$complete_form) {
    static::alterProperties($element);
    $element = parent::processManagedFile($element, $form_state, $complete_form);

    $fids = $element['#value']['fids'] ?? [];
    $element['#description'] = [
      '#theme' => 'file_upload_help',
      '#upload_validators' => $element['#upload_validators'],
      '#description' => $element['#description'] ?? '',
      '#array_parents' => [],
    ];

    array_unshift($element['remove_button']['#submit'], [
      static::class, 'removeCallback',
    ]);
    $element['cfids'] = [
      '#type' => 'hidden',
      '#value' => $element['#value']['cfids'] ?? [],
    ];

    if (!empty($fids) && $element['#files']) {
      foreach ($element['#files'] as $delta => $file) {
        /** @var \Drupal\file\FileInterface $file */
        $display = &$element['file_' . $delta];
        if (!empty($display['filename']['#theme'])) {
          $display['filename']['#theme'] = 'neo_config_file_link';
        }
      }
    }

    $form_state->setTemporaryValue([
      'neo_config_file_field_names',
      implode('][', $element['#array_parents']),
    ], $element['#array_parents']);

    // Forms may relocate their actions (e.g. neo_alchemist's
    // InstanceComponentForm moves them into a footer wrapper), so check the
    // known locations for the submit button.
    $submit = NULL;
    if (isset($complete_form['actions']['submit'])) {
      $submit = &$complete_form['actions']['submit'];
    }
    elseif (isset($complete_form['footer']['actions']['submit'])) {
      $submit = &$complete_form['footer']['actions']['submit'];
    }
    if ($submit !== NULL) {
      if (empty($submit['#submit'])) {
        // We have no submit handler. This typically means submitForm would have
        // been called. We need to check if we have this method and add it.
        $form_object = $form_state->getFormObject();
        if (method_exists($form_object, 'submitForm')) {
          $submit['#submit'][] = '::submitForm';
        }
      }

      // Add global submit handler.
      $submit_handler_exists = isset($submit['#submit']) && array_filter($submit['#submit'], function ($handler) {
        return is_array($handler) && isset($handler[1]) && $handler[1] === 'neoConfigFilesSubmit';
      });

      if (!$submit_handler_exists) {
        $submit['#submit'][] = [
          static::class, 'neoConfigFilesSubmit',
        ];
      }
    }
    else {
      throw new \Exception('Submit button not found.');
    }

    return $element;
  }

  /**
   * Form submit handler for #type 'neo_config_file'.
   *
   * This will only be called a single time on a form no matter how many
   * 'neo_config_file' fields are on the form.
   *
   * @param array $form
   *   The form definition array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function neoConfigFilesSubmit(array $form, FormStateInterface $form_state) {
    $fields = $form_state->getTemporaryValue('neo_config_file_field_names');
    if (!empty($fields)) {
      $form_object = $form_state->getFormObject();
      /** @var \Drupal\neo_config_file\ConfigFileStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('neo_config_file');
      foreach ($fields as $array_parents) {
        $element = NestedArray::getValue($form, $array_parents);
        $values = $form_state->getValue($element['#parents']);
        $values = is_array($values) ? $values : [$values];
        foreach (array_filter($values) as $neo_config_file_id) {
          $config_file = $storage->load($neo_config_file_id);
          if (!$config_file) {
            continue;
          }
          $config_file->set('parent_form_id', $form_object->getFormId());
          // Store dependencies.
          if (!empty($element['#dependencies'])) {
            foreach ($element['#dependencies'] as $type => $dependents) {
              foreach ($dependents as $name) {
                $config_file->addDependent($type, $name);
              }
            }
          }
          if ($form_object instanceof EntityFormInterface) {
            $parent_entity = $form_object->getEntity();
            $field_name = end($element['#parents']);
            // Only config entities can be tracked as the parent; content-entity
            // forms (e.g. the transient Alchemist block host) rely on the
            // declared #dependencies instead.
            if ($parent_entity instanceof ConfigEntityInterface) {
              $config_file->setParentEntity($parent_entity);
            }
            $config_file->set('parent_field', $field_name);
          }
          $config_file->save();
          if ($file = $config_file->getFile()) {
            $file->setPermanent();
            $file->save();
          }
        }
      }
    }
  }

  /**
   * Remove callback.
   */
  public static function removeCallback($form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#array_parents'];
    array_pop($parents);
    $element = NestedArray::getValue($form, $parents);
    $fids = array_keys($element['#files']);
    // Get files that will be removed.
    if ($element['#multiple']) {
      $remove_fids = [];
      foreach (Element::children($element) as $name) {
        if (strpos($name, 'file_') === 0 && $element[$name]['selected']['#value']) {
          $remove_fids[] = (int) substr($name, 5);
        }
      }
      $fids = array_diff($fids, $remove_fids);
    }
    else {
      // If we deal with single upload element remove the file and set
      // element's value to empty array (file could not be removed from
      // element if we don't do that).
      $remove_fids = $fids;
      $fids = [];
    }
    foreach ($remove_fids as $fid) {
      $file = File::load($fid);
      if ($file) {
        // Set file to temporary so that it is removed.
        $file->setTemporary();
        $file->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    static::alterProperties($element);

    // Rename the uploaded file to the filename specified in the element, and
    // deduplicate re-processing of the SAME upload.
    //
    // A file input keeps its selection after the user chooses a file, so any
    // form that refreshes on change (e.g. the Alchemist component preview)
    // re-submits the same bytes in a second request that races the managed-file
    // upload. Without a guard the parent ManagedFile saves the identical upload
    // twice and a duplicate File + neo_config_file ("…_0") is created. Key a
    // short-lived per-user store on the element and a content fingerprint: an
    // identical re-send reuses the file that was already saved, while a
    // genuinely different file (even one the user happens to name the same)
    // still gets its own copy. Form-cache state is unreliable here because the
    // racing requests submit the same (pre-upload) form build id, so a
    // build-independent tempstore is used.
    $dedupe_key = NULL;
    $fresh_upload = FALSE;
    if (!empty($element['#filename'])) {
      $request = \Drupal::request();
      $all_files = $request->files->get('files', []);
      $upload_name = implode('_', $element['#parents']);
      if (!empty($all_files[$upload_name])) {
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
        $file = $all_files[$upload_name];
        if ($file->isValid()) {
          $store = \Drupal::service('tempstore.private')->get('neo_config_file');
          $dedupe_key = 'upload:' . $upload_name . ':' . hash_file('sha256', $file->getPathname());
          $existing_fid = $store->get($dedupe_key);
          if ($existing_fid && File::load($existing_fid)) {
            // The same upload was already saved during this interaction. Reuse
            // it and drop the pending upload so the parent does not save it a
            // second time.
            $input = is_array($input) ? $input : [];
            $input['fids'] = (string) $existing_fid;
            unset($all_files[$upload_name]);
            $request->files->set('files', $all_files);
          }
          else {
            // First time seeing this upload: rename it to the deterministic
            // filename and let the parent save it. The resulting fid is
            // recorded below so subsequent re-sends reuse it.
            $newName = $element['#filename'] . '.' . $file->getClientOriginalExtension();
            $newFile = new UploadedFile($file->getPath() . '/' . $file->getFilename(), $newName, $file->getClientMimeType(), FALSE);
            $all_files[$upload_name] = $newFile;
            $request->files->set('files', $all_files);
            $fresh_upload = TRUE;
          }
        }
      }
    }

    if (!empty($element['#default_value'])) {
      if (!$element['#multiple'] && is_string($element['#default_value'])) {
        $element['#default_value'] = [$element['#default_value']];
      }
      if (isset($element['#default_value']['fids'])) {
        $element['#default_value'] = $element['#default_value']['fids'];
      }
      else {
        // Default values will be the id for the neo config file instead of the
        // file entity. We need to convert to fids.
        /** @var \Drupal\neo_config_file\ConfigFileStorageInterface $storage */
        $storage = \Drupal::entityTypeManager()->getStorage('neo_config_file');
        foreach ($element['#default_value'] as $delta => $value) {
          if ($config_file = $storage->load($value)) {
            if ($file = $config_file->getFile()) {
              $element['#default_value'][$delta] = $file->id();
            }
          }
        }
      }
    }
    $value = parent::valueCallback($element, $input, $form_state);

    // Remember the fid produced by a fresh upload so an identical re-send in a
    // racing request reuses it instead of saving a duplicate.
    if ($fresh_upload && $dedupe_key && !empty($value['fids'])) {
      \Drupal::service('tempstore.private')->get('neo_config_file')->set($dedupe_key, (int) reset($value['fids']));
    }

    $value['cfids'] = [];
    if (!empty($value['fids'])) {
      /** @var \Drupal\neo_config_file\ConfigFileStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('neo_config_file');
      foreach ($value['fids'] as $fid) {
        if ($config_file = $storage->loadByFileId($fid)) {
          $value['cfids'][] = $config_file->id();
        }
      }
    }
    return $value;
  }

  /**
   * Render API callback: Validates the managed_file element.
   */
  public static function validateManagedFile(&$element, FormStateInterface $form_state, &$complete_form) {
    parent::validateManagedFile($element, $form_state, $complete_form);

    // Consolidate the array value of this field to array of FIDs.
    if (!$element['#extended']) {
      $value = $element['cfids']['#value'];
      if (!$element['#multiple']) {
        $value = reset($value);
      }
      $form_state->setValueForElement($element, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function afterBuildManagedFile(array $element, FormStateInterface $form_state) {
    // Consolidate the array value of this field to array of FIDs.
    if ($form_state->isProcessingInput()) {
      $value = $form_state->getValue($element['#parents']);
      if (is_array($value) && isset($value['cfids'])) {
        $value = $value['cfids'];
        if (!$element['#multiple']) {
          $value = reset($value);
        }
        $form_state->setValueForElement($element, $value);
      }
    }
    return $element;
  }

  /**
   * Alter properties to force certain properties.
   *
   * @param array $element
   *   The renderable element.
   */
  protected static function alterProperties(array &$element) {
    $element['#upload_location'] = ConfigFileInterface::PUBLIC_URI;
    if (!empty($element['#extensions']) && empty($element['#upload_validators']['FileExtension'])) {
      $element['#upload_validators']['FileExtension'] = [
        'extensions' => implode(' ', $element['#extensions']),
      ];
    }
    $element['#upload_validators']['FileSizeLimit'] = ['fileLimit' => Bytes::toNumber('20MB')];
  }

}
