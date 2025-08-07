<?php

namespace Drupal\neo_config_file;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the taxonomy vocabulary entity type.
 *
 * @see \Drupal\taxonomy\Entity\Vocabulary
 */
final class ConfigFileAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\neo_config_file\ConfigFileInterface $entity */
    if ($entity->getParentFormId() !== 'neo_config_file_add') {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    // Implement any specific access checks for the ConfigFile entity.
    // For example, you might want to restrict access based on user roles or
    // other conditions.
    return parent::checkAccess($entity, $operation, $account);
  }

}
