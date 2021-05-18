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
 * @subpackage  Cron
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Cron;

use Lengow\Connector\Helper\Config as ConfigHelper;
use Lengow\Connector\Helper\Sync as SyncHelper;
use Lengow\Connector\Model\Import\Action as LengowAction;
use Lengow\Connector\Model\Import as LengowImport;

class LaunchSynchronization
{
    /**
     * @var ConfigHelper Lengow config helper instance
     */
    protected $_configHelper;

    /**
     * @var SyncHelper Lengow sync helper instance
     */
    protected $_syncHelper;

    /**
     * @var LengowAction Lengow action instance
     */
    protected $_action;

    /**
     * @var LengowImport Lengow import instance
     */
    protected $_import;

    /**
     * Constructor
     *
     * @param ConfigHelper $configHelper Lengow config helper instance
     * @param SyncHelper $syncHelper Lengow sync helper instance
     * @param LengowImport $import Lengow import instance
     * @param LengowAction $action Lengow action instance
     */
    public function __construct(
        ConfigHelper $configHelper,
        SyncHelper $syncHelper,
        LengowImport $import,
        LengowAction $action
    ) {
        $this->_configHelper = $configHelper;
        $this->_syncHelper = $syncHelper;
        $this->_import = $import;
        $this->_action = $action;
    }

    /**
     * Synchronize catalogs, orders, actions and options for each store with cron job
     */
    public function execute()
    {
        if ($this->_configHelper->get(ConfigHelper::SYNCHRONISATION_MAGENTO_CRON_ENABLED)) {
            // sync catalogs id between Lengow and Magento
            $this->_syncHelper->syncCatalog();
            // sync orders between Lengow and Magento
            $this->_import->init([LengowImport::PARAM_TYPE => LengowImport::TYPE_MAGENTO_CRON]);
            $this->_import->exec();
            // sync action between Lengow and Magento
            $this->_action->checkFinishAction();
            $this->_action->checkOldAction();
            $this->_action->checkActionNotSent();
            // sync options between Lengow and Magento
            $this->_syncHelper->setCmsOption();
        }
    }
}
