<h1>Test Driving MySqlExcelBuilder</h1>
<? 
require_once('MySqlExcelBuilder.class.php');

// Intialize the object with the database variables
$database = 'xls_sample';
$user='yourdbusername';
$pwd='yourdbpassword';
$mysql_xls = new MySqlExcelBuilder($database,$user,$pwd);

// Setup the SQL Statements
$sql_statement = <<<END_OF_SQL

SELECT id,substring(vf.create_time,1,10) 'date',title,certificate_no,s.description status,
                  case s.description
                  when 'rejected' then 'Appeal'
                  else '' 
                  end as actions
                  FROM film f, vendor_film vf,status s
                  WHERE  f.id=vf.film_id and
                  f.status=s.code
                  and  vendor_id=324119

END_OF_SQL;

$sql_statement2 = <<<END_OF_SQL2

select distinct title,g.id, publisher,max_age,g.consumer_advice,Concat( first_name, ' ', last_name ) AS contact_person,r.date_rated
from game g,rating r, mukonin_audit.user u
where g.id=r.game_id and r.user_id=u.id and g.user_id=u.id

END_OF_SQL2;



// Add the SQL statements to the spread sheet
$mysql_xls->add_page('Film',$sql_statement,'Price','B',2);
$mysql_xls->add_page('Game',$sql_statement2,'Price','B',2);

// Get the spreadsheet after the SQL statements are built...
$phpExcel = $mysql_xls->getExcel(); // This needs to come after all the pages have been added.

$phpExcel->setActiveSheetIndex(0); // Set the sheet to the first page.
// Do some addtional formatting using PHPExcel
$sheet = $phpExcel->getActiveSheet();
$date = date('Y-m-d');
$cellKey = "A1"; 
$sheet->setCellValue($cellKey,"Gold Mugs Sold as Of $date");
$style = $sheet->getStyle($cellKey);                              
$style->getFont()->setBold(true);

$phpExcel->setActiveSheetIndex(1); // Set the sheet to the second page.
$sheet = $phpExcel->getActiveSheet(); 
$sheet->setCellValue($cellKey,"Tea Sold as Of $date");
$style = $sheet->getStyle($cellKey);                              
$style->getFont()->setBold(true);

$phpExcel->setActiveSheetIndex(0); // Set the sheet back to the first page.

// Write the spreadsheet file...
$objWriter = PHPExcel_IOFactory::createWriter($phpExcel, 'Excel5'); // 'Excel5' is the oldest format and can be read by old programs.
$fname = "TestFile.xls";
$objWriter->save($fname);

// Make it available for download.
echo "<a href=\"$fname\">Download $fname</a>";


?>