<?php

use Adyen\Payment\Cron\Providers\PayByLinkExpiredPaymentOrdersProvider;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\ObjectFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Data\Collection;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Interceptor as OrderPayment;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class PayByLinkExpiredPaymentOrdersProviderTest extends TestCase
{
    protected PayByLinkExpiredPaymentOrdersProvider $payByLinkExpiredPaymentOrdersProvider;
    protected OrderRepositoryInterface|MockObject $orderRepositoryMock;
    protected OrderPaymentRepositoryInterface|MockObject $orderPaymentRepositoryMock;
    protected Collection|MockObject $orderPaymentCollectionMock;
    protected Collection|MockObject $orderCollectionMock;

    public function setUp(): void
    {
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->orderPaymentRepositoryMock = $this->createMock(OrderPaymentRepositoryInterface::class);
        $objectFactoryMock = $this->createMock(ObjectFactory::class);
        $filterBuilder = new FilterBuilder($objectFactoryMock);
        $filterGroupBuilder = new FilterGroupBuilder($objectFactoryMock, $filterBuilder);
        $searchCriteriaBuilder = new SearchCriteriaBuilder($objectFactoryMock, $filterGroupBuilder, $filterBuilder);
        $abstractSimpleObject = $this->createMock(SearchCriteriaInterface::class);
        $objectFactoryMock->method('create')->willReturn($abstractSimpleObject);

        $this->orderPaymentCollectionMock = $this->createMock(Collection::class);
        $this->orderPaymentRepositoryMock->method('getList')->willReturn($this->orderPaymentCollectionMock);
        $this->orderCollectionMock = $this->createMock(Collection::class);
        $this->orderRepositoryMock->method('getList')->willReturn($this->orderCollectionMock);

        $this->payByLinkExpiredPaymentOrdersProvider = new PayByLinkExpiredPaymentOrdersProvider(
            $this->orderRepositoryMock,
            $this->orderPaymentRepositoryMock,
            $searchCriteriaBuilder,
            $filterBuilder,
            $filterGroupBuilder
        );
    }

    public function testProvideExpiredOrdersReturnsNoOrdersSuccessfully()
    {
        $this->orderPaymentCollectionMock->method('getItems')->willReturn([]);
        $this->orderCollectionMock->method('getItems')->willReturn([]);
        $expiredPaymentLinksOrders = $this->payByLinkExpiredPaymentOrdersProvider->provide();
        $this->assertEqualsCanonicalizing($expiredPaymentLinksOrders, []);
    }

    public function testProvideExpiredOrdersReturnsOrdersSuccessfully()
    {
        $formattedYesterdayDate = (new \DateTime())->modify('-1 day')->format(DATE_ATOM);
        $formattedTomorrowDate = (new \DateTime())->modify('-1 day')->format(DATE_ATOM);
        $expiredOrderPaymentMock = $this->createMock(OrderPayment::class);
        $nonExpiredOrderPaymentMock = $this->createMock(OrderPayment::class);
        $expiredOrderPaymentMock
            ->method('getAdditionalInformation')
            ->willReturn([AdyenPayByLinkConfigProvider::EXPIRES_AT_KEY => $formattedYesterdayDate]);
        $nonExpiredOrderPaymentMock
            ->method('getAdditionalInformation')
            ->willReturn([AdyenPayByLinkConfigProvider::EXPIRES_AT_KEY => $formattedTomorrowDate]);

        $orderPayments = [
            $expiredOrderPaymentMock,
            $nonExpiredOrderPaymentMock
        ];

        $orderWithNewStateMock = $this->createMock(OrderInterface::class);
        $expectedOrders = [$orderWithNewStateMock];

        $this->orderPaymentCollectionMock->method('getItems')->willReturn($orderPayments);
        $this->orderCollectionMock->method('getItems')->willReturn($expectedOrders);
        $expiredPaymentLinksOrders = $this->payByLinkExpiredPaymentOrdersProvider->provide();
        $this->assertEqualsCanonicalizing($expiredPaymentLinksOrders, $expectedOrders);
    }
}