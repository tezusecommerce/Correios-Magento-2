<?php

/**
 * Copyright ©   All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Tezus\Correios\Model\Config\Source;

class ShipmentType {

  const SEDEXSC = 4014;
  const SEDEXCC = 40096;
  const ESEDEXCC = 84019;
  const PACSC = 4510;
  const PACCC = 41068;
  const SEDEX10 = 4790;
  const SEDEXHJ = 4804;
  const SEDEXAC = 40045;

  public function toOptionArray() {
    return array(
      array('value' => self::SEDEXSC, 'label' => 'SEDEX sem contrato'),
      array('value' => self::SEDEXCC, 'label' => 'SEDEX com contrato'),
      array('value' => self::ESEDEXCC, 'label' => 'E-SEDEX com contrato'),
      array('value' => self::PACSC, 'label' => 'PAC sem contrato'),
      array('value' => self::PACCC, 'label' => 'PAC com contrato'),
      array('value' => self::SEDEX10, 'label' => 'SEDEX 10'),
      array('value' => self::SEDEXHJ, 'label' => 'SEDEX HOJE'),
      array('value' => self::SEDEXAC, 'label' => 'SEDEX a cobrar'),
    );
  }
  
}
