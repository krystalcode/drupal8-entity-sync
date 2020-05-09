<?php

namespace Drupal\entity_sync\Plugin\Block;

use Drupal\entity_sync\ManagerInterface;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBuilderInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'SyncBlock' block plugin.
 *
 * @Block(
 *   id = "app_import_company_form",
 *   admin_label = @Translation("Sync block"),
 *   category = @Translation("Entity Sync"),
 *   deriver = "Drupal\entity_sync\Plugin\Derivative\SyncBlock"
 * )
 */
class SyncBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The sync manager service.
   *
   * @var \Drupal\entity_sync\ManagerInterface
   */
  protected $syncManager;

  /**
   * Creates a SyncBlock instance.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\entity_sync\ManagerInterface $sync_manager
   *   The sync manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    FormBuilderInterface $form_builder,
    ManagerInterface $sync_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configFactory = $config_factory;
    $this->formbuilder = $form_builder;
    $this->syncManager = $sync_manager;

    $block_id_array = explode("-", $this->getDerivativeId());

    $this->label = $block_id_array['2'];
    $this->operationId = $block_id_array['1'];
    $this->config = $this->configFactory->get($block_id_array['0'])->get();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('form_builder'),
      $container->get('entity_sync.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $block_operations = $this->syncManager->getSyncOperationsForBlock();

    return $this->formbuilder->getForm(
      $block_operations['import_list'],
      $this->label,
      $this->config
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    $entity_type_id = $this->config['entity']['type_id'];
    $bundle = $this->config['entity']['bundle'];

    // If a bundle is set for the config we use the bundle and entity perm,
    // if not we use the entity type permission.
    $permission = "entity_sync $this->operationId ${entity_type_id}";
    if ($bundle) {
      $permission = "entity_sync $this->operationId ${bundle} ${entity_type_id}";
    }

    return AccessResult::allowedIfHasPermission(
      $account,
      $permission
    );
  }

}
