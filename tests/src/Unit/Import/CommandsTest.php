<?php

namespace Drupal\Tests\entity_sync\Unit\Import;

use Drupal\entity_sync\Commands\Import as Command;
use Drupal\entity_sync\Import\ManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\entity_sync\Commands\Import
 *
 * @group contrib
 * @group entity_sync
 * @group unit
 */
class CommandsTest extends UnitTestCase {

  /**
   * Different values for the datetime filters.
   *
   * They will be strings as they are passed from the command line.
   */
  private $datetimeValues = [
    'string_start' => '2020-05-22T00:00:00Z',
    'string_end' => '2020-05-27T23:59:59Z',
    'invalid' => '---',
  ];

  /**
   * We want to test different of valid or invalid arguments and options.
   */
  public function dataProvider() {
    return [
      // No command options.
      // We are not testing for no arguments as that should be guaranteed by
      // Drush, and because here it seems that Drush/PHP will not throw an error
      // if we pass NULL for the arguments so there's no way to test that case
      // really.
      [
        'sync_id' => 'user',
        'command_options' => [],
        'import_filters' => [],
        'import_options' => [],
      ],
      // Complete set of arguments and options.
      [
        'sync_id' => 'user',
        'command_options' => [
          'changed-start' => $this->datetimeValues['string_start'],
          'changed-end' => $this->datetimeValues['string_end'],
          'limit' => '10',
          'parameters' => 'key1=value1,key2=value2',
        ],
        'import_filters' => [
          'changed_start' => $this->datetimeValues['string_start'],
          'changed_end' => $this->datetimeValues['string_end'],
        ],
        'import_options' => [
          'limit' => 10,
          'client' => [
            'parameters' => [
              'key1' => 'value1',
              'key2' => 'value2',
            ],
          ],
        ],
      ],
      // Only import filters.
      [
        'sync_id' => 'user',
        'command_options' => [
          'changed-start' => $this->datetimeValues['string_start'],
          'changed-end' => $this->datetimeValues['string_end'],
        ],
        'import_filters' => [
          'changed_start' => $this->datetimeValues['string_start'],
          'changed_end' => $this->datetimeValues['string_end'],
        ],
        'import_options' => [],
      ],
      // Only import options.
      [
        'sync_id' => 'user',
        'command_options' => [
          'limit' => '10',
          'parameters' => 'key1=value1,key2=value2',
        ],
        'import_filters' => [],
        'import_options' => [
          'limit' => 10,
          'client' => [
            'parameters' => [
              'key1' => 'value1',
              'key2' => 'value2',
            ],
          ],
        ],
      ],
      // Provide invalid datetime filters.
      // Make sure the filters are passed as they are without any validation.
      [
        'sync_id' => 'user',
        'command_options' => [
          'changed-start' => $this->datetimeValues['invalid'],
          'changed-end' => $this->datetimeValues['invalid'],
        ],
        'import_filters' => [
          'changed_start' => $this->datetimeValues['invalid'],
          'changed_end' => $this->datetimeValues['invalid'],
        ],
        'import_options' => [],
      ],
    ];
  }

  /**
   * Tests that the import is called with the data properly formatted.
   *
   * @covers ::importRemoteList
   * @dataProvider dataProvider
   */
  public function testImportRemoteList(
    $sync_id,
    $command_options,
    $import_filters,
    $import_options
  ) {
    $manager = $this->prophesizeImportRemoteListEntityManager(
      $sync_id,
      $import_filters,
      $import_options
    );
    $command = new Command($manager->reveal());
    $command->importRemoteList($sync_id, $command_options);
  }

  /**
   * Tests that the import is called with the correct arguments.
   *
   * @covers ::importRemoteEntity
   */
  public function testImportRemoteEntity() {
    $sync_id = 'user';
    $remote_entity_id = 1;

    $manager = $this->prophesizeImportRemoteEntityEntityManager(
      $sync_id,
      $remote_entity_id
    );
    $command = new Command($manager->reveal());
    $command->importRemoteEntity($sync_id, $remote_entity_id);
  }

  /**
   * Prepare the import entity manager mock object with the right expectations.
   */
  private function prophesizeImportRemoteListEntityManager(
    $sync_id,
    $filters,
    $options
  ) {
    $manager = $this->prophesize(ManagerInterface::class);
    $manager
      ->importRemoteList($sync_id, $filters, $options)
      ->shouldBeCalledOnce();

    return $manager;
  }

  /**
   * Prepare the import entity manager mock object with the right expectations.
   */
  private function prophesizeImportRemoteEntityEntityManager(
    $sync_id,
    $remote_entity_id
  ) {
    $manager = $this->prophesize(ManagerInterface::class);
    $manager
      ->importRemoteEntityById($sync_id, $remote_entity_id)
      ->shouldBeCalledOnce();

    return $manager;
  }

}
