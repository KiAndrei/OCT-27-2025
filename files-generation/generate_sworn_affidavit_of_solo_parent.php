<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Get form data from URL parameters
$fullName = isset($_GET['fullName']) ? htmlspecialchars($_GET['fullName']) : '';
$completeAddress = isset($_GET['completeAddress']) ? htmlspecialchars($_GET['completeAddress']) : '';
$childrenNames = isset($_GET['childrenNames']) ? $_GET['childrenNames'] : '';
$childrenAges = isset($_GET['childrenAges']) ? $_GET['childrenAges'] : '';
$yearsUnderCase = isset($_GET['yearsUnderCase']) ? htmlspecialchars($_GET['yearsUnderCase']) : '';
$reasonSection = isset($_GET['reasonSection']) ? htmlspecialchars($_GET['reasonSection']) : '';
$otherReason = isset($_GET['otherReason']) ? htmlspecialchars($_GET['otherReason']) : '';
$employmentStatus = isset($_GET['employmentStatus']) ? htmlspecialchars($_GET['employmentStatus']) : '';
$employeeAmount = isset($_GET['employeeAmount']) ? htmlspecialchars($_GET['employeeAmount']) : '';
$selfEmployedAmount = isset($_GET['selfEmployedAmount']) ? htmlspecialchars($_GET['selfEmployedAmount']) : '';
$unemployedDependent = isset($_GET['unemployedDependent']) ? htmlspecialchars($_GET['unemployedDependent']) : '';
$dateOfNotary = isset($_GET['dateOfNotary']) ? htmlspecialchars($_GET['dateOfNotary']) : '';

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('BOSS-KIAN');
$pdf->SetAuthor('BOSS-KIAN');
$pdf->SetTitle('Sworn Affidavit (Solo Parent)');
$pdf->SetSubject('Sworn Affidavit (Solo Parent)');

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

// Parse children data
$childrenNamesArray = [];
$childrenAgesArray = [];

if (!empty($childrenNames)) {
    if (is_string($childrenNames)) {
        $childrenNamesArray = explode(',', $childrenNames);
    } else {
        $childrenNamesArray = $childrenNames;
    }
}

if (!empty($childrenAges)) {
    if (is_string($childrenAges)) {
        $childrenAgesArray = explode(',', $childrenAges);
    } else {
        $childrenAgesArray = $childrenAges;
    }
}

// Build children table rows - only show actual data, minimum 1 row
$maxChildren = max(count($childrenNamesArray), count($childrenAgesArray), 1);
$childrenRows = '';
for ($i = 0; $i < $maxChildren; $i++) {
    $name = isset($childrenNamesArray[$i]) ? trim($childrenNamesArray[$i]) : '';
    $age = isset($childrenAgesArray[$i]) ? trim($childrenAgesArray[$i]) : '';
    
    $childrenRows .= '<tr>
        <td style="width:80%; padding:8px 5px; border:1px solid #000;">' . $name . '</td>
        <td style="width:20%; padding:8px 5px; border:1px solid #000; text-align:center;">' . $age . '</td>
    </tr>';
}

// Prepare checkbox values
$leftChecked = ($reasonSection === 'Left the family home and abandoned us') ? 'X' : '&nbsp;';
$diedChecked = ($reasonSection === 'Died last') ? 'X' : '&nbsp;';
$otherChecked = ($reasonSection === 'Other reason, please state') ? 'X' : '&nbsp;';
$otherReasonText = ($reasonSection === 'Other reason, please state' && !empty($otherReason)) ? '<u><b>' . $otherReason . '</b></u>' : '';

$empChecked = ($employmentStatus === 'Employee and earning') ? 'X' : '&nbsp;';
$selfChecked = ($employmentStatus === 'Self-employed and earning') ? 'X' : '&nbsp;';
$unempChecked = ($employmentStatus === 'Un-employed and dependent upon') ? 'X' : '&nbsp;';
$empAmountText = ($employmentStatus === 'Employee and earning' && !empty($employeeAmount)) ? '<u><b>' . $employeeAmount . '</b></u>' : '';
$selfAmountText = ($employmentStatus === 'Self-employed and earning' && !empty($selfEmployedAmount)) ? '<u><b>' . $selfEmployedAmount . '</b></u>' : '';
$unempDependentText = ($employmentStatus === 'Un-employed and dependent upon' && !empty($unemployedDependent)) ? '<u><b>' . $unemployedDependent . '</b></u>' : '';

