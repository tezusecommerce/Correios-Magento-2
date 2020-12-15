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
    \Magento\Catalog\Model\ProductRepository $productRepository,
    \Tezus\Correios\Helper\Data $helper,
    array $data = []
  ) {
    parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);

    $this->rateResultFactory = $rateResultFactory;
    $this->rateMethodFactory = $rateMethodFactory;
    $this->productRepository = $productRepository;
    $this->helperData = $helper;
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
      /** @var \Magento\Shipping\Model\Rate\Result $result */
      $result = $this->rateResultFactory->create();

      $methods = explode(",", $this->getConfigData('shipment_type'));
      foreach ($methods as $keys => $send) {
        if ($request->getAllItems()) {
          $data['cubic'] = 0;
          $total_peso = 0;
          $total_cm_cubico = 0;

          //$attributes = $this->helperData->getAttributes();
          foreach ($request->getAllItems() as $key => $item) {
            $product = $this->productRepository->getById($item->getProductId());
            $productData['height'] = $product->getData()['magecommerce_height'];
            $productData['width'] = $product->getData()['magecommerce_width'];
            $productData['length'] = $product->getData()['magecommerce_length'];

            if ($this->helperData->validateProduct($productData)) {
              $data['cubic'] +=  $productData['height'] * $productData['width'] * $productData['length'] * 300 / 1000000 * $item->getQty();
              $row_peso = $request->getPackageWeight() * $item->getQty();
              $row_cm = ($productData['height'] * $productData['width'] * $productData['length']) * $item->getQty();

              $total_peso += $row_peso;
              $total_cm_cubico += $row_cm;
            }
          }
          $raiz_cubica = round(pow($total_cm_cubico, 1 / 3), 2);
        }
        if ($this->getConfigFlag('contract_number')) {
          $data[$keys]['nCdEmpresa'] = $this->getConfigFlag('contract_number');
        }
        if ($this->getConfigFlag('contrac_password')) {
          $data[$keys]['sDsSenha'] = $this->getConfigFlag('contrac_password');
        }

        $data[$keys]['nCdServico'] = $send;
        $data[$keys]['nVlPeso'] = $total_peso < 0.3 ? 0.3 : $total_peso;
        $data[$keys]['nCdFormato'] = '1';
        $data[$keys]['nVlComprimento'] = $raiz_cubica < 16 ? 16 : $raiz_cubica;
        $data[$keys]['nVlAltura'] = $raiz_cubica < 2 ? 2 : $raiz_cubica;
        $data[$keys]['nVlLargura'] = $raiz_cubica < 11 ? 11 : $raiz_cubica;
        $data[$keys]['nVlDiametro'] = hypot($data[$keys]['nVlComprimento'], $data[$keys]['nVlLargura']);
        $data[$keys]['sCdMaoPropria'] = $this->getConfigData('own_hands');
        $data[$keys]['sCepDestino'] = $request->getDestPostcode();
        $data[$keys]['sCepOrigem'] = $this->helperData->getOriginCep();
        $data[$keys]['nVlValorDeclarado'] = $request->getBaseCurrency()->convert(
          $request->getPackageValue(),
          $request->getPackageCurrency()
        );

        $response = $this->requestCorreios('http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?StrRetorno=xml&' . http_build_query($data[$keys]));

        $dom = new \DOMDocument('1.0', 'ISO-8859-1');
        $dom->loadXml($response);

        if ($dom->getElementsByTagName('MsgErro')->item(0)->nodeValue !== "") {
          throw new \Exception($dom->getElementsByTagName('MsgErro')->item(0)->nodeValue, 1);
        }

        $valor = $dom->getElementsByTagName('Valor')->item(0)->nodeValue;
        $prazo = $dom->getElementsByTagName('PrazoEntrega')->item(0)->nodeValue;

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($this->_code);
        $method->setMethodTitle($this->helperData->getMethodName($send) . " - Em média $prazo dia(s)");

        $shippingCost = str_replace(",", ".", $valor);

        $method->setPrice($shippingCost);
        $method->setCost($shippingCost);

        $result->append($method);
      }
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

  public function requestCorreios($url) {
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
    ));

    $content = curl_exec($curl);

    curl_close($curl);

    return $content;
  }
}
