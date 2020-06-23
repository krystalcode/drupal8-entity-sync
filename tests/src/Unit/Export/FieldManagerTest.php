<?php

namespace Drupal\Tests\entity_sync\Unit\Export;

use Drupal\entity_sync\Config\ManagerInterface as ConfigManagerInterface;
use Drupal\entity_sync\Exception\FieldExportException;
use Drupal\entity_sync\Export\Event\Events;
use Drupal\entity_sync\Export\Event\FieldMappingEvent;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\entity_sync\TestTrait\DataProviderTrait;
use Drupal\Tests\entity_sync\TestTrait\FixturesTrait;
use Drupal\Tests\entity_sync\Exception\TestRuntimeException;
use Drupal\Tests\entity_sync\Mock\Export\FieldManager;
use Drupal\Tests\UnitTestCase;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Prophecy\Argument;

/**
 * @I Use autoloading for loading test classes
 *    type     : task
 *    priority : low
 *    labels   : testing
 */
require_once __DIR__ . '/../../TestTrait/DataProviderTrait.php';
require_once __DIR__ . '/../../TestTrait/FixturesTrait.php';
require_once __DIR__ . '/../../Exception/TestRuntimeException.php';
require_once __DIR__ . '/../../Mock/Export/FieldManager.php';

/**
 * @coversDefaultClass \Drupal\entity_sync\Export\FieldManager
 *
 * @group contrib
 * @group entity_sync
 * @group unit
 */
class FieldManagerTest extends UnitTestCase {

  use DataProviderTrait;
  use FixturesTrait;

  use \phpmock\phpunit\PHPMock;

  /**
   * Holds the return value of the field manager for each test case.
   *
   * @var array
   */
  private $expectedFields;

  /**
   * Reset the expected return value of the field manager export.
   *
   * We set the expected value where appropriate in the branching methods.
   */
  public function setUp() {
    $this->expectedFields = [];
  }

  /**
   * Data provider; prepares all combinations of data from other providers.
   *
   * @I Add option data provider and related tests
   *    type     : task
   *    priority : low
   *    labels   : export, testing
   */
  public function dataProvider() {
    $providers = [
      'syncCaseDataProvider',
      'eventFieldMappingDataProvider',
      'remoteEntityIdDataProvider',
      'localEntityDataProvider',
      'localEntityFieldDataProvider',
      'fieldExportSuccessDataProvider',
    ];

    return $this->combineDataProviders($providers);
  }

  /**
   * This is the main test function. It is provided with all possible
   * combinations of data and configuration so that we test all possibilities
   * that would otherwise be extremely difficul to structure and test.
   *
   * From within this main test function, we start a tree of branching methods
   * in order to test the different scenarios of what happens for each data
   * combination. All branching functions start with the `branch` prefix.
   *
   * @covers ::export
   * @dataProvider dataProvider
   */
  public function testExport(
    $sync_case,
    $event_field_mapping,
    $remote_entity_id,
    $local_entity_class,
    $local_entity_field,
    $field_export_success
  ) {
    // Mock services required for instantiating the export manager.
    $logger = $this->prophesize(LoggerInterface::class);
    $config_manager = $this->prophesize(ConfigManagerInterface::class);

    $event_dispatcher = $this->buildEventDispatcher($event_field_mapping);

    // Mock the synchronization configuration.
    $sync = $this->prophesize(ImmutableConfig::class);

    // Mock the local entity. For some reason when the class is prophesized
    // within the data provider the assertions fail to register. We therefore
    // provide the class (config or content entity) and we prophesize here.
    $local_entity = $this->prophesize($local_entity_class);

    // Prepare the test case context as an array so we can easily pass it around
    // to branching methods.
    $test_context = [
      'logger' => $logger,
      'config_manager' => $config_manager,
      'sync' => $sync,
      'sync_case' => $sync_case,
      'event_field_mapping' => $event_field_mapping,
      'remote_entity_id' => $remote_entity_id,
      'local_entity' => $local_entity,
      'local_entity_field' => $local_entity_field,
      'field_export_success' => $field_export_success,
    ];

    // Start branching so that we register any additional expectations on the
    // mock objects depending on the scenario provided by the current data
    // combination (test context).
    $this->branchEmptyFieldMapping($test_context);
    $this->branchFieldMapping($test_context);

    // In all cases we expect the entity to not be saved.
    $local_entity
      ->save()
      ->shouldNotBeCalled();

    // Run!
    $manager = new FieldManager(
      $config_manager->reveal(),
      $event_dispatcher,
      $logger->reveal()
    );
    $fields = $manager->export(
      $local_entity->reveal(),
      $remote_entity_id,
      $sync->reveal()
    );

    $this->assertEquals($this->expectedFields, $fields);
  }

