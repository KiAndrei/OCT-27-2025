<?php
require_once 'session_manager.php';
validateUserAccess('employee');
require_once 'config.php';

// Fetch pending requests count for notification badge
$stmt = $conn->prepare("SELECT COUNT(*) FROM client_request_form WHERE status = 'Pending'");
$stmt->execute();
$pending_requests_count = $stmt->get_result()->fetch_row()[0];
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

$employee_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}

// Check for edit modal error from session
$modal_error = null;
$edit_form_data = null;
if (isset($_SESSION['edit_modal_error'])) {
    $modal_error = $_SESSION['edit_modal_error'];
    if (isset($_SESSION['edit_form_data'])) {
        $edit_form_data = $_SESSION['edit_form_data'];
    }
    // Clear the session variables
    unset($_SESSION['edit_modal_error']);
    unset($_SESSION['edit_form_data']);
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }

// Helper functions
function get_current_book_number() {
    return date('n'); // Current month (1-12)
}

function get_next_doc_number($conn, $book_number) {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(doc_number), 0) + 1 FROM employee_documents WHERE book_number = ?");
    $stmt->bind_param("i", $book_number);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_row()[0];
}

function log_activity($conn, $doc_id, $action, $user_id, $user_name, $doc_number, $book_number, $file_name) {
    $stmt = $conn->prepare("INSERT INTO employee_document_activity (document_id, action, user_id, user_name, form_number, file_name) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isisis', $doc_id, $action, $user_id, $user_name, $doc_number, $file_name);
    $stmt->execute();
}

function truncate_document_name($name, $max_length = 18) {
    // Remove file extension for display since we have icons
    $name_without_ext = pathinfo($name, PATHINFO_FILENAME);
    
    if (strlen($name_without_ext) <= $max_length) {
        return $name_without_ext;
    }
    return substr($name_without_ext, 0, $max_length) . '........';
}

// Handle multiple document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['documents'])) {
    $uploaded_count = 0;
    $errors = [];
    $validated_files = [];
    
    $current_book = get_current_book_number();
    
    // FIRST PASS: Validate ALL files before uploading ANY
    foreach ($_FILES['documents']['name'] as $key => $filename) {
        if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
            // Check if the corresponding form data exists
            if (!isset($_POST['category'][$key])) {
                $errors[] = "Category is required for file: " . $filename;
                continue;
            }
            
            $category = $_POST['category'][$key];
            
            if ($category === 'Notarized Documents') {
                if (!isset($_POST['surnames'][$key]) || !isset($_POST['first_names'][$key]) || !isset($_POST['doc_numbers'][$key]) || !isset($_POST['book_numbers'][$key])) {
                    $errors[] = "Missing form data for Notarized Documents: " . $filename;
                    continue;
                }
            } else if ($category === 'Law Office Files') {
                if (!isset($_POST['document_names'][$key]) || empty(trim($_POST['document_names'][$key]))) {
                    $errors[] = "Document name is required for Law Office Files: " . $filename;
                    continue;
                }
            }
            
            if ($category === 'Notarized Documents') {
                $surname = trim($_POST['surnames'][$key]);
                $first_name = trim($_POST['first_names'][$key]);
                $middle_name = trim($_POST['middle_names'][$key] ?? '');
                $doc_number = intval($_POST['doc_numbers'][$key]);
                $book_number = intval($_POST['book_numbers'][$key]);
                $affidavit_type = trim($_POST['affidavit_types'][$key] ?? '');
            } else {
                $surname = '';
                $first_name = '';
                $middle_name = '';
                $doc_number = 0;
                $book_number = 0;
                $affidavit_type = '';
            }
            
            // Set document name based on category
            if ($category === 'Notarized Documents') {
                $doc_name = $surname . ', ' . $first_name;
                if (!empty($middle_name)) {
                    $doc_name .= ' ' . $middle_name;
                }
            } else {
                $doc_name = trim($_POST['document_names'][$key]);
            }
            
            // Validation based on category
            if ($category === 'Notarized Documents') {
                if (empty($surname) || empty($first_name)) {
                    $errors[] = "Surname and First Name are required for file: " . $filename;
                    continue;
                }
                
                if (empty($affidavit_type)) {
                    $errors[] = "Affidavit Type is required for file: " . $filename;
                    continue;
                }
                
                if ($doc_number <= 0) {
                    $errors[] = "Doc number must be greater than 0 for file: " . $filename;
                    continue;
                }
                
                if ($book_number < 1 || $book_number > 12) {
                    $errors[] = "Book number must be between 1-12 for file: " . $filename;
                    continue;
                }
                
                // Check for duplicate doc number in same book
                $dupCheck = $conn->prepare("SELECT id FROM employee_documents WHERE doc_number = ? AND book_number = ?");
                $dupCheck->bind_param('ii', $doc_number, $book_number);
                $dupCheck->execute();
                $dupCheck->store_result();
                
                if ($dupCheck->num_rows > 0) {
                    $errors[] = "Doc Number $doc_number already exists in Book $book_number for file: " . $filename;
                    continue;
                }
            }
            
            // Check for duplicate doc number in current upload batch (only for Notarized Documents)
            if ($category === 'Notarized Documents') {
                for ($j = 0; $j < $key; $j++) {
                    if (isset($_POST['doc_numbers'][$j]) && isset($_POST['book_numbers'][$j]) && isset($_POST['category'][$j]) && $_POST['category'][$j] === 'Notarized Documents') {
                        $prev_doc_num = intval($_POST['doc_numbers'][$j]);
                        $prev_book_num = intval($_POST['book_numbers'][$j]);
                        if ($prev_doc_num == $doc_number && $prev_book_num == $book_number) {
                            $errors[] = "Doc Number $doc_number in Book $book_number is duplicated in current upload for file: " . $filename;
                            continue 2;
                        }
                    }
                }
            }
            
            // If we reach here, the file is valid
            $validated_files[$key] = [
                'filename' => $filename,
                'surname' => $surname,
                'first_name' => $first_name,
                'middle_name' => $middle_name,
                'doc_name' => $doc_name,
                'doc_number' => $doc_number,
                'book_number' => $book_number,
                'affidavit_type' => $affidavit_type
            ];
        }
    }
    
    // SECOND PASS: Only upload if ALL files are valid
    if (empty($errors)) {
        foreach ($validated_files as $key => $fileData) {
            $filename = $fileData['filename'];
            $doc_name = $fileData['doc_name'];
            $doc_number = $fileData['doc_number'];
            $book_number = $fileData['book_number'];
            $affidavit_type = $fileData['affidavit_type'];
            
            $fileInfo = pathinfo($filename);
            $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
            $safeDocName = preg_replace('/[^A-Za-z0-9 _\-]/', '', $doc_name);
            $fileName = $safeDocName . $extension;
            
            $targetDir = 'uploads/employee/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $targetFile = $targetDir . time() . '_' . $key . '_' . $fileName;
            $file_size = $_FILES['documents']['size'][$key];
            $file_type = $_FILES['documents']['type'][$key];
            
            if (move_uploaded_file($_FILES['documents']['tmp_name'][$key], $targetFile)) {
            $uploadedBy = $_SESSION['user_id'] ?? 1;
            $user_name = $_SESSION['employee_name'] ?? 'Employee';
            $category = $_POST['category'][$key];
                
                $stmt = $conn->prepare("INSERT INTO employee_documents (file_name, file_path, category, uploaded_by, doc_number, book_number, file_size, file_type, document_name, affidavit_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('sssiiisiss', $fileName, $targetFile, $category, $uploadedBy, $doc_number, $book_number, $file_size, $file_type, $doc_name, $affidavit_type);
                $stmt->execute();
                
                $doc_id = $conn->insert_id;
                
                // log_activity($conn, $doc_id, 'Uploaded', $uploadedBy, $user_name, $doc_number, $book_number, $fileName);
                
                // Log to audit trail
                global $auditLogger;
                $auditLogger->logAction(
                    $uploadedBy,
                    $user_name,
                    'employee',
                    'Document Upload',
                    'Document Management',
                    "Uploaded document: $fileName (Category: $category, Doc #: $doc_number, Book #: $book_number)",
                    'success',
                    'medium'
                );
                
                $uploaded_count++;
            } else {
                $errors[] = "Failed to upload file: " . $filename;
            }
        }
    }
    
            if ($uploaded_count > 0) {
                $success = "Successfully uploaded $uploaded_count document(s)!";
                // Auto redirect to show uploaded documents
                echo "<script>
                    alert('Successfully uploaded $uploaded_count document(s)!');
                    window.location.reload();
                </script>";
                exit();
            }
            if (!empty($errors)) {
                $error = implode('<br>', $errors);
                // Add JavaScript to show alert and prevent form submission
                echo "<script>
                    alert('Upload Error:\\n\\n" . addslashes(implode('\\n', $errors)) . "');
                    // Keep the form data intact
                    document.getElementById('filePreview').style.display = 'block';
                    document.getElementById('uploadBtn').style.display = 'inline-flex';
                </script>";
            }
}

