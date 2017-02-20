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

namespace Lengow\Connector\Model;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\CatalogInventory\Model\Configuration as CatalogInventoryConfiguration;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Lengow\Connector\Helper\Data as DataHelper;
use Lengow\Connector\Helper\Config as ConfigHelper;
use Lengow\Connector\Model\Export\Feed;
use Lengow\Connector\Model\Export\Product;
use Lengow\Connector\Model\Exception as LengowException;

/**
 * Lengow export
 */
class Export
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface Magento store manager instance
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime Magento datetime instance
     */
    protected $_dateTime;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface Magento scope config instance
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Source\Status Magento product status instance
     */
    protected $_productStatus;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory Magento product collection factory
     */
    protected $_productCollectionFactory;

    /**
     * @var \Lengow\Connector\Helper\Data Lengow data helper instance
     */
    protected $_dataHelper;

    /**
     * @var \Lengow\Connector\Helper\Config Lengow config helper instance
     */
    protected $_configHelper;

    /**
     * @var \Lengow\Connector\Model\Export\Feed Lengow feed instance
     */
    protected $_feed;

    /**
     * @var \Lengow\Connector\Model\Export\Product Lengow product instance
     */
    protected $_product;

    /**
     * @var array available formats for export
     */
    protected $_availableFormats = [
        'csv',
        'json',
        'yaml',
        'xml',
    ];

    /**
     * @var array available formats for export
     */
    protected $_availableProductTypes = [
        'configurable',
        'simple',
        'downloadable',
        'grouped',
        'virtual',
    ];

    /**
     * @var array default fields for export
     */
    protected $_defaultFields = [
        'id'                             => 'id',
        'sku'                            => 'sku',
        'name'                           => 'name',
        'child_name'                     => 'child_name',
        'quantity'                       => 'quantity',
        'status'                         => 'status',
        'category'                       => 'category',
        'url'                            => 'url',
        'price_excl_tax'                 => 'price_excl_tax',
        'price_incl_tax'                 => 'price_incl_tax',
        'price_before_discount_excl_tax' => 'price_before_discount_excl_tax',
        'price_before_discount_incl_tax' => 'price_before_discount_incl_tax',
        'discount_amount'                => 'discount_amount',
        'discount_percent'               => 'discount_percent',
        'discount_start_date'            => 'discount_start_date',
        'discount_end_date'              => 'discount_end_date',
        'shipping_method'                => 'shipping_method',
        'shipping_cost'                  => 'shipping_cost',
        'currency'                       => 'currency',
        'image_default'                  => 'image_default',
        'image_url_1'                    => 'image_url_1',
        'image_url_2'                    => 'image_url_2',
        'image_url_3'                    => 'image_url_3',
        'image_url_4'                    => 'image_url_4',
        'image_url_5'                    => 'image_url_5',
        'image_url_6'                    => 'image_url_6',
        'image_url_7'                    => 'image_url_7',
        'image_url_8'                    => 'image_url_8',
        'image_url_9'                    => 'image_url_9',
        'image_url_10'                   => 'image_url_10',
        'type'                           => 'type',
        'parent_id'                      => 'parent_id',
        'variation'                      => 'variation',
        'language'                       => 'language',
        'description'                    => 'description',
        'description_html'               => 'description_html',
        'description_short'              => 'description_short',
        'description_short_html'         => 'description_short_html',
    ];

    /**
     * @var \Magento\Store\Model\Store\Interceptor Magento store instance
     */
    protected $_store;

    /**
     * @var integer Magento store id
     */
    protected $_storeId;

    /**
     * @var integer amount of products to export
     */
    protected $_limit;

    /**
     * @var integer offset of total product
     */
    protected $_offset;

    /**
     * @var string format to return
     */
    protected $_format;

    /**
     * @var boolean stream return
     */
    protected $_stream;

    /**
     * @var string currency iso code for conversion
     */
    protected $_currency;

    /**
     * @var boolean export Lengow selection
     */
    protected $_selection;

    /**
     * @var boolean export out of stock product
     */
    protected $_outOfStock;

    /**
     * @var boolean include active products
     */
    protected $_inactive;

    /**
     * @var boolean see log or not
     */
    protected $_logOutput;

    /**
     * @var array export product types
     */
    protected $_productTypes;

    /**
     * @var array product ids to be exported
     */
    protected $_productIds;

    /**
     * @var boolean update export date.
     */
    protected $_updateExportDate;

    /**
     * @var string export type (manual, cron or magento cron)
     */
    protected $_exportType;

    /**
     * Constructor
     *
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager Magento store manager instance
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime Magento datetime instance
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig Magento scope config instance
     * @param \Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus Magento product status instance
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Lengow\Connector\Helper\Data $dataHelper Lengow data helper instance
     * @param \Lengow\Connector\Helper\Config $configHelper Lengow config helper instance
     * @param \Lengow\Connector\Model\Export\Feed $feed Lengow feed instance
     * @param \Lengow\Connector\Model\Export\Product $product Lengow product instance
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        DateTime $dateTime,
        ScopeConfigInterface $scopeConfig,
        ProductStatus $productStatus,
        ProductCollectionFactory $productCollectionFactory,
        DataHelper $dataHelper,
        ConfigHelper $configHelper,
        Feed $feed,
        Product $product
    )
    {
        $this->_storeManager = $storeManager;
        $this->_dataHelper = $dataHelper;
        $this->_configHelper = $configHelper;
        $this->_feed = $feed;
        $this->_product = $product;
        $this->_dateTime = $dateTime;
        $this->_scopeConfig = $scopeConfig;
        $this->_productStatus = $productStatus;
        $this->_productCollectionFactory = $productCollectionFactory;
    }

    /**
     * Init a new export
     *
     * @param array $params optional options for init
     * integer store_id     ID of store
     * integer limit        The number of product to be exported
     * integer offset       From what product export
     * string  mode         Export mode => size: display only exported products, total: display all products
     * string  format       Export Format (csv|yaml|xml|json)
     * string  type         Type of export (manual, cron or magento cron)
     * string  product_type Type(s) of product
     * string  currency     Currency for export
     * string  product_ids  Ids product to export
     * boolean inactive     Export disabled products (1) | Export only enabled products (0)
     * boolean out_of_stock Export product in stock and out stock (1) | Export Only in stock product (0)
     * boolean selection    Export selected product (1) | Export all products (0)
     * boolean stream       Display file when call script (1) | Save File (0)
     * boolean log_output   See logs (only when stream = 0) (1) | no logs (0)
     */
    public function init($params)
    {
        $this->_storeId = isset($params['store_id']) ? (int)$params['store_id'] : 0;
        $this->_store = $this->_storeManager->getStore($this->_storeId);
        $this->_limit = isset($params['limit']) ? (int) $params['limit'] : 0;
        $this->_offset = isset($params['offset']) ? (int) $params['offset'] : 0;
        $this->_stream = isset($params['stream'])
            ? (bool)$params['stream']
            : !(bool)$this->_configHelper->get('file_enable', $this->_storeId);
        $this->_selection = isset( $params['selection'] )
            ? (bool)$params['selection']
            : (bool)$this->_configHelper->get('selection_enable', $this->_storeId);
        $this->_inactive = isset( $params['inactive'] )
            ? (bool)$params['inactive']
            : (bool)$this->_configHelper->get('product_status', $this->_storeId);
        $this->_outOfStock = isset( $params['out_of_stock'] ) ? $params['out_of_stock'] : true;
        $this->_updateExportDate = isset($params['update_export_date']) ? (bool)$params['update_export_date'] : true;
        $this->_format = $this->_setFormat(isset($params['format']) ? $params['format'] : 'csv');
        $this->_productIds = $this->_setProductIds(isset($params['product_ids']) ? $params['product_ids'] : false);
        $this->_productTypes = $this->_setProductTypes(
            isset($params['product_types']) ? $params['product_types'] : false
        );
        $this->_logOutput = $this->_setLogOutput(isset($params['log_output']) ? $params['log_output'] : true);
        $this->_currency = $this->_setCurrency(isset($params['currency']) ? $params['currency'] : false);
        $this->_exportType = $this->_setType(isset($params['type']) ? $params['type'] : false);
    }

    /**
     * Get total available products
     *
     * @return integer
     **/
    public function getTotalProduct()
    {
        $productCollection = $this->_productCollectionFactory->create()
            ->setStoreId($this->_storeId)
            ->addStoreFilter($this->_storeId)
            ->addAttributeToFilter('type_id', ['nlike' => 'bundle']);
        return $productCollection->getSize();
    }

    /**
     * Get total exported products
     *
     * @return integer
     **/
    public function getTotalExportedProduct()
    {
        $productCollection = $this->_getQuery();
        return $productCollection->getSize();
    }

    /**
     * Execute the export
     **/
    public function exec()
    {
        try {
            // start chrono
            $timeStart = $this->_microtimeFloat();
            // Clean logs
            $this->_dataHelper->cleanLog();
            $this->_dataHelper->log(
                'Export',
                $this->_dataHelper->setLogMessage('## start %1 export ##', [$this->_exportType]),
                $this->_logOutput
            );
            $this->_dataHelper->log(
                'Export',
                $this->_dataHelper->setLogMessage(
                    'start export in store %1 (%2)',
                    [$this->_store->getName(), $this->_storeId]
                ),
                $this->_logOutput
            );
            // get fields to export
            $fields = $this->_getFields();
            // get products to be exported
            $productCollection = $this->_getQuery();
            $products = $productCollection->getData();
            $this->_dataHelper->log(
                'Export',
                $this->_dataHelper->setLogMessage('%1 product(s) found', [count($products)]),
                $this->_logOutput
            );
            $this->_export($products, $fields);
            if ($this->_updateExportDate) {
                $this->_configHelper->set('last_export', $this->_dateTime->gmtTimestamp(), $this->_storeId);
            }
            $timeEnd = $this->_microtimeFloat();
            $this->_dataHelper->log(
                'Export',
                $this->_dataHelper->setLogMessage('memory usage: %1 Mb', [round(memory_get_usage() / 1000000, 2)]),
                $this->_logOutput
            );
            $this->_dataHelper->log(
                'Export',
                $this->_dataHelper->setLogMessage('execution time: %1 seconds', [$timeEnd - $timeStart]),
                $this->_logOutput
            );
            $this->_dataHelper->log(
                'Export',
                $this->_dataHelper->setLogMessage('## end %1 export ##', [$this->_exportType]),
                $this->_logOutput
            );
        } catch (LengowException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $errorMessage = '[Magento error] "'.$e->getMessage().'" '.$e->getFile().' | '.$e->getLine();
        }
        if (isset($errorMessage)) {
            $decodedMessage = $this->_dataHelper->decodeLogMessage($errorMessage, false);
            $this->_dataHelper->log(
                'Export',
                $this->_dataHelper->setLogMessage('export failed - %1', [$decodedMessage]),
                $this->_logOutput
            );
        }
    }

    /**
     * Export products
     *
     * @param array $products list of products to be exported
     * @param array $fields list of fields to export
     *
     * @throws LengowException Export folder not writable
     */
    protected function _export($products, $fields)
    {
        $isFirst = true;
        $productCount = 0;
        // Get modulo for export counter
        $productModulo = $this->_getProductModulo(count($products));
        // Get the maximum of character for yaml format
        $maxCharacter = $this->_getMaxCharacterSize($fields);
        // Get feed to export
        $this->_feed->init(
            [
                'stream'     => $this->_stream,
                'format'     => $this->_format,
                'store_code' => $this->_store->getCode()
            ]
        );
        $this->_feed->write('header', $fields);
        foreach ($products as $product) {
            $productDatas = [];
            $this->_product->init(
                [
                    'product_id' => $product['entity_id'],
                    'currency'   => $this->_currency
                ]
            );
            foreach ($fields as $field) {
                if (isset($this->_defaultFields[$field])) {
                    $productDatas[$field] = $this->_product->getData($this->_defaultFields[$field]);
                } else {
                    $productDatas[$field] = $this->_product->getData($field);
                }
            }
            // write product data
            $this->_feed->write('body', $productDatas, $isFirst, $maxCharacter);
            $productCount++;
            $this->_setCounterLog($productModulo, $productCount);
            // clean data for next product
            $this->_product->clean();
            unset($productDatas);
            $isFirst = false;
        }
        $success = $this->_feed->end();
        if (!$success) {
            throw new LengowException(
                $this->_dataHelper->setLogMessage('unable to access the folder %1', [$this->_feed->getFolderPath()])
            );
        }
        // Product counter
        $allCounter = $this->_product->getAllCounter();
        $this->_dataHelper->log(
            'Export',
            $this->_dataHelper->setLogMessage(
                '%1 product(s) exported, (%2 simple product(s), %3 configurable product(s),
                %4 grouped product(s), %5 virtual product(s), %6 downloadable product(s))',
                [
                    $allCounter['total'],
                    $allCounter['simple_enable'],
                    $allCounter['configurable'],
                    $allCounter['grouped'],
                    $allCounter['virtual'],
                    $allCounter['downloadable'],
                ]
            ),
            $this->_logOutput
        );
        // Warning for simple product associated with configurable products disabled
        if ($allCounter['simple_disabled'] > 0) {
            $this->_dataHelper->log(
                'Export',
                $this->_dataHelper->setLogMessage(
                    'WARNING! %1 simple product(s) associated with configurable products are disabled',
                    [$allCounter['simple_disabled']]
                ),
                $this->_logOutput
            );
        }
        // Link generation
        if (!$this->_stream) {
            $feedUrl = $this->_feed->getUrl();
            if ($feedUrl) {
                $this->_dataHelper->log(
                    'Export',
                    $this->_dataHelper->setLogMessage(
                        'the export for the store %1 (%2) generated the following file: %3',
                        [$this->_store->getName(), $this->_storeId, $feedUrl]
                    ),
                    $this->_logOutput
                );
            }
        }
    }

    /**
     * Get fields to export
     *
     * @return array
     */
    protected function _getFields()
    {
        $fields = [];
        foreach ($this->_defaultFields as $key => $defaultField) {
            $fields[] = $key;
        }
        $selectedAttributes = $this->_configHelper->getSelectedAttributes($this->_storeId);
        foreach ($selectedAttributes as $selectedAttribute) {
            $fields[] = $selectedAttribute;
        }
        return $fields;
    }

    /**
     * Set format to export
     *
     * @param string $format export format
     *
     * @return string
     */
    protected function _setFormat($format)
    {
        return !in_array($format, $this->_availableFormats) ? 'csv' : $format;
    }

    /**
     * Set product ids to export
     *
     * @param boolean|array $productIds product ids to export
     *
     * @return array
     */
    protected function _setProductIds($productIds)
    {
        $ids = [];
        if ($productIds) {
            $exportedIds = explode(',', $productIds);
            foreach ($exportedIds as $id) {
                if (is_numeric($id) && $id > 0) {
                    $ids[] = (int)$id;
                }
            }
        }
        return $ids;
    }

    /**
     * Set product types to export
     *
     * @param boolean|array $productTypes product types to export
     *
     * @return array
     */
    protected function _setProductTypes($productTypes)
    {
        $types = [];
        if ($productTypes) {
            $exportedTypes = explode(',', $productTypes);
            foreach ($exportedTypes as $type) {
                if (in_array($type, $this->_availableProductTypes)) {
                    $types[] = $type;
                }
            }
        }
        if (count($types) == 0) {
            $types = explode(',', $this->_configHelper->get('product_type', $this->_storeId));
        }
        return $types;
    }

    /**
     * Set Log output for export
     *
     * @param boolean $logOutput see logs or not
     *
     * @return boolean
     */
    protected function _setLogOutput($logOutput)
    {
        return $this->_stream ? false : $logOutput;
    }

    /**
     * Set currency for export
     *
     * @param boolean|string $currency see logs or not
     *
     * @return string
     */
    protected function _setCurrency($currency)
    {
        $availableCurrencies = $this->_store->getAvailableCurrencyCodes();
        if (!$currency || !in_array($currency, $availableCurrencies)) {
            $currency = $this->_store->getCurrentCurrencyCode();
        }
        return $currency;
    }

    /**
     * Set export type
     *
     * @param boolean|string $type export type (manual, cron or magento cron)
     *
     * @return string
     */
    protected function _setType($type)
    {
        if (!$type) {
            $type = $this->_updateExportDate ? 'cron' : 'manual';
        }
        return $type;
    }

    /**
     * Set the product counter log
     *
     * @param integer $productModulo
     * @param integer $productCount
     *
     */
    protected function _setCounterLog($productModulo, $productCount)
    {
        $logMessage = $this->_dataHelper->setLogMessage('%1 product(s) exported', [$productCount]);
        // Save 10 logs maximum in database
        if ($productCount % $productModulo == 0) {
            $this->_dataHelper->log('Export', $logMessage);
        }
        if (!$this->_stream && $this->_logOutput) {
            if ($productCount % 50 == 0) {
                $countMessage = $this->_dataHelper->decodeLogMessage($logMessage, false);
                echo '[Export] '.$countMessage.'<br />';
            }
            flush();
        }
    }

    /**
     * Get product modulo for counter log
     *
     * @param integer $productTotal number of product to export
     *
     * @return integer
     */
    protected function _getProductModulo($productTotal)
    {
        $productModulo = (int) ($productTotal / 10);
        return $productModulo < 50 ? 50 : $productModulo;
    }

    /**
     * Get max character size for yaml format
     *
     * @param array $fields list of fields to export
     *
     * @return integer
     */
    protected function _getMaxCharacterSize($fields)
    {
        $maxCharacter = 0;
        foreach ($fields as $field) {
            if (strlen($field) > $maxCharacter) {
                $maxCharacter = strlen($field);
            }
        }
        return $maxCharacter;
    }

    /**
     * Get microtime float
     */
    protected function _microtimeFloat()
    {
        list($usec, $sec) = explode(' ', microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * Get products collection for export
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected function _getQuery()
    {
        // Export only specific products types for one store
        $productCollection = $this->_productCollectionFactory->create()
            ->setStoreId($this->_storeId)
            ->addStoreFilter($this->_storeId)
            ->addAttributeToFilter('type_id', ['in' => $this->_productTypes]);
        // Export only enabled products
        if (!$this->_inactive) {
            $productCollection->addAttributeToFilter('status', ['in' => $this->_productStatus->getVisibleStatusIds()]);
        }
        // Export only selected products
        if ($this->_selection) {
            $productCollection->addAttributeToFilter('lengow_product', 1);
        }
        // Export out of stock products
        if (!$this->_outOfStock) {
            $config = (int)$this->_scopeConfig->isSetFlag(CatalogInventoryConfiguration::XML_PATH_MANAGE_STOCK);
            $condition = '({{table}}.`is_in_stock` = 1) '
                .' OR IF({{table}}.`use_config_manage_stock` = 1, '.$config.', {{table}}.`manage_stock`) = 0';
            $productCollection->joinTable(
                'cataloginventory_stock_item',
                'product_id=entity_id',
                ['qty' => 'qty', 'is_in_stock' => 'is_in_stock'],
                $condition
            );
        }
        // Export specific products with id
        if (count($this->_productIds) > 0) {
            $productCollection->addAttributeToFilter('entity_id', ['in' => $this->_productIds]);
        }
        // Export with limit & offset
        if ($this->_limit > 0) {
            if ($this->_offset > 0) {
                $productCollection->getSelect()->limit($this->_limit, $this->_offset);
            } else {
                $productCollection->getSelect()->limit($this->_limit);
            }
        }
        return $productCollection;
    }
}
