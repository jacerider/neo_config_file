<?php

namespace Drupal\neo_config_file\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Render\Element;
use Drupal\neo_config_file\ConfigFileInterface;
use Drupal\file\Element\ManagedFile;
use Drupal\file\Entity\File;

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

    // $field_name = end($element['#parents']);
    $form_state->setTemporaryValue([
      'neo_config_file_field_names',
      implode('][', $element['#array_parents']),
    ], $element['#array_parents']);

    // Add global submit handler.
    $submit_handler_exists = array_filter($complete_form['actions']['submit']['#submit'], function ($submit) {
      return is_array($submit) && isset($submit[1]) && $submit[1] === 'neoConfigFilesSubmit';
    });
    if (!$submit_handler_exists) {
      $complete_form['actions']['submit']['#submit'][] = [
        static::class, 'neoConfigFilesSubmit',
      ];
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
      if ($form_object instanceof EntityFormInterface) {
        /** @var \Drupal\neo_config_file\ConfigFileStorageInterface $storage */
        $storage = \Drupal::entityTypeManager()->getStorage('neo_config_file');
        foreach ($fields as $array_parents) {
          $element = NestedArray::getValue($form, $array_parents);
          $values = $form_state->getValue($element['#parents']);
          $values = is_array($values) ? $values : [$values];
          foreach ($values as $neo_config_file_id) {
            $config_file = $storage->load($neo_config_file_id);
            // Store dependencies.
            if (!empty($element['#dependencies'])) {
              foreach ($element['#dependencies'] as $type => $dependents) {
                foreach ($dependents as $name) {
                  $config_file->addDependent($type, $name);
                }
              }
            }
            $parent_entity = $form_object->getEntity();
            $field_name = end($element['#parents']);
            $config_file->setParentEntity($parent_entity);
            $config_file->set('parent_field', $field_name);
            $config_file->save();
            if ($file = $config_file->getFile()) {
              $file->setPermanent();
              $file->save();
            }
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
   * Alter properties to force certain properties.
   *
   * @param array $element
   *   The renderable element.
   */
  protected static function alterProperties(array &$element) {
    $element['#upload_location'] = ConfigFileInterface::PUBLIC_URI;
    if (!empty($element['#extensions']) && empty($element['#upload_validators']['file_validate_extensions'])) {
      $element['#upload_validators']['file_validate_extensions'] = [
        implode(' ', $element['#extensions']),
      ];
    }
    $element['#upload_validators']['file_validate_size'] = [
      Bytes::toNumber('10MB'),
    ];
  }

}
