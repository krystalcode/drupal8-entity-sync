<?php

namespace Drupal\Tests\entity_sync\Unit\Import;

use Drupal\entity_sync\Exception\FieldImportException;
use Drupal\entity_sync\Import\FieldManager;
use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\FieldMappingEvent;


use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\entity_sync\TestTrait\DataProviderTrait;
use Drupal\Tests\entity_sync\TestTrait\FixturesTrait;
use Drupal\Tests\entity_sync\Exception\TestRuntimeException;
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

/**
 * @coversDefaultClass \Drupal\entity_sync\Import\FieldManager
 * @group entity_sync
 */
class FieldManagerTest extends UnitTestCase {

  use DataProviderTrait;
  use FixturesTrait;

  use \phpmock\phpunit\PHPMock;

  /**
   * Different values for the remote changed field.
   *
   * @I Consider moving remote changed field values to fixtures
   *    type     : task
   *    priority : low
   *    labels   : testing
   */
  private $remoteChangedFieldValues = [
    'timestamp_int' => 1010101010,
    'timestamp_string' => '1010101010',
    'string' => '2020-05-12T05:16:54Z',
    'invalid' => '---',
  ];

  /**
   * Data provider; prepares all combinations of data from other providers.
   *
   * @I Add option data provider and related tests
   *    type     : task
   *    priority : low
   *    labels   : testing
   */
  public function dataProvider() {
    $providers = [
      'syncCaseDataProvider',
      'eventFieldMappingDataProvider',
      'remoteEntityDataProvider',
      'localEntityDataProvider',
      'localEntityFieldDataProvider',
      'fieldImportSuccessDataProvider',
      'remoteIdFieldImportSuccessDataProvider',
      'remoteChangedFieldImportSuccessDataProvider',
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
   * @test
   * @dataProvider dataProvider
   */
  public function testImport(
    $sync_case,
    $event_field_mapping,
    $remote_entity,
    $local_entity_class,
    $local_entity_field,
    $field_import_success,
    $remote_id_field_import_success,
    $remote_changed_field_import_success
  ) {
    // Mock services required for instantiating the import manager.
    $logger = $this->prophesize(LoggerInterface::class);

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
      'sync' => $sync,
      'sync_case' => $sync_case,
      'event_field_mapping' => $event_field_mapping,
      'remote_entity' => $remote_entity,
      'local_entity' => $local_entity,
      'local_entity_field' => $local_entity_field,
      'field_import_success' => $field_import_success,
      'remote_id_field_import_success' => $remote_id_field_import_success,
      'remote_changed_field_import_success' => $remote_changed_field_import_success,
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
      $event_dispatcher,
      $logger->reveal()
    );
    $manager->import($remote_entity, $local_entity->reveal(), $sync->reveal());
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
      // Complete and valid configuration that expects the remote changed field
      // in string format.
      'complete__remoted_changed_field__string',
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
    return [
      // No fields.
      [],
      // Some fields.
      // A field that does and a field that does not require special mapping.
      [
        [
          'machine_name' => 'mail',
          'remote_name' => 'email',
        ],
        [
          'machine_name' => 'name',
          'remote_name' => 'name',
          // The callback method does not need to actually exist; we will just
          // be testing that the `call_user_func` function is called with the
          // right arguments.
          'import_callback' => '\Drupal\Tests\entity_sync\Unit\Import/FieldManagerTest::fieldCallback',
        ],
      ],
    ];
  }

  /**
   * Data provider for the remote entity being imported.
   *
   * @I Add tests for invalid remote entity data
   *    type     : task
   *    priority : normal
   *    labels   : testing
   */
  private function remoteEntityDataProvider() {
    return [
      // All expected fields.
      // Timestap format given as integer.
      (object) [
        'userId' => 1,
        'lastModified' => $this->remoteChangedFieldValues['timestamp_int'],
        'email' => 'user-1@example.com',
        'name' => 'Example User',
      ],
      // Timestap format given as string.
      (object) [
        'userId' => 1,
        'lastModified' => $this->remoteChangedFieldValues['timestamp_string'],
        'email' => 'user-1@example.com',
        'name' => 'Example User',
      ],
      // ISO-8061 format.
      (object) [
        'userId' => 1,
        'lastModified' => $this->remoteChangedFieldValues['string'],
        'email' => 'user-1@example.com',
        'name' => 'Example User',
      ],
      // Invalid format.
      (object) [
        'userId' => 1,
        'lastModified' => $this->remoteChangedFieldValues['invalid'],
        'email' => 'user-1@example.com',
        'name' => 'Example User',
      ],
      // Remote ID field missing.
      (object) [
        'lastModified' => $this->remoteChangedFieldValues['timestamp_int'],
        'email' => 'user-1@example.com',
        'name' => 'Example User',
      ],
      // Remote changed field missing.
      (object) [
        'userId' => 1,
        'email' => 'user-1@example.com',
        'name' => 'Example User',
      ],
      // Fields expected by field mapping missing.
      (object) [
        'userId' => 1,
        'lastModified' => $this->remoteChangedFieldValues['timestamp_int'],
      ],
      // Fields expected by field mapping having a NULL value.
      (object) [
        'userId' => 1,
        'lastModified' => $this->remoteChangedFieldValues['timestamp_int'],
        'email' => NULL,
        'name' => NULL,
      ],
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
   * Data provider for the success of the entity field import.
   *
   * @I Find an easy way to test one field failing while others suceeding
   *    type     : task
   *    priority : normal
   *    labels   : testing
   */
  private function fieldImportSuccessDataProvider() {
    return [
      // The field value was successfully imported.
      TRUE,
      // An error occurred while importing the field value.
      FALSE,
    ];
  }

  /**
   * Data provider for the success of the remote ID field import.
   */
  private function remoteIdFieldImportSuccessDataProvider() {
    return [
      // The field value was successfully imported.
      TRUE,
      // An error occurred while importing the field value.
      FALSE,
    ];
  }

  /**
   * Data provider for the success of the remote changed field import.
   */
  private function remoteChangedFieldImportSuccessDataProvider() {
    return [
      // The field value was successfully imported.
      TRUE,
      // An error occurred while importing the field value.
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

    // We expect no value to be set directly or via a callback.
    $test_context['local_entity']
      ->set(Argument::any())
      ->shouldNotBeCalled();

    $call_user_func = $this->getFunctionMock(
      '\Drupal\entity_sync\Import',
      'call_user_func'
    );
    $call_user_func
      ->expects($this->never());
  }

  /**
   * The final field mapping contains fields to be imported.
   */
  private function branchFieldMapping(array $test_context) {
    if (!$test_context['event_field_mapping']) {
      return;
    }

    // We want to test whether errors that may happen during importing fields
    // are properly handled. These errors are not necessarily thrown by our
    // class though; they may be thrown by the import callback. We therefore
    // simulate such scenarios by throwing and catching an exception within the
    // test.
    $errors = FALSE;

    foreach ($test_context['event_field_mapping'] as $field_info) {
      try {
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
   * The field mapping is done by a callback.
   */
  private function branchFieldMappingWithCallback(
    array $test_context,
    array $field_info
  ) {
    if (!isset($field_info['import_callback'])) {
      return;
    }

    $this->branchFieldMappingWithCallbackImportSuccess(
      $test_context,
      $field_info
    );
    $this->branchFieldMappingWithCallbackImportError(
      $test_context,
      $field_info
    );
  }

  /**
   * The field mapping is done by a callback and the import is successful.
   */
  private function branchFieldMappingWithCallbackImportSuccess(
    array $test_context,
    array $field_info
  ) {
    if (!$test_context['field_import_success']) {
      return;
    }

    // We expect the callback function to be successfully called.
    $this->mockCallUserFunc($test_context, $field_info);
  }

  /**
   * The field mapping is done by a callback and the import fails.
   */
  private function branchFieldMappingWithCallbackImportError(
    array $test_context,
    array $field_info
  ) {
    if ($test_context['field_import_success']) {
      return;
    }

    // We expect the callback function to be called but to fail, throwing an
    // exception.
    $this->mockCallUserFunc($test_context, $field_info)
      ->will($this->throwException(new \Exception('Exception message')));

    $this->expectImportFieldException($test_context);

    throw new TestRuntimeException();
  }

  /**
   * The field mapping is done by the default value copy.
   */
  private function branchFieldMappingWithoutCallback(
    array $test_context,
    array $field_info
  ) {
    if (isset($field_info['import_callback'])) {
      return;
    }

    $this->branchFieldMappingWithoutCallbackFieldsDoNotExist(
      $test_context,
      $field_info
    );
    $this->branchFieldMappingWithoutCallbackFieldDoesNotExistOnLocal(
      $test_context,
      $field_info
    );
    $this->branchFieldMappingWithoutCallbackFieldDoesNotExistOnRemote(
      $test_context,
      $field_info
    );
    $this->branchFieldMappingWithoutCallbackFieldsExist(
      $test_context,
      $field_info
    );
  }

  /**
   * The fields do not exist in neither the local nor the remote entity.
   */
  private function branchFieldMappingWithoutCallbackFieldsDoNotExist(
    array $test_context,
    array $field_info
  ) {
    if ($test_context['local_entity_field']) {
      return;
    }

    // If the property exists but is NULL, we consider it a valid value. It
    // means the field is intentionally empty which is legitimate.
    $field_exists = property_exists(
      $test_context['remote_entity'],
      $field_info['remote_name']
    );
    if ($field_exists) {
      return;
    }

    // We expect the method that sets the value on the local entity to not be
    // called and an exception to be thrown.
    $test_context['local_entity']
      ->hasField($field_info['machine_name'])
      ->willReturn(FALSE)
      ->shouldBeCalledTimes(1);
    $test_context['local_entity']
      ->set($field_info['machine_name'], Argument::any())
      ->shouldNotBeCalled();

    $this->expectImportFieldException($test_context);

    throw new TestRuntimeException();
  }

  /**
   * The field exists on the remote but not on the local entity.
   */
  private function branchFieldMappingWithoutCallbackFieldDoesNotExistOnLocal(
    array $test_context,
    array $field_info
  ) {
    if ($test_context['local_entity_field']) {
      return;
    }

    // If the property exists but is NULL, we consider it a valid value. It
    // means the field is intentionally empty which is legitimate.
    $field_exists = property_exists(
      $test_context['remote_entity'],
      $field_info['remote_name']
    );
    if (!$field_exists) {
      return;
    }

    // We expect the method that sets the value on the local entity to not be
    // called and an exception to be thrown.
    $test_context['local_entity']
      ->hasField($field_info['machine_name'])
      ->willReturn(FALSE)
      ->shouldBeCalledTimes(1);
    $test_context['local_entity']
      ->set($field_info['machine_name'], Argument::any())
      ->shouldNotBeCalled();

    $this->expectImportFieldException($test_context);

    throw new TestRuntimeException();
  }

  /**
   * The field exists on the local but not on the remote entity.
   */
  private function branchFieldMappingWithoutCallbackFieldDoesNotExistOnRemote(
    array $test_context,
    array $field_info
  ) {
    if (!$test_context['local_entity_field']) {
      return;
    }

    // If the property exists but is NULL, we consider it a valid value. It
    // means the field is intentionally empty which is legitimate.
    $field_exists = property_exists(
      $test_context['remote_entity'],
      $field_info['remote_name']
    );
    if ($field_exists) {
      return;
    }

    // We expect the method that sets the value on the local entity to not be
    // called.
    $test_context['local_entity']
      ->hasField($field_info['machine_name'])
      ->willReturn(TRUE)
      ->shouldBeCalledTimes(1);
    $test_context['local_entity']
      ->set($field_info['machine_name'], Argument::any())
      ->shouldNotBeCalled();
  }

  /**
   * The fields exist on both the local and the remote entity.
   */
  private function branchFieldMappingWithoutCallbackFieldsExist(
    array $test_context,
    array $field_info
  ) {
    if (!$test_context['local_entity_field']) {
      return;
    }

    // If the property exists but is NULL, we consider it a valid value. It
    // means the field is intentionally empty which is legitimate.
    $field_exists = property_exists(
      $test_context['remote_entity'],
      $field_info['remote_name']
    );
    if (!$field_exists) {
      return;
    }

    $test_context['local_entity']
      ->hasField($field_info['machine_name'])
      ->willReturn(TRUE)
      ->shouldBeCalledTimes(1);

    $this->branchFieldMappingWithoutCallbackImportSuccess(
      $test_context,
      $field_info
    );
    $this->branchFieldMappingWithoutCallbackImportError(
      $test_context,
      $field_info
    );
  }

  /**
   * The field mapping is done without a callback and the import is successful.
   */
  private function branchFieldMappingWithoutCallbackImportSuccess(
    array $test_context,
    array $field_info
  ) {
    if (!$test_context['field_import_success']) {
      return;
    }

    // We expect the method that sets the value to be called without throwing an
    // exception.
    $test_context['local_entity']
      ->set(
        $field_info['machine_name'],
        $test_context['remote_entity']->{$field_info['remote_name']}
      )
      ->shouldBeCalledTimes(1);
  }

  /**
   * The field mapping is done without a callback and the import fails.
   *
   * That includes the field not existing on the local entity, the value being
   * in an unsupported format (could happen for special fields that should have
   * be imported by a callback), or any other error that could be thrown by the
   * entity or PHP while setting the value to the local entity field.
   */
  private function branchFieldMappingWithoutCallbackImportError(
    array $test_context,
    array $field_info
  ) {
    if ($test_context['field_import_success']) {
      return;
    }

    // We expect the method that sets the value to be called but to throw an
    // exception.
    $test_context['local_entity']
      ->set(
        $field_info['machine_name'],
        $test_context['remote_entity']->{$field_info['remote_name']}
      )
      ->willThrow(new \Exception('Exception message'))
      ->shouldBeCalledTimes(1);

    $this->expectImportFieldException($test_context);

    throw new TestRuntimeException();
  }

  /**
   * At least one of the fields fails importing.
   */
  private function branchFieldMappingWithErrors(
    array $test_context,
    $errors
  ) {
    if (!$errors) {
      return;
    }

    // We expect the remote ID and remote changed fields to not be set as we
    // have errors in a field defined by the field mapping; the program should
    // stop before reaching the remote ID and remote changed fields.
    $this->expectSyncFieldValueNotToBeSet($test_context, 'remote_id');
    $this->expectSyncFieldValueNotToBeSet($test_context, 'remote_changed');
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

    // We expect to proceed with importing the remote ID and remote changed
    // fields.
    //
    // If there is a failure during the remote ID import, we don't expect to
    // proceed with importing the remote changed field. We therefore throw an
    // exception within the test to prevent registering the related
    // expectations.
    try {
      $this->branchRemoteIdField($test_context);
    }
    catch (TestRuntimeException $exception) {
      return;
    }

    $this->branchRemoteChangedField($test_context);
  }

  /**
   * The remote ID field starts importing.
   */
  private function branchRemoteIdField(array $test_context) {
    $remote_id_field = $this->getFixtureDataProperty(
      'remote_resource.id_field',
      $test_context['sync_case']
    );
    $test_context['sync']
      ->get('remote_resource.id_field')
      ->willReturn($remote_id_field)
      ->shouldBeCalledTimes(1);

    $this->branchRemoteIdFieldDoesNotExist($test_context, $remote_id_field);
    $this->branchRemoteIdFieldExists($test_context, $remote_id_field);
  }

  /**
   * The remote ID field does not exist in the remote entity.
   */
  private function branchRemoteIdFieldDoesNotExist(
    array $test_context,
    $remote_id_field
  ) {
    if (isset($test_context['remote_entity']->{$remote_id_field})) {
      return;
    }

    $this->expectImportSyncFieldException($test_context, 'remote_id');
    $this->expectSyncFieldValueNotToBeSet($test_context, 'remote_id');

    throw new TestRuntimeException();
  }

  /**
   * The remote ID field exists in the remote entity.
   */
  private function branchRemoteIdFieldExists(
    array $test_context,
    $remote_id_field
  ) {
    if (!isset($test_context['remote_entity']->{$remote_id_field})) {
      return;
    }

    $local_id_field = $this->getFixtureDataProperty(
      'entity.remote_id_field',
      $test_context['sync_case']
    );
    $test_context['sync']
      ->get('entity.remote_id_field')
      ->willReturn($local_id_field)
      ->shouldBeCalledTimes(1);

    $this->branchRemoteIdFieldImportSuccess(
      $test_context,
      $remote_id_field,
      $local_id_field
    );
    $this->branchRemoteIdFieldImportError(
      $test_context,
      $remote_id_field,
      $local_id_field
    );
  }

  /**
   * The remote ID field is successfully imported.
   */
  private function branchRemoteIdFieldImportSuccess(
    array $test_context,
    $remote_id_field,
    $local_id_field
  ) {
    if (!$test_context['remote_id_field_import_success']) {
      return;
    }

    // We expect the function that sets the value to the remote ID field to be
    // successfully called.
    $test_context['local_entity']
      ->set($local_id_field, $test_context['remote_entity']->{$remote_id_field})
      ->shouldBeCalledTimes(1);
  }

  /**
   * The remote ID field fails importing.
   *
   * That includes the field not existing on the local entity, error with the
   * value being in an unsupported format, or any other error that could be
   * thrown by the entity or PHP while setting the value to the local entity
   * field.
   */
  private function branchRemoteIdFieldImportError(
    array $test_context,
    $remote_id_field,
    $local_id_field
  ) {
    if ($test_context['remote_id_field_import_success']) {
      return;
    }

    // We expect the function that sets the value of the remote ID field to be
    // called but to throw an exception that will be caught and logged as error.
    $test_context['local_entity']
      ->set($local_id_field, $test_context['remote_entity']->{$remote_id_field})
      ->willThrow(new \Exception('Exception message'))
      ->shouldBeCalledTimes(1);

    $this->expectImportSyncFieldException($test_context, 'remote_id');

    throw new TestRuntimeException();
  }

  /**
   * The remote changed field starts importing.
   */
  private function branchRemoteChangedField(array $test_context) {
    $remote_changed_field = $this->getFixtureDataProperty(
      'remote_resource.changed_field',
      $test_context['sync_case']
    );
    $test_context['sync']
      ->get('remote_resource.changed_field')
      ->willReturn($remote_changed_field)
      ->shouldBeCalledTimes(1);

    $this->branchRemoteChangedFieldDoesNotExist($test_context, $remote_changed_field);
    $this->branchRemoteChangedFieldExists($test_context, $remote_changed_field);
  }

  /**
   * The remote changed field does not exist in the remote entity.
   */
  private function branchRemoteChangedFieldDoesNotExist(
    array $test_context,
    $remote_changed_field
  ) {
    $remote_changed_field_name = $remote_changed_field['name'];
    if (isset($test_context['remote_entity']->{$remote_changed_field_name})) {
      return;
    }

    $this->expectImportSyncFieldException($test_context, 'remote_changed');
    $this->expectSyncFieldValueNotToBeSet($test_context, 'remote_changed');
  }

  /**
   * The remote changed field exists in the remote entity.
   */
  private function branchRemoteChangedFieldExists(
    array $test_context,
    $remote_changed_field
  ) {
    $remote_changed_field_name = $remote_changed_field['name'];
    if (!isset($test_context['remote_entity']->{$remote_changed_field_name})) {
      return;
    }

    $this->branchRemoteChangedFieldFormatError(
      $test_context,
      $remote_changed_field
    );
    $this->branchRemoteChangedFieldFormatSuccess(
      $test_context,
      $remote_changed_field
    );
  }

  /**
   * The remote changed field value on the remote is in the wrong format.
   */
  private function branchRemoteChangedFieldFormatError(
    array $test_context,
    $remote_changed_field
  ) {
    $format_error = $this->remoteChangedFieldFormatError(
      $test_context,
      $remote_changed_field
    );
    if (!$format_error) {
      return;
    }

    // We expect an exception to be thrown.
    $this->expectImportSyncFieldException($test_context, 'remote_changed');
    $this->expectSyncFieldValueNotToBeSet($test_context, 'remote_changed');
  }

  /**
   * The remote changed field value on the remote is in the correct format.
   */
  private function branchRemoteChangedFieldFormatSuccess(
    array $test_context,
    $remote_changed_field
  ) {
    $local_changed_field = $this->getFixtureDataProperty(
      'entity.remote_changed_field',
      $test_context['sync_case']
    );
    $test_context['sync']
      ->get('entity.remote_changed_field')
      ->willReturn($local_changed_field)
      ->shouldBeCalledTimes(1);

    $this->branchRemoteChangedFieldImportSuccess(
      $test_context,
      $remote_changed_field,
      $local_changed_field
    );
    $this->branchRemoteChangedFieldImportError(
      $test_context,
      $remote_changed_field,
      $local_changed_field
    );
  }

  /**
   * The remote changed field is successfully imported.
   */
  private function branchRemoteChangedFieldImportSuccess(
    array $test_context,
    $remote_changed_field,
    $local_changed_field
  ) {
    $format_error = $this->remoteChangedFieldFormatError(
      $test_context,
      $remote_changed_field
    );
    if ($format_error) {
      return;
    }
    if (!$test_context['remote_changed_field_import_success']) {
      return;
    }

    // We expect the function that sets the value to the remote changed field to
    // be successfully called.
    $remote_changed_field_name = $remote_changed_field['name'];
    $timestamp = $this->remoteChangedFieldToTimestamp(
      $test_context['remote_entity']->{$remote_changed_field_name}
    );
    $test_context['local_entity']
      ->set(
        $local_changed_field,
        $timestamp
      )
      ->shouldBeCalledTimes(1);
  }

  /**
   * The remote changed field fails importing.
   *
   * That includes the field not existing on the local entity or any other error
   * that could be thrown by the entity or PHP while setting the value to the
   * local entity field.
   */
  private function branchRemoteChangedFieldImportError(
    array $test_context,
    $remote_changed_field,
    $local_changed_field
  ) {
    $format_error = $this->remoteChangedFieldFormatError(
      $test_context,
      $remote_changed_field
    );
    if ($format_error) {
      return;
    }
    if ($test_context['remote_changed_field_import_success']) {
      return;
    }

    // We expect the function that sets the value of the remote changed field to
    // be called but to throw an exception that will be caught and logged as
    // error.
    $remote_changed_field_name = $remote_changed_field['name'];
    $timestamp = $this->remoteChangedFieldToTimestamp(
      $test_context['remote_entity']->{$remote_changed_field_name}
    );
    $test_context['local_entity']
      ->set(
        $local_changed_field,
        $timestamp
      )
      ->willThrow(new \Exception('Exception message'))
      ->shouldBeCalledTimes(1);

    $this->expectImportSyncFieldException($test_context, 'remote_changed');
  }

  /**
   * Helper methods that register expectations.
   */

  /**
   * A `FieldImportException` thrown when importing any field.
   */
  private function expectImportFieldException(array $test_context) {
    $field_name = $this->getFixtureDataProperty(
      'remote_resource.id_field',
      $test_context['sync_case']
    );
    $test_context['sync']
      ->get('remote_resource.id_field')
      ->willReturn($field_name)
      ->shouldBeCalledAddTimes(1);

    $test_context['local_entity']
      ->id()
      ->shouldBeCalledTimes(1);
    $test_context['local_entity']
      ->isNew()
      ->shouldBeCalledTimes(1);

    $this->expectException(FieldImportException::class);
  }

  /**
   * A `FieldImportException` thrown when importing the sync-managed fields.
   *
   * The Entity Sync-managed fields are the remote ID and the remote changed
   * fields.
   */
  private function expectImportSyncFieldException(
    array $test_context,
    $field
  ) {
    $field_config_keys = [];

    switch ($field) {
      case 'remote_id':
        $field_config_keys = [
          'entity.remote_id_field',
          'remote_resource.id_field',
        ];
        break;

      case 'remote_changed':
        $field_config_keys = [
          'entity.remote_changed_field',
          'remote_resource.changed_field.name',
        ];
        break;
    }

    foreach ($field_config_keys as $field_config_key) {
      $field_name = $this->getFixtureDataProperty(
        $field_config_key,
        $test_context['sync_case']
      );
      $test_context['sync']
        ->get($field_config_key)
        ->willReturn($field_name)
        ->shouldBeCalledAddTimes(1);
    }

    $this->expectImportFieldException($test_context);
  }

  /**
   * No value should be set for the given Entity Sync-managed field.
   */
  private function expectSyncFieldValueNotToBeSet(array $test_context, $field) {
    $field_name = $this->getFixtureDataProperty(
      'entity.' . $field . '_field',
      $test_context['sync_case']
    );

    $test_context['local_entity']
      ->set(
        $field_name,
        Argument::any()
      )
      ->shouldNotBeCalled();
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
    $event_dispatcher = new EventDispatcher();
    $event_dispatcher->addSubscriber(
      $this->buildFieldMappingEventSubscriber($event_field_mapping)
    );

    return $event_dispatcher;
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
      '\Drupal\entity_sync\Import',
      'call_user_func'
    );

    $local_entity = $test_context['local_entity'];
    return $call_user_func
      ->expects($this->once())
      ->with(
        $this->equalTo($field_info['import_callback']),
        $this->identicalTo($test_context['remote_entity']),
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
        $this->equalTo($field_info)
      );
  }

  /**
   * Other helper methods.
   */

  /**
   * Converts the remote changed field value to the expected format.
   *
   * Used to prepare the expectations for the method that sets the value on the
   * local entity.
   */
  private function remoteChangedFieldToTimestamp($changed_field_value) {
    switch ($changed_field_value) {
      case $this->remoteChangedFieldValues['timestamp_int']:
      case $this->remoteChangedFieldValues['timestamp_string']:
        return $this->remoteChangedFieldValues['timestamp_int'];

      case $this->remoteChangedFieldValues['string']:
        return 1589260614;

      default:
        throw new \InvalidArgumentException(
          sprintf(
            'Unknown "%s" timestamp.',
            $changed_field_value
          )
        );
    }
  }

  /**
   * Checks if the remote changed field is invalid or in an unexpected format.
   */
  private function remoteChangedFieldFormatError(
    array $test_context,
    $remote_changed_field
  ) {
    $field_name = $remote_changed_field['name'];
    $field_value = $test_context['remote_entity']->{$field_name};

    switch ($field_value) {
      case $this->remoteChangedFieldValues['timestamp_int']:
      case $this->remoteChangedFieldValues['timestamp_string']:
        return $remote_changed_field['format'] !== 'timestamp';

      case $this->remoteChangedFieldValues['string']:
        return $remote_changed_field['format'] !== 'string';

      case $this->remoteChangedFieldValues['invalid']:
        return TRUE;

      default:
        throw new \InvalidArgumentException(
          sprintf(
            'Unknown "%s" timestamp.',
            $field_value
          )
        );
    }
  }

  /**
   * The default fixture ID for the tests in this file.
   */
  private function defaultFixtureId() {
    return 'entity_sync.sync.user';
  }

}