  /**
   * Data providers.
   */

  /**
   * Data provider for the synchronization configurations.
   */
  private function syncCaseDataProvider() {
    return [
      // Complete and valid configuration.
      'complete',
    ];
  }

  /**
   * Data provider for the field mapping set via events subscribers.
   *
   * @I Add tests for invalid field mapping data
   *    type     : task
   *    priority : normal
   *    labels   : testing
   */
  private function eventFieldMappingDataProvider() {
    // Make sure that there are no same machine/remote field names on the same
    // data set; we expect certain methods to be called only once for each field
    // and having duplicate field mapping definitions would make the tests fail.
    // It is always possible that the event subscriber returns field mapping
    // that includes duplicate entries for a field. Such cases should be tested
    // when we add tests for invalid field mapping data and add validation in
    // the program's code as well.
    //
    // The `test` array item is not part of the field mapping; we use it to pass
    // data for test purposes such as the expected result in case of a
    // successfull export.
    return [
      // No fields.
      [],
      // Some fields.
      // A field that does and a field that does not require special mapping.
      [
        [
          'machine_name' => 'mail',
          'remote_name' => 'email',
          'test' => [
            'value' => 'john.doe@example.com',
            'expected_value' => 'john.doe@example.com',
          ],
        ],
        [
          'machine_name' => 'name',
          'remote_name' => 'name',
          // The callback method does not need to actually exist; we will just
          // be testing that the `call_user_func` function is called with the
          // right arguments.
          'export' => [
            'callback' => '\Drupal\Tests\entity_sync\Unit\Export\FieldManagerTest::fieldCallback',
          ],
          'test' => [
            'expected_value' => 'John Doe',
          ],
        ],
      ],
      // An empty field.
      [
        [
          'machine_name' => 'mail',
          'remote_name' => 'email',
          'test' => [
            'value' => NULL,
            'expected_value' => NULL,
          ],
        ],
      ],
      // A multi-value field.
      [
        [
          'machine_name' => 'mail',
          'remote_name' => 'email',
          'test' => [
            'value' => [
              'john.doe@example.com',
              'mary.smith@example.com'
            ],
            'expected_value' => [
              'john.doe@example.com',
              'mary.smith@example.com'
            ],
          ],
        ],
      ],
      // Fields that are configured to not be exported.
      [
        [
          'machine_name' => 'mail',
          'remote_name' => 'email',
          'export' => [
            'status' => FALSE,
          ],
        ],
        [
          'machine_name' => 'name',
          'remote_name' => 'name',
          // The callback method does not need to actually exist; we will just
          // be testing that the `call_user_func` function is called with the
          // right arguments.
          'export' => [
            'status' => FALSE,
            'callback' => '\Drupal\Tests\entity_sync\Unit\Export\FieldManagerTest::fieldCallback',
          ],
        ],
      ],
    ];
  }

  /**
   * Data provider for the ID of the remote entity being created/updated.
   *
   * @I Add tests for invalid remote entity ID
   *    type     : task
   *    priority : normal
   *    labels   : testing
   */
  private function remoteEntityIdDataProvider() {
    return [
      // Integer ID.
      1,
      // String ID.
      'X-1',
      // NULL, valid when creating a new entity.
      NULL,
    ];
  }

