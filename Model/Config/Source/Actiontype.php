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

namespace Lengow\Connector\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Lengow\Connector\Model\Import\Action as LengowAction;

class Actiontype implements ArrayInterface
{
    /**
     * Return array of lengow status
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => LengowAction::TYPE_SHIP, 'label' => __('ship')],
            ['value' => LengowAction::TYPE_CANCEL, 'label' => __('cancel')],
        ];
    }
}
