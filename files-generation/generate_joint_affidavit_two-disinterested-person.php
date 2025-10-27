<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Get form data from URL parameters
$firstPersonName = isset($_GET['firstPersonName']) ? htmlspecialchars($_GET['firstPersonName']) : '';
$secondPersonName = isset($_GET['secondPersonName']) ? htmlspecialchars($_GET['secondPersonName']) : '';
$firstPersonAddress = isset($_GET['firstPersonAddress']) ? htmlspecialchars($_GET['firstPersonAddress']) : '';
$secondPersonAddress = isset($_GET['secondPersonAddress']) ? htmlspecialchars($_GET['secondPersonAddress']) : '';
$childName = isset($_GET['childName']) ? htmlspecialchars($_GET['childName']) : '';
$fatherName = isset($_GET['fatherName']) ? htmlspecialchars($_GET['fatherName']) : '';
$motherName = isset($_GET['motherName']) ? htmlspecialchars($_GET['motherName']) : '';
$dateOfBirth = isset($_GET['dateOfBirth']) ? htmlspecialchars($_GET['dateOfBirth']) : '';
$placeOfBirth = isset($_GET['placeOfBirth']) ? htmlspecialchars($_GET['placeOfBirth']) : '';
$childNameNumber4 = isset($_GET['childNameNumber4']) ? htmlspecialchars($_GET['childNameNumber4']) : '';
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
        <title>Joint Affidavit (Two Disinterested Person) - Preview</title>
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
            <div style="text-align:center; font-size:12pt;">
                <b>JOINT AFFIDAVIT<br/>(Two Disinterested Person)</b>
            </div>
            <br/>
            <div style="text-align:left; font-size:11pt;">
                REPUBLIC OF THE PHILIPPINES )<br/>
                PROVINCE OF LAGUNA      ) SS<br/>
                CITY OF CABUYAO         )<br/><br/>
                WE, <u><b><?= $affiant1Name ?: '[AFFIANT 1 NAME]' ?></b></u> and <u><b><?= $affiant2Name ?: '[AFFIANT 2 NAME]' ?></b></u>, Filipinos, both of legal age, and permanent residents of <u><b><?= $affiantAddress ?: '[AFFIANT ADDRESS]' ?></b></u>, after being duly sworn in accordance with law hereby depose and say;<br/><br/>
                1. That we are not in any way related by affinity or consanguinity to: <u><b><?= $childName ?: '[CHILD NAME]' ?></b></u>, child of the spouses <u><b><?= $fatherName ?: '[FATHER NAME]' ?></b></u> and <u><b><?= $motherName ?: '[MOTHER NAME]' ?></b></u>;<br/><br/>
                2. That we know for a fact that he/she was born on <u><b><?= $birthDate ?: '[BIRTH DATE]' ?></b></u> at <u><b><?= $birthPlace ?: '[BIRTH PLACE]' ?></b></u>;<br/><br/>
                3. That we know the circumstances surrounding the birth of the said <u><b><?= $childName ?: '[CHILD NAME]' ?></b></u>, considering that we are present during delivery as we are well acquainted with his/her parents, being family friend and neighbors;<br/><br/>
                4. That we are executing this affidavit in order to furnish by secondary evidence as to the fact concerning the date and place of birth of <u><b><?= $childName ?: '[CHILD NAME]' ?></b></u> in the absence of his/her Birth Certificate and let this instrument be useful for whatever legal purpose it may serve best;<br/><br/>
                IN WITNESS WHEREOF, we have hereunto set our hands this <u><b><?= $dateOfNotary ?: '[DATE OF NOTARY]' ?></b></u> in Cabuyao City, Laguna.<br/><br/>
                <table style="width:100%;">
                    <tr>
                        <td style="width:50%; text-align:center;"><u><b><?= $affiant1Name ?: '[AFFIANT 1 NAME]' ?></b></u><br/>Affiant<br/>ID ____________________</td>
                        <td style="width:50%; text-align:center;"><u><b><?= $affiant2Name ?: '[AFFIANT 2 NAME]' ?></b></u><br/>Affiant<br/>ID ____________________</td>
                    </tr>
                </table><br/>
                SUBSCRIBED AND SWORN to before me this <u><b><?= $dateOfNotary ?: '[DATE OF NOTARY]' ?></b></u> at the City of Cabuyao, Laguna, Philippines, the affiants exhibited to me their respective proof of identification indicated below their name, attesting that the above statement are true and executed freely and voluntarily;<br/><br/>
                WITNESS my hand the date and place above-written.<br/><br/>
                Doc. No. _____<br/>
                Page No. _____<br/>
                Book No. _____<br/>
                Series of _____<br/>
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
$pdf->SetTitle('Joint Affidavit (Two Disinterested Person)');
$pdf->SetSubject('Joint Affidavit (Two Disinterested Person)');

