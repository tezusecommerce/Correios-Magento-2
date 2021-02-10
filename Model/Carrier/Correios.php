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
  protected $_isFixed = false;

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
          $total_peso = 0;
          $total_cm_cubico = 0;

          $attributes = $this->helperData->getAttributes();
          foreach ($request->getAllItems() as $key => $item) {
            $product = $this->productRepository->getById($item->getProductId());
            
            $productData['height'] = !isset($product->getData()[$attributes['height']]) ? $this->getConfigData('default_height') : $product->getData()[$attributes['height']];
            $productData['width'] = !isset($product->getData()[$attributes['width']]) ? $this->getConfigData('default_width') : $product->getData()[$attributes['width']];
            $productData['length'] = !isset($product->getData()[$attributes['length']]) ? $this->getConfigData('default_length') : $product->getData()[$attributes['length']];

            if ($this->helperData->validateProduct($productData)) {
              $row_peso = $request->getPackageWeight();
              $total_peso += $row_peso;
              $pesoMaximo = $this->getConfigData('maximum_weight');
              if ($total_peso > $pesoMaximo) {
                $row_cm = ($productData['height'] * $productData['width'] * $productData['length']) * $item->getQty();
                $total_cm_cubico += $row_cm;
                $pesos = [];
                while ($total_peso != 0.00) {
                  if ($total_peso > $pesoMaximo) {
                    $pesos[] = $pesoMaximo;
                    $total_peso -= $pesoMaximo;
                  } else {
                    $pesos[] = $total_peso;
                    $total_peso -= $total_peso;
                  }
                }
              }
            }
          }
          $raiz_cubica = round(pow($total_cm_cubico, 1 / 3), 2);
        }

        $valorCorreios = 0.00;
        foreach ($pesos as $peso) {
          if ($this->getConfigFlag('contract_number')) {
            $data[$keys]['nCdEmpresa'] = $this->getConfigFlag('contract_number');
          }
          if ($this->getConfigFlag('contrac_password')) {
            $data[$keys]['sDsSenha'] = $this->getConfigFlag('contrac_password');
          }

          $data[$keys]['nCdServico'] = $send;
          $data[$keys]['nVlPeso'] = $peso < 0.3 ? 0.3 : $peso;
          $data[$keys]['nCdFormato'] = '1';
          $data[$keys]['nVlComprimento'] = $raiz_cubica < 16 ? 16 : $raiz_cubica;
          $data[$keys]['nVlAltura'] = $raiz_cubica < 2 ? 2 : $raiz_cubica;
          $data[$keys]['nVlLargura'] = $raiz_cubica < 11 ? 11 : $raiz_cubica;
          $data[$keys]['nVlDiametro'] = hypot($data[$keys]['nVlComprimento'], $data[$keys]['nVlLargura']);
          $data[$keys]['sCdMaoPropria'] = $this->getConfigData('own_hands')  === '1' ? "S" : "N";
          $data[$keys]['sCepDestino'] = $request->getDestPostcode();
          $data[$keys]['sCepOrigem'] = $this->helperData->getOriginCep();
          $data[$keys]['nVlValorDeclarado'] = $request->getBaseCurrency()->convert(
            $request->getPackageValue(),
            $request->getPackageCurrency()
          );
          $data[$keys]['sCdAvisoRecebimento'] = $this->getConfigData('acknowledgment_of_receipt') === '1' ? "S" : "N";
        
          // print_r($data);
          $response = $this->requestCorreios('http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?StrRetorno=xml&' . http_build_query($data[$keys]));
          $dom = new \DOMDocument('1.0', 'ISO-8859-1');
          $dom->loadXml($response);

          if ($dom->getElementsByTagName('MsgErro')->item(0)->nodeValue !== "") {
            throw new \Exception($dom->getElementsByTagName('MsgErro')->item(0)->nodeValue, 1);
          }

          $valorCorreios += (float)str_replace(",", ".", $dom->getElementsByTagName('Valor')->item(0)->nodeValue);
          $prazo = (int)$dom->getElementsByTagName('PrazoEntrega')->item(0)->nodeValue + (int)$this->getConfigData('increment_days_in_delivery_time');
          $codigo = $dom->getElementsByTagName('Codigo')->item(0)->nodeValue;
        }
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        if ($this->getConfigData('display_delivery_time')) {
          $mensagem = $this->helperData->getMethodName($send) . " - Em média $prazo dia(s)";
        } else {
          $mensagem = $this->helperData->getMethodName($send);
        }

        $method->setMethod($codigo);
        $method->setMethodTitle($mensagem);
        $shippingCost = (float)$valorCorreios + (float)$this->getConfigData('handling_fee');

        $method->setPrice($shippingCost);
        $method->setCost($shippingCost);

        $result->append($method);
      }
    } catch (\Exception $e) {
      if ($this->getConfigData('showmethod')) {
        $result = $this->_rateErrorFactory->create();
        $result->setCarrier($this->_code)
          ->setCarrierTitle($this->getConfigData('name') . " - " . $this->getConfigData('title'))
          ->setErrorMessage(__($e->getMessage()));
      } else {
        return false;
      }
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

  private function requestCorreios($url) {
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
