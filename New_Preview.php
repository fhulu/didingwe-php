<?php
    require_once('pdf/fpdf.php');
    require_once('../common/db.php');
    require_once('rating.php');
    require_once('wor_wrap.php');
    
    class PreviewPDF extends FPDF
  {	
    
    function Body($request)
    { 
        global $db;
        
        $title = addslashes($request[title]);
        $sysnopsis = addslashes($request[sysnopsis]);
        $country_of_origin = $request[country_of_origin];
        $country = $db->read_one_value("select name from mukonin_contact.country where code = '$country_of_origin'");
        $year_of_production = $request[year_of_production];
        $platform_code = $request[code];
        $platform = $db->read_one_value("select description from mukonin_fpb.platform where code = '$platform_code'");
        $genre_code = $request[genre_code];
        $genre = $db->read_one_value("select description from mukonin_fpb.genre where code = '$genre_code'");
        $publisher = addslashes($request[publisher]);
        $release_date = $request[release_date];
        $board = $request[board]; 
        $reason = addslashes($request[prev_reason]);
        $rating = addslashes($request[prev_rating]);
        
        
        $pdf=new PDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial','',12);
        $text=str_repeat('this is a word wrap test ',20);
        $nb=$pdf->WordWrap($text,120);
        $pdf->Write(5,"This paragraph has $nb lines:\n\n");
        $pdf->Write(5,$text);
        $pdf->Output();
        
    }

  }
?>
