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
 * @subpackage  Model
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Model\Import;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\OrderFactory as MagentoOrderFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Lengow\Connector\Helper\Config as ConfigHelper;
use Lengow\Connector\Helper\Data as DataHelper;
use Lengow\Connector\Helper\Import as ImportHelper;
use Lengow\Connector\Model\Connector as LengowConnector;
use Lengow\Connector\Model\Exception as LengowException;
use Lengow\Connector\Model\ImportFactory as LengowImportFactory;
use Lengow\Connector\Model\Import as LengowImport;
use Lengow\Connector\Model\Import\Action as LengowAction;
use Lengow\Connector\Model\Import\ActionFactory as LengowActionFactory;
use Lengow\Connector\Model\Import\OrderFactory as LengowOrderFactory;
use Lengow\Connector\Model\Import\OrdererrorFactory as LengowOrderErrorFactory;
use Lengow\Connector\Model\Import\OrderlineFactory as LengowOrderLineFactory;
use Lengow\Connector\Model\ResourceModel\Order as LengowOrderResource;
use Lengow\Connector\Model\ResourceModel\Order\CollectionFactory as LengowOrderCollectionFactory;
use Lengow\Connector\Model\ResourceModel\Ordererror\CollectionFactory as LengowOrderErrorCollectionFactory;

/**
 * Model import order
 */
class Order extends AbstractModel
{
    /**
     * @var integer order process state for new order not imported
     */
    const PROCESS_STATE_NEW = 0;

    /**
     * @var integer order process state for order imported
     */
    const PROCESS_STATE_IMPORT = 1;

    /**
     * @var integer order process state for order finished
     */
    const PROCESS_STATE_FINISH = 2;

    /**
     * @var string order new
     */
    const STATE_NEW = 'new';

    /**
     * @var string order state waiting acceptance
     */
    const STATE_WAITING_ACCEPTANCE = 'waiting_acceptance';

    /**
     * @var string order state accepted
     */
    const STATE_ACCEPTED = 'accepted';

    /**
     * @var string order state waiting_shipment
     */
    const STATE_WAITING_SHIPMENT = 'waiting_shipment';

    /**
     * @var string order state shipped
     */
    const STATE_SHIPPED = 'shipped';

    /**
     * @var string order state closed
     */
    const STATE_CLOSED = 'closed';

    /**
     * @var string order state refused
     */
    const STATE_REFUSED = 'refused';

    /**
     * @var string order state canceled
     */
    const STATE_CANCELED = 'canceled';

    /**
     * @var string order state refunded
     */
    const STATE_REFUNDED = 'refunded';

    /**
     * @var string order type prime
     */
    const TYPE_PRIME = 'is_prime';

    /**
     * @var string order type express
     */
    const TYPE_EXPRESS = 'is_express';

    /**
     * @var string order type business
     */
    const TYPE_BUSINESS = 'is_business';

    /**
     * @var string order type delivered by marketplace
     */
    const TYPE_DELIVERED_BY_MARKETPLACE = 'is_delivered_by_marketplace';

    /**
     * @var string label fulfillment for old orders without order type
     */
    const LABEL_FULFILLMENT = 'Fulfillment';

    /**
     * @var MagentoOrderFactory Magento order factory instance
     */
    protected $_orderFactory;

    /**
     * @var InvoiceService Magento invoice service
     */
    protected $_invoiceService;

    /**
     * @var Transaction Magento transaction
     */
    protected $_transaction;

    /**
     * @var DateTime Magento datetime instance
     */
    protected $_dateTime;

    /**
     * @var ConvertOrder Magento convert order instance
     */
    protected $_convertOrder;

    /**
     * @var JsonHelper Magento json helper
     */
    protected $jsonHelper;

    /**
     * @var TrackFactory Magento shipment track instance
     */
    protected $_trackFactory;

    /**
     * @var DataHelper Lengow data helper instance
     */
    protected $_dataHelper;

    /**
     * @var ImportHelper Lengow import helper instance
     */
    protected $_importHelper;

    /**
     * @var ConfigHelper Lengow config helper instance
     */
    protected $_configHelper;

    /**
     * @var LengowAction Lengow action instance
     */
    protected $_action;

    /**
     * @var LengowActionFactory Lengow action factory instance
     */
    protected $_actionFactory;

    /**
     * @var LengowConnector Lengow connector instance
     */
    protected $_connector;

    /**
     * @var LengowImport Lengow import instance
     */
    protected $_import;

    /**
     * @var LengowImportFactory Lengow import factory instance
     */
    protected $_importFactory;

    /**
     * @var LengowOrderFactory Lengow order instance
     */
    protected $_lengowOrderFactory;

    /**
     * @var LengowOrderCollectionFactory Lengow order collection factory
     */
    protected $_orderCollection;

    /**
     * @var LengowOrderErrorFactory Lengow order error factory instance
     */
    protected $_orderErrorFactory;

