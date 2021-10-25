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

namespace Lengow\Connector\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Lengow\Connector\Helper\Data as DataHelper;
use Lengow\Connector\Model\Import as LengowImport;
use Lengow\Connector\Model\Import\Order as LengowOrder;
use Lengow\Connector\Model\Import\OrderFactory as LengowOrderFactory;

class MassReImport extends Action
{
    /**
     * @var DataHelper Lengow data helper instance
     */
    private $dataHelper;

    /**
     * @var LengowOrderFactory Lengow order factory instance
     */
    private $orderFactory;

    /**
     * Constructor
     *
     * @param Context $context Magento action context instance
     * @param DataHelper $dataHelper Lengow data helper instance
     * @param LengowOrderFactory $orderFactory Lengow order factory instance
     */
    public function __construct(
        Context $context,
        DataHelper $dataHelper,
        LengowOrderFactory $orderFactory
    ) {
        $this->dataHelper = $dataHelper;
        $this->orderFactory = $orderFactory;
        parent::__construct($context);
    }

    /**
     * Execute mass reimport
     *
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        $selectedIds = $this->getRequest()->getParam('selected');
        $excludedIds = $this->getRequest()->getParam('excluded', []);
        $excludedIds = $excludedIds === 'false' ? [] : $excludedIds;
        if (empty($selectedIds)) {
            $ids = [];
            $allLengowOrderIds = $this->orderFactory->create()->getAllLengowOrderIds();
            if ($allLengowOrderIds) {
                foreach ($allLengowOrderIds as $lengowOrderId) {
                    if (!in_array($lengowOrderId[LengowOrder::FIELD_ID], $excludedIds, true)) {
                        $ids[] = $lengowOrderId[LengowOrder::FIELD_ID];
                    }
                }
            }
        } else {
            $ids = $selectedIds;
        }
        // if ids is empty -> do nothing
        if (!is_array($ids) || empty($ids)) {
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('*/*/index', ['_current' => true]);
        }
        // reimport all selected orders
        $totalReImport = 0;
        foreach ($ids as $orderLengowId) {
            $result = $this->orderFactory->create()->reImportOrder((int) $orderLengowId);
            if (!empty($result[LengowImport::ORDERS_CREATED])) {
                $totalReImport++;
            }
        }
        // get the number of orders correctly re-imported
        $this->messageManager->addSuccessMessage(
            $this->dataHelper->decodeLogMessage(
                'A total of %1 order(s) in %2 selected have been imported.',
                true,
                [$totalReImport, count($ids)]
            )
        );
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/index', ['_current' => true]);
    }
}
