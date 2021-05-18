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

use Magento\Framework\Model\Context;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use Lengow\Connector\Helper\Config as ConfigHelper;
use Lengow\Connector\Helper\Data as DataHelper;
use Lengow\Connector\Helper\Sync as SyncHelper;
use Lengow\Connector\Model\Exception as LengowException;
use Lengow\Connector\Model\Import\Action as LengowAction;
use Lengow\Connector\Model\Import\Order as LengowOrder;
use Lengow\Connector\Model\Import\OrdererrorFactory as LengowOrderErrorFactory;

/**
 * Model marketplace
 */
class Marketplace extends AbstractModel
{
    /**
     * @var TimezoneInterface Magento datetime timezone instance
     */
    protected $_timezone;

    /**
     * @var DataHelper Lengow data helper instance
     */
    protected $_dataHelper;

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
    protected $_orderAction;

    /**
     * @var LengowOrderErrorFactory Lengow order error factory instance
     */
    protected $_orderErrorFactory;

    /**
     * @var array all valid actions
     */
    public static $validActions = [
        LengowAction::TYPE_SHIP,
        LengowAction::TYPE_CANCEL,
    ];

    /**
     * @var Object all marketplaces allowed for an account ID
     */
    public static $marketplaces = false;

    /**
     * @var mixed the current marketplace
     */
    public $marketplace;

    /**
     * @var string the name of the marketplace
     */
    public $name;

    /**
     * @var string the old code of the marketplace for v2 compatibility
     */
    public $legacyCode;

    /**
     * @var string the name of the marketplace
     */
    public $labelName;

    /**
     * @var boolean if the marketplace is loaded
     */
    public $isLoaded = false;

    /**
     * @var array Lengow states => marketplace states
     */
    public $statesLengow = [];

    /**
     * @var array marketplace states => Lengow states
     */
    public $states = [];

    /**
     * @var array all possible actions of the marketplace
     */
    public $actions = [];

    /**
     * @var array all possible values for actions of the marketplace
     */
    public $argValues = [];

    /**
     * @var array all carriers of the marketplace
     */
    public $carriers = [];

    /**
     * Constructor
     *
     * @param Context $context Magento context instance
     * @param Registry $registry Magento registry instance
     * @param TimezoneInterface $timezone Magento datetime timezone instance
     * @param DataHelper $dataHelper Lengow data helper instance
     * @param ConfigHelper $configHelper Lengow config helper instance
     * @param SyncHelper $syncHelper Lengow sync helper instance
     * @param LengowAction $orderAction Lengow action instance
     * @param LengowOrderErrorFactory $orderErrorFactory Lengow order error factory instance
     */
    public function __construct(
        Context $context,
        Registry $registry,
        TimezoneInterface $timezone,
        DataHelper $dataHelper,
        ConfigHelper $configHelper,
        SyncHelper $syncHelper,
        LengowAction $orderAction,
        LengowOrderErrorFactory $orderErrorFactory
    ) {
        $this->_timezone = $timezone;
        $this->_dataHelper = $dataHelper;
        $this->_configHelper = $configHelper;
        $this->_syncHelper = $syncHelper;
        $this->_orderAction = $orderAction;
        $this->_orderErrorFactory = $orderErrorFactory;
        parent::__construct($context, $registry);
    }