  /**
   * Data provider for the local entity.
   */
  private function localEntityDataProvider() {
    return [
      // Content entity.
      ContentEntityInterface::class,
    ];
  }

  /**
   * Data provider for the local entity fields.
   */
  private function localEntityFieldDataProvider() {
    return [
      // The field does exist in the local entity.
      TRUE,
      // The field does not exist in the local entity.
      FALSE,
    ];
  }

  /**
   * Data provider for the success of the entity field export.
   *
   * @I Find an easy way to test one field failing while others suceeding
   *    type     : task
   *    priority : normal
   *    labels   : testing
   */
  private function fieldExportSuccessDataProvider() {
    return [
      // The field value was successfully exported.
      TRUE,
      // An error occurred while exporting the field value.
      FALSE,
    ];
  }

  /**
   * Branching methods.
   */

  /**
   * The final field mapping is empty.
   */
  private function branchEmptyFieldMapping(array $test_context) {
    if ($test_context['event_field_mapping']) {
      return;
    }

    // We expect the manager to return an empty array - no fields to be
    // exported.
    $this->expectedFields = [];

    // We expect no value to be  directly or via a callback.
    $test_context['local_entity']
      ->get(Argument::any())
      ->shouldNotBeCalled();

    $call_user_func = $this->getFunctionMock(
      '\Drupal\entity_sync\Export',
      'call_user_func'
    );
    $call_user_func
      ->expects($this->never());
  }

  /**
   * The final field mapping contains fields to be exported.
   */
  private function branchFieldMapping(array $test_context) {
    if (!$test_context['event_field_mapping']) {
      return;
    }

    // We want to test whether errors that may happen during exporting fields
    // are properly handled. These errors are not necessarily thrown by our
    // class though; they may be thrown by the export callback. We therefore
    // simulate such scenarios by throwing and catching an exception within the
    // test.
    $errors = FALSE;

    foreach ($test_context['event_field_mapping'] as $field_info) {
      $this->expectFieldMappingMerge($test_context, $field_info);

      try {
        $this->branchFieldMappingDisabled($test_context, $field_info);
        $this->branchFieldMappingWithCallback($test_context, $field_info);
        $this->branchFieldMappingWithoutCallback($test_context, $field_info);
      }
      catch (TestRuntimeException $e) {
        $errors = TRUE;
        break;
      }
    }

    $this->branchFieldMappingWithErrors($test_context, $errors);
    $this->branchFieldMappingWithoutErrors($test_context, $errors);
  }

  /**
   * The field mapping is disabled.
   */
  private function branchFieldMappingDisabled(
    array $test_context,
    array $field_info
  ) {
    if (($field_info['export']['status'] ?? TRUE) !== FALSE) {
      return;
    }

    // We expect to not proceed with getting the value of this field neither
    // from the entity nor via calling a callback.
    $test_context['local_entity']
      ->get($field_info['machine_name'])
      ->shouldNotBeCalled();

    if (($field_info['export']['callback'] ?? FALSE) === FALSE) {
      return;
    }

    $call_user_func = $this->getFunctionMock(
      '\Drupal\entity_sync\Export',
      'call_user_func'
    );
    $call_user_func
      ->expects($this->never())
      ->with(
        $this->equalTo($field_info['export']['callback']),
        $this->any(),
        $this->any(),
        $this->equalTo($field_info)
      );
  }

  /**
   * The field mapping is done by a callback.
   */
  private function branchFieldMappingWithCallback(
    array $test_context,
    array $field_info
  ) {
    if (($field_info['export']['status'] ?? TRUE) !== TRUE) {
      return;
    }
    if (($field_info['export']['callback'] ?? FALSE) === FALSE) {
      return;
    }

    $this->branchFieldMappingWithCallbackExportSuccess(
      $test_context,
      $field_info
    );
    $this->branchFieldMappingWithCallbackExportError(
      $test_context,
      $field_info
    );
  }