// Handle edit
if (isset($_POST['edit_id'])) {
    $edit_id = intval($_POST['edit_id']);
    $new_name = trim($_POST['edit_document_name']);
    $new_doc_number = intval($_POST['edit_doc_number']);
    $new_book_number = intval($_POST['edit_book_number']);
    $new_affidavit_type = trim($_POST['edit_affidavit_type']);
    
    $uploadedBy = $_SESSION['user_id'] ?? 1;
    $user_name = $_SESSION['employee_name'] ?? 'Employee';
    
    // Determine current category first
    $catStmt = $conn->prepare("SELECT category, doc_number, book_number, affidavit_type FROM employee_documents WHERE id=?");
    $catStmt->bind_param('i', $edit_id);
    $catStmt->execute();
    $catRes = $catStmt->get_result();
    $current_category = 'Unknown';
    $existing = $catRes ? $catRes->fetch_assoc() : null;
    if ($existing) {
        $current_category = $existing['category'] ?? 'Unknown';
    }

    if ($current_category === 'Notarized Documents') {
        // Check for duplicate doc number in same book only for Notarized
        $dupCheck = $conn->prepare("SELECT id FROM employee_documents WHERE doc_number = ? AND book_number = ? AND id != ?");
        $dupCheck->bind_param('iii', $new_doc_number, $new_book_number, $edit_id);
        $dupCheck->execute();
        $dupCheck->store_result();
        if ($dupCheck->num_rows > 0) {
            $error = 'A document with Doc Number ' . $new_doc_number . ' already exists in Book ' . $new_book_number . '!';
            $_SESSION['edit_modal_error'] = $error;
            $_SESSION['edit_form_data'] = [
                'id' => $edit_id,
                'name' => $new_name,
                'doc_number' => $new_doc_number,
                'book_number' => $new_book_number,
                'affidavit_type' => $new_affidavit_type
            ];
            header('Location: employee_documents.php');
            exit();
        }
        // Proceed update all fields
        $stmt = $conn->prepare("UPDATE employee_documents SET file_name=?, document_name=?, doc_number=?, book_number=?, affidavit_type=? WHERE id=?");
        $stmt->bind_param('ssiisi', $new_name, $new_name, $new_doc_number, $new_book_number, $new_affidavit_type, $edit_id);
        $stmt->execute();
    } else {
        // Law Office Files: only update document name, keep others intact
        $stmt = $conn->prepare("UPDATE employee_documents SET file_name=?, document_name=? WHERE id=?");
        $stmt->bind_param('ssi', $new_name, $new_name, $edit_id);
        $stmt->execute();
        // keep numbers for logging
        if ($existing) {
            $new_doc_number = intval($existing['doc_number']);
            $new_book_number = intval($existing['book_number']);
            $new_affidavit_type = $existing['affidavit_type'] ?? '';
        }
    }
        
        // log_activity($conn, $edit_id, 'Edited', $uploadedBy, $user_name, $new_doc_number, $new_book_number, $new_name);
        
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $uploadedBy,
            $user_name,
            'employee',
            'Document Edit',
            'Document Management',
            "Edited document: $new_name (Category: $current_category, Doc #: $new_doc_number, Book #: $new_book_number)",
            'success',
            'medium'
        );
        
        header('Location: employee_documents.php#documents');
        exit();
    }

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT file_path, file_name, doc_number, book_number, uploaded_by, category, document_name FROM employee_documents WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res && $row = $res->fetch_assoc()) {
        @unlink($row['file_path']);
        $user_name = $_SESSION['employee_name'] ?? 'Employee';
        $user_id = $_SESSION['user_id'] ?? 1;
        
        // log_activity($conn, $id, 'Deleted', $user_id, $user_name, $row['doc_number'], $row['book_number'], $row['file_name']);
        
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $user_id,
            $user_name,
            'employee',
            'Document Delete',
            'Document Management',
            "Deleted document: {$row['document_name']} (Category: {$row['category']}, Doc #: {$row['doc_number']}, Book #: {$row['book_number']})",
            'success',
            'high'
        );
    }
    
    $stmt = $conn->prepare("DELETE FROM employee_documents WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: employee_documents.php#documents');
    exit();
}

// Build filter conditions
$where_conditions = [];
$where_params = [];
$where_types = '';

// Date filter
$filter_from = isset($_GET['filter_from']) ? $_GET['filter_from'] : '';
$filter_to = isset($_GET['filter_to']) ? $_GET['filter_to'] : '';

if ($filter_from && $filter_to) {
    $where_conditions[] = "DATE(upload_date) >= ? AND DATE(upload_date) <= ?";
    $where_params[] = $filter_from;
    $where_params[] = $filter_to;
    $where_types .= 'ss';
} elseif ($filter_from) {
    $where_conditions[] = "DATE(upload_date) = ?";
    $where_params[] = $filter_from;
    $where_types .= 's';
} elseif ($filter_to) {
    $where_conditions[] = "DATE(upload_date) <= ?";
    $where_params[] = $filter_to;
    $where_types .= 's';
}

// Doc number filter
$filter_doc_number = isset($_GET['doc_number']) ? $_GET['doc_number'] : '';
if ($filter_doc_number) {
    $where_conditions[] = "doc_number = ?";
    $where_params[] = $filter_doc_number;
    $where_types .= 'i';
}

// Book number filter
$filter_book_number = isset($_GET['book_number']) ? $_GET['book_number'] : '';
if ($filter_book_number) {
    $where_conditions[] = "book_number = ?";
    $where_params[] = $filter_book_number;
    $where_types .= 'i';
}

// Name filter
$filter_name = isset($_GET['name']) ? $_GET['name'] : '';
if ($filter_name) {
    $where_conditions[] = "file_name LIKE ?";
    $where_params[] = '%' . $filter_name . '%';
    $where_types .= 's';
}

// Affidavit type filter
$filter_affidavit_type = isset($_GET['affidavit_type']) ? $_GET['affidavit_type'] : '';
if ($filter_affidavit_type) {
    $where_conditions[] = "affidavit_type = ?";
    $where_params[] = $filter_affidavit_type;
    $where_types .= 's';
}