// Sworn Affidavit (Solo Parent) content
$html = <<<EOD
<div style="font-size:11pt; line-height:1.2;">
    <br/>
    
    <div style="margin-top:10px;">
        REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
        PROVINCE OF LAGUNA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>S.S</b><br/>&nbsp;
        CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)
    </div>
    
    <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:-15px 0;">
        SWORN AFFIDAVIT OF SOLO PARENT
    </div>
    <br/>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;That &nbsp;&nbsp; I, &nbsp; <u><b>{$fullName}</b></u>, &nbsp; Filipino &nbsp; Citizen, &nbsp; of &nbsp; legal &nbsp; age, single/ married /<br/>
        widow, with residence and postal address at <u><b>{$completeAddress}</b></u><br/>
        City ppf Cabuyao, Laguna after having been duly sworn in accordance with law hereby depose<br/>
        and state that;
    </div>
    
    
        <div style="margin-left: 40px;">
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. That I am a single parent and the Mother/Father of the following child/children namely:
        </div>
        
        <div style="margin-left: 60px; margin-right: 60px;">
            <table style="width:100%; border-collapse:collapse; margin-bottom:2px;">
                <tr>
                    <td style="width:80%; text-align:center; border:none;"><b>Name</b></td>
                    <td style="width:20%; text-align:center; border:none;"><b>Age</b></td>
                </tr>
            </table>
            <table style="width:100%; border-collapse: collapse;">
                {$childrenRows}
            </table>
            <br/>
        
        
        <div style="margin-left: 40px;">
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. That I am solely taking care and providing for my said child's / children's needs and <br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;everything indispensable for his/her/their wellbeing for <u><b>{$yearsUnderCase}</b></u> year/s now<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;since his/her / their biological Mother/Father
        </div>
        <br>
        
        
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<table style="border-collapse:collapse;">
                <tr>
                    <td style="width:16px; vertical-align:top; padding-top:2px;"><div style="width:12px; height:12px; border:1px solid #000; text-align:center; line-height:12px; font-size:10px;">{$leftChecked}</div></td>
                    <td>left the family home and abandoned us;</td>
                </tr>
                <tr>
                    <td style="width:16px; vertical-align:top; padding-top:2px;"><div style="width:12px; height:12px; border:1px solid #000; text-align:center; line-height:12px; font-size:10px;">{$diedChecked}</div></td>
                    <td>died last <span style="display:inline-block; border-bottom:1px solid #000; width: 180px;"></span>;</td>
                </tr>
                <tr>
                    <td style="width:16px; vertical-align:top; padding-top:2px;"><div style="width:12px; height:12px; border:1px solid #000; text-align:center; line-height:12px; font-size:10px;">{$otherChecked}</div></td>
                    <td>(other reason please state) <span style="display:inline-block; border-bottom:1px solid #000; width: 220px;">{$otherReasonText}</span>;</td>
                </tr>
            </table>
        
        
        
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. I am attesting to the fact that I am not cohabiting with anybody to date;
        </div>
        
        <div style="margin-left: 40px;">
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. I am currently:<br>
        </div>
        <br>
        
        
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<table style="border-collapse:collapse;">
                <tr>
                    <td style="width:16px; vertical-align:top; padding-top:2px;"><div style="width:12px; height:12px; border:1px solid #000; text-align:center; line-height:12px; font-size:10px;">{$empChecked}</div></td>
                    <td>Employed and earning Php <span style="display:inline-block; border-bottom:1px solid #000; width: 160px;">{$empAmountText}</span> per month;</td>
                </tr>
                <tr>
                    <td style="width:16px; vertical-align:top; padding-top:2px;"><div style="width:12px; height:12px; border:1px solid #000; text-align:center; line-height:12px; font-size:10px;">{$selfChecked}</div></td>
                    <td>
Self-employed and earning Php <span style="display:inline-block; border-bottom:1px solid #000; width: 160px;">{$selfAmountText}</span> per month,
                        <div>from my job as_____________________;</div>
                    </td>
                </tr>
                <tr>
                    <td style="width:16px; vertical-align:top; padding-top:2px;"><div style="width:12px; height:12px; border:1px solid #000; text-align:center; line-height:12px; font-size:10px;">{$unempChecked}</div></td>
                    <td>Un-employed and dependent upon <span style="display:inline-block; border-bottom:1px solid #000; width: 200px;">{$unempDependentText}</span>;</td>
                </tr>
            </table>
        
        
        
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;5. That I am executing this affidavit, to affirm the truth and veracity of the foregoing <br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;statements and be use for whatever legal purpose it may serve.
       
    </div>
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, I have hereunto affixed my signature this<br/>
        <u><b>{$dateOfNotary}</b></u> at the City of Cabuyao, Laguna.
    </div>
    
    
    <div style="text-align:center; margin:15px 0;">
        ____________________________<br/>
        <b>AFFIANT</b>
    </div>
    
    
    <div style="text-align:justify; margin-bottom:15px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; SUBSCRIBED AND SWORN to before me this _____________________ at the City of Cabuyao, Laguna, affiant personally appeared and exhibiting to me his/her _____________________ with ID No. _____________________ as competent proof of identity.
    </div>
    
    
    <div style="text-align:left; margin-left: -5px;">
Doc. No. _______<br/>
        Page No. _______<br/>
        Book No. _______<br/>
        Series of 2025
    </div>
</div>
EOD;

$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$pdf->Output('Sworn_Affidavit_Solo_Parent.pdf', 'D');
?>