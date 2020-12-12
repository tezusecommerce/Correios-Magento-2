<?php

/**
 * Copyright ©   All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Tezus\Correios\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class AddComprimentoCorreiosProductAttribute implements DataPatchInterface, PatchRevertableInterface {

  /**
   * @var ModuleDataSetupInterface
   */
  private $moduleDataSetup;
  /**
   * @var EavSetupFactory
   */
  private $eavSetupFactory;

  /**
   * Constructor
   *
   * @param ModuleDataSetupInterface $moduleDataSetup
   * @param EavSetupFactory $eavSetupFactory
   */
  public function __construct(
    ModuleDataSetupInterface $moduleDataSetup,
    EavSetupFactory $eavSetupFactory
  ) {
    $this->moduleDataSetup = $moduleDataSetup;
    $this->eavSetupFactory = $eavSetupFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function apply() {
    $this->moduleDataSetup->getConnection()->startSetup();
    /** @var EavSetup $eavSetup */
    $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
    $eavSetup->addAttribute(
      \Magento\Catalog\Model\Product::ENTITY,
      'comprimento_correios',
      [
        'group' => 'Correios',
        'type' => 'varchar',
        'label' => 'Comprimento Correios',
        'input' => 'text',
        'source' => '',
        'frontend' => '',
        'required' => false,
        'backend' => '',
        'default' => null,
        'user_defined' => true,
        'unique' => false,
      ]
    );

    $this->moduleDataSetup->getConnection()->endSetup();
  }

  public function revert() {
    $this->moduleDataSetup->getConnection()->startSetup();
    /** @var EavSetup $eavSetup */
    $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
    $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'comprimento_correios');

    $this->moduleDataSetup->getConnection()->endSetup();
  }

  /**
   * {@inheritdoc}
   */
  public function getAliases() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function getDependencies() {
    return [];
  }
}