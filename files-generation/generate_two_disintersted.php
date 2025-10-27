<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('BOSS-KIAN');
$pdf->SetAuthor('BOSS-KIAN');
$pdf->SetTitle('JOINT AFFIDAVIT (Two Disinterested Person)');
$pdf->SetSubject('JOINT AFFIDAVIT (Two Disinterested Person)');

// Set default header data
$pdf->SetHeaderData('', 0, '', '');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins - reduced for single page
$pdf->SetMargins(28, 15, 28);
$pdf->SetAutoPageBreak(FALSE, 15);

// Set font
$pdf->SetFont('times', '', 11);

// Add a page
$pdf->AddPage();

// Joint Affidavit (Two Disinterested Person) content with exact formatting from image
$html = <<<EOD
<div style="text-align:left; font-size:11pt;">
REPUBLIC OF THE PHILIPPINES)<br/>
    PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;) SS<br/>
    CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/><br/>
</div>

<div style="text-align:center; font-size:13pt; font-weight:bold;">
    <b>JOINT AFFIDAVIT<br/>(Two Disinterested Person)</b>
</div>
<br/>

<div style="text-align:left; font-size:11pt;">
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;WE, _________________________ and _________________________<br/>
    Filipinos, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; both &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; legal &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; age &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; , &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; permanent &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; residents &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of <br/>
    ______________________________ and ______________________________ both in the<br/>
    City of Cabuyao, Laguna after being duly sworn in accordance with law hereby depose<br/>
    and say that;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. We &nbsp;&nbsp; are &nbsp; not &nbsp; related &nbsp;&nbsp; by &nbsp;&nbsp; affinity &nbsp;&nbsp; or &nbsp;&nbsp;&nbsp; consanguinity &nbsp;&nbsp;&nbsp; to &nbsp;&nbsp;&nbsp; the &nbsp;&nbsp; child &nbsp; :<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;_________________________________________, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; who was born on<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;________________________________ in &nbsp;&nbsp;&nbsp;&nbsp;____________________________;<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cabuyao, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Laguna, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Philippines, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; to &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; his/her &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; parents:<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;______________________________ and &nbsp;______________________________<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. We &nbsp; are &nbsp; well &nbsp; acquainted &nbsp;&nbsp; with &nbsp;&nbsp;their family, being neighbors and friends that <br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; we know that circumstances surroundding his/her birth ;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. However, &nbsp;&nbsp; such &nbsp;&nbsp; facts &nbsp;&nbsp; of &nbsp;&nbsp; birth &nbsp;&nbsp;were &nbsp;&nbsp; not &nbsp;&nbsp; registered &nbsp;&nbsp;as &nbsp; evidenced by a<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; certification issued by the philippine Statistics Authority;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. We &nbsp;&nbsp;&nbsp; execute &nbsp;&nbsp; this &nbsp;&nbsp; affidavit &nbsp;&nbsp; to attest to the truth of the foregoing facts based<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;on &nbsp;&nbsp; our &nbsp;&nbsp; personal &nbsp;&nbsp; knowledge &nbsp; and experience, and let this instrument be use as<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;a &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; requirement &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; for &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Late &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Registration &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; the &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;said<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;___________________________________ .<br/><br/>
    
AFFIANTS FURTHER SAYETH NAUGHT.<br><br/>

    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cabuyao City, Laguna, __________________ .<br/><br/>
    
    <div style="text-align:center; font-weight:bold;">AFFIANT:</div>
    <div style="text-align:center;">____________________________<br/>Affiant<br/>ID Presented: _________________</div><br/>
    <br/>
    <br/>
    
WITNESS my hand and seal the date and place above-written.<br/><br/>
    
    
Doc. No. _____<br/>
    Page No. _____<br/>
    Book No. _____<br/>
    Series of _____<br/>
</div>
EOD;

$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$pdf->Output('JOINT_AFFIDAVIT_Two_Disinterested_Person.pdf', 'D');
?>
