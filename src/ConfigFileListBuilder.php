<?php

namespace Drupal\neo_config_file;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of config files.
 */
class ConfigFileListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('File');
    $header['info'] = $this->t('Info');
    $header['dependencies'] = $this->t('Dependencies');
    $header['status'] = $this->t('Config Status');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\neo_config_file\ConfigFileInterface $entity */
    $row['label'][] = $entity->label();
    $row['info'] = [
      'data' => [
        '#theme' => 'description_list',
        '#neo_style' => 'inline',
        '#neo_size' => 'xs',
        '#items' => [
          [
            'term' => $this->t('ID'),
            'description' => $entity->id(),
          ],
          [
            'term' => $this->t('Config URI'),
            'description' => $entity->getConfigUri(),
          ],
        ],
      ],
      '#neo_size' => 'min',
    ];
    if ($parent_form_id = $entity->getParentFormId()) {
      $row['info']['data']['#items'][] = [
        'term' => $this->t('Parent form ID'),
        'description' => $parent_form_id,
      ];
    }
    if ($parent_entity = $entity->getParentEntity()) {
      $row['info']['data']['#items'][] = [
        'term' => $this->t('Parent entity'),
        'description' => $parent_entity->label() . ' (' . $parent_entity->getEntityTypeId() . ':' . $parent_entity->id() . ')',
      ];
    }
    if ($file = $entity->getFile()) {
      $row['label'] = [];
      $row['label']['data'] = [
        '#theme' => 'neo_config_file_link',
        '#file' => $file,
      ];
      $row['info']['data']['#items'][] = [
        'term' => $this->t('File URI'),
        'description' => $file->getFileUri(),
      ];
      $row['info']['data']['#items'][] = [
        'term' => $this->t('File ID'),
        'description' => $file->id(),
      ];
    }
    $dependencies = [
      '#theme' => 'description_list',
      '#neo_style' => 'inline',
      '#neo_size' => 'xs',
      '#items' => [],
    ];
    foreach ($entity->getDependencies() as $type => $names) {
      $dependencies['#items'][] = [
        'term' => ucwords($type),
        'description' => implode(', ', $names),
      ];
    }
    $row['dependencies'] = [
      'data' => $dependencies,
      '#neo_size' => 'min',
    ];
    $row['status'] = $entity->hasConfig() ? $this->t('Active') : $this->t('Pending');
    return $row;
  }

}