    /**
     * @var LengowOrderErrorCollectionFactory Lengow order error collection factory
     */
    protected $_orderErrorCollection;

    /**
     * @var LengowOrderLineFactory Lengow orderline factory instance
     */
    protected $_lengowOrderLineFactory;

    /**
     * @var array $_fieldList field list for the table lengow_order_line
     * required => Required fields when creating registration
     * update   => Fields allowed when updating registration
     */
    protected $_fieldList = [
        'order_id' => ['required' => false, 'updated' => true],
        'order_sku' => ['required' => false, 'updated' => true],
        'store_id' => ['required' => true, 'updated' => false],
        'delivery_address_id' => ['required' => true, 'updated' => false],
        'delivery_country_iso' => ['required' => false, 'updated' => true],
        'marketplace_sku' => ['required' => true, 'updated' => false],
        'marketplace_name' => ['required' => true, 'updated' => false],
        'marketplace_label' => ['required' => true, 'updated' => false],
        'order_lengow_state' => ['required' => true, 'updated' => true],
        'order_process_state' => ['required' => false, 'updated' => true],
        'order_date' => ['required' => true, 'updated' => false],
        'order_item' => ['required' => false, 'updated' => true],
        'order_types' => ['required' => true, 'updated' => false],
        'currency' => ['required' => false, 'updated' => true],
        'total_paid' => ['required' => false, 'updated' => true],
        'customer_vat_number' => ['required' => true, 'updated' => true],
        'commission' => ['required' => false, 'updated' => true],
        'customer_name' => ['required' => false, 'updated' => true],
        'customer_email' => ['required' => false, 'updated' => true],
        'carrier' => ['required' => false, 'updated' => true],
        'carrier_method' => ['required' => false, 'updated' => true],
        'carrier_tracking' => ['required' => false, 'updated' => true],
        'carrier_id_relay' => ['required' => false, 'updated' => true],
        'sent_marketplace' => ['required' => false, 'updated' => true],
        'is_in_error' => ['required' => false, 'updated' => true],
        'is_reimported' => ['required' => false, 'updated' => true],
        'message' => ['required' => true, 'updated' => true],
        'extra' => ['required' => false, 'updated' => true],
    ];

    /**
     * Constructor
     *
     * @param MagentoOrderFactory $orderFactory Magento order factory instance
     * @param Context $context Magento context instance
     * @param Registry $registry Magento registry instance
     * @param InvoiceService $invoiceService Magento invoice service
     * @param Transaction $transaction Magento transaction
     * @param DateTime $dateTime Magento datetime instance
     * @param ConvertOrder $convertOrder Magento convert order instance
     * @param TrackFactory $trackFactory Magento shipment track factory instance
     * @param JsonHelper $jsonHelper Magento json helper
     * @param DataHelper $dataHelper Lengow data helper instance
     * @param ImportHelper $importHelper Lengow import helper instance
     * @param ConfigHelper $configHelper Lengow config helper instance
     * @param LengowOrderErrorFactory $orderErrorFactory Lengow order error factory instance
     * @param LengowOrderErrorCollectionFactory $orderErrorCollection Lengow ordererror collection factory instance
     * @param LengowOrderFactory $lengowOrderFactory Lengow order factory instance
     * @param LengowOrderCollectionFactory $orderCollection Lengow order collection factory instance
     * @param LengowOrderLineFactory $orderLineFactory Lengow orderline factory instance
     * @param LengowActionFactory $actionFactory Lengow action factory instance
     * @param LengowConnector $connector Lengow connector instance
     * @param LengowImport $import Lengow import instance
     * @param LengowAction $action Lengow action instance
     * @param LengowImportFactory $importFactory Lengow import factory instance
     * @param AbstractResource $resource Magento abstract resource instance
     * @param AbstractDb $resourceCollection Magento abstract db instance
     */
    public function __construct(
        MagentoOrderFactory $orderFactory,
        Context $context,
        Registry $registry,
        InvoiceService $invoiceService,
        Transaction $transaction,
        DateTime $dateTime,
        ConvertOrder $convertOrder,
        TrackFactory $trackFactory,
        JsonHelper $jsonHelper,
        DataHelper $dataHelper,
        ImportHelper $importHelper,
        ConfigHelper $configHelper,
        LengowOrderErrorFactory $orderErrorFactory,
        LengowOrderErrorCollectionFactory $orderErrorCollection,
        LengowOrderFactory $lengowOrderFactory,
        LengowOrderCollectionFactory $orderCollection,
        LengowOrderLineFactory $orderLineFactory,
        LengowActionFactory $actionFactory,
        LengowConnector $connector,
        LengowImport $import,
        LengowAction $action,
        LengowImportFactory $importFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null
    ) {
        $this->_orderFactory = $orderFactory;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_dateTime = $dateTime;
        $this->_convertOrder = $convertOrder;
        $this->_trackFactory = $trackFactory;
        $this->jsonHelper = $jsonHelper;
        $this->_dataHelper = $dataHelper;
        $this->_importHelper = $importHelper;
        $this->_configHelper = $configHelper;
        $this->_orderErrorFactory = $orderErrorFactory;
        $this->_orderErrorCollection = $orderErrorCollection;
        $this->_lengowOrderFactory = $lengowOrderFactory;
        $this->_orderCollection = $orderCollection;
        $this->_lengowOrderLineFactory = $orderLineFactory;
        $this->_actionFactory = $actionFactory;
        $this->_connector = $connector;
        $this->_import = $import;
        $this->_action = $action;
        $this->_importFactory = $importFactory;
        parent::__construct($context, $registry, $resource, $resourceCollection);
    }

