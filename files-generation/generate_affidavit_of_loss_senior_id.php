<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Get form data from URL parameters
$fullName = isset($_GET['fullName']) ? htmlspecialchars($_GET['fullName']) : '';
$completeAddress = isset($_GET['completeAddress']) ? htmlspecialchars($_GET['completeAddress']) : '';
$relationship = isset($_GET['relationship']) ? htmlspecialchars($_GET['relationship']) : '';
$seniorCitizenName = isset($_GET['seniorCitizenName']) ? htmlspecialchars($_GET['seniorCitizenName']) : '';
$detailsOfLoss = isset($_GET['detailsOfLoss']) ? htmlspecialchars($_GET['detailsOfLoss']) : '';
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
        <title>Affidavit of Loss (Senior ID) - Preview</title>
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
                REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
                PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;S.S<br>
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)
            </div>
            
            <br>
            <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:15px;">
                AFFIDAVIT OF LOSS<br/>
                (SENIOR ID)
            </div>
            <br>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b><?= $fullName ?: '[FULL NAME]' ?></b></u>, Filipino, of legal age, and with<br/>
                residence and currently residing at <u><b><?= $completeAddress ?: '[COMPLETE ADDRESS]' ?></b></u>, after having been sworn<br/>
                in accordance with law hereby depose and state:
            </div>
            <br>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am the <u><b><?= $relationship ?: '[RELATIONSHIP]' ?></b></u> of <u><b><?= $seniorCitizenName ?: '[SENIOR CITIZEN NAME]' ?></b></u>, who is<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;the lawful owner of a Senior Citizen ID issued by OSCA-Cabuyao;
            </div>
            <br>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That unfortunately, the said Senior ID was lost under the following<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; circumstances:<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b><?= $detailsOfLoss ?: '[DETAILS OF LOSS]' ?></b></u><br>
            </div>
            <br>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. That despite diligent efforts to search for the missing Senior Citizen ID,<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; the same can no longer be found;
            </div>
            <br>
            
            <div style="margin-left: 40px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. That I am executing this affidavit to attest the truth of the foregoing facts<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and for whatever intents it may serve in accordance with law.
            </div>
            <br>
            
            <div style="margin-left: 60px;">
                IN WITNESS WHEREOF, I have hereunto set my hands this <u><b><?= $dateOfNotary ?: '[DATE OF NOTARY]' ?></b></u> in<br>
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
                SUBSCRIBED AND SWORN to before me this <u><b><?= $dateOfNotary ?: '[DATE OF NOTARY]' ?></b></u> at the City of Cabuyao, Laguna, affiant exhibiting to me his/her respective proofs of identity, indicated below their names personally attesting that the foregoing statements is true to their best of knowledge and belief.
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
$pdf->SetTitle('Affidavit of Loss (Senior ID)');
$pdf->SetSubject('Affidavit of Loss (Senior ID)');

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

// Affidavit of Loss (Senior ID) content
$html = <<<EOD
<div style="font-size:11pt; line-height:1.2;">
    <br/>
    
    <div style="margin-top:10px;">
        REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
        PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;S.S<br>
        CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)
    </div>
    
    <br>
    <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:15px;">
        AFFIDAVIT OF LOSS<br/>
        (SENIOR ID)
    </div>
    <br>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>{$fullName}</b></u>, Filipino, of legal age, and with<br/>
        residence and currently residing at <u><b>{$completeAddress}</b></u>, after having been sworn<br/>
        in accordance with law hereby depose and state:
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am the <u><b>{$relationship}</b></u> of <u><b>{$seniorCitizenName}</b></u>, who is<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;the lawful owner of a Senior Citizen ID issued by OSCA-Cabuyao;
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That unfortunately, the said Senior ID was lost under the following<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;circumstances:<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>{$detailsOfLoss}</b></u><br>
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. That &nbsp; despite &nbsp; diligent &nbsp; efforts &nbsp; to &nbsp; retrieve &nbsp; the &nbsp; said &nbsp; Senior ID, &nbsp; the &nbsp; same  &nbsp; can  no<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;longer be restored and therefore considered lost;
    </div>
    
    <div style="margin-left: 40px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. I &nbsp; am &nbsp; executing &nbsp; this &nbsp; affidavit &nbsp; to &nbsp; attest &nbsp; to &nbsp; the &nbsp; truth  &nbsp; of &nbsp; the foregoing facts and<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;for whatever legal intents and purposes whatever legal intents and<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;purposes.
    </div>
    
    <div style="margin-left: 40px; margin-top:15px;">
AFFIANT FURTHER SAYETH NAUGHT.
    </div>
    
    <div style="margin-left: 40px; margin-top:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, I have hereunto set my hand this<br/>
        <u><b>{$dateOfNotary}</b></u>, in the City of Cabuyao, Laguna.
        <br>
    
    <div style="text-align:center; margin:15px 0;">
        <u><b>{$fullName}</b></u><br/>
        <b>AFFIANT</b>
    </div>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SUBSCRIBED &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; AND &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; SWORN &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; to &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; before &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; me, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; this<br/> 
_____________________________________, in the City of Cabuyao, Laguna, affiant exhibiting<br/>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;to me his/her ___________________________ as valid proof of identification.
    </div>
    <br>

    <div style="text-align:left; margin-left: -5px;">
Doc. No. _______<br/>
        Page No. _______<br/>
        Book No. _______<br/>
        Series of _______
    </div>
</div>
EOD;

$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$pdf->Output('Affidavit_of_Loss_Senior_ID.pdf', 'D'); 