    /**
     * Construct a new Marketplace instance with marketplace API
     *
     * @param array $params options
     * string name Marketplace name
     *
     * @throws LengowException
     */
    public function init($params = [])
    {
        $this->loadApiMarketplace();
        $this->name = strtolower($params['name']);
        if (!isset(self::$marketplaces->{$this->name})) {
            throw new LengowException(
                $this->_dataHelper->setLogMessage(
                    'Lengow error: %1 does not feature in the marketplace list',
                    [$this->name]
                )
            );
        }
        $this->marketplace = self::$marketplaces->{$this->name};
        if (!empty($this->marketplace)) {
            $this->legacyCode = $this->marketplace->legacy_code;
            $this->labelName = $this->marketplace->name;
            foreach ($this->marketplace->orders->status as $key => $state) {
                foreach ($state as $value) {
                    $this->statesLengow[(string) $value] = (string) $key;
                    $this->states[(string) $key][(string) $value] = (string) $value;
                }
            }
            foreach ($this->marketplace->orders->actions as $key => $action) {
                foreach ($action->status as $state) {
                    $this->actions[(string) $key]['status'][(string) $state] = (string) $state;
                }
                foreach ($action->args as $arg) {
                    $this->actions[(string) $key]['args'][(string) $arg] = (string) $arg;
                }
                foreach ($action->optional_args as $optional_arg) {
                    $this->actions[(string) $key]['optional_args'][(string) $optional_arg] = $optional_arg;
                }
                foreach ($action->args_description as $argKey => $argDescription) {
                    $validValues = [];
                    if (isset($argDescription->valid_values)) {
                        foreach ($argDescription->valid_values as $code => $validValue) {
                            $validValues[(string) $code] = isset($validValue->label)
                                ? (string) $validValue->label
                                : (string) $validValue;
                        }
                    }
                    $defaultValue = isset($argDescription->default_value)
                        ? (string) $argDescription->default_value
                        : '';
                    $acceptFreeValue = isset($argDescription->accept_free_values)
                        ? (bool) $argDescription->accept_free_values
                        : true;
                    $this->argValues[(string) $argKey] = [
                        'default_value' => $defaultValue,
                        'accept_free_values' => $acceptFreeValue,
                        'valid_values' => $validValues,
                    ];
                }
            }
            if (isset($this->marketplace->orders->carriers)) {
                foreach ($this->marketplace->orders->carriers as $key => $carrier) {
                    $this->carriers[(string) $key] = (string) $carrier->label;
                }
            }
            $this->isLoaded = true;
        }
    }


    /**
     * Load the json configuration of all marketplaces
     */
    public function loadApiMarketplace()
    {
        if (!self::$marketplaces) {
            self::$marketplaces = $this->_syncHelper->getMarketplaces();
        }
    }

    /**
     * Get the real lengow's state
     *
     * @param string $name The marketplace state
     *
     * @return string The lengow state
     */
    public function getStateLengow($name)
    {
        if (array_key_exists($name, $this->statesLengow)) {
            return $this->statesLengow[$name];
        }
        return '';
    }

    /**
     * Get the action with parameters
     *
     * @param string $action order action (ship or cancel)
     *
     * @return array|false
     */
    public function getAction($action)
    {
        if (array_key_exists($action, $this->actions)) {
            return $this->actions[$action];
        }
        return false;
    }

    /**
     * Get the default value for argument
     *
     * @param string $name The argument's name
     *
     * @return string|false
     */
    public function getDefaultValue($name)
    {
        if (array_key_exists($name, $this->argValues)) {
            $defaultValue = $this->argValues[$name]['default_value'];
            if (!empty($defaultValue)) {
                return $defaultValue;
            }
        }
        return false;
    }