  /**
   * The field mapping is done by a callback and the export is successful.
   */
  private function branchFieldMappingWithCallbackExportSuccess(
    array $test_context,
    array $field_info
  ) {
    if (!$test_context['field_export_success']) {
      return;
    }

    // We expect the callback function to be successfully called.
    $this->mockCallUserFunc($test_context, $field_info);
  }

  /**
   * The field mapping is done by a callback and the export fails.
   */
  private function branchFieldMappingWithCallbackExportError(
    array $test_context,
    array $field_info
  ) {
    if ($test_context['field_export_success']) {
      return;
    }

    // We expect the callback function to be called but to fail, throwing an
    // exception.
    $this->mockCallUserFunc($test_context, $field_info)
      ->will($this->throwException(new \Exception('Exception message')));

    $this->expectExportFieldException($test_context);

    throw new TestRuntimeException();
  }

  /**
   * The field mapping is done by the default value copy.
   */
  private function branchFieldMappingWithoutCallback(
    array $test_context,
    array $field_info
  ) {
    if (($field_info['export']['status'] ?? TRUE) !== TRUE) {
      return;
    }
    if (($field_info['export']['callback'] ?? FALSE) !== FALSE) {
      return;
    }

    // Unlike the import field manager, here we don't have a remote entity to
    // check whether the field exists. We only have the cases defined by whether
    // the field exists or not on the local entity.
    $this->branchFieldMappingWithoutCallbackFieldDoesNotExist(
      $test_context,
      $field_info
    );
    $this->branchFieldMappingWithoutCallbackFieldExists(
      $test_context,
      $field_info
    );
  }

  /**
   * The field does not exist on the local entity.
   */
  private function branchFieldMappingWithoutCallbackFieldDoesNotExist(
    array $test_context,
    array $field_info
  ) {
    if ($test_context['local_entity_field']) {
      return;
    }

    // We expect the method that gets the value from the local entity to not be
    // called and an exception to be thrown.
    $test_context['local_entity']
      ->hasField($field_info['machine_name'])
      ->willReturn(FALSE)
      ->shouldBeCalledTimes(1);
    $test_context['local_entity']
      ->get($field_info['machine_name'], Argument::any())
      ->shouldNotBeCalled();

    $this->expectExportFieldException($test_context);

    throw new TestRuntimeException();
  }

  /**
   * The field exists on the local entity.
   */
  private function branchFieldMappingWithoutCallbackFieldExists(
    array $test_context,
    array $field_info
  ) {
    if (!$test_context['local_entity_field']) {
      return;
    }

    $test_context['local_entity']
      ->hasField($field_info['machine_name'])
      ->willReturn(TRUE)
      ->shouldBeCalledTimes(1);

    $this->branchFieldMappingWithoutCallbackExportSuccess(
      $test_context,
      $field_info
    );
    $this->branchFieldMappingWithoutCallbackExportError(
      $test_context,
      $field_info
    );
  }

  /**
   * The field mapping is done without a callback and the export is successful.
   */
  private function branchFieldMappingWithoutCallbackExportSuccess(
    array $test_context,
    array $field_info
  ) {
    if (!$test_context['field_export_success']) {
      return;
    }

    $field = NULL;
    if (isset($field_info['test']['value'])) {
      $field = $this->prophesizeFieldItemList(
        FALSE,
        $field_info['test']['value']
      );
    }
    else {
      $field = $this->prophesizeFieldItemList(TRUE);
    }

    $test_context['local_entity']
      ->get($field_info['machine_name'])
      ->willReturn($field->reveal())
      ->shouldBeCalledTimes(1);
  }

