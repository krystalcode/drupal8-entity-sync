<?php

namespace Drupal\entity_sync\Import;

use Drupal\entity_sync\Config\ManagerInterface as ConfigManagerInterface;
use Drupal\entity_sync\Exception\FieldImportException;
use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\FieldMappingEvent;
use Drupal\entity_sync\Utility\DateTime;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The default field manager.
 */
class FieldManager implements FieldManagerInterface {

  /**
   * The Entity Sync configuration manager.
   *
   * @var \Drupal\entity_sync\Config\ManagerInterface
   */
  protected $configManager;

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
   * @param \Drupal\entity_sync\Config\ManagerInterface $config_manager
   *   The Entity Sync configuration manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    ConfigManagerInterface $config_manager,
    EventDispatcherInterface $event_dispatcher,
    LoggerInterface $logger
  ) {
    $this->configManager = $config_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function import(
    object $remote_entity,
    ContentEntityInterface $local_entity,
    ImmutableConfig $sync,
    array $options = []
  ) {
    // Build the field mapping for the fields that will be imported.
    // @I Validate the final field mapping
    //    type     : bug
    //    priority : normal
    //    labels   : mapping, validation
    $field_mapping = $this->fieldMapping($remote_entity, $local_entity, $sync);

    // If the field mapping is empty we will not be updating any fields in the
    // local entity; nothing to do.
    if (!$field_mapping) {
      return;
    }

    $this->doImport(
      $remote_entity,
      $local_entity,
      $sync,
      $field_mapping
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setRemoteIdField(
    object $remote_entity,
    ContentEntityInterface $local_entity,
    ImmutableConfig $sync,
    $force = TRUE
  ) {
    // By default, it is expected that the remote ID field exists on the local
    // entity  because it is the default way that we detect the entity
    // mapping. We therefore want developers to intentionally disable that in
    // the synchronization configuration. Until that is supported, we proceed
    // and the `\Drupal\Core\Entity\ContentEntityInterface::set()` method throws
    // an exception.
    //
    // Similarly, we throw an exception if the field does not exist on the
    // remote entity as otherwise something's wrong. When we support disabling
    // the remote ID field method of entity mapping we will make sure that the
    // program does not intends to store the remote ID in the first place.
    //
    // @I Support disabling the remote ID field method of entity mapping
    //    type     : feature
    //    priority : low
    //    labels   : import, mapping
    $remote_id_field = $sync->get('remote_resource.id_field');
    if (!isset($remote_entity->{$remote_id_field})) {
      throw new \RuntimeException(
        sprintf(
          'The non-existing remote entity field "%s" was requested to be mapped to the remote entity ID field on the local entity.',
          $remote_id_field
        )
      );
    }

    $local_id_field = $sync->get('entity.remote_id_field');
    if (!$force && !$local_entity->get($local_id_field)->isEmpty()) {
      return;
    }

    $local_entity->set(
      $local_id_field,
      $remote_entity->{$remote_id_field}
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setRemoteChangedField(
    object $remote_entity,
    ContentEntityInterface $local_entity,
    ImmutableConfig $sync
  ) {
    // The changed fields may not exist when the list import is always a full
    // import i.e. not based on fetching recently created or updated list of
    // remote entities. We therefore simply do not anything if the changed field
    // is not defined. If it is defined in the configuration though but it does
    // not exist in the remote entity, we throw an exception.
    //
    // @I Validate that the changed field is defined wherever appropriate
    //    type     : improvement
    //    priority : normal
    //    labels   : config, validation
    //    notes    : Validate when the sync configuration is imported.
    // @I Validate that both changed fields are defined or not
    //    type     : improvement
    //    priority : normal
    //    labels   : config, validation
    //    notes    : Validate when the sync configuration is imported.
    $field_config = $sync->get('remote_resource.changed_field');
    if (!$field_config) {
      return;
    }

    $field_name = $field_config['name'];
    if (!isset($remote_entity->{$field_name})) {
      throw new \RuntimeException(
        sprintf(
          'The non-existing remote entity field "%s" was requested to be mapped to the remote entity changed field on the local entity.',
          $field_name
        )
      );
    }

    // Prepare the value based on the configured format.
    // @I Throw an exception when the changed value isn't in the expected format
    //    type     : bug
    //    priority : normal
    //    labels   : error-handling, import
    $field_value = NULL;
    if ($field_config['format'] === 'timestamp') {
      $field_value = $remote_entity->{$field_name};
      if (!DateTime::isTimestamp($field_value)) {
        throw new \RuntimeException(
          sprintf(
            'The remote entity field "%s" that was requested to be mapped to the remote entity changed field on the local entity was expected to be in Unix timestamp format, "%s" given.',
            $field_name,
            $field_value
          )
        );
      }
    }
    elseif ($field_config['format'] === 'string') {
      $field_value = strtotime($remote_entity->{$field_name});
      if ($field_value === FALSE) {
        throw new \RuntimeException(
          sprintf(
            'The remote entity field "%s" that was requested to be mapped to the remote entity changed field on the local entity was expected to be in textual datetime format supported by PHP\'s `strtotime`, "%s" given.',
            $field_name,
            $field_value
          )
        );
      }
    }

    $local_entity->set(
      $sync->get('entity.remote_changed_field'),
      $field_value
    );
  }

  /**
   * Builds and returns the field mapping for the given entities.
   *
   * The field mapping defines which local entity fields will be updated with
   * which values contained in the given remote entity. The default mapping is
   * defined in the synchronization to which the operation we are currently
   * executing belongs.
   *
   * An event is dispatched that allows subscribers to alter the default field
   * mapping.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\core\Entity\ContentEntityInterface $local_entity
   *   The local entity.
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
   *    labels   : import, mapping, validation
   */
  protected function fieldMapping(
    object $remote_entity,
    ContentEntityInterface $local_entity,
    ImmutableConfig $sync
  ) {
    $event = new FieldMappingEvent(
      $remote_entity,
      $local_entity,
      $sync
    );
    $this->eventDispatcher->dispatch(Events::FIELD_MAPPING, $event);

    // Return the final mappings.
    return $event->getFieldMapping();
  }

  /**
   * Does the actual import of the fields for the given entities.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The associated local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $field_mapping
   *   The field mapping.
   *   See \Drupal\entity_sync\Import\Event\FieldMapping::fieldMapping.
   *
   * @throws \Drupal\entity_sync\Exception\FieldImportException
   *   When an error occurs while importing a field.
   */
  protected function doImport(
    object $remote_entity,
    ContentEntityInterface $local_entity,
    ImmutableConfig $sync,
    array $field_mapping
  ) {
    foreach ($field_mapping as $field_info) {
      $field_info = $this->configManager
        ->mergeImportFieldMappingDefaults($field_info);
      if (!$field_info['import']['status']) {
        continue;
      }

      try {
        $this->doImportField($remote_entity, $local_entity, $field_info, $sync);
      }
      catch (\Throwable $throwable) {
        $this->throwImportFieldException(
          $remote_entity,
          $local_entity,
          $field_info,
          $sync,
          $throwable
        );
      }
    }

    // Update the remote ID field.
    // @I Support not updating the remote ID field
    //    type     : improvement
    //    priority : low
    //    labels   : import
    try {
      $this->setRemoteIdField($remote_entity, $local_entity, $sync);
    }
    catch (\Throwable $throwable) {
      $this->throwImportSyncFieldException(
        $remote_entity,
        $local_entity,
        'remote_id',
        $sync,
        $throwable
      );
    }

    // Update the remote changed field. The remote changed field will be used in
    // `hook_entity_insert` and `hook_entity_update` to prevent triggering an
    // export of the local entity as a result of an import.
    try {
      $this->setRemoteChangedField($remote_entity, $local_entity, $sync);
    }
    catch (\Throwable $throwable) {
      $this->throwImportSyncFieldException(
        $remote_entity,
        $local_entity,
        'remote_changed',
        $sync,
        $throwable
      );
    }
  }

  /**
   * Performs the actual import of a remote field to a local field.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The associated local entity.
   * @param array $field_info
   *   The field info.
   *   See \Drupal\entity_sync\Import\Event\FieldMapping::fieldMapping.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   */
  protected function doImportField(
    object $remote_entity,
    ContentEntityInterface $local_entity,
    array $field_info,
    ImmutableConfig $sync
  ) {
    // If the field value should be converted and stored by a custom callback,
    // then invoke that.
    if (($field_info['import']['callback'] ?? FALSE) !== FALSE) {
      call_user_func(
        $field_info['import']['callback'],
        $remote_entity,
        $local_entity,
        $field_info
      );
    }
    // Else, we assume direct copy of the remote field value into the local
    // field.
    // @I Add more details about the field mapping in the exception message
    //    type     : task
    //    priority : low
    //    labels   : error-handling, import
    // @I Provide the option to continue the entity import when a field fails
    //    type     : improvement
    //    priority : normal
    //    labels   : error-handling, import
    // @I Log warning or throw exception when the remote field does not exist
    //    type     : improvement
    //    priority : normal
    //    labels   : error-handling, import
    // @I Implement log levels e.g. error, warning, info, debug
    //    type     : feature
    //    priority : normal
    //    labels   : error-handling, import
    // @I Support configuration entities
    //    type     : feature
    //    priority : normal
    //    labels   : import
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
    // We  copy the value when the field exists even if it is NULL. When that's
    // the case, that would result any existing field value to be unset - which
    // is legitimate case, a field can be empty.
    // More advanced configuration options will be provided in the future via
    // field mapping import modes that will allow to determine the desired
    // behavior in such cases.
    elseif (property_exists($remote_entity, $field_info['remote_name'])) {
      $local_entity->set(
        $field_info['machine_name'],
        $remote_entity->{$field_info['remote_name']}
      );
    }
  }

  /**
   * Throws a wrapper exception for exceptions thrown during field import.
   *
   * We do this so that we can recognize as a ImportFieldException any exception
   * that may be thrown during field import runtime.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The associated local entity.
   * @param array $field_info
   *   The field info.
   *   See \Drupal\entity_sync\Import\Event\FieldMapping::fieldMapping.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param \Throwable $throwable
   *   The exception that was thrown during field import.
   */
  protected function throwImportFieldException(
    object $remote_entity,
    ContentEntityInterface $local_entity,
    array $field_info,
    ImmutableConfig $sync,
    \Throwable $throwable
  ) {
    $id_field = $sync->get('remote_resource.id_field');
    $entity_text = sprintf(
      'the local entity with ID "%s"',
      $local_entity->id()
    );
    throw new FieldImportException(
      sprintf(
        '"%s" exception was thrown while importing the "%s" field of the remote entity with ID "%s" into the "%s" field of %s. The error message was: %s. The field mapping was: %s',
        get_class($throwable),
        $field_info['remote_name'],
        $remote_entity->{$id_field} ?? '',
        $field_info['machine_name'],
        $local_entity->isNew() ? 'a new local entity' : $entity_text,
        $throwable->getMessage(),
        json_encode($field_info)
      )
    );
  }

  /**
   * Throws a wrapper exception for exceptions thrown during sync field import.
   *
   * We do this so that we can recognize as a ImportFieldException any exception
   * that may be thrown during field import runtime.
   *
   * Sync fields are special fields required by the module to correctly perform
   * its operations e.g. remote ID and remote changed fields.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The associated local entity.
   * @param string $sync_field
   *   The sync field being imported. Supported values are:
   *   - `remote_id` for the remote ID field.
   *   - `remote_changed` for the remote changed field.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param \Throwable $throwable
   *   The exception that was thrown during field import.
   */
  protected function throwImportSyncFieldException(
    object $remote_entity,
    ContentEntityInterface $local_entity,
    $sync_field,
    ImmutableConfig $sync,
    \Throwable $throwable
  ) {
    // We want to reuse the way the field import exceptions are thrown by the
    // `throwImportFieldException` method; the only difference in the arguments
    // is that it expects the field info array in the format provided by the
    // synchronization configuration for standard fields. We therefore prepare
    // that info for the given sync field.
    $field_info = [];
    switch ($sync_field) {
      case 'remote_id':
        $field_info['machine_name'] = $sync->get('entity.remote_id_field');
        $field_info['remote_name'] = $sync->get('remote_resource.id_field');
        break;

      case 'remote_changed':
        $field_info['machine_name'] = $sync->get('entity.remote_changed_field');
        $field_info['remote_name'] = $sync->get('remote_resource.changed_field.name');
        break;

      default:
        throw new \InvalidArgumentException(
          sprintf(
            'The "remote_id" and "remote_changed" sync fields are supported, "%s" given while throwing a field import exception.',
            $sync_field
          )
        );
    }

    $this->throwImportFieldException(
      $remote_entity,
      $local_entity,
      $field_info,
      $sync,
      $throwable
    );
  }

}
