<?php
  require_once('../pdf/fpdf.php');
  require_once('../common/db.php');
  
  class CertificatePDF extends FPDF
  {	
    function Body($vendor_id)
    { 
      global $db;
      list($vendor_id, $certificate_number, $applicant_name, $trading_name, $address, $distributor_type, $from_date, $to_date)
        = $db->read_one("select v.id,fpb_reg_no, co_name, trading_as, physical_address, t.description, '', ''
          from vendor v, vendor_type t where v.type = t.code and v.id = '$vendor_id' or v.fpb_reg_no = '$vendor_id'");
      $this->AddPage();
      $address = preg_split('/,|\r\n?|\n\r?/', $address); 
      
      //the middle of the "PDF screen", fixed by now
      $mid_x = 104; 
      
      //place variables on pdf
      $this->SetFont('Arial','',20); 
      $this->Text($mid_x - ($this->GetStringWidth($applicant_name) / 2), 134,$applicant_name);
      $this->Text($mid_x - ($this->GetStringWidth($trading_name) / 2), 155,$trading_name);
      $this->SetFont('Arial','',12); 
	  $y = 173;
	  foreach($address as $line)
	  {
		$this->Text($mid_x - ($this->GetStringWidth($line) / 2), $y,$line);
		$y += 7;
	  }  
      $this->Text($mid_x - ($this->GetStringWidth($distributor_type) / 2), 214,$distributor_type);
      
      //Fixed text starts here
      $this->SetFont('Arial','',12); 
      $this->Text(160, 17,$certificate_number);
      $this->SetFont('Arial','IB',25);   
      $this->Text($mid_x - ($this->GetStringWidth('CERTIFICATE OF REGISTRATION') / 2), 92,'CERTIFICATE OF REGISTRATION'); 
      $this->SetFont('Arial','',13);
      $this->Text($mid_x - ($this->GetStringWidth('issued under the Films and Publications Act, 1996') / 2), 112,"issued under the Films and Publications Act, 1996"); 
      $this->Text($mid_x - ($this->GetStringWidth('The Film and Publication Board hereby certifies that') / 2), 122,"The Film and Publication Board hereby certifies that"); 
      $this->SetFont('Arial', '',11);
      $this->Text($mid_x - ($this->GetStringWidth('trading as') / 2), 143,"trading as");
      $this->Text($mid_x - ($this->GetStringWidth('at') / 2), 164,"at");
      $this->Text($mid_x - ($this->GetStringWidth('has been registered as') / 2), 206,"has been registered as");
      $this->Text(9.5, 260,"VALID FROM " . $from_date . " TO " . $to_date);
      $this->Text(140, 260,"_____________________________");
      $this->Text(140, 265," FILM AND PUBLICATION BOARD"); 
      $this->SetFont('Arial', '',9);
      $this->Text($mid_x - ($this->GetStringWidth('subject to the conditions in the attached Notice') / 2), 220,"subject to the conditions in the attached Notice");       
    } 
    function Terms()
    { 
      $this->SetX(8);
      $this->SetFont('Arial','',9);
      // Read text file
      $txt = file_get_contents('footer.txt');
      // Output justified text
      $this->MultiCell(0,3,$txt);  
    }
    function Header()
    {
      // Logo
      $this->Image('logo.png',84,9,45);
      $this->SetFont('Arial','B',10);
      $this->Text(84, 61,'Film and Publication Board',10,0,'C');
      $this->SetFont('Arial','',19);
      $this->Text(59, 70,'REPUBLIC OF SOUTH AFRICA',10,0,'C');
    }
    function Footer()
    {
      $this->SetLeftMargin(30);
      $this->SetRightMargin(25);
      // Position at 1.5 cm from bottom
      $this->SetY(-15);
      $this->Text(22,280,'____________________________________________________________________________________');       
      $this->Terms();
    }
  }
  // Instanciation of inherited class
  /*
    Usage
  $pdf = new CertificatePDF();
  
  $pdf->Body('121','131''jfs','asdf',';qq2','qweq');
  $pdf->Output();
  */
 ?>