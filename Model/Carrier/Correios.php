<?php

namespace Tezus\Correios\Model\Carrier;

use Exception;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;

/**
 * Correios shipping model
 */
class Correios extends AbstractCarrier implements CarrierInterface {
  /**
   * @var string
   */
  protected $_code = 'correios';

  /**
   * @var bool
   */
  protected $_isFixed = true;

  /**
   * @var \Magento\Shipping\Model\Rate\ResultFactory
   */
  private $rateResultFactory;

  /**
   * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
   */
  private $rateMethodFactory;

  /**
   * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
   * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
   * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
   * @param array $data
   */
  public function __construct(
    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
    \Psr\Log\LoggerInterface $logger,
    \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
    \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
    array $data = []
  ) {
    parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);

    $this->rateResultFactory = $rateResultFactory;
    $this->rateMethodFactory = $rateMethodFactory;
  }

  /**
   * Correios Shipping Rates Collector
   *
   * @param RateRequest $request
   * @return \Magento\Shipping\Model\Rate\Result|bool
   */
  public function collectRates(RateRequest $request) {
    if (!$this->getConfigFlag('active')) {
      return false;
    }

    try {

      if ($request->getAllItems()) {
        $data['cubic'] = 0;
        //$attributes = $this->helperData->getAttributes();
        foreach ($request->getAllItems() as $key => $item) {
          $product = $this->productRepository->getById($item->getProductId());
          if ($this->helperData->validateProduct($product)) {
            $data['cubic'] +=  $product->getData()['altura_correios'] * $product->getData()['largura_correios'] * $product->getData()['comprimento_correios'] * 300 / 1000000 * $item->getQty();
          }
        }
      }

      $data['packageWeight'] = $request->getPackageWeight();
      $data['postDest'] = $request->getDestPostcode();
      $data['postOrigin'] = $request->getOrigPostcode();
      $data['packageValue'] = $request->getBaseCurrency()->convert(
        $request->getPackageValue(),
        $request->getPackageCurrency()
      );

      /** @var \Magento\Shipping\Model\Rate\Result $result */
      $result = $this->rateResultFactory->create();

      /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
      $method = $this->rateMethodFactory->create();

      $method->setCarrier($this->_code);
      $method->setCarrierTitle($this->getConfigData('title'));

      $method->setMethod($this->_code);
      $method->setMethodTitle($this->getConfigData('name'));

      $shippingCost = (float)500;

      $method->setPrice($shippingCost);
      $method->setCost($shippingCost);

      $result->append($method);
    } catch (\Exception $e) {
      $result = $this->_rateErrorFactory->create();
      $result->setCarrier($this->_code)
        ->setCarrierTitle($this->getConfigData('name') . " - " . $this->getConfigData('title'))
        ->setErrorMessage(__($e->getMessage()));
    }
    return $result;
  }

  public function isTrackingAvailable() {
    return true;
  }

  /**
   * @return array
   */
  public function getAllowedMethods() {
    return [$this->_code => $this->getConfigData('name')];
  }
}
