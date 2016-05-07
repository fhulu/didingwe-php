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
  function ExtPDF($options = [])
  {
    $this->options = array_merge([
      'left_margin'=>10,
      'right_margin'=>10,
      'top_margin'=>10,
      'bottom_margin'=>10,
      'vertical_spacing'=>5,
    ], $options);
    FPDF::FPDF();
 }

  function wrap($sentence, $x=null, &$y=null, &$options=[])
  {
    $options = array_merge($this->options, $options);
    if (!is_null($x)) $options['left_margin'] = $x;
    if (!is_null($y)) $options['top_margin'] = $y;
    list($left_margin, $right_margin, $y, $vertical_spacing) = to_array(
      $options, 'left_margin', 'right_margin', 'top_margin', 'vertical_spacing');

    $x = $left_margin;
    $xmax = $this->w - $$right_margin;
    $words = preg_split('/([^\s,;.-]+[\s,;.-]+)/',$sentence, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    foreach($words as $word) {
      $word = $this->prepareText($word, $options);
      $width = $this->GetStringWidth($word);
      if ($x + $width > $xmax) {
        $x = $left_margin;
        $y += $vertical_spacing;
      }
      $this->Text($x, $y, $word);
      $x += $width;
    }
    $y += $vertical_spacing;
  }

  function setOption($option, $value)
  {
    $this->options[$option] = $value;
  }

  function writeRow($cells, &$y, $options=[])
  {
    $options = array_merge($this->options, $options);
    $x = $options['left_margin'];
    $top = $ymax = $y;
    $widths = $this->options['columnWidths'];
    foreach($cells as $cell) {
      $y = $top;
      $this->wrap($cell, $x, $y, $options);
      $x += array_shift($widths);
      if ($y > $ymax) $ymax = y;
    }
  }

  function getCenterX($width)
  {
    return ($this->w - $width)/2;
  }

  function prepareText($text, $options =[])
  {
    if ($options['capitalise']) $text = strtoupper($text);
    return html_entity_decode(htmlentities($text),ENT_HTML401,"ISO-8859-1");
  }

  function centerText($text, $y, $options = [])
  {
    $text = $this->prepareText($text, $options);
    $width = $this->GetStringWidth($text);
    $this->Text($this->getCenterX($width), $y, $text);
  }

  function centerImage($file, $width, $y)
  {
    $this->Image($file,$this->getCenterX($width),$y,$width);
  }

  function rightText($text, $y, $options = [])
  {
    $text = $this->prepareText($text, $options);
    $this->Text($this->w - $this->options['right_margin'] - $this->GetStringWidth($text), $y, $text);
  }

  function getPrintableWidth()
  {
    return $this->w - $this->options['right_margin'] - $this->options['left_margin'];
  }

  function getTextWidth($text, $options=[])
  {
    return $this->GetStringWidth($this->prepareText($text));
  }
}
