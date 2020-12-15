<?php

namespace Tezus\Correios\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\State;

class Data extends AbstractHelper {
  /**
   * @var \Magento\Framework\App\Config\ScopeConfigInterface
   */
  protected $scopeConfig;
  protected $ourHands;
  protected $_session;
  protected $logger;
  protected $productRepository;
  protected $appState;
  protected $backendSessionQuote;

  /**
   *config path
   */

  public function __construct(
    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    \Magento\Backend\Model\Session\Quote $backendSessionQuote,
    \Magento\Catalog\Model\ProductRepository $productRepository,
    State $appState,
    Session $session
  ) {
    $writer = new Stream(BP . '/var/log/tezus_correios.log');
    $this->logger = new Logger();
    $this->logger->addWriter($writer);
    $this->appState = $appState;
    $this->_session = $session;
    $this->productRepository = $productRepository;
    $this->scopeConfig = $scopeConfig;
    $this->backendSessionQuote = $backendSessionQuote;
  }

  /**
   * returning config value
   **/

  public function getConfig($path) {
    $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
    return $this->scopeConfig->getValue($path, $storeScope);
  }

  public function getOriginCep() {
    return $this->getConfig('shipping/origin/postcode');
  }

  /**
   * @param $message
   */
  public function logMessage($message) {
    $this->logger->info($message);
  }

  /**
   * Return zip code formated
   * @param $zipcode
   */

  public function formatZip($zipcode) {
    $new = trim($zipcode);
    $new = preg_replace('/[^0-9\s]/', '', $new);
    if (!preg_match("/^[0-9]{7,8}$/", $new)) {
      return false;
    } elseif (preg_match("/^[0-9]{7}$/", $new)) {
      $new = "0" . $new;
    }
    return $new;
  }

  public function validateProduct($_product) {
    $rightHeight = [1, 100];
    $rightWidth = [10, 100];
    $rightLength = [15, 100];

    $height = $_product['height'];
    $width = $_product['width'];
    $length = $_product['length'];

    if (!$length || !$width || !$height) {
      throw new \Exception("Dimensões de um ou mais produtos não preenchidas!", 1);
    }

    if ($this->getConfig('carriers/correios/validate_dimensions')) {
      if ($height < $rightHeight[0] || $height > $rightHeight[1]) {
        throw new \Exception("Altura de um ou mais produtos está fora do permitido.", 1);
      }
      if ($width < $rightWidth[0] || $width > $rightWidth[1]) {
        throw new \Exception("Largura de um ou mais produtos está fora do permitido.", 1);
      }
      if ($length < $rightLength[0] || $length > $rightLength[1]) {
        throw new \Exception("Comprimento de um ou mais produtos está fora do permitido.", 1);
      }
    }

    return true;
  }

  public function getMethodName($method) {
    switch ($method) {
      case 4014: {
          return "SEDEX";
        }
      case 4510: {
          return "PAC";
        }
      case 4782: {
          return "SEDEX 12";
        }
      case 4790: {
          return "SEDEX 10";
        }
      case 4804: {
          return "SEDEX Hoje";
        }
    }
  }
}
