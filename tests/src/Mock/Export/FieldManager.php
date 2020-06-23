<?php

namespace Drupal\Tests\entity_sync\Mock\Export;

use Drupal\entity_sync\Export\FieldManager as RealFieldManager;
use Drupal\Core\Field\FieldItemInterface;

/**
 * Mock class for the export field manager.
 */
class FieldManager extends RealFieldManager {

  /**
   * Workaround for mocking the field item's static function.
   */
  protected function getFieldMainPropertyName(FieldItemInterface $field_item) {
    return 'main-property-name';
  }

}
