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
 * @subpackage  UI
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Lengow\Connector\Model\Import\Order as LengowOrder;

class LengowOrderStatus extends Column
{
    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context Magento ui context instance
     * @param \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory Magento ui factory instance
     * @param array $components component data
     * @param array $data additional params
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource row data source
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        $dataSource = parent::prepareDataSource($dataSource);
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (!is_null($item['order_lengow_state'])) {
                    $status = $item['order_lengow_state'];
                    switch ($status) {
                        case LengowOrder::STATE_ACCEPTED:
                            $translation = 'Accepted';
                            break;
                        case LengowOrder::STATE_WAITING_SHIPMENT:
                            $translation = 'Awaiting shipment';
                            break;
                        case LengowOrder::STATE_SHIPPED:
                            $translation = 'Shipped';
                            break;
                        case LengowOrder::STATE_REFUNDED:
                            $translation = 'Refunded';
                            break;
                        case LengowOrder::STATE_CLOSED:
                            $translation = 'Closed';
                            break;
                        case LengowOrder::STATE_CANCELED:
                            $translation = 'Canceled';
                            break;
                        default:
                            $translation = $status;
                            break;
                    }
                    $item['order_lengow_state'] = '<span class="lgw-label lgw-label-' . $status . '">'
                        . __($translation) . '</span>';
                }
            }
        }
        return $dataSource;
    }
}