  /**
   * The field mapping is done without a callback and the export fails.
   *
   * That includes  any error that could be thrown by the entity or PHP while
   * getting the value from the local entity field.
   */
  private function branchFieldMappingWithoutCallbackExportError(
    array $test_context,
    array $field_info
  ) {
    if ($test_context['field_export_success']) {
      return;
    }

    // The error/exception could be thrown by various methods. It's fairly safe
    // to simulate throwing it by one method as it's rather tedious to test all
    // possible scenarios; if that is caught any other throwable should be
    // caught as well.
    $test_context['local_entity']
      ->get($field_info['machine_name'])
      ->willThrow(new \Exception('Exception message'))
      ->shouldBeCalledTimes(1);

    $this->expectExportFieldException($test_context);

    throw new TestRuntimeException();
  }

  /**
   * At least one of the fields fails exporting.
   */
  private function branchFieldMappingWithErrors(
    array $test_context,
    $errors
  ) {
    if (!$errors) {
      return;
    }

    // We expect nothing to happen as an exception will be thrown and the
    // program will stop.
  }

  /**
   * All fields are imported successfully.
   */
  private function branchFieldMappingWithoutErrors(
    array $test_context,
    $errors
  ) {
    if ($errors) {
      return;
    }

    // We expect the return value to be an array of all expected field values -
    // excluding fields for which export is disabled.
    foreach ($test_context['event_field_mapping'] as $field_info) {
      if (($field_info['export']['status'] ?? TRUE) === FALSE) {
        continue;
      }

      $this->expectedFields[$field_info['remote_name']] = $field_info['test']['expected_value'];
    }
  }

  /**
   * Helper methods that register expectations.
   */

  /**
   * A `FieldExportException` thrown when exporting any field.
   */
  private function expectExportFieldException(array $test_context) {
    $test_context['local_entity']
      ->isNew()
      ->willReturn(FALSE)
      ->shouldBeCalledTimes(1);
    $test_context['local_entity']
      ->id()
      ->shouldBeCalledTimes(1);

    $this->expectException(FieldExportException::class);
  }

  /**
   * Helper methods for preparing objects and mock objects.
   */

  /**
   * Builds the event subscriber for the `FIELD_MAPPING` event.
   */
  private function buildFieldMappingEventSubscriber($field_mapping) {
    return new class($field_mapping) implements EventSubscriberInterface {

      private $fieldMapping;

      public function __construct(array $field_mapping) {
        $this->fieldMapping = $field_mapping;
      }

      public static function getSubscribedEvents() {
        return [Events::FIELD_MAPPING => 'callback'];
      }

      public function callback(FieldMappingEvent $event) {
        $event->setFieldMapping($this->fieldMapping);
      }

    };
  }

  /**
   * Prepare the event dispatcher object.
   *
   * This is not a mock object. We register real event subscribers that will be
   * setting the given values.
   */
  private function buildEventDispatcher(array $event_field_mapping) {
    // Remove array item added to field mapping items for testing purposes.
    foreach ($event_field_mapping as &$field_info) {
      unset($field_info['test']);
    }

    $event_dispatcher = new EventDispatcher();
    $event_dispatcher->addSubscriber(
      $this->buildFieldMappingEventSubscriber($event_field_mapping)
    );

    return $event_dispatcher;
  }

  /**
   * Prepare a field item list mock object.
   */
  private function prophesizeFieldItemList($empty, $value = NULL) {
    $field = $this->prophesize(FieldItemListInterface::class);
    $field
      ->isEmpty()
      ->willReturn($empty)
      ->shouldBeCalledTimes(1);

    if ($empty) {
      return $field;
    }

    if ($value === []) {
      $field
        ->filterEmptyItems()
        ->willReturn([])
        ->shouldBeCalledTimes(1);
    }
    elseif (is_string($value)) {
      $this->expectCardinality($field, 1);

      $field_item = $this->prophesizeFieldItem($value);
      $field
        ->filterEmptyItems()
        ->willReturn([$field_item->reveal()])
        ->shouldBeCalledTimes(1);
    }
    elseif (is_array($value)) {
      $this->expectCardinality($field, count($value));

      $field_items = [];
      foreach ($value as $field_item_value) {
        $field_items[] = $this->prophesizeFieldItem($field_item_value)->reveal();
      }
      $field
        ->filterEmptyItems()
        ->willReturn($field_items)
        ->shouldBeCalledTimes(1);
    }
    else {
      throw new \InvalidArgumentException(
        'A string or an array must be given as the test values of a field.'
      );
    }

    return $field;
  }

