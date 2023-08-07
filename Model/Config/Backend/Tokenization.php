<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Method\PaymentMethodInterface;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Helper\Data;

/**
 * Class Moto
 * @package Adyen\Payment\Model\Config\Backend
 */
class Tokenization extends Value
{
    private SerializerInterface $serializer;
    private EncryptorInterface $encryptor;
    protected Random $mathRandom;
    private PaymentMethods $paymentMethodsHelper;
    private Data $dataHelper;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        Random $mathRandom,
        SerializerInterface $serializer,
        EncryptorInterface $encryptor,
        PaymentMethods $paymentMethodsHelper,
        Data $dataHelper,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->encryptor = $encryptor;
        $this->mathRandom = $mathRandom;
        $this->serializer = $serializer;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->dataHelper = $dataHelper;

        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        $value = $this->getValue();
        if (!is_array($value)) {
            return $this;
        }
        $result = [];
        foreach ($value as $data) {
            if (!$data || !is_array($data) || count($data) < 2) {
                continue;
            }

            $paymentMethodCode = $data['payment_method_code'];
            $name = $data['name'];
            $enabled = $data['enabled'];
            $recurringProcessingModel = $data['recurring_processing_model'];

            $result[$paymentMethodCode] = array(
                "name" => $name,
                "enabled" => $enabled,
                "recurringProcessingModel" => $recurringProcessingModel
            );
        }

        $this->setValue($this->serializer->serialize($result));
        return $this;
    }

    protected function _afterLoad()
    {
        $value = $this->getValue();

        if (empty($value)) {
            $value = $this->buildPaymentMethodArray();
        } else {
            $value = $this->serializer->unserialize($value);
            $value = $this->encodeArrayFieldValue($value);
        }

        $this->setValue($value);
        return $this;
    }

    protected function encodeArrayFieldValue(array $value)
    {
        $result = [];

        foreach ($value as $paymentMethodCode => $items) {
            $resultId = $this->mathRandom->getUniqueHash('_');
            $result[$resultId] = [
                'payment_method_code' => $paymentMethodCode,
                'name' => $items['name'],
                'enabled' => $items['enabled'],
                'recurring_processing_model' => $items['recurringProcessingModel']
            ];
        }

        $result = $this->compareConfigurationArrayValues($result);

        return $result;
    }

    private function buildPaymentMethodArray(): array
    {
        $adyenPaymentMethods = $this->paymentMethodsHelper->getAdyenPaymentMethods();

        $result = [];

        foreach ($adyenPaymentMethods as $adyenPaymentMethod) {
            $methodInstance = $this->dataHelper->getMethodInstance($adyenPaymentMethod);

            if ($adyenPaymentMethod === AdyenCcConfigProvider::CODE) {
                $paymentMethodValue = [
                    'name' => 'Cards',
                    'payment_method_code' => AdyenCcConfigProvider::CODE,
                    'enabled' => false,
                    'recurring_processing_model' => null
                ];

                $arrayKey = $this->mathRandom->getUniqueHash('_');
                $result[$arrayKey] = $paymentMethodValue;
            } elseif ($methodInstance instanceof PaymentMethodInterface && $methodInstance->supportsRecurring()) {
                $paymentMethodValue = [
                    'name' => $methodInstance::NAME,
                    'payment_method_code' => $adyenPaymentMethod,
                    'enabled' => false,
                    'recurring_processing_model' => null
                ];

                $arrayKey = $this->mathRandom->getUniqueHash('_');
                $result[$arrayKey] = $paymentMethodValue;
            }
        }

        return $result;
    }

    private function compareConfigurationArrayValues($value): array
    {
        $paymentMethods = $this->buildPaymentMethodArray();
        $availableConfigurationValues = array_column($value, 'payment_method_code');

        foreach ($paymentMethods as $paymentMethod) {
            // Add payment method if it is not in the list.
            if (!in_array($paymentMethod['payment_method_code'], $availableConfigurationValues)) {
                $paymentMethodValue = [
                    'payment_method_code' => $paymentMethod['payment_method_code'],
                    'name' => $paymentMethod['name'],
                    'enabled' => false,
                    'recurring_processing_model' => null
                ];

                $resultId = $this->mathRandom->getUniqueHash('_');
                $value[$resultId] = $paymentMethodValue;
            }
        }

        // Clean up the values which shouldn't be there
        $availablePaymentMethodValues = array_column($paymentMethods, 'payment_method_code');
        foreach ($value as $key => $configItem) {
            if (!in_array($configItem['payment_method_code'], $availablePaymentMethodValues)) {
                unset($value[$key]);
            }
        }

        return $value;
    }
}
