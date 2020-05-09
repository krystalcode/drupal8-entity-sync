<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\RemoteEntityMappingEvent;
use Drupal\entity_sync\Import\ManagerInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Builds the default remote entity mapping for import operations.
 */
class DefaultImportRemoteEntityMapping implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DefaultImportRemoteEntityMapping object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      Events::REMOTE_ENTITY_MAPPING => ['buildEntityMapping', 0],
    ];
    return $events;
  }

  /**
   * Builds the default remote entity mapping.
   *
   * The default entity mapping is defined in the synchronization configuration
   * object.
   *
   * @param \Drupal\entity_sync\Import\Event\RemoteEntityMappingEvent $event
   *   The entity mapping event.
   */
  public function buildEntityMapping(RemoteEntityMappingEvent $event) {
    $entity_mapping = [];

    $sync = $event->getSync();
    $remote_entity = $event->getRemoteEntity();
    $remote_id_field = $sync->get('remote_resource.id_field');
    $entity_info = $sync->get('entity');

    $local_entity_ids = $this->entityTypeManager
      ->getStorage($entity_info['type_id'])
      ->getQuery()
      // @I Review whether disabling access check is always safe
      //    type     : bug
      //    priority : high
      //    labels   : security
      // @I Review solutions from preventing anonymous entity ownership
      //    type     : bug
      //    priority : high
      //    labels   : security
      ->accessCheck(FALSE)
      ->condition(
        $entity_info['remote_id_field'],
        $remote_entity->{$remote_id_field}
      )
      ->execute();

    if ($local_entity_ids) {
      $entity_mapping = $this->buildUpdateEntityMapping(
        $entity_info,
        current($local_entity_ids)
      );
    }
    else {
      $entity_mapping = $this->buildCreateEntityMapping($entity_info);
    }

    $event->setEntityMapping($entity_mapping);
  }

  /**
   * Builds the entity mapping for the create action.
   *
   * @param array $entity_info
   *   The entity information part of the synchronization object.
   *
   * @return array
   *   The entity mapping.
   */
  protected function buildCreateEntityMapping(array $entity_info) {
    $entity_mapping = [
      'action' => ManagerInterface::ACTION_CREATE,
      'entity_type_id' => $entity_info['type_id'],
    ];

    if (isset($entity_info['bundle'])) {
      $entity_mapping['entity_bundle'] = $entity_info['bundle'];
    }

    return $entity_mapping;
  }

  /**
   * Builds the entity mapping for the update action.
   *
   * @param array $entity_info
   *   The entity information part of the synchronization object.
   * @param string|int $entity_id
   *   The ID of the local entity that was found to be associated with the given
   *   remote entity.
   *
   * @return array
   *   The entity mapping.
   */
  protected function buildUpdateEntityMapping(array $entity_info, $entity_id) {
    $entity_mapping = [
      'action' => ManagerInterface::ACTION_UPDATE,
      'entity_type_id' => $entity_info['type_id'],
      'id' => $entity_id,
    ];

    if (isset($entity_info['bundle'])) {
      $entity_mapping['entity_bundle'] = $entity_info['bundle'];
    }

    return $entity_mapping;
  }

}