  /**
   * Prepare a field item mock object.
   */
  private function prophesizeFieldItem($value) {
    $field_item = $this->prophesize(FieldItemInterface::class);
    $field_item
      ->getValue()
      ->willReturn(['main-property-name' => $value])
      ->shouldBeCalledTimes(1);

    return $field_item;
  }

  /**
   * Mock the methods calls on a field item list for getting its cardinality.
   */
  private function expectCardinality($field, $cardinality) {
    $field_storage_definition = $this->prophesize(FieldStorageDefinitionInterface::class);
    $field_storage_definition
      ->getCardinality()
      ->willReturn($cardinality)
      ->shouldBeCalledTimes(1);

    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition
      ->getFieldStorageDefinition()
      ->willReturn($field_storage_definition->reveal());

    $field
      ->getFieldDefinition()
      ->willReturn($field_definition->reveal())
      ->shouldBeCalledTimes(1);
  }

  /**
   * Mocks the method call for merging in the default export field mapping.
   */
  private function expectFieldMappingMerge($test_context, array $field_info) {
    unset($field_info['test']);
    $merged_field_info = NestedArray::mergeDeep(
      [
        'export' => [
          'status' => TRUE,
          'callback' => FALSE,
        ],
      ],
      $field_info
    );

    $test_context['config_manager']
      ->mergeExportFieldMappingDefaults($field_info)
      ->willReturn($merged_field_info)
      ->shouldBeCalledTimes(1);
  }

  /**
   * Mock `call_user_func`.
   *
   * @I Use `php-mock/php-mock-prophecy` for mocking global functions
   *    type     : task
   *    priority : low
   *    labels   : testing
   */
  private function mockCallUserFunc(array $test_context, array $field_info) {
    $call_user_func = $this->getFunctionMock(
      '\Drupal\entity_sync\Export',
      'call_user_func'
    );

    // Remove array item added to field mapping items for testing purposes.
    $expected_value = $field_info['test']['expected_value'];
    unset($field_info['test']);

    // Expect the field mapping defaults to be set if the corresponding values
    // are not set.
    if (!isset($field_info['export'])) {
      $field_info['export'] = [];
    }
    if (!isset($field_info['export']['status'])) {
      $field_info['export']['status'] = TRUE;
    }
    if (!isset($field_info['export']['callback'])) {
      $field_info['export']['callback'] = FALSE;
    }

    $local_entity = $test_context['local_entity'];
    return $call_user_func
      ->expects($this->once())
      ->with(
        $this->equalTo($field_info['export']['callback']),
        // The `identicalTo` parameter matcher seems to fail when the parameters
        // are mock objects. We write a custom callback that compares the
        // objects' hashes.
        $this->callback(
          function (ContentEntityInterface $subject) use ($local_entity) {
            if (!is_object($subject)) {
              return FALSE;
            }

            $local_entity_hash = spl_object_hash($local_entity->reveal());
            return $local_entity_hash === spl_object_hash($subject);
          }
        ),
        $this->equalTo($test_context['remote_entity_id']),
        $this->equalTo($field_info)
      )
      ->will($this->returnValue($expected_value));
  }

  /**
   * Other helper methods.
   */

  /**
   * The default fixture ID for the tests in this file.
   */
  private function defaultFixtureId() {
    return 'entity_sync.sync.user';
  }

}
