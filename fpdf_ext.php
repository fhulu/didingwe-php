<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of fpdf_ext
 *
 * @author luxolo
 */
require_once('FPDF/fpdf.php');

class fpdf_ext extends FPDF {

  var $header;
  var $footer;
  // Margins
  var $left = 10;
  var $right = 10;
  var $top = 10;
  var $bottom = 10;
  var $height;
  var $cell;
 
//  function __construct($header, $footer) 
//  {
//    $this->header = $header;
//    $this->footer = $footer;
//  }
  
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
//  function Footer()
//{
//    // Go to 1.5 cm from bottom
//    $this->SetY($this->footer['x']);
//    // Select Arial italic 8
//    $this->SetFont($this->footer['font_style'], $this->footer['font_type'], $this->footer['font_size']);
//    // Print centered page number
//    $this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C');
//}

function WriteTable($rows)
{
   // go through all colums
   foreach ($rows as $row) {
      $height = 0;
      
      // get max height of current col
      $nb=0;
      foreach ($row as $cell) {
         // set style
         $this->SetFont($cell['font_name'], $cell['font_style'], $cell['font_size']);
         $color = explode(",", $cell['fillcolor']);
         if (sizeof($color) > 2) $this->SetFillColor($color[0], $color[1], $color[2]);
         $color = explode(",", $cell['textcolor']);
         if (sizeof($color) > 2) $this->SetTextColor($color[0], $color[1], $color[2]);            
         $color = explode(",", $cell['drawcolor']);            
         if (sizeof($color) > 2) $this->SetDrawColor($color[0], $color[1], $color[2]);
         $this->SetLineWidth($cell['linewidth']);
                     
         $nb = max($nb, $this->NbLines($cell['width'], $cell['text']));            
         $height = $cell['height'];
      }  
      $h=$height*$nb;
      
      
      // Issue a page break first if needed
      $this->CheckPageBreak($h);
      
      // Draw the cells of the row
      foreach ($row as $cell) {
         $w = $cell['width'];
         $a = $cell['align'];
         
         // Save the current position
         $x=$this->GetX();
         $y=$this->GetY();
         
         // set style
         $this->SetFont($cell['font_name'], $cell['font_style'], $cell['font_size']);
         $color = explode(",", $cell['fill_color']);
         if (sizeof($color) > 2) $this->SetFillColor($color[0], $color[1], $color[2]);
         $color = explode(",", $cell['text_color']);
         if (sizeof($color) > 2) $this->SetTextColor($color[0], $color[1], $color[2]);            
         $color = explode(",", $cell['draw_color']);            
         if (sizeof($color) > 2) $this->SetDrawColor($color[0], $color[1], $color[2]);
         $this->SetLineWidth($cell['line_width']);
         
         $color = explode(",", $cell['fillcolor']);            
         if (sizeof($color) > 2) $this->SetDraw_Color($color[0], $color[1], $color[2]);
         
         
         // Draw Cell Background
         $this->Rect($x, $y, $w, $h, 'FD');
         
         $color = explode(",", $cell['drawcolor']);            
         if (sizeof($color) > 2) $this->SetDrawColor($color[0], $color[1], $color[2]);
         
         // Draw Cell Border
         $border_side = at($cell, 'linearea', []);
         if (!is_array($border_side))  $border_side = str_split($border_side);
         if (in_array("T", $border_side) > 0) {
            $this->Line($x, $y, $x+$w, $y);
         }            
         if (in_array("B", $border_side)) {
            $this->Line($x, $y+$h, $x+$w, $y+$h);
         }            
         
         if (in_array("L", $border_side)) {
            $this->Line($x, $y, $x, $y+$h);
         }
                     
         if (in_array("R", $border_side) > 0) {
            $this->Line($x+$w, $y, $x+$w, $y+$h);
         }
         
         
         // Print the text
         $this->MultiCell($w, $cell['height'], $cell['text'], 0, $a, 0);
         
         // Put the position to the right of the cell
         $this->SetXY($x+$w, $y);         
      }
      
      // Go to the next line
      $this->Ln($h);          
   }                  
}

   
   // If the height h would cause an overflow, add a new page immediately
   function CheckPageBreak($h)
   {
      if($this->GetY()+$h>$this->PageBreakTrigger)
         $this->AddPage($this->CurOrientation);
   }


   // Computes the number of lines a MultiCell of width w will take
   function NbLines($w, $txt)
   {
      $cw=&$this->CurrentFont['cw'];
      if($w==0)
         $w=$this->w-$this->rMargin-$this->x;
      $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
      $s=str_replace("\r", '', $txt);
      $nb=strlen($s);
      if($nb>0 and $s[$nb-1]=="\n")
         $nb--;
      $sep=-1;
      $i=0;
      $j=0;
      $l=0;
      $nl=1;
      while($i<$nb)
      {
         $c=$s[$i];
         if($c=="\n")
         {
            $i++;
            $sep=-1;
            $j=$i;
            $l=0;
            $nl++;
            continue;
         }
         if($c==' ')
            $sep=$i;
         $l+=$cw[$c];
         if($l>$wmax)
         {
            if($sep==-1)
            {
               if($i==$j)
                  $i++;
            }
            else
               $i=$sep+1;
            $sep=-1;
            $j=$i;
            $l=0;
            $nl++;
         }
         else
            $i++;
      }
      return $nl;
   }
        
}