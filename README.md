Entity Synchronization
----------------------

## Configuration

### Create a provider

A provider needs to be created first that defines which integrations are
available. For example, if you want to integrate with an external ERP system,
create an `entity_sync.provider.erp.yml` file in your configuration folder with
the following content:

```
id: erp
operations:
  - import
  - importList
  - export
```

For the full configuration schema, see `config/schema/entity_sync.schema.yml`.

### Create a synchronization

You can define multiple synchronizations as configuration objects. The Entity
Synchronization module will run operations based on those configurations. For
example, to regularly import the list of recently created or updated users from
the remote ERP system, create an `entity_sync.sync.user.yml` file in your
configuration folder with the following content:

```
id: user
entity:
  type_id: user
  remote_id_field: sync_remote_id
remote_resource:
  provider_id: erp
  name: User
  identifier: userId
  client:
    type: service
    service: my_module.entity_sync_client.user
  operations:
    - id: importList
      status: true
      label: 'Import users'
      url_path: users
  fields:
    machine_name: field_first_name
    remote_name: firstName
    machine_name: field_last_name
    remote_name: lastName
```

For the full configuration schema, see `config/schema/entity_sync.schema.yml`.
