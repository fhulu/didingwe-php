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
require_once('pdf/fpdf.php');

class ExtPDF extends FPDF {

  var $options;
  var $footer;
  function __construct($options = [])
  {
    $this->options = array_merge([
      'left_margin'=>10,
      'right_margin'=>10,
      'top_margin'=>10,
      'bottom_margin'=>10,
      'vertical_spacing'=>5,
    ], $options);
 }

  function wrap($sentence, $x=null, &$y=null, &$options=[])
  {
    $options = array_merge($this->options, $options);
    if (!is_null($x)) $options['left_margin'] = $x;
    if (!is_null($y)) $options['top_margin'] = $y;
    list($left_margin, $right_margin, $y, $vertical_spacing) = to_array(
      $options, 'left_margin', 'right_margin', 'top_margin', 'vertical_spacing');

    $x = $left_margin;
    $words = preg_split('/([^\s,;.-]+[\s,;.-]+)/',$sentence, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
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
