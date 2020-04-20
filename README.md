INTRODUCTION
------------

This module provides a framework to sync entities to and from remote systems.

- Defines an EntitySyncFrom service that syncs entities from remote services to Drupal.
  - Syncs entities via hook_cronapi.
  - Entities to sync are defined by allowing modules to subscribe to the
SyncEntityTypes event.
- Defines an EntitySyncTo service that syncs entities from Drupal to remote services.

REQUIREMENTS
------------

- Ultimate Cron

INSTALLATION
------------

- Install the module as you would any Drupal module.
- Ensure the following fields are created on the application:
  - `field_sync_remote_id` (field type: string, cardinality: 1)
  - `field_sync_changed` (field type: timestamp, cardinality: 1)

CONFIGURATION
-------------
No manual configuration is needed at this point.