// Category filter
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
if ($filter_category) {
    $where_conditions[] = "category = ?";
    $where_params[] = $filter_category;
    $where_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Fetch documents with uploader name
$documents = [];
$query = "SELECT ed.*, uf.name as uploader_name, uf.user_type FROM employee_documents ed LEFT JOIN user_form uf ON ed.uploaded_by = uf.id $where_clause ORDER BY ed.book_number DESC, ed.doc_number ASC";
$stmt = $conn->prepare($query);

if (!empty($where_params)) {
    $stmt->bind_param($where_types, ...$where_params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
}

// Fetch recent activity
$activity = [];
$actRes = $conn->query("SELECT * FROM employee_document_activity ORDER BY timestamp DESC LIMIT 10");
if ($actRes && $actRes->num_rows > 0) {
    while ($row = $actRes->fetch_assoc()) {
        $activity[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Document Storage - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <style>
        .category-tabs {
            display: flex;
            margin-bottom: 20px;
            gap: 8px;
            border-bottom: 2px solid #e5e7eb;
            background: white;
            padding: 0;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .tab-btn {
            background: white;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 6px 6px 0 0;
        }
        
        .tab-btn:hover {
            background: #f9fafb;
            color: #374151;
        }
        
        .tab-btn.active {
            color: #5D0E26;
            border-bottom-color: #5D0E26;
            background: #fef2f2;
            font-weight: 600;
        }
        
        .tab-btn i {
            font-size: 1rem;
        }
        
        /* Download Modal Styles */
        .download-modal {
            max-width: 900px;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px 15px 25px;
            border-bottom: 2px solid #e5e7eb;
            background: #f8fafc;
            margin: -10px -25px 20px -25px;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #1f2937;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .close-btn {
            background: #dc2626;
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            position: relative;
            right: 0;
            margin: 0;
            flex-shrink: 0;
        }
        
        .close-btn:hover {
            background: #b91c1c;
            transform: scale(1.1);
        }
        
        .date-filter-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .filter-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-btn {
            background: white;
            border: 1px solid #d1d5db;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .filter-btn:hover {
            border-color: #5D0E26;
            color: #5D0E26;
        }
        
        .filter-btn.active {
            background: #5D0E26;
            color: white;
            border-color: #5D0E26;
        }
        
        .custom-date-range {
            display: none !important;
            gap: 10px;
            align-items: center;
            margin-left: 10px;
        }
        
        .custom-date-range.active,
        .custom-date-range[style*="flex"] {
            display: flex !important;
        }
        
        .date-inputs {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .date-inputs div {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .date-inputs label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #6b7280;
        }
        
        .date-inputs input[type="date"] {
            padding: 5px 6px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.7rem;
            min-width: 100px;
        }
        
        .download-list-container {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .list-header {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            margin-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .download-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: white;
        }
        
        .download-item {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid #f3f4f6;
            align-items: center;
            gap: 12px;
            transition: background 0.2s ease;
        }
        
        .download-item:last-child {
            border-bottom: none;
        }
        
        .download-item:hover {
            background: #f9fafb;
        }
        
        .download-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .item-header {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        
        .file-icon {
            width: 32px;
            height: 32px;
            background: #f3f4f6;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-details h4 {
            margin: 0 0 6px 0;
            font-size: 0.95rem;
            color: #1f2937;
            font-weight: 600;
        }
        
        .item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .doc-info {
            font-size: 0.75rem;
            color: #5D0E26;
            font-weight: 600;
            background: #fef2f2;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #fecaca;
        }
        
        .upload-date {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .category-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .category-badge.notarized-documents {
            background: #def7ec;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .category-badge.law-office-files {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        
        .modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0 0 0;
            border-top: 2px solid #e5e7eb;
            margin-top: 20px;
        }
        
        .btn-select-all, .btn-clear {
            background: #6b7280;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        
        .btn-select-all:hover {
            background: #4b5563;
        }
        
        .btn-clear {
            background: #dc2626;
        }
        
        .btn-clear:hover {
            background: #b91c1c;
        }
        
        .btn-download {
            background: #5D0E26;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-download:hover:not(:disabled) {
            background: #4a0b20;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }
        
        .btn-download:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .document-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(280px, 300px));
            gap: 6px;
            margin-bottom: 30px;
            align-items: stretch;
            justify-content: start;
            max-width: 100%;
            overflow-x: auto;
        }
        
        @media (max-width: 1400px) {
            .document-grid {
                grid-template-columns: repeat(4, minmax(260px, 280px));
            }
        }
        
        @media (max-width: 1200px) {
            .document-grid {
                grid-template-columns: repeat(3, minmax(300px, 1fr));
                gap: 8px;
            }
        }
        
        @media (max-width: 1000px) {
            .document-grid {
                grid-template-columns: repeat(2, minmax(45%, 1fr));
                gap: 10px;
            }
        }
        
        @media (max-width: 600px) {
            .document-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }
        
        .document-card {
            background: white;
            border-radius: 16px;
            padding: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #f1f5f9;
            width: 100%;
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .document-card .card-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 8px;
            justify-content: space-between;
            gap: 12px;
        }
        
        .document-card .document-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .document-icon i {
            font-size: 18px;
            color: #1976d2;
        }
        
        .document-info {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .document-info h3 {
            margin: 0 0 8px 0;
            font-size: 0.92rem;
            color: #111827;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .document-meta {
            font-size: 0.78rem;
            color: #6b7280;
            line-height: 1.3;
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-top: 8px;
        }
        
        .document-meta div {
            margin-bottom: 0;
        }
        
        .document-meta strong {
            color: #374151;
            font-weight: 600;
        }
        
        .document-actions {
            display: flex;
            gap: 6px;
            margin-top: auto;
            justify-content: space-between;
            padding: 8px 0 0 0;
            border-top: 1px solid #f1f5f9;
            flex-shrink: 0;
        }
        
        .btn-action {
            padding: 10px 12px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            flex: 1;
            height: 40px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            font-weight: 600;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Rounded corners only on first and last buttons to align with card edges */
        .btn-action:first-of-type {
            border-radius: 0 0 0 6px;
        }
        .btn-action:last-of-type {
            border-radius: 0 0 6px 0;
        }

        /* Maroon Theme for Statistics */
        .stat-number {
            color: #5D0E26 !important;
            font-weight: 700;
        }

        .stat-card {
            border-left: 4px solid #5D0E26 !important;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.15);
        }
        
        .filters-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
            border-left: 4px solid #5D0E26;
        }

        .filters-section h2 {
            color: #5D0E26;
            margin: 0 0 15px 0;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filters-section h2 i {
            font-size: 1rem;
        }

        .filter-group label {
            color: #5D0E26;
            font-weight: 600;
        }

        .filter-group input,
        .filter-group select {
            border: 1px solid #5D0E26 !important;
            border-radius: 6px !important;
            padding: 8px 12px !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #8B1538 !important;
            box-shadow: 0 0 0 2px rgba(93, 14, 38, 0.1) !important;
            outline: none !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5D0E26, #8B1538) !important;
            border: none !important;
            color: white !important;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4A0B1E, #6B0F2A) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #374151;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-primary {
            background: #1976d2;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .upload-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .upload-section h2 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s;
            background: #f9fafb;
            min-height: 100px;
        }
        
        .upload-area:hover {
            border-color: #1976d2;
            background: #f8fafc;
        }
        
        .upload-area.dragover {
            border-color: #1976d2;
            background: #eff6ff;
        }
        
        .file-preview {
            display: none;
            margin-top: 20px;
        }
        
        .preview-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e5e7eb;
            gap: 10px;
        }
        
        .preview-item img,
        .preview-item iframe {
            border-radius: 4px;
            border: 1px solid #d1d5db;
        }
        
        .preview-item i {
            color: #6b7280;
        }
        
        .preview-item input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .preview-item button {
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        
        .preview-item button:hover {
            background: #b91c1c;
        }
        
        /* Modern Edit Modal Styles */
        .modern-edit-modal {
            max-width: 500px;
            max-height: 80vh;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(93, 14, 38, 0.3);
            border: 1px solid rgba(93, 14, 38, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .view-modal {
            max-width: 900px;
            max-height: 90vh;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(93, 14, 38, 0.3);
            border: 1px solid rgba(93, 14, 38, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .document-details {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .detail-column {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            gap: 10px;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-row label {
            font-weight: 600;
            color: #374151;
            min-width: 140px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-row span {
            color: #6b7280;
            font-weight: 500;
        }

        .document-preview {
            flex: 1;
            min-height: 400px;
        }

        @media (max-width: 768px) {
            .document-details {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: none;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .modal-header h2 i {
            font-size: 1.1rem;
        }

        .close-modal-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .close-modal-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .modal-body {
            padding: 24px;
            background: white;
            flex: 1;
            overflow-y: auto;
        }

        /* Custom scrollbar for edit modal */
        .modern-edit-modal .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modern-edit-modal .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .modern-edit-modal .modal-body::-webkit-scrollbar-thumb {
            background: #5D0E26;
            border-radius: 3px;
        }

        .modern-edit-modal .modal-body::-webkit-scrollbar-thumb:hover {
            background: #8B1538;
        }

        /* Custom scrollbar for delete modal */
        #deleteConfirmModal .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        #deleteConfirmModal .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        #deleteConfirmModal .modal-body::-webkit-scrollbar-thumb {
            background: #dc2626;
            border-radius: 3px;
        }

        #deleteConfirmModal .modal-body::-webkit-scrollbar-thumb:hover {
            background: #b91c1c;
        }

        .modern-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: #5D0E26;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .form-group input,
        .form-group select {
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #5D0E26;
            background: white;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }

        .form-group input:hover,
        .form-group select:hover {
            border-color: #8B1538;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 8px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
            justify-content: center;
        }

        .btn-secondary {
            background: white !important;
            color: #6c757d !important;
            border: 1px solid #e0e0e0 !important;
            padding: 9px 18px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #f8f9fa !important;
            color: #495057 !important;
            border-color: #d0d0d0 !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4A0B1E, #6B0F2A);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(93, 14, 38, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .btn-danger:hover:not(:disabled) {
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(220, 38, 38, 0.4);
        }

        .btn-danger:disabled {
            background: #d1d5db;
            color: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn i {
            font-size: 0.9rem;
        }

        /* Modal overlay improvements */
        .modal {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
        }
        
        .download-modal {
            max-width: 700px;
        }
        
        /* Add proper padding to modal content */
        .modal-content {
            padding: 25px !important;
            box-sizing: border-box;
        }
        
        /* Adjust header padding to compensate */
        .modal-header {
            margin: -25px -25px 20px -25px;
        }
        
        .download-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
        }
        
        .download-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .download-item:last-child {
            border-bottom: none;
        }
        
        .download-item input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .download-item-info {
            flex: 1;
        }
        
        .download-item-info h4 {
            margin: 0 0 5px 0;
            font-size: 0.9rem;
        }
        
        .download-item-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1976d2;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        /* Override dashboard.css for document upload form */
        .preview-item input[type="text"],
        .preview-item input[type="number"],
        .preview-item select {
            width: auto !important;
            padding: 8px 12px !important;
            border: 1px solid #ced4da !important;
            border-radius: 4px !important;
            height: 36px !important;
            font-size: 0.85rem !important;
            background: white !important;
            margin: 0 !important;
            box-sizing: border-box !important;
        }
        
        .preview-item input[type="text"]:focus,
        .preview-item input[type="number"]:focus,
        .preview-item select:focus {
            outline: none !important;
            border-color: #5D0E26 !important;
            box-shadow: 0 0 0 2px rgba(93, 14, 38, 0.1) !important;
        }
        
        /* Ensure proper flex behavior */
        .preview-item > div {
            width: 100% !important;
        }

        /* Notification Badge Styles */
        .notification-badge {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 4px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            margin-right: 8px;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
            animation: pulseBadge 2s infinite;
        }

        .notification-badge:empty {
            display: none;
        }

        @keyframes pulseBadge {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
                <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="employee_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="employee_documents.php" class="active"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li class="has-submenu">
                <a href="#" class="submenu-toggle"><i class="fas fa-file-alt"></i><span>Document Generations</span><i class="fas fa-chevron-down submenu-arrow"></i></a>
                <ul class="submenu">
                    <li><a href="employee_document_generation.php"><i class="fas fa-file-plus"></i><span>Generate Documents</span></a></li>
                    <li><a href="employee_send_files.php"><i class="fas fa-paper-plane"></i><span>Send Files</span></a></li>
                </ul>
            </li>
            <li><a href="employee_schedule.php"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a></li>
            <li><a href="employee_clients.php"><i class="fas fa-users"></i><span>Client Management</span></a></li>
            <li><a href="employee_request_management.php"><i class="fas fa-clipboard-check"></i><span>Request Review</span><?php if ($pending_requests_count > 0): ?><span class="notification-badge"><?= $pending_requests_count ?></span><?php endif; ?></a></li>
            <li><a href="employee_messages.php"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php 
        $page_title = 'Advanced Document Storage';
        $page_subtitle = 'Upload, manage, and organize documents with advanced features';
        include 'components/profile_header.php'; 
        ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($documents) ?></div>
                <div class="stat-label">Total Documents</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= get_current_book_number() ?></div>
                <div class="stat-label">Current Book</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= get_next_doc_number($conn, get_current_book_number()) - 1 ?></div>
                <div class="stat-label">Last Doc Number</div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Upload Section -->
        <div class="upload-section">
            <h2><i class="fas fa-upload"></i> Upload Documents</h2>
            <form method="POST" enctype="multipart/form-data" id="uploadForm" onsubmit="return handleUploadSubmit(event)">
                <div class="upload-area" id="uploadArea">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #6b7280; margin-bottom: 10px;"></i>
                    <h3 style="font-size: 1.1rem; margin-bottom: 5px;">Drag & Drop Files Here</h3>
                    <p style="font-size: 0.9rem; color: #6b7280;">or click to select files (PDF, Word documents only - up to 10 documents)</p>
                    <input type="file" name="documents[]" id="fileInput" multiple accept=".pdf,.doc,.docx" style="display: none;">
                </div>
                
                <div class="file-preview" id="filePreview">
                    <h4>Document Details</h4>
                <div style="background: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; padding: 8px; margin-bottom: 15px; font-size: 0.8rem;">
                    <strong>📚 Book Number:</strong> Current month is <strong><?= date('F') ?> (<?= date('n') ?>)</strong> - You can change this if needed
                </div>
                    <div id="previewList"></div>
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" class="btn-primary" id="uploadBtn" style="display: none;">
                        <i class="fas fa-upload"></i> Upload Documents
            </button>
                </div>
            </form>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <h2><i class="fas fa-filter"></i> Filters & Search</h2>
            <form method="GET" id="filterForm" onsubmit="return handleFilterSubmit(event)">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Date From:</label>
                        <input type="date" name="filter_from" value="<?= htmlspecialchars($filter_from) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Date To:</label>
                        <input type="date" name="filter_to" value="<?= htmlspecialchars($filter_to) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Doc Number:</label>
                        <input type="number" name="doc_number" value="<?= htmlspecialchars($filter_doc_number) ?>" placeholder="Enter doc number">
                    </div>
                    <div class="filter-group">
                        <label>Book Number:</label>
                        <select name="book_number">
                            <option value="">All Books</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $filter_book_number == $i ? 'selected' : '' ?>>
                                    Book <?= $i ?> (<?= date('F', mktime(0, 0, 0, $i, 1)) ?>)
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Document Name:</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($filter_name) ?>" placeholder="Search by name">
                    </div>
                    <div class="filter-group">
                        <label>Category:</label>
                        <select name="category">
                            <option value="">All Categories</option>
                            <option value="Notarized Documents" <?= (isset($_GET['category']) && $_GET['category'] == 'Notarized Documents') ? 'selected' : '' ?>>Notarized Documents</option>
                            <option value="Law Office Files" <?= (isset($_GET['category']) && $_GET['category'] == 'Law Office Files') ? 'selected' : '' ?>>Law Office Files</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Affidavit Type:</label>
                        <select name="affidavit_type">
                            <option value="">All Types</option>
                            <option value="Affidavit of Loss" <?= $filter_affidavit_type == 'Affidavit of Loss' ? 'selected' : '' ?>>Affidavit of Loss</option>
                            <option value="Affidavit of Loss (Senior ID)" <?= $filter_affidavit_type == 'Affidavit of Loss (Senior ID)' ? 'selected' : '' ?>>Affidavit of Loss (Senior ID)</option>
                            <option value="Affidavit of Loss (PWD ID)" <?= $filter_affidavit_type == 'Affidavit of Loss (PWD ID)' ? 'selected' : '' ?>>Affidavit of Loss (PWD ID)</option>
                            <option value="Affidavit of Loss (Boticab Booklet/ID)" <?= $filter_affidavit_type == 'Affidavit of Loss (Boticab Booklet/ID)' ? 'selected' : '' ?>>Affidavit of Loss (Boticab Booklet/ID)</option>
                            <option value="Sworn Affidavit of Solo Parent" <?= $filter_affidavit_type == 'Sworn Affidavit of Solo Parent' ? 'selected' : '' ?>>Sworn Affidavit of Solo Parent</option>
                            <option value="Sworn Affidavit of Mother" <?= $filter_affidavit_type == 'Sworn Affidavit of Mother' ? 'selected' : '' ?>>Sworn Affidavit of Mother</option>
                            <option value="Sworn Affidavit (Solo Parent)" <?= $filter_affidavit_type == 'Sworn Affidavit (Solo Parent)' ? 'selected' : '' ?>>Sworn Affidavit (Solo Parent)</option>
                            <option value="Joint Affidavit (Two Disinterested Person)" <?= $filter_affidavit_type == 'Joint Affidavit (Two Disinterested Person)' ? 'selected' : '' ?>>Joint Affidavit (Two Disinterested Person)</option>
                            <option value="Joint Affidavit of Two Disinterested Person (Solo Parent)" <?= $filter_affidavit_type == 'Joint Affidavit of Two Disinterested Person (Solo Parent)' ? 'selected' : '' ?>>Joint Affidavit of Two Disinterested Person (Solo Parent)</option>
                        </select>
                    </div>
        </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="employee_documents.php" class="btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                    <button type="button" class="btn-secondary" onclick="openDownloadModal()">
                        <i class="fas fa-download"></i> Select Download
                    </button>
            </div>
            </form>
        </div>

        <!-- Category Tabs -->
        <?php
        // Count documents by category
        $lawOfficeDocs = array_filter($documents, function($doc) {
            return $doc['category'] === 'Law Office Files';
        });
        
        $notarizedDocs = array_filter($documents, function($doc) {
            return $doc['category'] === 'Notarized Documents';
        });
        ?>
        
        <div class="category-tabs">
            <button class="tab-btn active" onclick="showCategory('all')">
                <i class="fas fa-list"></i> All Documents (<?= count($documents) ?>)
            </button>
            
            <button class="tab-btn" onclick="showCategory('notarized')">
                <i class="fas fa-file-contract"></i> Notarized Documents (<?= count($notarizedDocs) ?>)
            </button>
            
            <button class="tab-btn" onclick="showCategory('lawoffice')">
                <i class="fas fa-folder-open"></i> Law Office Files (<?= count($lawOfficeDocs) ?>)
            </button>
        </div>

        <!-- Documents Grid -->
        <div class="document-grid" id="documents">
            <?php if (empty($documents)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <i class="fas fa-folder-open" style="font-size: 3rem; color: #d1d5db; margin-bottom: 15px;"></i>
                    <h3 style="color: #6b7280;">No documents found</h3>
                    <p style="color: #9ca3af;">Try adjusting your filters or upload some documents.</p>
                </div>
            <?php else: ?>
            <?php foreach ($documents as $doc): ?>
                    <div class="document-card" data-category="<?= htmlspecialchars($doc['category']) ?>">
                        <div class="card-header">
                            <div class="document-icon" style="margin-right: 8px !important; padding-right: 0px !important;">
                                <?php 
                                $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                if($ext === 'pdf'): ?>
                                    <i class="fas fa-file-pdf" style="color: #d32f2f;"></i>
                        <?php elseif($ext === 'doc' || $ext === 'docx'): ?>
                                    <i class="fas fa-file-word" style="color: #1976d2;"></i>
                        <?php elseif($ext === 'xls' || $ext === 'xlsx'): ?>
                                    <i class="fas fa-file-excel" style="color: #388e3c;"></i>
                        <?php else: ?>
                            <i class="fas fa-file-alt"></i>
                        <?php endif; ?>
                    </div>
                    <div class="document-info">
                                 <h3 title="<?= htmlspecialchars($doc['document_name'] ?? $doc['file_name']) ?>"><?= htmlspecialchars(truncate_document_name($doc['document_name'] ?? $doc['file_name'])) ?></h3>
                        <div class="document-meta">
                            <?php 
                            $category_colors = [
                                'Notarized Documents' => ['bg' => '#5D0E26', 'text' => 'white'],
                                'Law Office Files' => ['bg' => '#059669', 'text' => 'white']
                            ];
                            $colors = $category_colors[$doc['category']] ?? ['bg' => '#6b7280', 'text' => 'white'];
                            ?>
                            <div style="font-size: 0.7rem; color: <?= $colors['text'] ?>; font-weight: 600; background: <?= $colors['bg'] ?>; padding: 4px 8px; border-radius: 6px; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <?= htmlspecialchars($doc['category']) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 500; margin-top: 4px;">
                                <strong>Date Uploaded:</strong> <?= date('M d, Y', strtotime($doc['upload_date'])) ?>
                            </div>
                        </div>
                    </div>
                    </div>

                        <div class="document-actions">
                            <button onclick="openViewModal('<?= htmlspecialchars($doc['file_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['document_name'] ?? $doc['file_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['category'], ENT_QUOTES) ?>', '<?= $doc['doc_number'] ?>', '<?= $doc['book_number'] ?>', '<?= htmlspecialchars($doc['affidavit_type'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['uploader_name'] ?? 'Employee', ENT_QUOTES) ?>')" class="btn-action btn-view" title="View Document">
                                <i class="fas fa-eye"></i>
                            </button>
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" download onclick="return showDownloadConfirmModal('<?= htmlspecialchars($doc['document_name'] ?? $doc['file_name'], ENT_QUOTES) ?>', this.href)" class="btn-action btn-view" title="Download Document">
                                <i class="fas fa-download"></i>
                            </a>
                            <button onclick="openEditModal(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['document_name'], ENT_QUOTES) ?>', <?= $doc['doc_number'] ?>, <?= $doc['book_number'] ?>, '<?= htmlspecialchars($doc['affidavit_type'], ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['category'], ENT_QUOTES) ?>')" class="btn-action btn-edit" title="Edit Document">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete=<?= $doc['id'] ?>" onclick="return showDeleteConfirmModal('<?= htmlspecialchars($doc['document_name'] ?? $doc['file_name'], ENT_QUOTES) ?>', this.href)" class="btn-action btn-delete" title="Delete Document">
                                <i class="fas fa-trash"></i>
                            </a>
                </div>
        </div>
                    <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content modern-edit-modal">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Document</h2>
                <button class="close-modal-btn" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" class="modern-form" id="editForm">
                    <input type="hidden" name="edit_id" id="edit_id">
                    
                    <!-- Error Display Area -->
                    <div id="editErrorDisplay" style="display: none; background: #fee; border: 1px solid #fb7185; border-radius: 6px; padding: 12px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-exclamation-circle" style="color: #dc2626; font-size: 16px;"></i>
                            <span id="editErrorText" style="color: #dc2626; font-weight: 500; font-size: 14px;"></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_document_name">
                            <i class="fas fa-file-alt"></i> Document Name
                        </label>
                        <input type="text" name="edit_document_name" id="edit_document_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_doc_number">
                            <i class="fas fa-hashtag"></i> Doc Number
                        </label>
                        <input type="number" name="edit_doc_number" id="edit_doc_number" required onchange="clearEditError()">
                    </div>
                    <div class="form-group">
                        <label for="edit_book_number">
                            <i class="fas fa-book"></i> Book Number
                        </label>
                        <input type="number" name="edit_book_number" id="edit_book_number" required onchange="clearEditError()">
                    </div>
                    <div class="form-group">
                        <label for="edit_affidavit_type">
                            <i class="fas fa-file-contract"></i> Type of Affidavit
                        </label>
                        <select name="edit_affidavit_type" id="edit_affidavit_type" required onchange="clearEditError()" style="width: 100%; padding: 12px 16px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #fafafa;">
                            <option value="">Select Affidavit Type</option>
                            <option value="Affidavit of Loss">Affidavit of Loss</option>
                            <option value="Affidavit of Loss (Senior ID)">Affidavit of Loss (Senior ID)</option>
                            <option value="Affidavit of Loss (PWD ID)">Affidavit of Loss (PWD ID)</option>
                            <option value="Affidavit of Loss (Boticab Booklet/ID)">Affidavit of Loss (Boticab Booklet/ID)</option>
                            <option value="Sworn Affidavit of Solo Parent">Sworn Affidavit of Solo Parent</option>
                            <option value="Sworn Affidavit of Mother">Sworn Affidavit of Mother</option>
                            <option value="Sworn Affidavit (Solo Parent)">Sworn Affidavit (Solo Parent)</option>
                            <option value="Joint Affidavit (Two Disinterested Person)">Joint Affidavit (Two Disinterested Person)</option>
                            <option value="Joint Affidavit of Two Disinterested Person (Solo Parent)">Joint Affidavit of Two Disinterested Person (Solo Parent)</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content view-modal">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> View Document</h2>
                <button class="close-modal-btn" onclick="closeViewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="document-details">
                    <div class="detail-column">
                        <div class="detail-row">
                            <label><i class="fas fa-file-alt"></i> Document Name:</label>
                            <span id="viewDocumentName"></span>
                        </div>
                        <div class="detail-row">
                            <label><i class="fas fa-folder"></i> Category:</label>
                            <span id="viewCategory"></span>
                        </div>
                        <div class="detail-row">
                            <label><i class="fas fa-user"></i> Uploaded by:</label>
                            <span id="viewUploader"></span>
                        </div>
                    </div>
                    <div class="detail-column">
                        <div class="detail-row" id="viewDocNumberRow" style="display: none;">
                            <label><i class="fas fa-hashtag"></i> Doc Number:</label>
                            <span id="viewDocNumber"></span>
                        </div>
                        <div class="detail-row" id="viewBookNumberRow" style="display: none;">
                            <label><i class="fas fa-book"></i> Book Number:</label>
                            <span id="viewBookNumber"></span>
                        </div>
                        <div class="detail-row" id="viewAffidavitTypeRow" style="display: none;">
                            <label><i class="fas fa-certificate"></i> Affidavit Type:</label>
                            <span id="viewAffidavitType"></span>
                        </div>
                    </div>
                </div>
                <div class="document-preview">
                    <iframe id="documentFrame" src="" width="100%" height="500px" style="border: 1px solid #ddd; border-radius: 8px;"></iframe>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <a id="downloadLink" href="" download class="btn btn-primary">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
        </div>
    </div>

    <!-- Download Modal -->
    <div id="downloadModal" class="modal">
        <div class="modal-content download-modal">
            <!-- Modal Header -->
            <div class="modal-header">
                <h2><i class="fas fa-download"></i> Download Documents</h2>
                <button class="close-btn" onclick="closeDownloadModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Date Filter Section -->
            <div class="date-filter-section">
                <div class="filter-tabs">
                    <button class="filter-btn active" onclick="setDateFilter('all')">All Documents</button>
                    <button class="filter-btn" onclick="setDateFilter('today')">Today</button>
                    <button class="filter-btn" onclick="setDateFilter('yesterday')">Yesterday</button>
                    <button class="filter-btn" onclick="setDateFilter('custom')">Custom Range</button>
                    
                    <!-- Date inputs next to Custom Range button -->
                    <div id="customDateRange" class="custom-date-range">
                        <div class="date-inputs">
                            <div>
                                <label>From:</label>
                                <input type="date" id="dateFrom" onchange="filterByCustomDate()">
                            </div>
                            <div>
                                <label>To:</label>
                                <input type="date" id="dateTo" onchange="filterByCustomDate()">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Documents List -->
            <div class="download-list-container">
                <div class="list-header">
                    <span class="doc-count">Documents: <span id="docCount">0</span></span>
                    <span class="selected-count">Selected: <span id="selectedCount">0</span></span>
                </div>
                
                <div class="download-list" id="downloadList">
                    <?php foreach ($documents as $doc): ?>
                        <?php if ($doc['category'] === 'Notarized Documents'): ?>
                        <div class="download-item" data-date="<?= date('Y-m-d', strtotime($doc['upload_date'])) ?>">
                            <input type="checkbox" value="<?= $doc['id'] ?>" onchange="updateSelectedCount()" 
                                   data-name="<?= htmlspecialchars($doc['file_name']) ?>" 
                                   data-path="<?= htmlspecialchars($doc['file_path']) ?>" 
                                   data-doc-number="<?= $doc['doc_number'] ?>" 
                                   data-book-number="<?= $doc['book_number'] ?>">
                            <div class="download-item-info">
                                <div class="item-header">
                                    <div class="file-icon">
                                        <?php 
                                        $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                        if($ext === 'pdf'): ?>
                                            <i class="fas fa-file-pdf"></i>
                                        <?php elseif($ext === 'doc' || $ext === 'docx'): ?>
                                            <i class="fas fa-file-word"></i>
                                        <?php elseif($ext === 'xls' || $ext === 'xlsx'): ?>
                                            <i class="fas fa-file-excel"></i>
                                        <?php else: ?>
                                            <i class="fas fa-file-alt"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-details">
                                        <h4 title="<?= htmlspecialchars($doc['document_name'] ?? $doc['file_name']) ?>"><?= htmlspecialchars(truncate_document_name($doc['document_name'] ?? $doc['file_name'])) ?></h4>
                                        <div class="item-meta">
                                            <span class="doc-info">Doc #<?= $doc['doc_number'] ?> | Book #<?= $doc['book_number'] ?></span>
                                            <span class="upload-date"><?= date('M d, Y', strtotime($doc['upload_date'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="modal-footer">
                <button onclick="selectAllDownloads()" class="btn-select-all">
                    <i class="fas fa-check-square"></i> Select All
                </button>
                <button onclick="clearSelection()" class="btn-clear">
                    <i class="fas fa-times"></i> Clear Selection
                </button>
                <button onclick="downloadSelected()" class="btn-download" disabled id="downloadBtn">
                    <i class="fas fa-download"></i> Download ZIP
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal">
        <div class="modal-content" style="max-width: 350px; width: 80%; max-height: 70vh; display: flex; flex-direction: column;">
            <div class="modal-header" style="padding: 15px 20px 10px 20px;">
                <h2 style="font-size: 1.1rem;"><i class="fas fa-exclamation-triangle"></i> Delete Document</h2>
                <button class="close-modal-btn" onclick="closeDeleteConfirmModal()" style="width: 28px; height: 28px; font-size: 12px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 15px; flex: 1; overflow-y: auto;">
                <div style="text-align: center;">
                    <div style="font-size: 2rem; color: #dc2626; margin-bottom: 10px;">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <h3 style="color: #dc2626; margin-bottom: 10px; font-size: 1rem;">⚠️ WARNING</h3>
                    <p style="color: #6b7280; font-size: 0.85rem; margin-bottom: 15px;">
                        You are about to permanently delete this document:
                    </p>
                    <div style="background: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; padding: 10px; margin-bottom: 15px;">
                        <strong id="deleteDocumentName" style="color: #dc2626; font-size: 0.9rem;"></strong>
                    </div>
                    <div style="background: #fef3cd; border: 1px solid #fde68a; border-radius: 4px; padding: 8px; margin-bottom: 15px;">
                        <p style="color: #92400e; font-size: 0.8rem; margin: 0;">
                            <i class="fas fa-exclamation-circle"></i> This action cannot be undone!
                        </p>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: #374151; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem;">
                            Type "DELETE" to confirm:
                        </label>
                        <input type="text" id="deleteConfirmInput" placeholder="Type DELETE here" 
                               style="width: 100%; padding: 8px; border: 2px solid #e5e7eb; border-radius: 4px; font-size: 0.9rem; text-align: center; letter-spacing: 1px;">
                    </div>
                </div>
                <div class="modal-actions" style="margin-top: 15px; padding-top: 15px;">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteConfirmModal()" style="padding: 8px 16px; font-size: 0.85rem;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="proceedDelete()" id="deleteBtn" disabled style="padding: 8px 16px; font-size: 0.85rem;">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Download Confirmation Modal -->
    <div id="downloadConfirmModal" class="modal">
        <div class="modal-content" style="max-width: 200px; width: 40%; max-height: 200px;">
            <div class="modal-header" style="padding: 8px 15px 5px 15px;">
                <h2 style="font-size: 0.85rem; margin: 0;"><i class="fas fa-download"></i> Download</h2>
                <button class="close-modal-btn" onclick="closeDownloadConfirmModal()" style="width: 20px; height: 20px; font-size: 10px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 8px;">
                <div style="text-align: center;">
                    <div style="font-size: 1rem; color: #5D0E26; margin-bottom: 4px;">
                        <i class="fas fa-download"></i>
                    </div>
                    <p style="color: #6b7280; font-size: 0.7rem; margin-bottom: 4px;">
                        Download this document?
                    </p>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 3px; padding: 4px; margin-bottom: 6px;">
                        <strong id="downloadDocumentName" style="color: #5D0E26; font-size: 0.7rem;"></strong>
                    </div>
                </div>
                <div class="modal-actions" style="margin-top: 6px; padding-top: 6px;">
                    <button type="button" class="btn btn-secondary" onclick="closeDownloadConfirmModal()" style="padding: 4px 8px; font-size: 0.7rem;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="proceedDownload()" style="padding: 4px 8px; font-size: 0.7rem;">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal">
        <div class="modal-content" style="max-width: 90%; max-height: 90%;">
            <span class="close" onclick="closePreviewModal()">&times;</span>
            <h2 id="previewTitle">Document Preview</h2>
            <div id="previewContent" style="text-align: center; margin-top: 20px;">
                <!-- Preview content will be inserted here -->
            </div>
        </div>
    </div>

    <script>
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');
        const previewList = document.getElementById('previewList');
        const uploadBtn = document.getElementById('uploadBtn');

        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('dragleave', handleDragLeave);
        uploadArea.addEventListener('drop', handleDrop);
        fileInput.addEventListener('change', handleFileSelect);

        function handleDragOver(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        }

        function handleDrop(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            handleFiles(files);
        }

        function handleFileSelect(e) {
            const files = e.target.files;
            handleFiles(files);
        }

        // Store file data for persistent preview
        let fileDataStore = new Map();
        let currentBookNumber = <?= date('n') ?>; // Current month

        function handleFiles(files) {
            if (files.length > 10) {
                alert('Maximum 10 files allowed');
                return;
            }

            previewList.innerHTML = '';
            fileDataStore.clear(); // Clear previous data
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.setAttribute('data-file-index', i);
                
                // Store file data for persistent preview
                const fileId = 'file_' + Date.now() + '_' + i;
                fileDataStore.set(fileId, {
                    file: file,
                    url: URL.createObjectURL(file),
                    name: file.name,
                    type: file.type
                });
                
                // Create preview based on file type
                let previewContent = '';
                if (file.type.startsWith('image/')) {
                    previewContent = `
                        <div style="position: relative; margin-right: 10px;">
                            <img src="${fileDataStore.get(fileId).url}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; border: 1px solid #d1d5db;">
                            <button type="button" onclick="openPreviewModal('${fileId}')" style="position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 3px; padding: 2px 6px; font-size: 10px; cursor: pointer;">👁</button>
                        </div>
                    `;
                } else if (file.type === 'application/pdf') {
                    previewContent = `
                        <div style="position: relative; margin-right: 10px;">
                            <iframe src="${fileDataStore.get(fileId).url}" style="width: 80px; height: 80px; border-radius: 4px; border: 1px solid #d1d5db;"></iframe>
                            <button type="button" onclick="openPreviewModal('${fileId}')" style="position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 3px; padding: 2px 6px; font-size: 10px; cursor: pointer;">👁</button>
                        </div>
                    `;
                } else {
                    previewContent = `
                        <div style="position: relative; margin-right: 10px;">
                            <i class="fas fa-file" style="font-size: 48px; color: #6b7280; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; border: 1px solid #d1d5db; border-radius: 4px;"></i>
                            <button type="button" onclick="openPreviewModal('${fileId}')" style="position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 3px; padding: 2px 6px; font-size: 10px; cursor: pointer;">👁</button>
                        </div>
                    `;
                }
                
                previewItem.innerHTML = `
                    <div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 15px; margin-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 12px;">
                            ${previewContent}
                            <div style="flex: 1;">
                                <div style="font-size: 0.8rem; color: #495057; font-weight: 500; margin-bottom: 2px;">Document Name:</div>
                                <div style="font-size: 0.9rem; color: #212529; font-weight: 600;">${file.name}</div>
                            </div>
                            <button type="button" onclick="removePreviewItem(this)" style="background: #dc3545; color: white; border: none; border-radius: 6px; padding: 8px 12px; cursor: pointer; font-size: 0.8rem; font-weight: 500;">Remove</button>
                        </div>
                        <div class="category-row" style="display:flex; align-items:center; gap:8px; margin-bottom: 12px; width:100%;">
                            <select name="category[]" required onchange="toggleFieldsBasedOnCategory(this)" style="flex: 0 0 220px; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white;">
                                <option value="">Select Category *</option>
                                <option value="Notarized Documents">Notarized Documents</option>
                                <option value="Law Office Files">Law Office Files</option>
                            </select>
                            <!-- Law Office Files inline field -->
                            <div id="lawOfficeFields" style="display: none; flex: 1;">
                                <input type="text" name="document_names[]" placeholder="Enter document name/description" style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white; min-width: 0;">
                            </div>
                        </div>
                        <!-- Notarized Documents Fields -->
                        <div id="notarizedFields" style="display: none;">
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; width: 100%;">
                                <input type="text" name="surnames[]" placeholder="Surname" style="flex: 1; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white; min-width: 0;">
                                <input type="text" name="first_names[]" placeholder="First Name" style="flex: 1; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white; min-width: 0;">
                                <input type="text" name="middle_names[]" placeholder="Middle Name" style="flex: 1; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white; min-width: 0;">
                                <input type="number" name="doc_numbers[]" placeholder="Doc #" style="flex: 0 0 80px; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white;">
                                <input type="number" name="book_numbers[]" value="${currentBookNumber}" min="1" max="12" placeholder="Book" style="flex: 0 0 80px; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white;" title="Book Number (1-12, represents month)">
                                <select name="affidavit_types[]" style="flex: 1; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white; min-width: 0;">
                                    <option value="">Select Affidavit Type</option>
                                    <option value="Affidavit of Loss">Affidavit of Loss</option>
                                    <option value="Affidavit of Loss (Senior ID)">Affidavit of Loss (Senior ID)</option>
                                    <option value="Affidavit of Loss (PWD ID)">Affidavit of Loss (PWD ID)</option>
                                    <option value="Affidavit of Loss (Boticab Booklet/ID)">Affidavit of Loss (Boticab Booklet/ID)</option>
                                    <option value="Sworn Affidavit of Solo Parent">Sworn Affidavit of Solo Parent</option>
                                    <option value="Sworn Affidavit of Mother">Sworn Affidavit of Mother</option>
                                    <option value="Sworn Affidavit (Solo Parent)">Sworn Affidavit (Solo Parent)</option>
                                    <option value="Joint Affidavit (Two Disinterested Person)">Joint Affidavit (Two Disinterested Person)</option>
                                    <option value="Joint Affidavit of Two Disinterested Person (Solo Parent)">Joint Affidavit of Two Disinterested Person (Solo Parent)</option>
                                </select>
                            </div>
                        </div>
                        
                    </div>
                `;
                previewList.appendChild(previewItem);
            }
            
            filePreview.style.display = 'block';
            uploadBtn.style.display = 'inline-flex';
        }

        function removePreviewItem(button) {
            const previewItem = button.parentElement;
            const fileIndex = previewItem.getAttribute('data-file-index');
            
            // Remove the preview item
            previewItem.remove();
            
            // Create a new FileList without the removed file
            const currentFiles = fileInput.files;
            const newFiles = [];
            for (let i = 0; i < currentFiles.length; i++) {
                if (i != fileIndex) {
                    newFiles.push(currentFiles[i]);
                }
            }
            
            // Create a new DataTransfer object to update the file input
            const dt = new DataTransfer();
            newFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
            
            // Update preview indices for remaining items
            const remainingItems = previewList.children;
            for (let i = 0; i < remainingItems.length; i++) {
                remainingItems[i].setAttribute('data-file-index', i);
            }
            
            if (previewList.children.length === 0) {
                filePreview.style.display = 'none';
                uploadBtn.style.display = 'none';
            }
        }

        // Handle form submission with AJAX
        function handleUploadSubmit(event) {
            event.preventDefault();
            
            // First validate the form
            if (!validateUploadForm()) {
                return false;
            }
            
            // Show loading state
            const uploadBtn = document.getElementById('uploadBtn');
            const originalText = uploadBtn.innerHTML;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            uploadBtn.disabled = true;
            
            // Create FormData
            const formData = new FormData(document.getElementById('uploadForm'));
            
            // Submit via AJAX
            fetch('employee_documents.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Reset button
                uploadBtn.innerHTML = originalText;
                uploadBtn.disabled = false;
                
                // Check if there are errors in the response
                if (data.includes('Upload Error:')) {
                    // Extract error message from response
                    const errorMatch = data.match(/alert\('Upload Error:\\n\\n([^']+)'\)/);
                    if (errorMatch) {
                        const errorMessage = errorMatch[1].replace(/\\n/g, '\n');
                        alert('Upload Error:\n\n' + errorMessage);
                    }
                } else if (data.includes('Successfully uploaded')) {
                    // Extract success message
                    const successMatch = data.match(/Successfully uploaded (\d+) document\(s\)!/);
                    if (successMatch) {
                        alert('Successfully uploaded ' + successMatch[1] + ' document(s)!');
                        // Clear the form
                        document.getElementById('filePreview').style.display = 'none';
                        document.getElementById('uploadBtn').style.display = 'none';
                        document.getElementById('fileInput').value = '';
                        fileDataStore.clear();
                        // Reload the page to show new documents
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                }
            })
            .catch(error => {
                // Reset button
                uploadBtn.innerHTML = originalText;
                uploadBtn.disabled = false;
                alert('Upload failed: ' + error.message);
            });
            
            return false;
        }

        // Form validation with detailed error messages
        function validateUploadForm() {
            const categories = document.querySelectorAll('select[name="category[]"]');
            const errors = [];
            
            for (let i = 0; i < categories.length; i++) {
                const category = categories[i].value;
                const previewItem = categories[i].closest('.preview-item');
                
                if (!category) {
                    errors.push(`File ${i + 1}: Category is required`);
                    continue;
                }
                
                if (category === 'Notarized Documents') {
                    const surname = previewItem.querySelector('input[name="surnames[]"]');
                    const firstName = previewItem.querySelector('input[name="first_names[]"]');
                    const docNumber = previewItem.querySelector('input[name="doc_numbers[]"]');
                    const bookNumber = previewItem.querySelector('input[name="book_numbers[]"]');
                    const affidavitType = previewItem.querySelector('select[name="affidavit_types[]"]');
                    
                    if (!surname || !surname.value.trim()) {
                        errors.push(`File ${i + 1}: Surname is required`);
                    }
                    if (!firstName || !firstName.value.trim()) {
                        errors.push(`File ${i + 1}: First Name is required`);
                    }
                    if (!docNumber || !docNumber.value || docNumber.value <= 0) {
                        errors.push(`File ${i + 1}: Doc Number must be greater than 0`);
                    }
                    if (!bookNumber || !bookNumber.value || bookNumber.value < 1 || bookNumber.value > 12) {
                        errors.push(`File ${i + 1}: Book Number must be between 1-12`);
                    }
                    if (!affidavitType || !affidavitType.value) {
                        errors.push(`File ${i + 1}: Affidavit Type is required`);
                    }
                } else if (category === 'Law Office Files') {
                    const documentName = previewItem.querySelector('input[name="document_names[]"]');
                    
                    if (!documentName || !documentName.value.trim()) {
                        errors.push(`File ${i + 1}: Document name is required`);
                    }
                }
            }
            
            if (errors.length > 0) {
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }
            
            // Check for duplicate doc numbers in the current upload batch (Notarized Documents only)
            const docBookPairs = [];
            for (let i = 0; i < categories.length; i++) {
                const category = categories[i].value;
                
                if (category === 'Notarized Documents') {
                    const previewItem = categories[i].closest('.preview-item');
                    const docNumber = previewItem.querySelector('input[name="doc_numbers[]"]');
                    const bookNumber = previewItem.querySelector('input[name="book_numbers[]"]');
                    
                    if (docNumber && bookNumber) {
                        const docNum = docNumber.value;
                        const bookNum = bookNumber.value;
                        const pair = `${docNum}-${bookNum}`;
                        
                        if (docBookPairs.includes(pair)) {
                            alert(`Error: Doc Number ${docNum} in Book ${bookNum} is duplicated in this upload. Please use unique Doc Numbers within the same Book for this upload.`);
                            return false;
                        }
                        docBookPairs.push(pair);
                    }
                }
            }
            
            return true;
        }

        // Preview functions
        function openPreviewModal(fileId) {
            const fileData = fileDataStore.get(fileId);
            if (!fileData) {
                alert('File data not found. Please reselect the files.');
                return;
            }
            
            document.getElementById('previewTitle').textContent = `Preview: ${fileData.name}`;
            const previewContent = document.getElementById('previewContent');
            
            if (fileData.type.startsWith('image/')) {
                previewContent.innerHTML = `
                    <div style="position: relative;">
                        <img src="${fileData.url}" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        <button onclick="closePreviewModal()" style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
                    </div>
                `;
            } else if (fileData.type === 'application/pdf') {
                previewContent.innerHTML = `
                    <div style="position: relative;">
                        <iframe src="${fileData.url}" style="width: 100%; height: 70vh; border: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></iframe>
                        <button onclick="closePreviewModal()" style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 9999;">&times;</button>
                    </div>
                `;
            } else {
                previewContent.innerHTML = `
                    <div style="padding: 40px; position: relative;">
                        <button onclick="closePreviewModal()" style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
                        <i class="fas fa-file" style="font-size: 4rem; color: #6b7280; margin-bottom: 20px;"></i>
                        <h3>${fileData.name}</h3>
                        <p>This file type cannot be previewed in the browser.</p>
                        <p>Please download the file to view its contents.</p>
                    </div>
                `;
            }
            
            document.getElementById('previewModal').style.display = 'block';
        }

        function closePreviewModal() {
            document.getElementById('previewModal').style.display = 'none';
            // Don't clean up URLs here - keep them for persistent preview
        }

        // Modal functions
        function openEditModal(id, documentName, docNumber, bookNumber, affidavitType, category) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_document_name').value = documentName;
            document.getElementById('edit_doc_number').value = docNumber;
            document.getElementById('edit_book_number').value = bookNumber;
            document.getElementById('edit_affidavit_type').value = affidavitType;
            
            // Show/hide fields based on category
            const docNumberGroup = document.getElementById('edit_doc_number').closest('.form-group');
            const bookNumberGroup = document.getElementById('edit_book_number').closest('.form-group');
            const affidavitTypeGroup = document.getElementById('edit_affidavit_type').closest('.form-group');
            
            if (category === 'Law Office Files') {
                // Hide notarized fields for Law Office Files and remove required attribute
                docNumberGroup.style.display = 'none';
                bookNumberGroup.style.display = 'none';
                affidavitTypeGroup.style.display = 'none';
                document.getElementById('edit_doc_number').required = false;
                document.getElementById('edit_book_number').required = false;
                document.getElementById('edit_affidavit_type').required = false;
            } else {
                // Show all fields for Notarized Documents and add required attribute
                docNumberGroup.style.display = 'block';
                bookNumberGroup.style.display = 'block';
                affidavitTypeGroup.style.display = 'block';
                document.getElementById('edit_doc_number').required = true;
                document.getElementById('edit_book_number').required = true;
                document.getElementById('edit_affidavit_type').required = true;
            }
            
            // Clear any previous error
            document.getElementById('editErrorDisplay').style.display = 'none';
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function clearEditError() {
            document.getElementById('editErrorDisplay').style.display = 'none';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function openViewModal(filePath, documentName, category, docNumber, bookNumber, affidavitType, uploader) {
            // Set document details
            document.getElementById('viewDocumentName').textContent = documentName;
            document.getElementById('viewCategory').textContent = category;
            document.getElementById('viewUploader').textContent = uploader;
            
            // Show/hide fields based on category
            const docNumberRow = document.getElementById('viewDocNumberRow');
            const bookNumberRow = document.getElementById('viewBookNumberRow');
            const affidavitTypeRow = document.getElementById('viewAffidavitTypeRow');
            
            if (category === 'Notarized Documents') {
                document.getElementById('viewDocNumber').textContent = docNumber;
                document.getElementById('viewBookNumber').textContent = bookNumber;
                document.getElementById('viewAffidavitType').textContent = affidavitType;
                docNumberRow.style.display = 'flex';
                bookNumberRow.style.display = 'flex';
                affidavitTypeRow.style.display = 'flex';
            } else {
                docNumberRow.style.display = 'none';
                bookNumberRow.style.display = 'none';
                affidavitTypeRow.style.display = 'none';
            }
            
            // Set iframe source and download link
            document.getElementById('documentFrame').src = filePath;
            document.getElementById('downloadLink').href = filePath;
            
            // Show modal
            document.getElementById('viewModal').style.display = 'block';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
            // Clear iframe to stop loading
            document.getElementById('documentFrame').src = '';
        }

        // Delete confirmation modal functions
        let deleteUrl = '';
        
        function showDeleteConfirmModal(documentName, url) {
            event.preventDefault();
            deleteUrl = url;
            document.getElementById('deleteDocumentName').textContent = documentName;
            document.getElementById('deleteConfirmInput').value = '';
            document.getElementById('deleteBtn').disabled = true;
            document.getElementById('deleteConfirmModal').style.display = 'block';
            
            // Focus on input field
            setTimeout(() => {
                document.getElementById('deleteConfirmInput').focus();
            }, 100);
            
            return false;
        }
        
        function closeDeleteConfirmModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            deleteUrl = '';
            document.getElementById('deleteConfirmInput').value = '';
            document.getElementById('deleteBtn').disabled = true;
        }
        
        function proceedDelete() {
            if (deleteUrl) {
                // Navigate to delete URL
                window.location.href = deleteUrl;
            }
        }
        
        // Enable/disable delete button based on input
        document.addEventListener('DOMContentLoaded', function() {
            const deleteInput = document.getElementById('deleteConfirmInput');
            const deleteBtn = document.getElementById('deleteBtn');
            
            if (deleteInput && deleteBtn) {
                deleteInput.addEventListener('input', function() {
                    if (this.value === 'DELETE') {
                        deleteBtn.disabled = false;
                    } else {
                        deleteBtn.disabled = true;
                    }
                });
                
                // Allow Enter key to proceed if DELETE is typed
                deleteInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && this.value === 'DELETE') {
                        proceedDelete();
                    }
                });
            }
        });

        // Download confirmation modal functions
        let downloadUrl = '';
        
        function showDownloadConfirmModal(documentName, url) {
            event.preventDefault();
            downloadUrl = url;
            document.getElementById('downloadDocumentName').textContent = documentName;
            document.getElementById('downloadConfirmModal').style.display = 'block';
            return false;
        }
        
        function closeDownloadConfirmModal() {
            document.getElementById('downloadConfirmModal').style.display = 'none';
            downloadUrl = '';
        }
        
        function proceedDownload() {
            if (downloadUrl) {
                // Create a temporary link to trigger download
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = '';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
            closeDownloadConfirmModal();
        }

        function confirmDownload(documentName) {
            return confirm(`📥 Download Document\n\nAre you sure you want to download:\n"${documentName}"?`);
        }

        function confirmDelete(documentName) {
            const firstConfirm = confirm(`⚠️ WARNING: Delete Document\n\nYou are about to delete:\n"${documentName}"\n\nThis action cannot be undone!\n\nAre you sure you want to proceed?`);
            
            if (firstConfirm) {
                const secondConfirm = confirm(`🚨 FINAL CONFIRMATION 🚨\n\nYou are about to PERMANENTLY DELETE:\n"${documentName}"\n\nThis action CANNOT be undone!\n\nType "DELETE" in the next prompt to confirm.`);
                
                if (secondConfirm) {
                    const deleteText = prompt(`Type "DELETE" (in capital letters) to confirm deletion of:\n"${documentName}"`);
                    
                    if (deleteText === 'DELETE') {
                        return true;
                    } else if (deleteText !== null) {
                        alert('❌ Deletion cancelled. You must type "DELETE" exactly to confirm.');
                        return false;
                    }
                    return false;
                }
                return false;
            }
            return false;
        }

        function openDownloadModal() {
            document.getElementById('downloadModal').style.display = 'block';
            
            // Debug: Check how many download items exist
            const allItems = document.querySelectorAll('.download-item');
            console.log('Total download items found:', allItems.length);
            
            updateDocumentCount();
            updateSelectedCount();
        }

        function closeDownloadModal() {
            document.getElementById('downloadModal').style.display = 'none';
            // Reset filters
            setDateFilter('all');
        }
        
        function setDateFilter(type) {
            // Update active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            const today = new Date();
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            
            const downloadItems = document.querySelectorAll('.download-item');
            let selectedCount = 0;
            
            downloadItems.forEach(item => {
                const uploadDate = new Date(item.getAttribute('data-date'));
                let show = false;
                
                switch(type) {
                    case 'all':
                        show = true;
                        document.getElementById('customDateRange').style.display = 'none';
                        document.getElementById('customDateRange').classList.remove('active');
                        break;
                    case 'today':
                        show = uploadDate.toDateString() === today.toDateString();
                        document.getElementById('customDateRange').style.display = 'none';
                        document.getElementById('customDateRange').classList.remove('active');
                        break;
                    case 'yesterday':
                        show = uploadDate.toDateString() === yesterday.toDateString();
                        document.getElementById('customDateRange').style.display = 'none';
                        document.getElementById('customDateRange').classList.remove('active');
                        break;
                    case 'custom':
                        const customRange = document.getElementById('customDateRange');
                        if (customRange) {
                            customRange.style.display = 'flex';
                            customRange.classList.add('active');
                            console.log('Custom range shown');
                        } else {
                            console.log('Custom range element not found');
                        }
                        // Don't filter yet, wait for user to select dates
                        show = true;
                        break;
                }
                
                item.style.display = show ? 'flex' : 'none';
                if (show) {
                    const checkbox = item.querySelector('input[type="checkbox"]');
                    if (checkbox && checkbox.checked) {
                        selectedCount++;
                    }
                }
            });
            
            updateDocumentCount();
            updateDownloadButton();
            document.getElementById('customDateRange').style.display = 'none';
        }
        
        function filterByCustomDate() {
            const fromDate = document.getElementById('dateFrom').value;
            const toDate = document.getElementById('dateTo').value;
            
            console.log('Filtering by custom date:', fromDate, 'to', toDate);
            
            if (!fromDate || !toDate) {
                console.log('Dates incomplete, showing all documents');
                // If dates not complete, show all documents
                document.querySelectorAll('.download-item').forEach(item => {
                    item.style.display = 'flex';
                });
                updateDocumentCount();
                updateSelectedCount();
                updateDownloadButton();
                return;
            }
            
            const downloadItems = document.querySelectorAll('.download-item');
            console.log('Found', downloadItems.length, 'download items');
            
            let visibleCount = 0;
            
            downloadItems.forEach(item => {
                const uploadDate = new Date(item.getAttribute('data-date'));
                const filterFrom = new Date(fromDate);
                const filterTo = new Date(toDate);
                
                console.log('Checking item:', item.querySelector('.item-details h4').textContent, 'uploaded on:', uploadDate);
                
                // Add one day to 'to' date to include the entire day
                filterTo.setDate(filterTo.getDate() + 1);
                
                const show = uploadDate >= filterFrom && uploadDate < filterTo;
                item.style.display = show ? 'flex' : 'none';
                
                if (show) {
                    visibleCount++;
                }
            });
            
            console.log('Visible documents after filtering:', visibleCount);
            
            updateDocumentCount();
            updateSelectedCount();
            updateDownloadButton();
        }
        
        function updateDocumentCount() {
            const visibleItems = document.querySelectorAll('.download-item[style*="flex"], .download-item:not([style*="none"])');
            document.getElementById('docCount').textContent = visibleItems.length;
            console.log('Updated document count:', visibleItems.length);
        }
        
        function updateSelectedCount() {
            const visibleItems = document.querySelectorAll('.download-item[style*="flex"], .download-item:not([style*="none"])');
            let selectedCount = 0;
            
            visibleItems.forEach(item => {
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (checkbox && checkbox.checked) {
                    selectedCount++;
                }
            });
            
            document.getElementById('selectedCount').textContent = selectedCount;
            updateDownloadButton();
        }
        
        function updateDownloadButton() {
            const selectedCount = parseInt(document.getElementById('selectedCount').textContent);
            const downloadBtn = document.getElementById('downloadBtn');
            
            downloadBtn.disabled = selectedCount === 0;
        }
        
        function selectAllDownloads() {
            const visibleItems = document.querySelectorAll('.download-item[style*="flex"], .download-item:not([style])');
            visibleItems.forEach(item => {
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            updateSelectedCount();
        }
        
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.download-item input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('#downloadList input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = true);
        }

        function downloadSelected() {
            const selected = document.querySelectorAll('#downloadList input[type="checkbox"]:checked');
            if (selected.length === 0) {
                alert('Please select at least one document');
                return;
            }

            // Show confirmation dialog
            const confirmMessage = `Are you sure you want to download ${selected.length} selected document(s)?\n\nThis will create a ZIP file containing all selected documents.`;
            if (!confirm(confirmMessage)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'download_selected_documents.php';
            
            selected.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_docs[]';
                input.value = cb.value;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const viewModal = document.getElementById('viewModal');
            const downloadModal = document.getElementById('downloadModal');
            const downloadConfirmModal = document.getElementById('downloadConfirmModal');
            const deleteConfirmModal = document.getElementById('deleteConfirmModal');
            const previewModal = document.getElementById('previewModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === viewModal) {
                closeViewModal();
            }
            if (event.target === downloadModal) {
                closeDownloadModal();
            }
            if (event.target === downloadConfirmModal) {
                closeDownloadConfirmModal();
            }
            if (event.target === deleteConfirmModal) {
                closeDeleteConfirmModal();
            }
            if (event.target === previewModal) {
                closePreviewModal();
            }
        }

        // Handle filter form submission without page reload
        function handleFilterSubmit(event) {
            event.preventDefault();
            
            // Save current scroll position
            sessionStorage.setItem('scrollPosition', window.scrollY);
            
            // Submit the form normally (this will reload the page)
            document.getElementById('filterForm').submit();
            
            return false;
        }

        // Restore scroll position after page load and handle edit modal error
        window.addEventListener('load', function() {
            const savedScrollPosition = sessionStorage.getItem('scrollPosition');
            if (savedScrollPosition) {
                window.scrollTo(0, parseInt(savedScrollPosition));
                sessionStorage.removeItem('scrollPosition');
            }
            
            // Check for edit modal error from PHP session
            <?php if ($modal_error && $edit_form_data): ?>
                // Display error in modal and populate form with previous data
                document.getElementById('edit_id').value = '<?= $edit_form_data['id'] ?>';
                document.getElementById('edit_document_name').value = '<?= htmlspecialchars($edit_form_data['name']) ?>';
                document.getElementById('edit_doc_number').value = '<?= $edit_form_data['doc_number'] ?>';
                document.getElementById('edit_book_number').value = '<?= $edit_form_data['book_number'] ?>';
                document.getElementById('edit_affidavit_type').value = '<?= htmlspecialchars($edit_form_data['affidavit_type']) ?>';
                
                // Show error
                document.getElementById('editErrorText').textContent = '<?= htmlspecialchars($modal_error) ?>';
                document.getElementById('editErrorDisplay').style.display = 'block';
                
                // Open modal
                document.getElementById('editModal').style.display = 'block';
            <?php endif; ?>
        });

        // Cleanup function for page unload
        window.addEventListener('beforeunload', function() {
            // Clean up all stored URLs to free memory
            fileDataStore.forEach((fileData, fileId) => {
                if (fileData.url && fileData.url.startsWith('blob:')) {
                    URL.revokeObjectURL(fileData.url);
                }
            });
            fileDataStore.clear();
        });
    </script>
    
    <script>
        function showCategory(category) {
            // Update active tab
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filter documents
            const cards = document.querySelectorAll('.document-card');
            
            cards.forEach(card => {
                const cardCategory = card.getAttribute('data-category');
                
                switch(category) {
                    case 'all':
                        card.style.display = 'flex';
                        break;
                    case 'notarized':
                        card.style.display = cardCategory === 'Notarized Documents' ? 'flex' : 'none';
                        break;
                    case 'lawoffice':
                        card.style.display = cardCategory === 'Law Office Files' ? 'flex' : 'none';
                        break;
                }
            });
            
            // Show message if no documents in category
            const visibleCards = Array.from(cards).filter(card => card.style.display !== 'none');
            let noDocsMessage = document.getElementById('no-docs-message');
            
            if (visibleCards.length === 0) {
                if (!noDocsMessage) {
                    noDocsMessage = document.createElement('div');
                    noDocsMessage.id = 'no-docs-message';
                    noDocsMessage.style.cssText = 'grid-column: 1 / -1; text-align: center; padding: 40px;';
                    noDocsMessage.innerHTML = '<i class="fas fa-folder-open" style="font-size: 3rem; color: #d1d5db; margin-bottom: 15px;"></i><h3 style="color: #6b7280;">No documents in this category</h3><p style="color: #9ca3af;">Upload some documents to see them here.</p>';
                    document.getElementById('document-container').appendChild(noDocsMessage);
                }
                noDocsMessage.style.display = 'block';
            } else {
                if (noDocsMessage) {
                    noDocsMessage.style.display = 'none';
                }
            }
        }
        
        function toggleFieldsBasedOnCategory(selectElement) {
            const category = selectElement.value;
            const previewItem = selectElement.closest('.preview-item');
            
            if (!previewItem) return;
            
            const notarizedFields = previewItem.querySelector('#notarizedFields');
            const lawOfficeFields = previewItem.querySelector('#lawOfficeFields');
            const originalCategorySelect = previewItem.querySelector('select[name="category[]"]');
            
            // Sync the original category dropdown with the embedded one
            if (originalCategorySelect && originalCategorySelect !== selectElement) {
                originalCategorySelect.value = category;
            }
            
            if (category === 'Notarized Documents') {
                if (notarizedFields) notarizedFields.style.display = 'block';
                if (lawOfficeFields) lawOfficeFields.style.display = 'none';
                // keep category visible
                // Make Notarized fields required
                if (notarizedFields) {
                    notarizedFields.querySelectorAll('input, select').forEach(field => {
                        field.required = true;
                    });
                }
                // Remove required from Law Office fields
                if (lawOfficeFields) {
                    lawOfficeFields.querySelectorAll('input').forEach(field => {
                        field.required = false;
                    });
                }
            } else if (category === 'Law Office Files') {
                if (notarizedFields) notarizedFields.style.display = 'none';
                if (lawOfficeFields) lawOfficeFields.style.display = 'block';
                // keep category visible next to the input
                if (originalCategorySelect) {
                    originalCategorySelect.style.display = 'block';
                    originalCategorySelect.style.flex = '0 0 220px';
                }
                // Remove required from Notarized fields
                if (notarizedFields) {
                    notarizedFields.querySelectorAll('input, select').forEach(field => {
                        field.required = false;
                    });
                }
                // Make Law Office fields required
                if (lawOfficeFields) {
                    lawOfficeFields.querySelectorAll('input').forEach(field => {
                        field.required = true;
                    });
                }
            } else {
                // Hide both if no category selected
                if (notarizedFields) notarizedFields.style.display = 'none';
                if (lawOfficeFields) lawOfficeFields.style.display = 'none';
                if (originalCategorySelect) originalCategorySelect.style.display = 'block';
                // Remove required from both
                if (notarizedFields) {
                    notarizedFields.querySelectorAll('input, select').forEach(field => {
                        field.required = false;
                    });
                }
                if (lawOfficeFields) {
                    lawOfficeFields.querySelectorAll('input').forEach(field => {
                        field.required = false;
                    });
                }
            }
        }
    </script>
    
    <!-- Sidebar Dropdown Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const submenuToggles = document.querySelectorAll('.submenu-toggle');
            
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const submenu = this.parentElement;
                    submenu.classList.toggle('open');
                });
            });
        });
    </script>

<script src="assets/js/unread-messages.js?v=1761535513"></script></body>
</html> 
