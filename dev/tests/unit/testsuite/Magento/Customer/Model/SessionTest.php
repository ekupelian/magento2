<?php
/**
 * Unit test for session \Magento\Customer\Model\Session
 *
 * @copyright Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 */
namespace Magento\Customer\Model;

class SessionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $_storageMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $_eventManagerMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $_httpContextMock;

    /**
     * @var \Magento\Framework\UrlFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $urlFactoryMock;

    /**
     * @var \Magento\Customer\Model\CustomerFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $customerFactoryMock;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $customerRepositoryMock;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_model;

    protected function setUp()
    {
        $this->_storageMock = $this->getMock(
            'Magento\Customer\Model\Session\Storage',
            ['getIsCustomerEmulated', 'getData', 'unsIsCustomerEmulated', '__sleep', '__wakeup'],
            [],
            '',
            false
        );
        $this->_eventManagerMock = $this->getMock('Magento\Framework\Event\ManagerInterface', [], [], '', false);
        $this->_httpContextMock = $this->getMock('Magento\Framework\App\Http\Context', [], [], '', false);
        $this->urlFactoryMock = $this->getMock('Magento\Framework\UrlFactory', [], [], '', false);
        $this->customerFactoryMock = $this->getMockBuilder('Magento\Customer\Model\CustomerFactory')
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $this->customerRepositoryMock = $this->getMock(
            'Magento\Customer\Api\CustomerRepositoryInterface',
            [],
            [],
            '',
            false
        );
        $helper = new \Magento\TestFramework\Helper\ObjectManager($this);
        $this->_model = $helper->getObject(
            'Magento\Customer\Model\Session',
            [
                'customerFactory' => $this->customerFactoryMock,
                'storage' => $this->_storageMock,
                'eventManager' => $this->_eventManagerMock,
                'httpContext' => $this->_httpContextMock,
                'urlFactory' => $this->urlFactoryMock,
                'customerRepository' => $this->customerRepositoryMock,
            ]
        );
    }

    public function testSetCustomerAsLoggedIn()
    {
        $customer = $this->getMock('Magento\Customer\Model\Customer', [], [], '', false);
        $customerDto = $this->getMock('Magento\Customer\Api\Data\CustomerInterface', [], [], '', false);
        $customer->expects($this->any())
            ->method('getDataModel')
            ->will($this->returnValue($customerDto));

        $this->_eventManagerMock->expects($this->at(0))
            ->method('dispatch')
            ->with('customer_login', ['customer' => $customer]);
        $this->_eventManagerMock->expects($this->at(1))
            ->method('dispatch')
            ->with('customer_data_object_login', ['customer' => $customerDto]);

        $_SESSION = [];
        $this->_model->setCustomerAsLoggedIn($customer);
        $this->assertSame($customer, $this->_model->getCustomer());
    }

    public function testSetCustomerDataAsLoggedIn()
    {
        $customer = $this->getMock('Magento\Customer\Model\Customer', [], [], '', false);
        $customerDto = $this->getMock('Magento\Customer\Api\Data\CustomerInterface', [], [], '', false);

        $this->customerFactoryMock->expects($this->once())
            ->method('create')
            ->will($this->returnValue($customer));
        $customer->expects($this->once())
            ->method('updateData')
            ->with($customerDto)
            ->will($this->returnSelf());

        $this->_eventManagerMock->expects($this->at(0))
            ->method('dispatch')
            ->with('customer_login', ['customer' => $customer]);
        $this->_eventManagerMock->expects($this->at(1))
            ->method('dispatch')
            ->with('customer_data_object_login', ['customer' => $customerDto]);

        $this->_model->setCustomerDataAsLoggedIn($customerDto);
        $this->assertSame($customer, $this->_model->getCustomer());
    }

    public function testAuthenticate()
    {
        $urlMock = $this->getMock('Magento\Framework\Url', [], [], '', false);
        $urlMock->expects($this->exactly(2))
            ->method('getUrl')
            ->will($this->returnValue(''));
        $urlMock->expects($this->once())
            ->method('getRebuiltUrl')
            ->will($this->returnValue(''));
        $this->urlFactoryMock->expects($this->exactly(3))
            ->method('create')
            ->will($this->returnValue($urlMock));

        $responseMock = $this->getMock('Magento\Framework\App\Response\Http', [], [], '', false);
        $responseMock->expects($this->once())
            ->method('setRedirect')
            ->with('')
            ->will($this->returnValue(''));

        $actionMock = $this->getMock('Magento\Framework\App\Action\Action', [], [], '', false);
        $actionMock->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($responseMock));

        $this->assertFalse($this->_model->authenticate($actionMock));
    }

    public function testLoginById()
    {
        $customerId = 1;

        $customerDataMock = $this->prepareLoginDataMock($customerId);

        $this->customerRepositoryMock->expects($this->once())
            ->method('getById')
            ->with($this->equalTo($customerId))
            ->will($this->returnValue($customerDataMock));

        $this->assertTrue($this->_model->loginById($customerId));
    }

    /**
     * @param $customerId
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function prepareLoginDataMock($customerId)
    {
        $customerDataMock = $this->getMock('Magento\Customer\Api\Data\CustomerInterface', [], [], '', false);
        $customerDataMock->expects($this->once())
            ->method('getId')
            ->will($this->returnValue($customerId));

        $customerMock = $this->getMock('Magento\Customer\Model\Customer', [], [], '', false);
        $customerMock->expects($this->once())
            ->method('getId')
            ->will($this->returnValue($customerId));
        $customerMock->expects($this->once())
            ->method('isConfirmationRequired')
            ->will($this->returnValue(true));
        $customerMock->expects($this->never())
            ->method('getConfirmation')
            ->will($this->returnValue($customerId));

        $this->customerFactoryMock->expects($this->once())
            ->method('create')
            ->will($this->returnValue($customerMock));
        $customerMock->expects($this->once())
            ->method('updateData')
            ->with($customerDataMock)
            ->will($this->returnSelf());

        $this->_httpContextMock->expects($this->exactly(3))
            ->method('setValue');
        return $customerDataMock;
    }

    /**
     * @param bool $expectedResult
     * @param bool $isCustomerIdValid
     * @param bool $isCustomerEmulated
     * @dataProvider getIsLoggedInDataProvider
     */
    public function testIsLoggedIn($expectedResult, $isCustomerIdValid, $isCustomerEmulated)
    {
        $customerId = 1;
        $this->_storageMock->expects($this->any())->method('getData')->with('customer_id')
            ->will($this->returnValue($customerId));

        if ($isCustomerIdValid) {
            $this->customerRepositoryMock->expects($this->once())
                ->method('getById')
                ->with($customerId);
        } else {
            $this->customerRepositoryMock->expects($this->once())
                ->method('getById')
                ->with($customerId)
                ->will($this->throwException(new \Exception('Customer ID is invalid.')));
        }
        $this->_storageMock->expects($this->any())->method('getIsCustomerEmulated')
            ->will($this->returnValue($isCustomerEmulated));
        $this->assertEquals($expectedResult, $this->_model->isLoggedIn());
    }

    /**
     * @return array
     */
    public function getIsLoggedInDataProvider()
    {
        return [
            ['expectedResult' => true, 'isCustomerIdValid' => true, 'isCustomerEmulated' => false],
            ['expectedResult' => false, 'isCustomerIdValid' => true, 'isCustomerEmulated' => true,],
            ['expectedResult' => false, 'isCustomerIdValid' => false, 'isCustomerEmulated' => false,],
            ['expectedResult' => false, 'isCustomerIdValid' => false, 'isCustomerEmulated' => true,],
        ];
    }

    public function testSetCustomerRemovesFlagThatShowsIfCustomerIsEmulated()
    {
        $customerMock = $this->getMock('Magento\Customer\Model\Customer', [], [], '', false);
        $this->_storageMock->expects($this->once())->method('unsIsCustomerEmulated');
        $this->_model->setCustomer($customerMock);
    }
}