// Set default header data
$pdf->SetHeaderData('', 0, '', '');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(28, 5, 28);
$pdf->SetAutoPageBreak(TRUE, 20);

// Set font
$pdf->SetFont('times', '', 12);

// Add a page
$pdf->AddPage();

// Joint Affidavit (Two Disinterested Person) content - matching specified format
$html = <<<EOD
<div style="text-align:left; font-size:11pt;">
REPUBLIC OF THE PHILIPPINES)<br/>
    PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;) SS<br/>
    CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/><br/>
</div>

<div style="text-align:center; font-size:13pt; font-weight:bold;">
    <b>JOINT AFFIDAVIT<br/>(Two Disinterested Person)</b>
</div>
<br/>

<div style="text-align:left; font-size:11pt;">
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;WE, <u><b>{$firstPersonName}</b></u> and <u><b>{$secondPersonName}</b></u><br/>
    Filipinos, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; both &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; legal &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; age &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; , &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; permanent &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; residents &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of <br/>
    <u><b>{$firstPersonAddress}</b></u> and <u><b>{$secondPersonAddress}</b></u> both in the<br/>
    City of Cabuyao, Laguna after being duly sworn in accordance with law hereby depose<br/>
    and say that;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. We &nbsp;&nbsp; are &nbsp; not &nbsp; related &nbsp;&nbsp; by &nbsp;&nbsp; affinity &nbsp;&nbsp; or &nbsp;&nbsp;&nbsp; consanguinity &nbsp;&nbsp;&nbsp; to &nbsp;&nbsp;&nbsp; the &nbsp;&nbsp; child &nbsp; :<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$childName}</b></u>, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; who was born on<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$dateOfBirth}</b></u> in &nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$placeOfBirth}</b></u>;<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cabuyao, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Laguna, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Philippines, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; to &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; his/her &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; parents:<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$fatherName}</b></u> and &nbsp;<u><b>{$motherName}</b></u><br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. We &nbsp; are &nbsp; well &nbsp; acquainted &nbsp;&nbsp; with &nbsp;&nbsp;their family, being neighbors and friends that <br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; we know that circumstances surroundding his/her birth ;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. However, &nbsp;&nbsp; such &nbsp;&nbsp; facts &nbsp;&nbsp; of &nbsp;&nbsp; birth &nbsp;&nbsp;were &nbsp;&nbsp; not &nbsp;&nbsp; registered &nbsp;&nbsp;as &nbsp; evidenced by a<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; certification issued by the philippine Statistics Authority;<br/><br>
    
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. We &nbsp;&nbsp;&nbsp; execute &nbsp;&nbsp; this &nbsp;&nbsp; affidavit &nbsp;&nbsp; to attest to the truth of the foregoing facts based<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;on &nbsp;&nbsp; our &nbsp;&nbsp; personal &nbsp;&nbsp; knowledge &nbsp; and experience, and let this instrument be use as<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;a &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; requirement &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; for &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Late &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Registration &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; the &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;said<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u><b>{$childNameNumber4}</b></u> .<br/><br/>
    
AFFIANTS FURTHER SAYETH NAUGHT.<br><br/>

    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cabuyao City, Laguna, <u><b>{$dateOfNotary}</b></u> .<br/><br/>
    
    <table style="width:100%;">
        <tr>
            <td style="width:50%; text-align:center;"><u><b>{$firstPersonName}</b></u><br/>Affiant<br/>ID Presented: _________________</td>
            <td style="width:50%; text-align:center;"><u><b>{$secondPersonName}</b></u><br/>Affiant<br/>ID Presented: _________________</td>
        </tr>
    </table><br/>
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
$pdf->Output('Joint_Affidavit_Two_Disinterested_Person.pdf', 'D'); 