    /**
     * Initialize order model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(LengowOrderResource::class);
    }

    /**
     * Create Lengow order
     *
     * @param array $params order parameters
     *
     * @return Order|false
     */
    public function createOrder($params = [])
    {
        foreach ($this->_fieldList as $key => $value) {
            if (!array_key_exists($key, $params) && $value['required']) {
                return false;
            }
        }
        foreach ($params as $key => $value) {
            $this->setData($key, $value);
        }
        if (!array_key_exists('order_process_state', $params)) {
            $this->setData('order_process_state', self::PROCESS_STATE_NEW);
        }
        if (!$this->getCreatedAt()) {
            $this->setData('created_at', $this->_dateTime->gmtDate('Y-m-d H:i:s'));
        }
        try {
            return $this->save();
        } catch (\Exception $e) {
            $errorMessage = 'Orm error: "' . $e->getMessage() . '" ' . $e->getFile() . ' line ' . $e->getLine();
            $this->_dataHelper->log(
                DataHelper::CODE_ORM,
                $this->_dataHelper->setLogMessage('Error while inserting record in database - %1', [$errorMessage])
            );
            return false;
        }
    }

    /**
     * Update Lengow order
     *
     * @param array $params order parameters
     *
     * @return Order|false
     */
    public function updateOrder($params = [])
    {
        if (!$this->getId()) {
            return false;
        }
        $updatedFields = $this->getUpdatedFields();
        foreach ($params as $key => $value) {
            if (in_array($key, $updatedFields, true)) {
                $this->setData($key, $value);
            }
        }
        $this->setData('updated_at', $this->_dateTime->gmtDate('Y-m-d H:i:s'));
        try {
            return $this->save();
        } catch (\Exception $e) {
            $errorMessage = 'Orm error: "' . $e->getMessage() . '" ' . $e->getFile() . ' line ' . $e->getLine();
            $this->_dataHelper->log(
                DataHelper::CODE_ORM,
                $this->_dataHelper->setLogMessage('Error while inserting record in database - %1', [$errorMessage])
            );
            return false;
        }
    }

    /**
     * Get updated fields
     *
     * @return array
     */
    public function getUpdatedFields()
    {
        $updatedFields = [];
        foreach ($this->_fieldList as $key => $value) {
            if ($value['updated']) {
                $updatedFields[] = $key;
            }
        }
        return $updatedFields;
    }

    /**
     * Check if order is express
     *
     * @return boolean
     */
    public function isExpress()
    {
        $orderTypes = (string) $this->getData('order_types');
        $orderTypes = $orderTypes !== '' ? json_decode($orderTypes, true) : [];
        return isset($orderTypes[self::TYPE_EXPRESS]) || isset($orderTypes[self::TYPE_PRIME]);
    }

    /**
     * Check if order is B2B
     *
     * @return boolean
     */
    public function isBusiness()
    {
        $orderTypes = (string) $this->getData('order_types');
        $orderTypes = $orderTypes !== '' ? json_decode($orderTypes, true) : [];
        return isset($orderTypes[self::TYPE_BUSINESS]);
    }

    /**
     * Check if order is delivered by marketplace
     *
     * @return boolean
     */
    public function isDeliveredByMarketplace()
    {
        $orderTypes = (string) $this->getData('order_types');
        $orderTypes = $orderTypes !== '' ? json_decode($orderTypes, true) : [];
        return isset($orderTypes[self::TYPE_DELIVERED_BY_MARKETPLACE]) || $this->getData('sent_marketplace');
    }

    /**
     * Check if an order has an error
     *
     * @param string $marketplaceSku marketplace sku
     * @param integer $deliveryAddressId delivery address id
     * @param string $type order error type (import or send)
     *
     * @return array|false
     */
    public function orderIsInError($marketplaceSku, $deliveryAddressId, $type = 'import')
    {
        $errorType = $this->_orderErrorFactory->create()->getOrderErrorType($type);
        // check if log already exists for the given order id
        $results = $this->_orderErrorCollection->create()
            ->join(
                'lengow_order',
                '`lengow_order`.id=main_table.order_lengow_id',
                ['marketplace_sku' => 'marketplace_sku', 'delivery_address_id' => 'delivery_address_id']
            )
            ->addFieldToFilter('marketplace_sku', $marketplaceSku)
            ->addFieldToFilter('delivery_address_id', $deliveryAddressId)
            ->addFieldToFilter('type', $errorType)
            ->addFieldToFilter('is_finished', ['eq' => 0])
            ->addFieldToSelect('id')
            ->addFieldToSelect('message')
            ->addFieldToSelect('created_at')
            ->load()
            ->getData();
        if (empty($results)) {
            return false;
        }
        return $results[0];
    }

