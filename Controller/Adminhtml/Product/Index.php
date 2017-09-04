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
 * @subpackage  Controller
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Controller\Adminhtml\Product;

use Lengow\Connector\Helper\Data as DataHelper;
use Lengow\Connector\Helper\Config as ConfigHelper;
use Magento\Backend\Helper\Data as BackendHelper;
use Lengow\Connector\Helper\Sync as SyncHelper;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Backend\App\Action;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Backend\App\Action\Context;
use Lengow\Connector\Model\Export;
use Magento\Framework\Json\Helper\Data as JsonHelperData;

class Index extends Action
{
    /**
     * @var \Lengow\Connector\Helper\Data Lengow data helper instance
     */
    protected $_dataHelper;

    /**
     * @var \Lengow\Connector\Helper\Config Lengow config helper instance
     */
    protected $_configHelper;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory Magento json factory instance
     */
    protected $_resultJsonFactory;

    /**
     * @var \Magento\Framework\Json\Helper\Data Magento json helper instance
     */
    protected $_jsonHelper;

    /**
     * @var \Lengow\Connector\Helper\Sync Lengow sync helper instance
     */
    protected $_syncHelper;

    /**
     * @var \Magento\Backend\App\Action\Context Magento action context instance
     */
    protected $_context;

    /**
     * @var \Lengow\Connector\Model\Export Lengow export instance
     */
    protected $_export;

    /**
     * @var \Magento\Catalog\Model\Product\Action Magento product action instance
     */
    protected $_productAction;

    /**
     * Constructor
     *
     * @param \Magento\Catalog\Model\Product\Action $productAction Magento product action instance
     * @param \Magento\Backend\App\Action\Context $context Magento action context instance
     * @param \Lengow\Connector\Helper\Config $configHelper Lengow config helper instance
     * @param \Lengow\Connector\Helper\Data $dataHelper Lengow data helper instance
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory Magento json factory instance
     * @param \Lengow\Connector\Helper\Sync $syncHelper Lengow sync helper instance
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper Magento json helper instance
     * @param \Lengow\Connector\Model\Export $export Lengow export instance
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        DataHelper $dataHelper,
        JsonFactory $resultJsonFactory,
        SyncHelper $syncHelper,
        JsonHelperData $jsonHelper,
        Export $export,
        ProductAction $productAction
    ) {
        $this->_context = $context;
        $this->_productAction = $productAction;
        $this->_configHelper = $configHelper;
        $this->_dataHelper = $dataHelper;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_jsonHelper = $jsonHelper;
        $this->_syncHelper = $syncHelper;
        $this->_export = $export;
        parent::__construct($context);
    }

    /**
     * Index action
     *
     * @return void|string
     */
    public function execute()
    {
        if ($this->getRequest()->getParam('isAjax')) {
            $action = $this->getRequest()->getParam('action');
            if ($action) {
                switch ($action) {
                    case 'change_option_selected':
                        $state = $this->getRequest()->getParam('state');
                        $storeId = $this->getRequest()->getParam('store_id');
                        if ($state !== null) {
                            $this->_configHelper->set('selection_enable', $state, $storeId);
                            $params = [
                                'store_id' => $storeId,
                                'selection' => $state
                            ];
                            $this->_export->init($params);
                            return $this->_resultJsonFactory->create()->setData(
                                [
                                    'state' => $state,
                                    'exported' => $this->_export->getTotalExportedProduct(),
                                    'total' => $this->_export->getTotalProduct()
                                ]
                            );
                        }
                        break;
                    case 'lengow_export_product':
                        $storeId = $this->getRequest()->getParam('store_id');
                        $state = $this->getRequest()->getParam('state');
                        $productId = $this->getRequest()->getParam('product_id');
                        if ($state !== null) {
                            $this->_productAction->updateAttributes(
                                [$productId],
                                ['lengow_product' => $state],
                                $storeId
                            );
                            $params = [
                                'store_id' => $storeId,
                                'selection' => 1
                            ];
                            $this->_export->init($params);
                            return $this->_resultJsonFactory->create()->setData(
                                [
                                    'exported' => $this->_export->getTotalExportedProduct(),
                                    'total' => $this->_export->getTotalProduct()
                                ]
                            );
                        }
                        break;
                }
            }
        } else {
            $this->_view->loadLayout();
            $this->_view->renderLayout();
        }
        return null;
    }
}
