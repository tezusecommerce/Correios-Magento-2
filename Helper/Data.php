<?php

namespace Tezus\Correios\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\State;

class Data extends AbstractHelper
{
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

  public function getConfig($path)
  {
    $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
    return $this->scopeConfig->getValue($path, $storeScope);
  }

  /**
   * @param $message
   */
  public function logMessage($message)
  {
    $this->logger->info($message);
  }

  /**
   * Return zip code formated
   * @param $zipcode
   */

  public function formatZip($zipcode)
  {
    $new = trim($zipcode);
    $new = preg_replace('/[^0-9\s]/', '', $new);
    if (!preg_match("/^[0-9]{7,8}$/", $new)) {
      return false;
    } elseif (preg_match("/^[0-9]{7}$/", $new)) {
      $new = "0" . $new;
    }
    return $new;
  }
}
