<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Get form data from URL parameters
$fullName = isset($_GET['fullName']) ? htmlspecialchars($_GET['fullName']) : '';
$completeAddress = isset($_GET['completeAddress']) ? htmlspecialchars($_GET['completeAddress']) : '';
$childName = isset($_GET['childName']) ? htmlspecialchars($_GET['childName']) : '';
$birthDate = isset($_GET['birthDate']) ? htmlspecialchars($_GET['birthDate']) : '';
$birthPlace = isset($_GET['birthPlace']) ? htmlspecialchars($_GET['birthPlace']) : '';
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
        <title>Sworn Affidavit of Mother - Preview</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Times New Roman', serif;
                font-size: 11pt;
                line-height: 1.2;
                padding: 40px;
                background: white;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .title {
                font-size: 14pt;
                font-weight: bold;
                margin-bottom: 30px;
            }
            .content {
                text-align: justify;
                margin-bottom: 20px;
            }
            .signature-section {
                text-align: center;
                margin-top: 50px;
            }
            .affiant {
                margin-bottom: 50px;
            }
            .notary-section {
                margin-top: 30px;
            }
            .notary-line {
                border-bottom: 1px solid black;
                width: 200px;
                margin: 0 auto;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div style="font-size: 11pt; margin-bottom: 20px;">
                REPUBLIC OF THE PHILIPPINES)<br/>
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>S.S</strong><br/>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div class="title">
                SWORN AFFIDAVIT OF MOTHER
            </div>
        </div>

        <div class="content">
            I, <strong><?= $fullName ?></strong>, of legal age, Filipino, and residing at <strong><?= $completeAddress ?></strong>, after having been duly sworn to in accordance with law, hereby depose and say:
        </div>

        <div class="content">
            That I am the mother of <strong><?= $childName ?></strong>, who was born on <strong><?= $birthDate ? date('F j, Y', strtotime($birthDate)) : '' ?></strong> at <strong><?= $birthPlace ?></strong>;
        </div>

        <div class="content">
            That I have personal knowledge of the birth of the said child and I am competent to testify on the matters stated herein;
        </div>

        <div class="content">
            That I am executing this affidavit to attest to the truth of the foregoing and for whatever legal purpose it may serve.
        </div>

        <div class="content">
            IN WITNESS WHEREOF, I have hereunto set my hand this <strong><?= $dateOfNotary ?></strong> at Cabuyao City, Laguna.
        </div>

        <div class="signature-section">
            <div class="affiant">
                <strong><?= $fullName ?></strong><br/>
                Affiant
            </div>
            
            <div class="notary-section">
                <div class="notary-line"></div>
                <div style="margin-top: 5px;">
                    Notary Public<br/>
                    Until December 31, 2025<br/>
                    PTR No. 1234567 / <?= date('Y') ?><br/>
                    IBP No. 123456 / <?= date('Y') ?><br/>
                    Roll No. 12345<br/>
                    MCLE Compliance No. 1234567
                </div>
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
$pdf->SetTitle('Sworn Affidavit of Mother');
$pdf->SetSubject('Sworn Affidavit of Mother');

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

// Format birth date properly
$formattedBirthDate = '';
if (!empty($birthDate)) {
    $formattedBirthDate = date('F j, Y', strtotime($birthDate));
}

// Sworn Statement of Mother content - matching client format
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
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>{$fullName}</b></u>, Filipino, married/single, and with residence<br/>
        and postal address at <u><b>{$completeAddress}</b></u>, after<br>
        being duly sworn in accordance with law, hereby depose and say that;
    </div>
    <br>
    <br>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am the biological mother of <u><b>{$childName}</b></u>, who was<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;born on <u><b>{$birthDate}</b></u> in <u><b>{$birthPlace}</b></u>;<br> 
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That the birth of the above-stated child was not registered with the Local Civil<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Registry of Cabuyao City, due to negligence on our part;
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. That I am now taking the appropriate action to register the birth of my said child.
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. I am executing this affidavit to attest to the truth of the foregoing facts and be<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;use for whatever legal purpose it may serve.
    </div>
    <br>
    
    <div style="margin-left: 60px;">
        IN WITNESS WHEREOF, I have hereunto set my hands this <u><b>{$dateOfNotary}</b></u>, in the<br>
        City of Cabuyao, Laguna.
        <br>
        <br>
    </div>
    
    <div style="text-align:center; margin:15px 0;">
        <u><b>{$fullName}</b></u><br/>
        <b>AFFIANT</b>
    </div>
    
    <br>
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SUBCRIBED AND SWORN to before me this <u><b>{$dateOfNotary}</b></u> in the City of <br>
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
$pdf->Output('sworn_affidavit_of_mother.pdf', 'I');
?>
