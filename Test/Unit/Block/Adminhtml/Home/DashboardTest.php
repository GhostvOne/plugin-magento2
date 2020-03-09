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
 * @subpackage  Test
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Test\Unit\Block\Adminhtml\Home;

use Lengow\Connector\Block\Adminhtml\Home\Dashboard;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Lengow\Connector\Test\Unit\Fixture;

class DashboardTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Lengow\Connector\Block\Adminhtml\Home\Dashboard
     */
    protected $_dashboard;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     */
    public function setUp()
    {
        $objectManager = new ObjectManager($this);
        $contextMock = $this->getMock('Magento\Backend\Block\Template\Context', [], [], '', false);
        $this->_dashboard = $objectManager->getObject(Dashboard::class, ['context' => $contextMock]);
    }

    public function testClassInstantiation()
    {
        $this->assertInstanceOf(
            Dashboard::class,
            $this->_dashboard,
            '[Test Class Instantiation] Check class instantiation'
        );
    }

    /**
     * @covers \Lengow\Connector\Block\Adminhtml\Home\Dashboard::getNumberOrderToBeSent
     */
    public function testGetNumberOrderToBeSent()
    {
        $fixture = New Fixture();
        $this->assertFalse(
            $this->_dashboard->getNumberOrderToBeSent(),
            '[Test Get Number Order To Be Sent] Check if return is valid when statistics is empty'
        );
        $fixture->setPrivatePropertyValue($this->_dashboard, ['_numberOrderToBeSent'], [18]);
        $this->assertEquals(
            18,
            $this->_dashboard->getNumberOrderToBeSent(),
            '[Test Get Number Order To Be Sent] Check if return is valid'
        );
    }
}