    /**
     * if order is already Imported
     *
     * @param string $marketplaceSku marketplace sku
     * @param string $marketplaceName marketplace name
     * @param integer $deliveryAddressId delivery address id
     *
     * @return integer|false
     */
    public function getOrderIdIfExist($marketplaceSku, $marketplaceName, $deliveryAddressId)
    {
        // get order id Magento from our table
        $results = $this->_orderCollection->create()
            ->addFieldToFilter('marketplace_sku', $marketplaceSku)
            ->addFieldToFilter('marketplace_name', $marketplaceName)
            ->addFieldToFilter('delivery_address_id', $deliveryAddressId)
            ->addFieldToSelect('order_id')
            ->load()
            ->getData();
        if (!empty($results)) {
            return $results[0]['order_id'];
        }
        return false;
    }

    /**
     * Get Lengow ID with order ID Magento and delivery address ID
     *
     * @param integer $orderId Magento order id
     * @param string $deliveryAddressId delivery address id
     *
     * @return string|false
     */
    public function getOrderIdWithDeliveryAddress($orderId, $deliveryAddressId)
    {
        $results = $this->_orderCollection->create()
            ->addFieldToFilter('order_id', $orderId)
            ->addFieldToFilter('delivery_address_id', $deliveryAddressId)
            ->addFieldToSelect('id')
            ->getData();
        if (!empty($results)) {
            return $results[0]['id'];
        }
        return false;
    }

    /**
     * Get ID record from lengow orders table
     *
     * @param string $marketplaceSku marketplace sku
     * @param string $marketplaceName marketplace name
     * @param integer $deliveryAddressId delivery address id
     *
     * @return integer|false
     */
    public function getLengowOrderId($marketplaceSku, $marketplaceName, $deliveryAddressId)
    {
        $results = $this->_orderCollection->create()
            ->addFieldToFilter('marketplace_sku', $marketplaceSku)
            ->addFieldToFilter('marketplace_name', $marketplaceName)
            ->addFieldToFilter('delivery_address_id', $deliveryAddressId)
            ->addFieldToSelect('id')
            ->getData();
        if (!empty($results)) {
            return (int) $results[0]['id'];
        }
        return false;
    }

    /**
     * Get ID record from lengow orders table with Magento order Id
     *
     * @param integer $orderId Magento order id
     *
     * @return integer|false
     */
    public function getLengowOrderIdWithOrderId($orderId)
    {
        $results = $this->_orderCollection->create()
            ->addFieldToFilter('order_id', $orderId)
            ->addFieldToSelect('id')
            ->getData();
        if (!empty($results)) {
            return (int) $results[0]['id'];
        }
        return false;
    }

    /**
     * Get Magento equivalent to lengow order state
     *
     * @param string $orderStateLengow Lengow state
     *
     * @return string
     */
    public function getOrderState($orderStateLengow)
    {
        switch ($orderStateLengow) {
            case self::STATE_NEW:
            case self::STATE_WAITING_ACCEPTANCE:
                return MagentoOrder::STATE_NEW;
            case self::STATE_ACCEPTED:
            case self::STATE_WAITING_SHIPMENT:
                return MagentoOrder::STATE_PROCESSING;
            case self::STATE_SHIPPED:
            case self::STATE_CLOSED:
                return MagentoOrder::STATE_COMPLETE;
            case self::STATE_REFUSED:
            case self::STATE_CANCELED:
                return MagentoOrder::STATE_CANCELED;
        }
    }

