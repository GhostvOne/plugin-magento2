<?php

/**
 * Copyright 2019 Lengow SAS
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
 * @copyright   2019 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Setup;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Lengow\Connector\Helper\Config as ConfigHelper;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var ConfigHelper Lengow config helper instance
     */
    protected $_configHelper;

    /**
     * Constructor
     *
     * @param ConfigHelper $configHelper Lengow config helper instance
     */
    public function __construct(ConfigHelper $configHelper)
    {
        $this->_configHelper = $configHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.1.0', '<')) {

            // *********************************************************
            //    active Lengow tracker for versions 1.0.0 - 1.0.3
            // *********************************************************

            $trackingEnable = (bool)$this->_configHelper->get('tracking_enable');
            if (!$this->_configHelper->isNewMerchant() && !$trackingEnable) {
                $this->_configHelper->set('tracking_enable', 1);
                // clean config cache to valid configuration
                $this->_configHelper->cleanConfigCache();
            }
        }

        if (version_compare($context->getVersion(), '1.2.0', '<')) {

            // **********************************************************
            // Delete statistic configurations for versions 1.0.0 - 1.1.5
            // **********************************************************

            $this->_configHelper->delete('lengow_global_options/advanced/order_statistic');
            $this->_configHelper->delete('lengow_global_options/advanced/last_statistic_update');

            // *************************************************************
            // Delete preprod mode configuration for versions 1.0.0 - 1.1.5
            // *************************************************************

            $this->_configHelper->delete('lengow_import_options/advanced/import_preprod_mode_enable');
        }


        $setup->endSetup();
    }
}
