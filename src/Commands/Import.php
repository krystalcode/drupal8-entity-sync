<?php

namespace Drupal\entity_sync\Commands;

use Drupal\entity_sync\Import\ManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Commands related to imports.
 */
class Import extends DrushCommands {

  /**
   * The Entity Sync import entity manager.
   *
   * @var \Drupal\entity_sync\Import\ManagerInterface
   */
  protected $manager;

  /**
   * Constructs a new Import object.
   *
   * @param \Drupal\entity_sync\Import\ManagerInterface $manager
   *   The Entity Sync import manager.
   */
  public function __construct(ManagerInterface $manager) {
    $this->manager = $manager;
  }

  /**
   * Imports a list of remote entities for the given synchronization.
   *
   * @param string $sync_id
   *   The ID of the synchronization for which to run the import.
   * @param array $command_options
   *   (optional) An array of options.
   *
   * @option changed-start
   *   The `changed_start` filter to pass to the import entity manager.
   * @option changed-end
   *   The `changed_end` filter to pass to the import entity manager.
   * @option limit
   *   The `limit` option to pass to the import entity manager.
   * @option parameters
   *   The `client[paramaters]` option to pass to the import entity manager.
   *
   * @usage drush entity-sync:import-remote-list "my_sync_id"
   *   Import the list of remote entities defined in the `import_list` operation
   *   of the `my_sync_id` synchronization.
   * @usage drush entity-sync:import-remote-list "my_sync_id" --limit="10"
   *   Import a list of maximum 10 remote entities defined in the `import_list`
   *   operation of the `my_sync_id` synchronization.
   * @usage drush entity-sync:import-remote-list "my_sync_id" --parameters="key1=value1,key2=value2"
   *   Import a list of remote entities defined in the `import_list` operation
   *   of the `my_sync_id` synchronization, passing custom parameters to the
   *   client that will be fetching the entities from the remote resource.
   * @usage drush entity-sync:import-remote-list "my_sync_id" --changed-start="2020-05-22T00:00:00Z" --changed-end="2020-05-27T23:59:59Z"
   *   Import a list of remote entities defined in the `import_list` operation
   *   of the `my_sync_id` synchronization; limit the entities to be imported to
   *   those changed between the given times. The times need to be given in the
   *   format expected by the client that will be fetching the entities from the
   *   remote resource.
   *
   * @command entity-sync:import-remote-list
   *
   * @aliases esync-irl
   *
   * @I Add created start/end filters when supported
   *    type     : improvement
   *    priority : low
   *    labels   : drush
   */
  public function importRemoteList(
    $sync_id,
    array $command_options = [
      'changed-start' => NULL,
      'changed-end' => NULL,
      'limit' => NULL,
      'parameters' => NULL,
    ]
  ) {
    $filters = [];
    if (isset($command_options['changed-start'])) {
      $filters['changed_start'] = $command_options['changed-start'];
    }
    if (isset($command_options['changed-end'])) {
      $filters['changed_end'] = $command_options['changed-end'];
    }

    // Prepare the options.
    $import_options = [];
    if (isset($command_options['limit'])) {
      $import_options['limit'] = (int) $command_options['limit'];
    }
    if (isset($command_options['parameters'])) {
      $import_options['client'] = [
        'parameters' => $this->keyValueStringToArray(
          $command_options['parameters']
        ),
      ];
    }

    $this->manager->importRemoteList(
      $sync_id,
      $filters,
      $import_options
    );
  }

  /**
   * Converts a key/value string to an array.
   *
   * @param string $input
   *   The input string containing the key value pairs.
   * @param string $delimiter
   *   The delimiter separating the key value pairs.
   * @param string $key_value_separator
   *   The separator that separates the key from a value in a key value pair.
   *
   * @return array
   *   An associative array containing the key value pairs.
   *
   * @I Move to a utility class and test
   *    type     : task
   *    priority : low
   *    labels   : refactoring, testing
   */
  protected function keyValueStringToArray(
    string $input,
    string $delimiter = ',',
    string $key_value_separator = '='
  ) : array {
    if (empty($input)) {
      return [];
    }

    $array = [];
    $parts = explode($delimiter, $input);
    foreach ($parts as $part) {
      $key_value_parts = explode($key_value_separator, $part);
      $array[$key_value_parts[0]] = $key_value_parts[1] ?? NULL;
    }

    return $array;
  }

}