    /**
     * Update order state to marketplace state
     *
     * @param MagentoOrder|OrderInterface $order Magento order instance
     * @param Order $lengowOrder Lengow order instance
     * @param string $orderStateLengow lengow order status
     * @param mixed $packageData package data
     *
     * @return string|false
     */
    public function updateState($order, $lengowOrder, $orderStateLengow, $packageData)
    {
        // finish actions if lengow order is shipped, closed, cancel or refunded
        $orderProcessState = $this->getOrderProcessState($orderStateLengow);
        $tracks = $packageData->delivery->trackings;
        if ($orderProcessState === self::PROCESS_STATE_FINISH) {
            $this->_actionFactory->create()->finishAllActions($order->getId());
            $this->_orderErrorFactory->create()->finishOrderErrors($lengowOrder->getId(), 'send');
        }
        // update Lengow order if necessary
        $params = [];
        if ($lengowOrder->getData('order_lengow_state') !== $orderStateLengow) {
            $params['order_lengow_state'] = $orderStateLengow;
            $params['carrier_tracking'] = !empty($tracks) ? (string) $tracks[0]->number : null;
        }
        if ($orderProcessState === self::PROCESS_STATE_FINISH) {
            if ((int) $lengowOrder->getData('order_process_state') !== $orderProcessState) {
                $params['order_process_state'] = $orderProcessState;
            }
            if ($lengowOrder->getData('is_in_error')) {
                $params['is_in_error'] = 0;
            }
        }
        if (!empty($params)) {
            $lengowOrder->updateOrder($params);
        }
        try {
            // update Magento order's status only if in accepted, waiting_shipment, shipped, closed or cancel
            if ($order->getData('from_lengow')
                && $order->getState() !== $this->getOrderState($orderStateLengow)
            ) {
                if (($orderStateLengow === self::STATE_SHIPPED || $orderStateLengow === self::STATE_CLOSED)
                    && ($order->getState() === $this->getOrderState(self::STATE_ACCEPTED)
                        || $order->getState() === $this->getOrderState(self::STATE_NEW)
                    )
                ) {
                    if (!empty($tracks)) {
                        $tracking = $tracks[0];
                        $carrierName = $tracking->carrier;
                        $carrierMethod = $tracking->method;
                        $trackingNumber = $tracking->number;
                    }
                    $this->toShip(
                        $order,
                        isset($carrierName) ? $carrierName : null,
                        isset($carrierMethod) ? $carrierMethod : null,
                        isset($trackingNumber) ? $trackingNumber : null
                    );
                    return 'Complete';
                }
                if (($orderStateLengow === self::STATE_CANCELED || $orderStateLengow === self::STATE_REFUSED)
                    && ($order->getState() === $this->getOrderState(self::STATE_NEW)
                        || $order->getState() === $this->getOrderState(self::STATE_ACCEPTED)
                        || $order->getState() === $this->getOrderState(self::STATE_SHIPPED)
                    )
                ) {
                    $this->toCancel($order);
                    return 'Canceled';
                }
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * Cancel order
     *
     * @param MagentoOrder $order Magento order instance
     */
    public function toCancel($order)
    {
        if ($order->canCancel()) {
            $order->cancel();
        }
    }

    /**
     * Create invoice
     *
     * @param MagentoOrder|OrderInterface $order Magento order instance
     *
     * @throws \Exception
     */
    public function toInvoice($order)
    {
        $invoice = $this->_invoiceService->prepareInvoice($order);
        if ($invoice) {
            $invoice->register();
            $invoice->getOrder()->setIsInProcess(true);
            $invoice->setState(Invoice::STATE_PAID);
            $transactionSave = $this->_transaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();
        }
    }

    /**
     * Ship order
     *
     * @param MagentoOrder|OrderInterface $order Magento order instance
     * @param string $carrierName carrier name
     * @param string $carrierMethod carrier method
     * @param string $trackingNumber tracking number
     *
     * @throws \Exception
     */
    public function toShip($order, $carrierName, $carrierMethod, $trackingNumber)
    {
        if ($order->canShip()) {
            $shipment = $this->_convertOrder->toShipment($order);
            if ($shipment) {
                foreach ($order->getAllItems() as $orderItem) {
                    if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                        continue;
                    }
                    $qtyShipped = $orderItem->getQtyToShip();
                    $shipmentItem = $this->_convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);
                    $shipment->addItem($shipmentItem);
                }
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                // add tracking information
                if ($trackingNumber !== null && $trackingNumber !== '') {
                    $title = $carrierName;
                    if ($title === null || $title === 'None') {
                        $title = $carrierMethod;
                    }
                    $track = $this->_trackFactory->create()
                        ->setNumber($trackingNumber)
                        ->setCarrierCode(Track::CUSTOM_CARRIER_CODE)
                        ->setTitle($title);
                    $shipment->addTrack($track);
                }
                $shipment->save();
                $shipment->getOrder()->save();
            }
        }
    }

    /**
     * Get marketplace sku by Magento order id from lengow orders table
     *
     * @param integer $orderId Magento order id
     *
     * @return string|false
     */
    public function getMarketplaceSkuByOrderId($orderId)
    {
        $results = $this->_orderCollection->create()
            ->addFieldToFilter('order_id', $orderId)
            ->addFieldToSelect('marketplace_sku')
            ->getData();
        if (!empty($results)) {
            return $results[0]['marketplace_sku'];
        }
        return false;
    }

    /**
     * Get Lengow Order by Magento order id from lengow orders table
     *
     * @param integer $orderId Magento order id
     *
     * @return integer|false
     */
    public function getLengowOrderIdByOrderId($orderId)
    {
        $results = $this->_orderCollection->create()
            ->addFieldToFilter('order_id', $orderId)
            ->getData();
        if (!empty($results)) {
            return (int) $results[0]['id'];
        }
        return false;
    }

    /**
     * Get all unset orders
     *
     * @return array|false
     */
    public function getUnsentOrders()
    {
        $date = strtotime('-5 days', time());
        $results = $this->_orderCollection->create()
            ->join(
                ['magento_order' => 'sales_order'],
                'magento_order.entity_id=main_table.order_id',
                [
                    'store_id' => 'store_id',
                    'updated_at' => 'updated_at',
                    'state' => 'state',
                ]
            )
            ->addFieldToFilter('magento_order.updated_at', ['from' => $date, 'datetime' => true])
            ->addFieldToFilter('magento_order.state', [['in' => ['cancel', 'complete']]])
            ->addFieldToFilter('main_table.order_process_state', ['eq' => 1])
            ->addFieldToFilter('main_table.is_in_error', ['eq' => 0])
            ->getData();
        if (!empty($results)) {
            return $results;
        }
        return false;
    }

    /**
     * Re-import order lengow
     *
     * @param integer $orderLengowId Lengow order id
     *
     * @return array|false
     */
    public function reImportOrder($orderLengowId)
    {
        $orderLengow = $this->_lengowOrderFactory->create()->load($orderLengowId);
        if ((int) $orderLengow->getData('order_process_state') === 0 && $orderLengow->getData('is_in_error')) {
            $params = [
                LengowImport::PARAM_TYPE => LengowImport::TYPE_MANUAL,
                LengowImport::PARAM_ORDER_LENGOW_ID => $orderLengowId,
                LengowImport::PARAM_MARKETPLACE_SKU => $orderLengow->getData('marketplace_sku'),
                LengowImport::PARAM_MARKETPLACE_NAME => $orderLengow->getData('marketplace_name'),
                LengowImport::PARAM_DELIVERY_ADDRESS_ID => $orderLengow->getData('delivery_address_id'),
                LengowImport::PARAM_STORE_ID => $orderLengow->getData('store_id'),
            ];
            $this->_import->init($params);
            return $this->_import->exec();
        }
        return false;
    }

    /**
     * Re-send order lengow
     *
     * @param integer $orderLengowId Lengow order id
     *
     * @return boolean
     */
    public function reSendOrder($orderLengowId)
    {
        $orderLengow = $this->_lengowOrderFactory->create()->load($orderLengowId);
        if ((int) $orderLengow->getData('order_process_state') === 1 && $orderLengow->getData('is_in_error')) {
            $orderId = $orderLengow->getData('order_id');
            if ($orderId !== null) {
                $order = $this->_orderFactory->create()->load($orderId);
                $action = $this->_action->getLastOrderActionType($orderId);
                if (!$action) {
                    $action = $order->getData('status') === self::STATE_CANCELED
                        ? LengowAction::TYPE_CANCEL
                        : LengowAction::TYPE_SHIP;
                }
                $shipment = $order->getShipmentsCollection()->getFirstItem();
                return $this->callAction($action, $order, $shipment);
            }
        }
        return false;
    }

    /**
     * Get order process state
     *
     * @param string $state state to be matched
     *
     * @return integer|false
     */
    public function getOrderProcessState($state)
    {
        switch ($state) {
            case self::STATE_ACCEPTED:
            case self::STATE_WAITING_SHIPMENT:
                return self::PROCESS_STATE_IMPORT;
            case self::STATE_SHIPPED:
            case self::STATE_CLOSED:
            case self::STATE_REFUSED:
            case self::STATE_CANCELED:
            case self::STATE_REFUNDED:
                return self::PROCESS_STATE_FINISH;
            default:
                return false;
        }
    }

    /**
     * Cancel and re-import order
     *
     * @param MagentoOrder $order Magento order instance
     * @param Order $lengowOrder Lengow order instance
     *
     * @return integer|false
     */
    public function cancelAndReImportOrder($order, $lengowOrder)
    {
        if (!$this->isReimported($lengowOrder)) {
            return false;
        }
        $params = [
            LengowImport::PARAM_MARKETPLACE_SKU => $lengowOrder->getData('marketplace_sku'),
            LengowImport::PARAM_MARKETPLACE_NAME => $lengowOrder->getData('marketplace_name'),
            LengowImport::PARAM_DELIVERY_ADDRESS_ID => $lengowOrder->getData('delivery_address_id'),
            LengowImport::PARAM_STORE_ID => $order->getData('store_id'),
        ];
        $import = $this->_importFactory->create();
        $import->init($params);
        $result = $import->exec();
        if ((isset($result['order_new']) && $result['order_new'])
            && (isset($result['order_id']) && (int) $result['order_id'] !== (int) $order->getData('order_id'))
        ) {
            try {
                // if state != STATE_COMPLETE or != STATE_CLOSED
                $order->setState('lengow_technical_error')->setStatus('lengow_technical_error');
                $order->save();
            } catch (\Exception $e) {
                $errorMessage = 'Orm error: "' . $e->getMessage() . '" ' . $e->getFile() . ' line ' . $e->getLine();
                $this->_dataHelper->log(
                    DataHelper::CODE_ORM,
                    $this->_dataHelper->setLogMessage('Error while inserting record in database - %1', [$errorMessage])
                );
            }
            return (int) $result['order_id'];
        }
        // finish all order errors before API call
        $this->_orderErrorFactory->create()->finishOrderErrors($lengowOrder->getId());
        $lengowOrder->updateOrder(
            [
                'order_id' => $order->getId(),
                'order_sku' => $order->getIncrementId(),
                'is_reimported' => 0,
                'is_in_error' => 0,
            ]
        );
        return false;
    }

    /**
     * Mark Lengow order as is_reimported in lengow_order table
     *
     * @param Order $lengowOrder Lengow order instance
     *
     * @return boolean
     */
    public function isReimported($lengowOrder)
    {
        $lengowOrder->updateOrder(['is_reimported' => 1]);
        // check success update in database
        if ($lengowOrder->getData('is_reimported')) {
            return true;
        }
        return false;
    }

    /**
     * Send Order action
     *
     * @param string $action Lengow Actions (ship or cancel)
     * @param MagentoOrder $order Magento order instance
     * @param Shipment|null $shipment Magento Shipment instance
     *
     * @return boolean
     */
    public function callAction($action, $order, $shipment = null)
    {
        $success = true;
        if (!(bool) $order->getData('from_lengow')) {
            return false;
        }
        $lengowOrderId = $this->getLengowOrderIdByOrderId($order->getId());
        if (!$lengowOrderId) {
            return false;
        }
        $lengowOrder = $this->_lengowOrderFactory->create()->load($lengowOrderId);
        $this->_dataHelper->log(
            DataHelper::CODE_ACTION,
            $this->_dataHelper->setLogMessage(
                'try to send %1 action (ORDER ID %2)',
                [$action, $order->getIncrementId()]
            ),
            false,
            $lengowOrder->getData('marketplace_sku')
        );
        // finish all order errors before API call
        $this->_orderErrorFactory->create()->finishOrderErrors($lengowOrder->getId(), 'send');
        if ($lengowOrder->getData('is_in_error')) {
            $lengowOrder->updateOrder(['is_in_error' => 0]);
        }
        try {
            $marketplace = $this->_importHelper->getMarketplaceSingleton($lengowOrder->getData('marketplace_name'));
            if ($marketplace->containOrderLine($action)) {
                $orderLineCollection = $this->_lengowOrderLineFactory->create()->getOrderLineByOrderID($order->getId());
                // get order line ids by API for security
                if (!$orderLineCollection) {
                    $orderLineCollection = $this->getOrderLineByApi(
                        $lengowOrder->getData('marketplace_sku'),
                        $lengowOrder->getData('marketplace_name'),
                        (int) $lengowOrder->getData('delivery_address_id')
                    );
                }
                if (!$orderLineCollection) {
                    throw new LengowException(
                        $this->_dataHelper->setLogMessage('order line is required but not found in the order')
                    );
                }
                $results = [];
                foreach ($orderLineCollection as $orderLine) {
                    $results[] = $marketplace->callAction(
                        $action,
                        $order,
                        $lengowOrder,
                        $shipment,
                        $orderLine['order_line_id']
                    );
                }
                $success = !in_array(false, $results, true);
            } else {
                $success = $marketplace->callAction($action, $order, $lengowOrder, $shipment);
            }
        } catch (LengowException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $errorMessage = 'Magento error: "' . $e->getMessage() . '" ' . $e->getFile() . ' line ' . $e->getLine();
        }
        if (isset($errorMessage)) {
            if ((int) $lengowOrder->getData('order_process_state') !== self::PROCESS_STATE_FINISH) {
                $lengowOrder->updateOrder(['is_in_error' => 1]);
                $orderError = $this->_orderErrorFactory->create();
                $orderError->createOrderError(
                    [
                        'order_lengow_id' => $lengowOrder->getId(),
                        'message' => $errorMessage,
                        'type' => 'send',
                    ]
                );
                unset($orderError);
            }
            $decodedMessage = $this->_dataHelper->decodeLogMessage($errorMessage, false);
            $this->_dataHelper->log(
                DataHelper::CODE_ACTION,
                $this->_dataHelper->setLogMessage('order action failed - %1', [$decodedMessage]),
                false,
                $lengowOrder->getData('marketplace_sku')
            );
            $success = false;
        }
        if ($success) {
            $message = $this->_dataHelper->setLogMessage(
                'action %1 successfully sent (ORDER ID %2)',
                [$action, $order->getIncrementId()]
            );
        } else {
            $message = $this->_dataHelper->setLogMessage(
                'WARNING! action %1 could not be sent (ORDER ID %2)',
                [$action, $order->getIncrementId()]
            );
        }
        $this->_dataHelper->log(DataHelper::CODE_ACTION, $message, false, $lengowOrder->getData('marketplace_sku'));
        return $success;
    }

    /**
     * Get order line by API
     *
     * @param string $marketplaceSku marketplace sku
     * @param string $marketplaceName marketplace name
     * @param integer $deliveryAddressId delivery address id
     *
     * @return array|false
     */
    public function getOrderLineByApi($marketplaceSku, $marketplaceName, $deliveryAddressId)
    {
        $orderLines = [];
        $results = $this->_connector->queryApi(
            LengowConnector::GET,
            LengowConnector::API_ORDER,
            [
                'marketplace_order_id' => $marketplaceSku,
                'marketplace' => $marketplaceName,
            ]
        );
        if (!isset($results->results) || (isset($results->count) && (int) $results->count === 0)) {
            return false;
        }
        $orderData = $results->results[0];
        foreach ($orderData->packages as $package) {
            $productLines = [];
            foreach ($package->cart as $product) {
                $productLines[] = ['order_line_id' => (string) $product->marketplace_order_line_id];
            }
            $orderLines[(int) $package->delivery->id] = $productLines;
        }
        $return = isset($orderLines[$deliveryAddressId]) ? $orderLines[$deliveryAddressId] : [];
        return !empty($return) ? $return : false;
    }

    /**
     * Get order ids from lengow order ID
     *
     * @param string $marketplaceSku marketplace sku
     * @param string $marketplaceName delivery address id
     *
     * @return array|false
     */
    public function getAllOrderIds($marketplaceSku, $marketplaceName)
    {
        $results = $this->_orderCollection->create()
            ->addFieldToFilter('marketplace_sku', $marketplaceSku)
            ->addFieldToFilter('marketplace_name', $marketplaceName)
            ->addFieldToSelect('order_id')
            ->getData();
        if (!empty($results)) {
            return $results;
        }
        return false;
    }

    /**
     * Get all Lengow order ids
     *
     * @return array|false
     */
    public function getAllLengowOrderIds()
    {
        $results = $this->_orderCollection->create()
            ->addFieldToSelect('id')
            ->getData();
        if (!empty($results)) {
            return $results;
        }
        return false;
    }

    /**
     * Synchronize order with Lengow API
     *
     * @param Order $lengowOrder Lengow order instance
     * @param LengowConnector|null $connector
     * @param boolean $logOutput see log or not
     *
     * @return boolean
     */
    public function synchronizeOrder($lengowOrder, $connector = null, $logOutput = false)
    {
        list($accountId, $accessToken, $secretToken) = $this->_configHelper->getAccessIds();
        if ($connector === null) {
            if ($this->_connector->isValidAuth($logOutput)) {
                $this->_connector->init(['access_token' => $accessToken, 'secret' => $secretToken]);
            } else {
                return false;
            }
        }
        $orderIds = $this->getAllOrderIds(
            $lengowOrder->getData('marketplace_sku'),
            $lengowOrder->getData('marketplace_name')
        );
        if ($orderIds) {
            $magentoIds = [];
            foreach ($orderIds as $orderId) {
                $magentoIds[] = (int) $orderId['order_id'];
            }
            try {
                $body = [
                    'account_id' => $accountId,
                    'marketplace_order_id' => $lengowOrder->getData('marketplace_sku'),
                    'marketplace' => $lengowOrder->getData('marketplace_name'),
                    'merchant_order_id' => $magentoIds,
                ];
                $result = $this->_connector->patch(
                    LengowConnector::API_ORDER_MOI,
                    [],
                    LengowConnector::FORMAT_JSON,
                    $this->jsonHelper->jsonEncode($body),
                    $logOutput
                );
            } catch (\Exception $e) {
                $message = $this->_dataHelper->decodeLogMessage($e->getMessage(), false);
                $error = $this->_dataHelper->setLogMessage('API call failed - %1 - %2', [$e->getCode(), $message]);
                $this->_dataHelper->log(DataHelper::CODE_CONNECTOR, $error, $logOutput);
                return false;
            }
            if ($result === null
                || (isset($result['detail']) && $result['detail'] === 'Pas trouvé.')
                || isset($result['error'])
            ) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Count order imported by Lengow in Magento
     *
     * @return integer
     */
    public function countOrderImportedByLengow()
    {
        $results = $this->_orderCollection->create()
            ->join(['magento_order' => 'sales_order'], 'magento_order.entity_id=main_table.order_id')
            ->addFieldToSelect('id')
            ->getData();
        return count($results);
    }

    /**
     * Count order lengow with error
     *
     * @return integer
     */
    public function countOrderWithError()
    {
        $results = $this->_orderCollection->create()
            ->addFieldToFilter('is_in_error', 1)
            ->addFieldToSelect('id')
            ->getData();
        return count($results);
    }

    /**
     * Count order lengow to be sent
     *
     * @return integer
     */
    public function countOrderToBeSent()
    {
        $results = $this->_orderCollection->create()
            ->addFieldToFilter('order_process_state', 1)
            ->addFieldToSelect('id')
            ->getData();
        return count($results);
    }
}
