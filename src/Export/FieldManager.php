<?php

namespace Drupal\entity_sync\Export;

use Drupal\entity_sync\Exception\FieldExportException;
use Drupal\entity_sync\Export\Event\Events;
use Drupal\entity_sync\Export\Event\FieldMappingEvent;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemInterface;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The default field manager.
 */
class FieldManager implements FieldManagerInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new FieldManager instance.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    EventDispatcherInterface $event_dispatcher,
    LoggerInterface $logger
  ) {
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function mappingDefaults() {
    return [
      'export' => [
        'status' => TRUE,
        'callback' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function export(
    ContentEntityInterface $local_entity,
    $remote_entity_id,
    ImmutableConfig $sync,
    array $options = []
  ) {
    // Build the field mapping for the fields that will be exported.
    // @I Validate the final field mapping
    //    type     : bug
    //    priority : normal
    //    labels   : export, mapping, validation
    $field_mapping = $this->fieldMapping(
      $local_entity,
      $remote_entity_id,
      $sync
    );

    // If the field mapping is empty we will not be exporting any fields.
    if (!$field_mapping) {
      return [];
    }

    return $this->doExport(
      $local_entity,
      $remote_entity_id,
      $sync,
      $field_mapping
    );
  }

  /**
   * Builds and returns the field mapping for the given entities.
   *
   * The field mapping defines which remote entity fields will be updated with
   * which values contained in the given local entity. The default mapping is
   * defined in the synchronization to which the operation we are currently
   * executing belongs.
   *
   * An event is dispatched that allows subscribers to alter the default field
   * mapping.
   *
   * @param \Drupal\core\Entity\ContentEntityInterface $local_entity
   *   The local entity.
   * @param int|string|null $remote_entity_id
   *   The ID of the remote entity that will be updated, or NULL if we are
   *   creating a new one.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   *
   * @return array
   *   The final field mapping.
   *
   * @I Validate the final field mapping
   *    type     : bug
   *    priority : normal
   *    labels   : export, mapping, validation
   */
  protected function fieldMapping(
    ContentEntityInterface $local_entity,
    $remote_entity_id,
    ImmutableConfig $sync
  ) {
    $event = new FieldMappingEvent(
      $local_entity,
      $remote_entity_id,
      $sync
    );
    $this->eventDispatcher->dispatch(Events::FIELD_MAPPING, $event);

    // Return the final mappings.
    return $event->getFieldMapping();
  }

  /**
   * Does the actual export of the fields for the given entities.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The associated local entity.
   * @param int|string|null $remote_entity_id
   *   The ID of the remote entity that will be updated, or NULL if we are
   *   creating a new one.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $field_mapping
   *   The field mapping.
   *   See \Drupal\entity_sync\Export\Event\FieldMapping::fieldMapping.
   *
   * @return array
   *   An associative array containing the remote fields, keyed by the field
   *   name and containing the field value.
   *
   * @throws \Drupal\entity_sync\Exception\FieldExportException
   *   When an error occurs while exporting a field.
   */
  protected function doExport(
    ContentEntityInterface $local_entity,
    $remote_entity_id,
    ImmutableConfig $sync,
    array $field_mapping
  ) {
    $fields = [];

    foreach ($field_mapping as $field_info) {
      $field_info = NestedArray::mergeDeep(
        $this->mappingDefaults(),
        $field_info
      );
      if (!$field_info['export']['status']) {
        continue;
      }

      try {
        $fields[$field_info['remote_name']] = $this->doExportField(
          $local_entity,
          $remote_entity_id,
          $field_info,
          $sync
        );
      }
      catch (\Throwable $throwable) {
        $this->throwExportFieldException(
          $local_entity,
          $remote_entity_id,
          $field_info,
          $sync,
          $throwable
        );
      }
    }

    return $fields;
  }

  /**
   * Performs the actual export of a local field to a remote field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The local entity.
   * @param int|string|null $remote_entity_id
   *   The ID of the remote entity that will be updated, or NULL if we are
   *   creating a new one.
   * @param array $field_info
   *   The field info.
   *   See \Drupal\entity_sync\Export\Event\FieldMapping::fieldMapping.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   *
   * @return string|array|null
   *   The exported value for the field, or NULL if the field is empty.
   */
  protected function doExportField(
    ContentEntityInterface $local_entity,
    $remote_entity_id,
    array $field_info,
    ImmutableConfig $sync
  ) {
    // If the field value should be converted and exported by a custom callback,
    // then invoke that.
    if (($field_info['export']['callback'] ?? FALSE) !== FALSE) {
      return call_user_func(
        $field_info['export']['callback'],
        $local_entity,
        $remote_entity_id,
        $field_info
      );
    }
    // Else, we assume direct copy of the remote field value into the local
    // field.
    // @I Add more details about the field mapping in the exception message
    //    type     : task
    //    priority : low
    //    labels   : error-handling, export
    // @I Provide the option to continue the entity export when a field fails
    //    type     : improvement
    //    priority : normal
    //    labels   : error-handling, export
    // @I Implement log levels e.g. error, warning, info, debug
    //    type     : feature
    //    priority : normal
    //    labels   : error-handling, export
    // @I Support configuration entities
    //    type     : feature
    //    priority : normal
    //    labels   : export
    //    notes    : Configuration entities do not have a function that checks
    //               whether a property exists and that case has to be handled
    //               differently.
    elseif (!$local_entity->hasField($field_info['machine_name'])) {
      throw new \RuntimeException(
        sprintf(
          'The non-existing local entity field "%s" was requested to be mapped to a remote field',
          $field_info['machine_name']
        )
      );
    }

    // We  copy the value when the field exists even if it is NULL.
    $field = $local_entity->get($field_info['machine_name']);

    if ($field->isEmpty()) {
      return;
    }

    $value = [];

    foreach ($field->filterEmptyItems() as $index => $field_item) {
      // The field item value is stored as an array of properties. However, the
      // majority of the fields have only one main property and the majority of
      // the remote resources expect that value directly. We therefore return
      // that value directly by default; if a remote resource expects the value
      // as an array, that can be handled by a callback.
      //
      // @I Provide export field callback that returns the value in array format
      //    type     : improvement
      //    priority : low
      //    labels   : export, field
      // @I Consider moving the no-callback logic to a default callback
      //    type     : improvement
      //    priority : low
      //    labels   : export, field
      $main_property_name = $this->getFieldMainPropertyName($field_item);
      $value[$index] = $field_item->getValue()[$main_property_name];
    }

    // If a field is a single-cardinality field we export a single value. If a
    // field is a multiple-cardinality field we export an array of values - even
    // when there is only one field item.
    $cardinality = $field->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getCardinality();
    return $cardinality === 1 ? current($value) : $value;
  }

  /**
   * Throws a wrapper exception for exceptions thrown during field export.
   *
   * We do this so that we can recognize as an `ExportFieldException` any
   * exception that may be thrown during field export runtime.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The associated local entity.
   * @param int|string|null $remote_entity_id
   *   The ID of the remote entity that will be updated, or NULL if we are
   *   creating a new one.
   * @param array $field_info
   *   The field info.
   *   See \Drupal\entity_sync\Export\Event\FieldMapping::fieldMapping.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param \Throwable $throwable
   *   The exception that was thrown during field export.
   */
  protected function throwExportFieldException(
    ContentEntityInterface $local_entity,
    $remote_entity_id,
    array $field_info,
    ImmutableConfig $sync,
    \Throwable $throwable
  ) {
    $local_entity_text = 'a new local entity';
    if (!$local_entity->isNew()) {
      $local_entity_text = sprintf(
        'the local entity with ID "%s"',
        $local_entity->id()
      );
    }
    $remote_entity_text = 'a new remote entity';
    if ($remote_entity_id !== NULL) {
      $remote_entity_text = sprintf(
        'the remote entity with ID "%s"',
        $remote_entity_id
      );
    }

    throw new FieldExportException(
      sprintf(
        '"%s" exception was thrown while exporting the "%s" field of the local entity with ID "%s" into the "%s" field of %s. The error message was: %s. The field mapping was: %s',
        get_class($throwable),
        $field_info['machine_name'],
        $local_entity_text,
        $field_info['remote_name'],
        $remote_entity_text,
        $throwable->getMessage(),
        json_encode($field_info)
      )
    );
  }

  /**
   * Returns the main property name for the given field item.
   *
   * This method is made available just so that we can bypass limitations of
   * mocking static methods by mocking this class.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $field_item
   *   The field item.
   *
   * @return string
   *   The name of the field item's main property.
   */
  protected function getFieldMainPropertyName(FieldItemInterface $field_item) {
    return $field_item::mainPropertyName();
  }

}