    /**
     * Is marketplace contain order Line
     *
     * @param string $action order action (ship or cancel)
     *
     * @return bool
     */
    public function containOrderLine($action)
    {
        if (isset($this->actions[$action])) {
            $actions = $this->actions[$action];
            if (isset($actions['args'])
                && is_array($actions['args'])
                && in_array(LengowAction::ARG_LINE, $actions['args'], true)
            ) {
                return true;
            }
            if (isset($actions['optional_args'])
                && is_array($actions['optional_args'])
                && in_array(LengowAction::ARG_LINE, $actions['optional_args'], true)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Call Action with marketplace
     *
     * @param string $action order action (ship or cancel)
     * @param MagentoOrder $order Magento order instance
     * @param LengowOrder $lengowOrder Lengow order instance
     * @param Shipment|null $shipment Magento shipment instance
     * @param string|null $orderLineId Lengow order line id
     *
     * @return boolean
     */
    public function callAction($action, $order, $lengowOrder, $shipment = null, $orderLineId = null)
    {
        try {
            // check the action and order data
            $this->_checkAction($action);
            $this->_checkOrderData($lengowOrder);
            // get all required and optional arguments for a specific marketplace
            $marketplaceArguments = $this->_getMarketplaceArguments($action);
            // get all available values from an order
            $params = $this->_getAllParams($action, $order, $lengowOrder, $shipment, $marketplaceArguments);
            // check required arguments and clean value for empty optionals arguments
            $params = $this->_checkAndCleanParams($action, $params);
            // complete the values with the specific values of the account
            if ($orderLineId !== null) {
                $params[LengowAction::ARG_LINE] = $orderLineId;
            }
            $params['marketplace_order_id'] = $lengowOrder->getData('marketplace_sku');
            $params['marketplace'] = $lengowOrder->getData('marketplace_name');
            $params[LengowAction::ARG_ACTION_TYPE] = $action;
            // checks whether the action is already created to not return an action
            $canSendAction = $this->_orderAction->canSendAction($params, $order);
            if ($canSendAction) {
                // send a new action on the order via the Lengow API
                $this->_orderAction->sendAction($params, $order, $lengowOrder);
            }
        } catch (LengowException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $errorMessage = 'Magento error: "' . $e->getMessage() . '" ' . $e->getFile() . ' line ' . $e->getLine();
        }
        if (isset($errorMessage)) {
            if ((int) $lengowOrder->getData('order_process_state') !== $lengowOrder->getOrderProcessState('closed')) {
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
            return false;
        }
        return true;
    }

    /**
     * Check if the action is valid and present on the marketplace
     *
     * @param string $action Lengow order actions type (ship or cancel)
     *
     * @throws LengowException
     */
    protected function _checkAction($action)
    {
        if (!in_array($action, self::$validActions, true)) {
            throw new LengowException($this->_dataHelper->setLogMessage('action %1 is not valid', [$action]));
        }
        if (!isset($this->actions[$action])) {
            throw new LengowException(
                $this->_dataHelper->setLogMessage('the marketplace action %1 is not present', [$action])
            );
        }
    }

    /**
     * Check if the essential data of the order are present
     *
     * @param LengowOrder $lengowOrder Lengow order instance
     *
     * @throws LengowException
     */
    protected function _checkOrderData($lengowOrder)
    {
        if ($lengowOrder->getData('marketplace_sku') === '') {
            throw new LengowException($this->_dataHelper->setLogMessage('marketplace order reference is required'));
        }
        if ($lengowOrder->getData('marketplace_name') === '') {
            throw new LengowException($this->_dataHelper->setLogMessage('marketplace name is required'));
        }
    }

    /**
     * Get all marketplace arguments for a specific action
     *
     * @param string $action Lengow order actions type (ship or cancel)
     *
     * @return array
     */
    protected function _getMarketplaceArguments($action)
    {
        $actions = $this->getAction($action);
        if (isset($actions['args'], $actions['optional_args'])) {
            $marketplaceArguments = array_merge($actions['args'], $actions['optional_args']);
        } elseif (!isset($actions['args']) && isset($actions['optional_args'])) {
            $marketplaceArguments = $actions['optional_args'];
        } elseif (isset($actions['args'])) {
            $marketplaceArguments = $actions['args'];
        } else {
            $marketplaceArguments = [];
        }
        return $marketplaceArguments;
    }

    /**
     * Get all available values from an order
     *
     * @param string $action Lengow order actions type (ship or cancel)
     * @param MagentoOrder $order Magento order instance
     * @param LengowOrder $lengowOrder Lengow order instance
     * @param Shipment $shipment Magento shipment instance
     * @param array $marketplaceArguments All marketplace arguments for a specific action
     *
     * @return array
     */
    protected function _getAllParams($action, $order, $lengowOrder, $shipment, $marketplaceArguments)
    {
        $params = [];
        $actions = $this->getAction($action);
        // get all order data
        foreach ($marketplaceArguments as $arg) {
            switch ($arg) {
                case LengowAction::ARG_TRACKING_NUMBER:
                    $tracks = $shipment->getAllTracks();
                    if (!empty($tracks)) {
                        $lastTrack = end($tracks);
                    }
                    $params[$arg] = isset($lastTrack) ? $lastTrack->getNumber() : '';
                    break;
                case LengowAction::ARG_CARRIER:
                case LengowAction::ARG_CARRIER_NAME:
                case LengowAction::ARG_SHIPPING_METHOD:
                case LengowAction::ARG_CUSTOM_CARRIER:
                    if ((string) $lengowOrder->getData('carrier') !== '') {
                        $carrierCode = (string) $lengowOrder->getData('carrier');
                    } else {
                        $tracks = $shipment->getAllTracks();
                        if (!empty($tracks)) {
                            $lastTrack = end($tracks);
                        }
                        $carrierCode = isset($lastTrack)
                            ? $this->_matchCarrier($lastTrack->getCarrierCode(), $lastTrack->getTitle())
                            : '';
                    }
                    $params[$arg] = $carrierCode;
                    break;
                case LengowAction::ARG_SHIPPING_PRICE:
                    $params[$arg] = $order->getShippingInclTax();
                    break;
                case LengowAction::ARG_SHIPPING_DATE:
                case LengowAction::ARG_DELIVERY_DATE:
                    $params[$arg] = $this->_timezone->date()->format('c');
                    break;
                default:
                    if (isset($actions['optional_args']) && in_array($arg, $actions['optional_args'], true)) {
                        break;
                    }
                    $defaultValue = $this->getDefaultValue((string) $arg);
                    $paramValue = $defaultValue ?: $arg . ' not available';
                    $params[$arg] = $paramValue;
                    break;
            }
        }
        return $params;
    }

    /**
     * Get all available values from an order
     *
     * @param string $action Lengow order actions type (ship or cancel)
     * @param array $params all available values
     *
     * @throws LengowException
     *
     * @return array
     */
    protected function _checkAndCleanParams($action, $params)
    {
        $actions = $this->getAction($action);
        // check all required arguments
        if (isset($actions['args'])) {
            foreach ($actions['args'] as $arg) {
                if (!isset($params[$arg]) || $params[$arg] === '') {
                    throw new LengowException(
                        $this->_dataHelper->setLogMessage("can't send action: %1 is required", [$arg])
                    );
                }
            }
        }
        // clean empty optional arguments
        if (isset($actions['optional_args'])) {
            foreach ($actions['optional_args'] as $arg) {
                if (isset($params[$arg]) && $params[$arg] === '') {
                    unset($params[$arg]);
                }
            }
        }
        return $params;
    }

    /**
     * Match carrier's name with accepted values
     *
     * @param string $code carrier code
     * @param string $title carrier title
     *
     * @return string
     */
    private function _matchCarrier($code, $title)
    {
        if (!empty($this->carriers)) {
            $codeCleaned = $this->_cleanString($code);
            $titleCleaned = $this->_cleanString($title);
            // search by Magento carrier code
            // strict search
            $result = $this->_searchCarrierCode($codeCleaned);
            if (!$result) {
                // approximate search
                $result = $this->_searchCarrierCode($codeCleaned, false);
            }
            // search by Magento carrier title if it is different from the Magento carrier code
            if (!$result && $titleCleaned !== $codeCleaned) {
                // strict search
                $result = $this->_searchCarrierCode($titleCleaned);
                if (!$result) {
                    // approximate search
                    $result = $this->_searchCarrierCode($titleCleaned, false);
                }
            }
            if ($result) {
                return $result;
            }
        }
        // no match
        if ($code === Track::CUSTOM_CARRIER_CODE) {
            return $title;
        }
        return $code;
    }

    /**
     * Cleaning a string before search
     *
     * @param string $string string to clean
     *
     * @return string
     */
    private function _cleanString($string)
    {
        $cleanFilters = [' ', '-', '_', '.'];
        return strtolower(str_replace($cleanFilters, '', trim($string)));
    }

    /**
     * Search carrier code in a chain
     *
     * @param string $search string cleaned to search
     * @param boolean $strict strict search
     *
     * @return string|false
     */
    private function _searchCarrierCode($search, $strict = true)
    {
        $result = false;
        foreach ($this->carriers as $key => $label) {
            $keyCleaned = $this->_cleanString($key);
            $labelCleaned = $this->_cleanString($label);
            // search on the carrier key
            $found = $this->_searchValue($keyCleaned, $search, $strict);
            // search on the carrier label if it is different from the key
            if (!$found && $labelCleaned !== $keyCleaned) {
                $found = $this->_searchValue($labelCleaned, $search, $strict);
            }
            if ($found) {
                $result = $key;
            }
        }
        return $result;
    }

    /**
     * Strict or approximate search for a chain
     *
     * @param string $pattern search pattern
     * @param string $subject string to search
     * @param boolean $strict strict search
     *
     * @return boolean
     */
    private function _searchValue($pattern, $subject, $strict = true)
    {
        if ($strict) {
            $found = $pattern === $subject;
        } else {
            $found = (bool) preg_match('`.*?' . $pattern . '.*?`i', $subject);
        }
        return $found;
    }
}
