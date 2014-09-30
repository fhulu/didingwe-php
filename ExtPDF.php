<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ExtPDF
 *
 * @author luxolo
 */
require('pdf/fpdf.php');

class ExtPDF extends FPDF {

  function wrap($left_margin, &$y, $right_margin, $sentence, $vertical_spacing=null)
  {
    if (is_null($vertical_spacing)) 
      $vertical_spacing = 5;  //todo: use current font height;
    
    $words = preg_split('/([^\s,;.-]+[\s,;.-]+)/',$sentence, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $x = $left_margin;
    foreach($words as $word) {
      $word = html_entity_decode(htmlentities($word),ENT_HTML401,"ISO-8859-1");
      $width = $this->GetStringWidth($word);

      if ($x + $width > $right_margin) {
        $x = $left_margin;
        $y += $vertical_spacing;
      }
      $this->Text($x, $y, $word);
      $x += $width;
    }
    $y += $vertical_spacing;
  }
}