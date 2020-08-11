<?php

namespace Drupal\Tests\entity_sync\Unit\Export;

use Drupal\entity_sync\Config\ManagerInterface as ConfigManagerInterface;
use Drupal\entity_sync\Export\FieldManager;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\entity_sync\TestTrait\FixturesTrait;
use Drupal\Tests\UnitTestCase;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Prophecy\Argument;

/**
 * @I Use autoloading for loading test classes
 *    type     : task
 *    priority : low
 *    labels   : testing
 */
require_once __DIR__ . '/../../TestTrait/FixturesTrait.php';

/**
 * @coversDefaultClass \Drupal\entity_sync\Export\FieldManager
 *
 * @group contrib
 * @group entity_sync
 * @group unit
 */
class FieldManagerGetNamesTest extends UnitTestCase {

  use FixturesTrait;

  /**
   * Data provider; prepares all combinations of data from other providers.
   *
   * @I Support loading fixtures in the data provider trait
   *    type     : task
   *    priority : low
   *    labels   : testing
   */
  public function dataProvider() {
    return array_map(
      function ($case) {
        return [
          $case['names_filter'],
          $case['are_changed_fields_known'],
          $case['changed_names'],
          $case['expected_names'],
        ];
      },
      $this->getFixtureAllData()
    );
  }

  /**
   *
   * @covers ::getExportableChangedNames
   * @dataProvider dataProvider
   */
  public function testExport(
    $names_filter,
    $are_changed_field_names_known,
    $changed_names,
    $expected_names
  ) {
    // Mock services required for instantiating the export field manager.
    $config_manager = $this->prophesizeConfigManager(
      $are_changed_field_names_known,
      $names_filter,
      $changed_names
    );
    $logger = $this->prophesize(LoggerInterface::class);
    $event_dispatcher = $this->prophesize(EventDispatcherInterface::class);

    // Mock the synchronization configuration.
    $field_mapping = $this->getFixtureDataProperty(
      'field_mapping',
      'field_mapping__with_defaults',
      'get_names_syncs'
    );

    $changed_entity = $this->prophesizeChangedEntity(
      $are_changed_field_names_known,
      $names_filter,
      $changed_names
    );
    $original_entity = $this->prophesizeOriginalEntity(
      $are_changed_field_names_known,
      $names_filter,
      $changed_names
    );

    // Run!
    $manager = new FieldManager(
      $config_manager->reveal(),
      $event_dispatcher->reveal(),
      $logger->reveal()
    );
    $exportable_names = $manager->getExportableChangedNames(
      $changed_entity->reveal(),
      $original_entity->reveal(),
      $field_mapping,
      $names_filter,
      $are_changed_field_names_known ? $changed_names : NULL
    );

    $this->assertEquals($expected_names, $exportable_names);
  }

  /**
   * Prepare the Entity Sync configuration manager mock object.
   */
  private function prophesizeConfigManager(
    $are_changed_field_names_known,
    $names_filter,
    array $changed_names
  ) {
    $config_manager = $this->prophesize(ConfigManagerInterface::class);

    if ($names_filter === []) {
      return $config_manager;
    }
    if ($are_changed_field_names_known && $changed_names === []) {
      return $config_manager;
    }

    $field_mapping = $this->getFixtureDataProperty(
      'field_mapping',
      'field_mapping__with_defaults',
      'get_names_syncs'
    );
    $complete_field_mapping = $this->getFixtureDataProperty(
      'field_mapping',
      'field_mapping__complete',
      'get_names_syncs'
    );
    foreach ($field_mapping as $index => $field_info) {
      $config_manager
        ->mergeExportFieldMappingDefaults($field_info)
        ->willReturn($complete_field_mapping[$index])
        ->shouldBeCalledOnce();
    }

    return $config_manager;
  }

  /**
   * Prepare the changed entity mock object.
   */
  private function prophesizeChangedEntity(
    $are_changed_field_names_known,
    $names_filter,
    array $changed_names
  ) {
    $changed_entity = $this->prophesize(ContentEntityInterface::class);

    if ($are_changed_field_names_known) {
      return $changed_entity;
    }
    if ($names_filter === []) {
      return $changed_entity;
    }

    $all_field_names = [
      'field_enabled_1',
      'field_enabled_2',
      'field_enabled_3',
      'field_enabled_4',
      'field_disabled_1',
      'field_disabled_2',
    ];

    $fields = [];
    foreach ($all_field_names as $field_name) {
      $fields[$field_name] = $this->prophesize(FieldItemListInterface::class);

      if ($names_filter === NULL || in_array($field_name, $names_filter)) {
        // In the test, the method is always called with an empty string as the
        // argument. See `prophesizeOriginalEntity`.
        $fields[$field_name]
          ->equals(Argument::any())
          ->willReturn(!in_array($field_name, $changed_names))
          ->shouldBeCalledOnce();
      }

      $fields[$field_name]->reveal();
    }

    $changed_entity
      ->getFields()
      ->willReturn($fields)
      ->shouldBeCalledOnce();

    return $changed_entity;
  }

  /**
   * Prepare the original entity mock object.
   */
  private function prophesizeOriginalEntity(
    $are_changed_field_names_known,
    $names_filter,
    array $changed_names
  ) {
    $original_entity = $this->prophesize(ContentEntityInterface::class);

    if ($are_changed_field_names_known) {
      return $original_entity;
    }
    if ($names_filter === []) {
      return $original_entity;
    }

    $all_field_names = [
      'field_enabled_1',
      'field_enabled_2',
      'field_enabled_3',
      'field_enabled_4',
      'field_disabled_1',
      'field_disabled_2',
    ];

    foreach ($all_field_names as $field_name) {
      if ($names_filter !== NULL && !in_array($field_name, $names_filter)) {
        continue;
      }

      // We always return an empty string as we don't care about the returned
      // value. What matters is the result of the call to the `equals` method
      // and that's set separately based on the expectation defined by the data
      // provider.
      $original_entity
        ->get($field_name)
        ->willReturn($this->prophesize(FieldItemListInterface::class))
        ->shouldBeCalledOnce();
    }

    return $original_entity;
  }

  /**
   * Other helper methods.
   */

  /**
   * The default fixture ID for the tests in this file.
   */
  private function defaultFixtureId() {
    return 'get_exportable_changed_names_data_provider';
  }

  /**
   * The default fixtures directory for the tests in this file.
   */
  private function defaultFixturesDirectory() {
    return __DIR__ . '/../../../fixtures/Export/FieldManager';
  }

}
