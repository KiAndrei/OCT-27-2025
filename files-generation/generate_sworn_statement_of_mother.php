<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Get form data from URL parameters
$fullName = isset($_GET['fullName']) ? htmlspecialchars($_GET['fullName']) : '';
$completeAddress = isset($_GET['completeAddress']) ? htmlspecialchars($_GET['completeAddress']) : '';
$childName = isset($_GET['childName']) ? htmlspecialchars($_GET['childName']) : '';
$birthDate = isset($_GET['birthDate']) ? htmlspecialchars($_GET['birthDate']) : '';
$birthPlace = isset($_GET['birthPlace']) ? htmlspecialchars($_GET['birthPlace']) : '';
$fatherName = isset($_GET['fatherName']) ? htmlspecialchars($_GET['fatherName']) : '';
$cityRegistry = isset($_GET['cityRegistry']) ? htmlspecialchars($_GET['cityRegistry']) : '';
$dateOfNotary = isset($_GET['dateOfNotary']) ? htmlspecialchars($_GET['dateOfNotary']) : '';

// Check if this is view-only mode
$viewOnly = isset($_GET['view_only']) && $_GET['view_only'] == '1';

if ($viewOnly) {
    // Output HTML version for viewing
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sworn Statement of Mother - Preview</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Times New Roman', serif;
                font-size: 16pt;
                line-height: 1.8;
                margin: 0;
                padding: 0;
                background: white;
                color: black;
                width: 100%;
                min-height: 100vh;
                overflow-x: hidden;
            }
            .document {
                width: 100%;
                max-width: 100%;
                background: white;
                color: black;
                min-height: 100%;
                padding: 20px 30px;
                font-family: 'Times New Roman', serif;
                font-size: 15pt;
                line-height: 1.4;
                margin: 0;
                overflow-x: hidden;
            }
        </style>
    </head>
    <body>
        <div class="document" style="font-size:11pt; line-height:1.2; padding: 20px 30px;">
            <br/>
            
            <div style="margin-top:10px;">
                REPUBLIC OF THE PHILIPPINES&nbsp;&nbsp;&nbsp;)<br/>&nbsp;
                PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<br>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;S.S
            </div>
            <br>
            
            <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:15px;">
                SWORN STATEMENT OF MOTHER
            </div>
            <br>
            <br>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, ___________________________, Filipino, married/single, and with residence<br/>
                and postall address at _____________________________________, after<br>
                being duly sworn in accordance with law, hereby depose and say that;
            </div>
            <br>
            <br>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am the biological mother of______________________, who was<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;born on__________________in_______________________________;<br> 
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That the birth of the above-stated child was not registered with the Local Civil<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Registry of Cabuyao City, due to negligence on our part;
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. i am now taking the appropriate action to register the birth of my said child.<br>
            </div>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. I am executing this affidavit to attest to the truth of the foregoing facts and be<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;use for whatever legal purpose it may serve.
            </div>
            <br>
            
            <div style="margin-left: 60px;">
                IN WITNESS WHEREOF, I have hereunto set my hands this___________________, in the
                the City of Cabuyao, Laguna.
                <br>
                <br>
            </div>
            
            <div style="text-align:center; margin:15px 0;">
                <u><b><?= $fullName ?: '[FULL NAME]' ?></b></u><br/>
                <b>AFFIANT</b>
            </div>
            
            <br>
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SUBCRIBED AND SWORN to before me this__________________________ in the City of <br>
                Cabuyao, Province of Laguna, affiant personally appeared, exhibiting to me her 
                Valid ID/No. _______________________________ as respective proof of identification.
            </div>
            <br>
            
            <div style="text-align:left; margin-left: -5px;">
                Doc. No. _______<br/>
                Book No. _______<br/>
                Page No. _______<br/>
                Series of _______
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('BOSS-KIAN');
$pdf->SetAuthor('BOSS-KIAN');
$pdf->SetTitle('Sworn Statement of Mother');
$pdf->SetSubject('Sworn Statement of Mother');

// Set default header data
$pdf->SetHeaderData('', 0, '', '');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(28, 5, 28);
$pdf->SetAutoPageBreak(FALSE);

// Set font
$pdf->SetFont('times', '', 11);

// Add a page
$pdf->AddPage();

// Sworn Statement of Mother content
$html = <<<EOD
<div style="font-size:11pt; line-height:1.2;">
    <br/>
    
    <div style="margin-top:10px;">
        REPUBLIC OF THE PHILIPPINES&nbsp;&nbsp;&nbsp;)<br/>&nbsp;
        PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<br>
        CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;S.S
    </div>
    <br>
    
    <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:15px;">
        SWORN STATEMENT OF MOTHER
    </div>
    <br>
    <br>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, ___________________________, Filipino, married/single, and with residence<br/>
        and postall address at _____________________________________, after<br>
        being duly sworn in accordance with law, hereby depose and say that;
    </div>
    <br>
    <br>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am the biological mother of______________________, who was<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;born on__________________in_______________________________;<br>
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That the birth of the above-stated child was not registered with the Local Civil<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Registry of Cabuyao City, due to negligence on our part;
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. i am now taking the appropriate action to register the birth of my said child.<br>
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. I am executing this affidavit to attest to the truth of the foregoing facts and be<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;use for whatever legal purpose it may serve.
    </div>
    <br>
    
    <div style="margin-left: 60px;">
IN WITNESS WHEREOF, I have hereunto set my hands this___________________, in the
        the City of Cabuyao, Laguna.
        <br>
    </div>
    
    <div style="text-align:center; margin:15px 0;">
        <u><b>{$fullName}</b></u><br/>
        <b>AFFIANT</b>
    </div>
    
    <br>
    <div style="text-align:justify; margin-bottom:15px;">
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SUBCRIBED AND SWORN to before me this____________________ in the City of <br>
Cabuyao, Province of Laguna, affiant personally appeared, exhibiting to me her<br>
Valid ID/No. _______________________________ as respective proof of identification.
    </div>
    <br>
    
    <div style="text-align:left; margin-left: -5px;">
Doc. No. _______<br/>
        Book No. _______<br/>
        Page No. _______<br/>
        Series of _______
    </div>
</div>
EOD;

$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$pdf->Output('Sworn_Statement_of_Mother.pdf', 'D'); 