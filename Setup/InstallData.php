<?php

/**
 * Copyright 2017 Lengow SAS
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @subpackage  Setup
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Setup;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Customer;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Setup\SalesSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Lengow\Connector\Helper\Config;
use Magento\Framework\ObjectManagerInterface;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    /**
     * @var \Magento\Eav\Setup\EavSetupFactory Magento EAV setup factory instance
     */
    private $_eavSetupFactory;

    /**
     * @var \Magento\Customer\Setup\CustomerSetupFactory Magento customer setup factory instance
     */
    private $_customerSetupFactory;

    /**
     * @var \Magento\Sales\Setup\SalesSetupFactory Magento sales setup factory instance
     */
    protected $_salesSetupFactory;

    /**
     * @var \Magento\Framework\ObjectManagerInterface Magento object manager instance
     */
    protected $_objectManager;

    /**
     * @var \Lengow\Connector\Helper\Config Lengow config helper instance
     */
    protected $_configHelper;

    /**
     * Init
     *
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory Magento EAV setup factory instance
     * @param \Magento\Customer\Setup\CustomerSetupFactory $customerSetupFactory Magento customer setup factory instance
     * @param \Magento\Sales\Setup\SalesSetupFactory $salesSetupFactory Magento sales setup factory instance
     * @param \Magento\Framework\ObjectManagerInterface $objectManager Magento object manager instance
     * @param \Lengow\Connector\Helper\Config $configHelper Lengow config helper instance
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        CustomerSetupFactory $customerSetupFactory,
        SalesSetupFactory $salesSetupFactory,
        ObjectManagerInterface $objectManager,
        Config $configHelper
    ) {
        $this->_eavSetupFactory = $eavSetupFactory;
        $this->_customerSetupFactory = $customerSetupFactory;
        $this->_salesSetupFactory = $salesSetupFactory;
        $this->_objectManager = $objectManager;
        $this->_configHelper = $configHelper;
    }

    /**
     * Installs data for a module
     *
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $setup Magento module data setup instance
     * @param \Magento\Framework\Setup\ModuleContextInterface $context Magento module context instance
     *
     * @return void
     */
    public function install(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $setup->startSetup();

        $eavSetup = $this->_eavSetupFactory->create(['setup' => $setup]);
        $customerSetup = $this->_customerSetupFactory->create(['setup' => $setup]);
        $salesSetup = $this->_salesSetupFactory->create(['setup' => $setup]);

        // create attribute lengow_product for product
        $entityTypeId = $customerSetup->getEntityTypeId(Product::ENTITY);
        $lengowProductAttribut = $eavSetup->getAttribute($entityTypeId, 'lengow_product');

        if (!$lengowProductAttribut) {
            $eavSetup->addAttribute(
                $entityTypeId,
                'lengow_product',
                [
                    'type' => 'int',
                    'backend' => '',
                    'frontend' => '',
                    'label' => 'Publish on Lengow',
                    'input' => 'boolean',
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'visible' => 1,
                    'required' => 0,
                    'user_defined' => 1,
                    'default' => 1,
                    'searchable' => 0,
                    'filterable' => 0,
                    'comparable' => 0,
                    'unique' => 0,
                    'visible_on_front' => 0,
                    'used_in_product_listing' => 1,
                    'system' => 0,
                    'group' => 'Lengow'
                ]
            );
        }

        // create attribute from_lengow for customer
        $entityTypeId = $customerSetup->getEntityTypeId(Customer::ENTITY);
        $fromLengowCustomer = $eavSetup->getAttribute($entityTypeId, 'from_lengow');
        if (!$fromLengowCustomer) {
            $eavSetup->addAttribute(
                $entityTypeId,
                'from_lengow',
                [
                    'type' => 'int',
                    'label' => 'From Lengow',
                    'visible' => true,
                    'required' => false,
                    'unique' => false,
                    'sort_order' => 700,
                    'default' => 0,
                    'input' => 'select',
                    'system' => 0,
                    'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean'
                ]
            );

            $fromLengowCustomer = $customerSetup->getEavConfig()->getAttribute('customer', 'from_lengow')
                ->addData(['used_in_forms' => 'adminhtml_customer']);
            $fromLengowCustomer->getResource()->save($fromLengowCustomer);
        }

        $entityTypeId = $salesSetup->getEntityTypeId(Order::ENTITY);
        // create attribute from_lengow for order
        $salesSetup->addAttribute(
            $entityTypeId,
            'from_lengow',
            [
                'label' => 'From Lengow',
                'type' => 'int',
                'visible' => true,
                'required' => false,
                'unique' => false,
                'filterable' => 1,
                'sort_order' => 700,
                'default' => 0,
                'input' => 'select',
                'system' => 0,
                'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                'grid' => true
            ]
        );

        // Set default attributes
        $this->_configHelper->setDefaultAttributes();

        //check if order state and status 'Lengow technical error' exists
        $collections = $this->_objectManager->create('Magento\Sales\Model\Order\Status')
            ->getCollection()
            ->toOptionArray();
        $lengowTechnicalExists = false;
        foreach ($collections as $value) {
            if ($value['value'] == 'lengow_technical_error') {
                $lengowTechnicalExists = true;
            }
        }
        // if not exists create new order state and status 'Lengow technical error'
        $statusTable = $setup->getTable('sales_order_status');
        $statusStateTable = $setup->getTable('sales_order_status_state');

        if (!$lengowTechnicalExists) {
            // Insert statuses
            $setup->getConnection()->insertArray(
                $statusTable,
                ['status', 'label'],
                [
                    [
                        'status' => 'lengow_technical_error',
                        'label' => 'Lengow Technical Error'
                    ]
                ]
            );
            // Insert states and mapping of statuses to states
            $setup->getConnection()->insertArray(
                $statusStateTable,
                ['status', 'state', 'is_default'],
                [
                    [
                        'status' => 'lengow_technical_error',
                        'state' => 'lengow_technical_error',
                        'is_default' => 1
                    ]
                ]
            );
        }

        $setup->endSetup();
    }
}
