<?php

require_once 'session_manager.php';

validateUserAccess('admin');

require_once 'config.php';

require_once 'audit_logger.php';

require_once 'action_logger_helper.php';



// Initialize messages

$success = $_GET['success'] ?? '';

$error = $_GET['error'] ?? '';



$admin_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");

$stmt->bind_param("i", $admin_id);

$stmt->execute();

$res = $stmt->get_result();

$profile_image = '';

if ($res && $row = $res->fetch_assoc()) {

    $profile_image = $row['profile_image'];

}

if (!$profile_image || !file_exists($profile_image)) {

    $profile_image = 'images/default-avatar.jpg';

}



// Helper functions

function get_current_book_number() {

    return date('n'); // Current month (1-12)

}



function truncate_document_name($name, $max_length = 35) {

    // Remove file extension for display since we have icons

    $name_without_ext = pathinfo($name, PATHINFO_FILENAME);

    
    
    if (strlen($name_without_ext) <= $max_length) {

        return $name_without_ext;

    }

    return substr($name_without_ext, 0, $max_length) . '...';

}



function get_next_doc_number($conn, $book_number, $source_type) {

    $table = $source_type . '_documents';

    $stmt = $conn->prepare("SELECT COALESCE(MAX(doc_number), 0) + 1 FROM $table WHERE book_number = ?");

    $stmt->bind_param("i", $book_number);

    $stmt->execute();

    $result = $stmt->get_result();

    return $result->fetch_row()[0];

}



function log_activity($conn, $doc_id, $action, $user_id, $user_name, $doc_number, $book_number, $file_name, $source_type) {

    $table = $source_type . '_document_activity';

    
    
    // Check if the activity table exists

    $checkTable = $conn->query("SHOW TABLES LIKE '$table'");

    if ($checkTable->num_rows == 0) {

        // Table doesn't exist, skip logging

        return;

    }

    
    
    // Check table structure to determine which columns exist

    $columns = $conn->query("SHOW COLUMNS FROM $table");

    $columnNames = [];

    while ($row = $columns->fetch_assoc()) {

        $columnNames[] = $row['Field'];

    }

    
    
    // Different insert statements based on actual table structure

    if ($source_type === 'employee') {

        // Check if doc_number and book_number columns exist

        if (in_array('doc_number', $columnNames) && in_array('book_number', $columnNames)) {

            $stmt = $conn->prepare("INSERT INTO $table (document_id, action, user_id, user_name, doc_number, book_number, file_name) VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param('isisiss', $doc_id, $action, $user_id, $user_name, $doc_number, $book_number, $file_name);

        } else {

            // Fallback for tables without doc_number/book_number columns

            $stmt = $conn->prepare("INSERT INTO $table (document_id, action, user_id, user_name, file_name) VALUES (?, ?, ?, ?, ?)");

            $stmt->bind_param('isiss', $doc_id, $action, $user_id, $user_name, $file_name);

        }

    } else {

        // Attorney document activity table

        $stmt = $conn->prepare("INSERT INTO $table (document_id, action, user_id, user_name, file_name) VALUES (?, ?, ?, ?, ?)");

        $stmt->bind_param('isiss', $doc_id, $action, $user_id, $user_name, $file_name);

    }

    
    
    $stmt->execute();

}



// Handle multiple document upload

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['documents'])) {

    $uploaded_count = 0;

    $errors = [];

    
    
    // Debug: Check if upload is being processed

    error_log("Admin upload processing started");

    error_log("POST data: " . print_r($_POST, true));

    error_log("FILES data: " . print_r($_FILES, true));

    
    
    // Get the current user ID for uploaded_by

    $uploadedBy = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 1; // Try multiple session variables, default to admin user ID

    
    
    // Debug: Check if we have a valid user ID

    if (empty($uploadedBy)) {

        $errors[] = "No valid user ID found in session";

    }

    
    
    $current_book = get_current_book_number();

    $source_type = $_POST['source_type'] ?? '';

    if (empty($source_type)) {

        $errors[] = "Source Type is required";

    }

    
    
        // If there are errors, don't proceed with upload

        if (!empty($errors)) {

            $error_message = implode("\\n", $errors);

            header('Location: admin_documents.php?error=' . urlencode($error_message));

            exit();

        }
    
    
    
    // FIRST PASS: Validate ALL files before uploading ANY (All-or-Nothing)

    $validated_files = [];

    
    
    foreach ($_FILES['documents']['name'] as $key => $filename) {

        if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {

            $doc_name = trim($_POST['doc_names'][$key] ?? '');

            // Remove file extension from document name if it exists

            $doc_name = pathinfo($doc_name, PATHINFO_FILENAME);

            $description = '';

            $category = '';

            
            
            // Handle different fields based on source type

            if ($source_type === 'attorney') {

                $category = trim($_POST['categories'][$key] ?? '');

                if (empty($category)) {

                    $errors[] = "Category is required for file: " . $filename;

                    continue;

                }

            } elseif ($source_type === 'employee') {

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

                    $doc_number = intval($_POST['doc_numbers'][$key] ?? 0);

                    $book_number = intval($_POST['book_numbers'][$key] ?? 0);

                    $affidavit_type = trim($_POST['affidavit_types'][$key] ?? '');

                    
                    
                    // Validate Notarized Documents fields

                    if (empty($surname) || empty($first_name) || $doc_number <= 0 || $book_number <= 0) {

                        $errors[] = "All Notarized Documents fields are required for file: " . $filename;

                        continue;

                    }

                    
                    
                    // Check for duplicate doc number in the same book using doc_number

                    $check_stmt = $conn->prepare("SELECT id FROM employee_documents WHERE doc_number = ? AND book_number = ?");

                    $check_stmt->bind_param('ii', $doc_number, $book_number);

                    $check_stmt->execute();

                    $result = $check_stmt->get_result();

                    
                    
                    if ($result->num_rows > 0) {

                        $errors[] = "Document number $doc_number already exists in Book $book_number for file: " . $filename;

                        continue;

                    }

                    
                    
                    // Use employee name format for document name

                    $doc_name = $surname . ', ' . $first_name . ($middle_name ? ' ' . $middle_name : '');

                } else if ($category === 'Law Office Files') {

                    $doc_name = trim($_POST['document_names'][$key]);

                    $surname = '';

                    $first_name = '';

                    $middle_name = '';

                    $doc_number = 0;

                    $book_number = 0;

                    $affidavit_type = '';

                }

            }

            
            
            // If we reach here, the file is valid - store it for upload

            $validated_files[$key] = [

                'filename' => $filename,

                'doc_name' => $doc_name,

                'category' => $category,

                'surname' => $surname ?? '',

                'first_name' => $first_name ?? '',

                'middle_name' => $middle_name ?? '',

                'doc_number' => $doc_number ?? 0,

                'book_number' => $book_number ?? 0,

                'affidavit_type' => $affidavit_type ?? ''

            ];

        }

    }

    
    
    // If there are ANY errors, don't upload ANY files

    if (!empty($errors)) {

        echo json_encode(['success' => false, 'message' => implode("\\n", $errors)]);

        exit();

    }

    
    
    // SECOND PASS: Upload ALL validated files

    foreach ($validated_files as $key => $fileData) {

        $filename = $fileData['filename'];

        $doc_name = $fileData['doc_name'];

        $category = $fileData['category'];

        $surname = $fileData['surname'];

        $first_name = $fileData['first_name'];

        $middle_name = $fileData['middle_name'];

        $doc_number = $fileData['doc_number'];

        $book_number = $fileData['book_number'];

        $affidavit_type = $fileData['affidavit_type'];

            
            
            $fileInfo = pathinfo($filename);

            $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';

            $safeDocName = preg_replace('/[^A-Za-z0-9 _\-]/', '', $doc_name);

            $fileName = $safeDocName . $extension;

            
            
            $targetDir = "uploads/$source_type/";

            if (!is_dir($targetDir)) {

                mkdir($targetDir, 0777, true);

            }

            
            
            $targetFile = $targetDir . time() . '_' . $key . '_' . $fileName;

            $file_size = $_FILES['documents']['size'][$key];

            $file_type = $_FILES['documents']['type'][$key];

            
            
            if (move_uploaded_file($_FILES['documents']['tmp_name'][$key], $targetFile)) {

                $table = $source_type . '_documents';

                
                
                // Different insert statements based on table structure

                if ($source_type === 'employee') {

                    // Employee documents table has: id, file_name, file_path, category, uploaded_by, upload_date, doc_number, book_number, document_name, affidavit_type

                    $stmt = $conn->prepare("INSERT INTO $table (file_name, file_path, category, uploaded_by, doc_number, book_number, document_name, affidavit_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                    $stmt->bind_param('sssiiiss', $fileName, $targetFile, $category, $uploadedBy, $doc_number, $book_number, $doc_name, $affidavit_type);

                } else {

                    // Attorney documents table has: id, file_name, file_path, category, uploaded_by, upload_date, case_id

                    $stmt = $conn->prepare("INSERT INTO $table (file_name, file_path, category, uploaded_by) VALUES (?, ?, ?, ?)");

                    $stmt->bind_param('sssi', $fileName, $targetFile, $category, $uploadedBy);

                }

                
                
                $stmt->execute();

                
                
                $doc_id = $conn->insert_id;

                $user_name = $_SESSION['admin_name'] ?? 'Admin';

                
                
                // log_activity($conn, $doc_id, 'Uploaded', $uploadedBy, $user_name, 0, 0, $fileName, $source_type);

                
                
                // Log to audit trail

                global $auditLogger;

                $auditLogger->logAction(

                    $uploadedBy,

                    $user_name,

                    'admin',

                    'Document Upload',

                    'Document Management',

                    "Uploaded document: $fileName to $source_type documents",

                    'success',

                    'medium'

                );

                
                
                $uploaded_count++;

            } else {

                $errors[] = "Failed to upload file: " . $filename;

            }

    }

    
    
    // Return results regardless of errors

    $response = [

        'uploaded_count' => $uploaded_count,

        'errors' => $errors

    ];

    
    
    if ($uploaded_count > 0 && empty($errors)) {

        $response['success'] = true;

        $response['message'] = "Successfully uploaded $uploaded_count document(s)!";

    } elseif ($uploaded_count > 0 && !empty($errors)) {

        $response['success'] = true;

        $response['message'] = "Successfully uploaded $uploaded_count document(s)! Some files had errors: " . implode("\\n", $errors);

    } else {

        $response['success'] = false;

        $response['message'] = implode("\\n", $errors);

    }

    
    
    echo json_encode($response);

    exit();

}



// Handle edit

if (isset($_POST['edit_id'])) {

    $edit_id = intval($_POST['edit_id']);

    $new_name = trim($_POST['edit_document_name']);

    $new_doc_number = intval($_POST['edit_doc_number']);

    $new_book_number = intval($_POST['edit_book_number']);

    $new_affidavit_type = trim($_POST['edit_affidavit_type'] ?? '');

    $new_category = trim($_POST['edit_category'] ?? '');

    $source_type = $_POST['edit_source_type'] ?? 'admin';

    
    
    $uploadedBy = $_SESSION['user_id'] ?? 1;

    $user_name = $_SESSION['admin_name'] ?? 'Admin';

    
    
    $table = $source_type . '_documents';

    
    
    if ($source_type === 'attorney') {

        // For attorney documents, update only document name and category

        $stmt = $conn->prepare("UPDATE $table SET file_name=?, category=? WHERE id=?");

        $stmt->bind_param('ssi', $new_name, $new_category, $edit_id);

        $stmt->execute();

        
        
        log_activity($conn, $edit_id, 'Edited', $uploadedBy, $user_name, 0, 0, $new_name, $source_type);

        
        
        // Log to audit trail

        global $auditLogger;

        $auditLogger->logAction(

            $uploadedBy,

            $user_name,

            'admin',

            'Document Edit',

            'Document Management',

            "Edited attorney document: $new_name (Category: $new_category)",

            'success',

            'medium'

        );

        
        
        header('Location: admin_documents.php?scroll=documents');

        exit();

    } else {

        // For other document types, check for duplicate doc number in same book

        if ($source_type === 'attorney') {

            // For attorney documents, only update file_name and category

            $stmt = $conn->prepare("UPDATE $table SET file_name=?, category=? WHERE id=?");

            $stmt->bind_param('ssi', $new_name, $new_category, $edit_id);

            $stmt->execute();

            
            
            log_activity($conn, $edit_id, 'Edited', $uploadedBy, $user_name, 0, 0, $new_name, $source_type);

        } else {

            // Get current document category to determine update logic

            $currentDoc = $conn->prepare("SELECT category FROM $table WHERE id = ?");

            $currentDoc->bind_param('i', $edit_id);

            $currentDoc->execute();

            $currentResult = $currentDoc->get_result();

            $currentCategory = $currentResult->fetch_assoc()['category'] ?? '';

            
            
            if ($currentCategory === 'Law Office Files') {

                // For Law Office Files, update only document name

                $stmt = $conn->prepare("UPDATE $table SET file_name=?, document_name=? WHERE id=?");

                $stmt->bind_param('ssi', $new_name, $new_name, $edit_id);

                $stmt->execute();

                
                
                log_activity($conn, $edit_id, 'Edited', $uploadedBy, $user_name, 0, 0, $new_name, $source_type);

            } else {

                // For Notarized Documents, check for duplicates and update all fields

                $dupCheck = $conn->prepare("SELECT id FROM $table WHERE doc_number = ? AND book_number = ? AND id != ?");

                $dupCheck->bind_param('iii', $new_doc_number, $new_book_number, $edit_id);

                $dupCheck->execute();

                $dupCheck->store_result();

                
                
                if ($dupCheck->num_rows > 0) {

                    $error = 'A document with Doc Number ' . $new_doc_number . ' already exists in Book ' . $new_book_number . '!';

                } else {

                    $stmt = $conn->prepare("UPDATE $table SET file_name=?, document_name=?, doc_number=?, book_number=?, affidavit_type=? WHERE id=?");

                    $stmt->bind_param('ssiisi', $new_name, $new_name, $new_doc_number, $new_book_number, $new_affidavit_type, $edit_id);

                    $stmt->execute();

                    
                    
                    log_activity($conn, $edit_id, 'Edited', $uploadedBy, $user_name, $new_doc_number, $new_book_number, $new_name, $source_type);

                }

            }

        }

            
            
            // Log to audit trail

            global $auditLogger;

            $auditLogger->logAction(

                $uploadedBy,

                $user_name,

                'admin',

                'Document Edit',

                'Document Management',

                $source_type === 'attorney' 

                    ? "Edited document: $new_name (Source: $source_type)"

                    : "Edited document: $new_name (Doc #: $new_doc_number, Book #: $new_book_number, Source: $source_type)",

                'success',

                'medium'

            );

            
            
            header('Location: admin_documents.php?scroll=documents');

            exit();

        }

    }



// Handle delete

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {

    $id = intval($_GET['delete']);

    $source_type = $_GET['source'] ?? 'admin';

    $table = $source_type . '_documents';

    
    
    if ($source_type === 'attorney') {

        $stmt = $conn->prepare("SELECT file_path, file_name, uploaded_by FROM $table WHERE id=?");

    } else {

        $stmt = $conn->prepare("SELECT file_path, file_name, doc_number, book_number, uploaded_by FROM $table WHERE id=?");

    }

    $stmt->bind_param("i", $id);

    $stmt->execute();

    $res = $stmt->get_result();

    
    
    if ($res && $row = $res->fetch_assoc()) {

        @unlink($row['file_path']);

        $user_name = $_SESSION['admin_name'] ?? 'Admin';

        $user_id = $_SESSION['user_id'] ?? 1;

        
        
        if ($source_type === 'attorney') {

            log_activity($conn, $id, 'Deleted', $user_id, $user_name, 0, 0, $row['file_name'], $source_type);

        } else {

            log_activity($conn, $id, 'Deleted', $user_id, $user_name, $row['doc_number'], $row['book_number'], $row['file_name'], $source_type);

        }

        
        
        // Log to audit trail

        global $auditLogger;

        $auditLogger->logAction(

            $user_id,

            $user_name,

            'admin',

            'Document Delete',

            'Document Management',

            $source_type === 'attorney' 

                ? "Deleted document: {$row['file_name']} (Source: $source_type)"

                : "Deleted document: {$row['file_name']} (Doc #: {$row['doc_number']}, Book #: {$row['book_number']}, Source: $source_type)",

            'success',

            'high'

        );

    }

    
    
    $stmt = $conn->prepare("DELETE FROM $table WHERE id=?");

    $stmt->bind_param("i", $id);

    $stmt->execute();

    header('Location: admin_documents.php?scroll=documents');

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



// Doc number filter (only for employee documents)

$filter_doc_number = isset($_GET['doc_number']) ? $_GET['doc_number'] : '';



// Book number filter (only for employee documents)

$filter_book_number = isset($_GET['book_number']) ? $_GET['book_number'] : '';



// Name filter

$filter_name = isset($_GET['name']) ? $_GET['name'] : '';

if ($filter_name) {

    $where_conditions[] = "file_name LIKE ?";

    $where_params[] = '%' . $filter_name . '%';

    $where_types .= 's';

}

// Category filter

$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

if ($filter_category) {

    $where_conditions[] = "category = ?";

    $where_params[] = $filter_category;

    $where_types .= 's';

}



// Source type filter

$filter_source = isset($_GET['source_type']) ? $_GET['source_type'] : '';



$where_clause = '';

if (!empty($where_conditions)) {

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

}

// Build attorney-specific WHERE clause (exclude doc_number and book_number)

$attorney_where_conditions = [];

$attorney_where_params = [];

$attorney_where_types = '';

// Date filters

if ($filter_from && $filter_to) {

    $attorney_where_conditions[] = "DATE(upload_date) >= ? AND DATE(upload_date) <= ?";

    $attorney_where_params[] = $filter_from;

    $attorney_where_params[] = $filter_to;

    $attorney_where_types .= 'ss';

} elseif ($filter_from) {

    $attorney_where_conditions[] = "DATE(upload_date) = ?";

    $attorney_where_params[] = $filter_from;

    $attorney_where_types .= 's';

} elseif ($filter_to) {

    $attorney_where_conditions[] = "DATE(upload_date) <= ?";

    $attorney_where_params[] = $filter_to;

    $attorney_where_types .= 's';

}

// Name filter

if ($filter_name) {

    $attorney_where_conditions[] = "file_name LIKE ?";

    $attorney_where_params[] = '%' . $filter_name . '%';

    $attorney_where_types .= 's';

}

// Category filter

$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

if ($filter_category) {

    $attorney_where_conditions[] = "category = ?";

    $attorney_where_params[] = $filter_category;

    $attorney_where_types .= 's';

}

$attorney_where_clause = '';

if (!empty($attorney_where_conditions)) {

    $attorney_where_clause = 'WHERE ' . implode(' AND ', $attorney_where_conditions);

}



// Fetch documents from all sources

$all_documents = [];

$stats_documents = [];



// Admin documents section removed - table was deleted

// $admin_where = $where_clause;

// if ($filter_source && $filter_source !== 'admin') {

//     $admin_where = '';

// } elseif ($filter_source === 'admin') {

//     // Keep existing where clause

// }

// 

// if ($admin_where) {

//     $stmt = $conn->prepare("SELECT ad.*, 'admin' as source_type, uf.name, uf.user_type FROM admin_documents ad LEFT JOIN user_form uf ON ad.uploaded_by = uf.id $admin_where ORDER BY ad.book_number DESC, ad.doc_number ASC");

//     if (!empty($where_params)) {

//         $stmt->bind_param($where_types, ...$where_params);

//     }

//     $stmt->execute();

//     $result = $stmt->get_result();

// } else {

//     $stmt = $conn->prepare("SELECT ad.*, 'admin' as source_type, uf.name, uf.user_type FROM admin_documents ad LEFT JOIN user_form uf ON ad.uploaded_by = uf.id ORDER BY ad.book_number DESC, ad.doc_number ASC");

//     $stmt->execute();

//     $result = $stmt->get_result();

// }

// 

// if ($result && $result->num_rows > 0) {

//     while ($row = $result->fetch_assoc()) {

//         $all_documents[] = $row;

//     }

// }



// Attorney documents

if (!$filter_source || $filter_source === 'attorney') {

    $attorney_where = $attorney_where_clause;

    
    
    if ($attorney_where) {

        $stmt = $conn->prepare("SELECT ad.*, 'attorney' as source_type, uf.name, uf.user_type, NULL as doc_number, NULL as book_number FROM attorney_documents ad LEFT JOIN user_form uf ON ad.uploaded_by = uf.id $attorney_where ORDER BY ad.upload_date DESC");

        if (!empty($attorney_where_params)) {

            $stmt->bind_param($attorney_where_types, ...$attorney_where_params);

        }

        $stmt->execute();

        $result = $stmt->get_result();

    } else {

        $stmt = $conn->prepare("SELECT ad.*, 'attorney' as source_type, uf.name, uf.user_type, NULL as doc_number, NULL as book_number FROM attorney_documents ad LEFT JOIN user_form uf ON ad.uploaded_by = uf.id ORDER BY ad.upload_date DESC");

        $stmt->execute();

        $result = $stmt->get_result();

    }



    if ($result && $result->num_rows > 0) {

        while ($row = $result->fetch_assoc()) {

            $all_documents[] = $row;

        }

    }

}



// Employee documents

if (!$filter_source || $filter_source === 'employee') {

    // Build employee-specific WHERE clause (include doc_number and book_number filters)

    $employee_where_conditions = [];

    $employee_where_params = [];

    $employee_where_types = '';

    

    // Date filters

    if ($filter_from && $filter_to) {

        $employee_where_conditions[] = "DATE(ed.upload_date) >= ? AND DATE(ed.upload_date) <= ?";

        $employee_where_params[] = $filter_from;

        $employee_where_params[] = $filter_to;

        $employee_where_types .= 'ss';

    } elseif ($filter_from) {

        $employee_where_conditions[] = "DATE(ed.upload_date) = ?";

        $employee_where_params[] = $filter_from;

        $employee_where_types .= 's';

    } elseif ($filter_to) {

        $employee_where_conditions[] = "DATE(ed.upload_date) <= ?";

        $employee_where_params[] = $filter_to;

        $employee_where_types .= 's';

    }

    

    // Doc number filter (only for employee documents)

    if ($filter_doc_number) {

        $employee_where_conditions[] = "ed.doc_number = ?";

        $employee_where_params[] = $filter_doc_number;

        $employee_where_types .= 'i';

    }

    

    // Book number filter (only for employee documents)

    if ($filter_book_number) {

        $employee_where_conditions[] = "ed.book_number = ?";

        $employee_where_params[] = $filter_book_number;

        $employee_where_types .= 'i';

    }

    

    // Name filter

    if ($filter_name) {

        $employee_where_conditions[] = "ed.file_name LIKE ?";

        $employee_where_params[] = '%' . $filter_name . '%';

        $employee_where_types .= 's';

    }

    

    // Category filter

    if ($filter_category) {

        $employee_where_conditions[] = "ed.category = ?";

        $employee_where_params[] = $filter_category;

        $employee_where_types .= 's';

    }

    

    $employee_where_clause = '';

    if (!empty($employee_where_conditions)) {

        $employee_where_clause = 'WHERE ' . implode(' AND ', $employee_where_conditions);

    }

    

    $employee_where = $employee_where_clause;

    
    
    if ($employee_where) {

        $stmt = $conn->prepare("SELECT ed.*, 'employee' as source_type, uf.name, uf.user_type, ed.doc_number, ed.book_number FROM employee_documents ed LEFT JOIN user_form uf ON ed.uploaded_by = uf.id $employee_where ORDER BY ed.book_number DESC, ed.doc_number ASC");

        if (!empty($employee_where_params)) {

            $stmt->bind_param($employee_where_types, ...$employee_where_params);

        }

        $stmt->execute();

        $result = $stmt->get_result();

    } else {

        $stmt = $conn->prepare("SELECT ed.*, 'employee' as source_type, uf.name, uf.user_type, ed.doc_number, ed.book_number FROM employee_documents ed LEFT JOIN user_form uf ON ed.uploaded_by = uf.id ORDER BY ed.book_number DESC, ed.doc_number ASC");

        $stmt->execute();

        $result = $stmt->get_result();

    }



    if ($result && $result->num_rows > 0) {

        while ($row = $result->fetch_assoc()) {

            $all_documents[] = $row;

        }

    }

}



// Sort all documents by book number and doc number

usort($all_documents, function($a, $b) {

    if ($a['book_number'] == $b['book_number']) {

        return $a['doc_number'] - $b['doc_number'];

    }

    return $b['book_number'] - $a['book_number'];

});



$documents = $all_documents;

// Fetch unfiltered documents for statistics

$stats_documents = [];

// Fetch all attorney documents for stats

$stmt = $conn->prepare("SELECT ad.*, 'attorney' as source_type, uf.name, uf.user_type, NULL as doc_number, NULL as book_number FROM attorney_documents ad LEFT JOIN user_form uf ON ad.uploaded_by = uf.id ORDER BY ad.upload_date DESC");

$stmt->execute();

$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {

        $stats_documents[] = $row;

    }

}

// Fetch all employee documents for stats

$stmt = $conn->prepare("SELECT ed.*, 'employee' as source_type, uf.name, uf.user_type, ed.doc_number, ed.book_number FROM employee_documents ed LEFT JOIN user_form uf ON ed.uploaded_by = uf.id ORDER BY ed.book_number DESC, ed.doc_number ASC");

$stmt->execute();

$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {

        $stats_documents[] = $row;

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Advanced Document Management - Opiña Law Office</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">

    <style>
        /* Modal Centering - ensures flexbox works properly when display is set to flex */
        .modal[style*="display: flex"] {
            display: flex !important;
        }
        
        /* Profile Modal Override - Ensure consistent compact modal */
        .modal#editProfileModal .modal-content {
            max-height: none !important;
            height: auto !important;
            min-height: auto !important;
            overflow-y: visible !important;
            overflow-x: visible !important;
            margin: 2% auto !important;
            width: 98% !important;
            max-width: 800px !important;
        }
        
        .modal#passwordVerificationModal .modal-content {
            max-height: none !important;
            height: auto !important;
            min-height: auto !important;
            overflow-y: visible !important;
            overflow-x: visible !important;
            margin: 2% auto !important;
            width: 98% !important;
            max-width: 800px !important;
        }
        
        .modal#editProfileModal .modal-body {
            max-height: none !important;
            height: auto !important;
            min-height: auto !important;
            overflow-y: visible !important;
            overflow-x: visible !important;
            padding: 12px !important;
        }
        
        .modal#passwordVerificationModal .modal-body {
            max-height: none !important;
            height: auto !important;
            min-height: auto !important;
            overflow-y: visible !important;
            overflow-x: visible !important;
            padding: 12px !important;
        }
        
        /* Compact modal elements */
        .modal#editProfileModal .form-section {
            margin-bottom: 6px !important;
            padding: 0 !important;
        }
        
        .modal#editProfileModal .form-group {
            margin-bottom: 4px !important;
        }
        
        .modal#editProfileModal .modal-header h2 {
            font-size: 1.1rem !important;
            padding: 8px 12px !important;
        }
        
        .modal#editProfileModal .modal-header {
            padding: 8px 12px !important;
        }
        
        .modal#editProfileModal .form-section h3 {
            font-size: 0.9rem !important;
            margin-bottom: 6px !important;
            padding-bottom: 2px !important;
        }
        
        .modal#editProfileModal .form-group label {
            font-size: 0.75rem !important;
            margin-bottom: 2px !important;
        }
        
        .modal#editProfileModal .form-group input {
            padding: 4px 6px !important;
            font-size: 0.8rem !important;
            border-radius: 4px !important;
        }
        
        .modal#editProfileModal .upload-btn {
            padding: 4px 8px !important;
            font-size: 0.7rem !important;
        }
        
        .modal#editProfileModal .upload-hint {
            font-size: 0.65rem !important;
        }
        
        .modal#editProfileModal .current-profile-image {
            width: 50px !important;
            height: 50px !important;
        }
        
        .modal#editProfileModal .form-actions button {
            padding: 4px 8px !important;
            font-size: 0.75rem !important;
        }
        
        .modal#editProfileModal small {
            font-size: 0.6rem !important;
        }

        .document-grid {

            display: grid;

            grid-template-columns: repeat(4, 1fr);

            gap: 15px;

            margin-bottom: 30px;

        }

        
        
        @media (max-width: 1200px) {

            .document-grid {

                grid-template-columns: repeat(3, 1fr);

            }

        }

        
        
        @media (max-width: 900px) {

            .document-grid {

                grid-template-columns: repeat(2, 1fr);

            }

        }

        
        
        @media (max-width: 600px) {

            .document-grid {

                grid-template-columns: 1fr;

            }

        }

        
        
        .document-card {

            background: white;

            border-radius: 12px;

            padding: 16px;

            box-shadow: 0 2px 8px rgba(0,0,0,0.1);

            transition: transform 0.3s, box-shadow 0.3s;

            border: 1px solid #e5e7eb;

            position: relative;

            min-height: 160px;

            display: flex;

            flex-direction: column;

            gap: 12px;

        }

        
        
        .document-card:hover {

            transform: translateY(-2px);

            box-shadow: 0 4px 16px rgba(0,0,0,0.15);

        }

        
        
        .source-badge {

            position: absolute;

            top: 10px;

            right: 10px;

            padding: 4px 8px;

            border-radius: 4px;

            font-size: 0.7rem;

            font-weight: 600;

            text-transform: uppercase;

        }

        
        
        .source-admin {

            background: #1976d2;

            color: white;

        }

        
        
        .source-attorney {

            background: #ffc107;

            color: #212529;

        }

        
        
        .source-employee {

            background: #6f42c1;

            color: white;

        }

        
        
        .card-header {

            display: flex !important;

            flex-direction: column !important;

            align-items: flex-start !important;

            margin-bottom: 12px !important;

            gap: 8px !important;

            justify-content: flex-start !important;

        }

        
        
        .document-icon {

            width: 45px !important;

            height: 45px !important;

            background: linear-gradient(135deg, #8B1538, #5D0E26) !important;

            border-radius: 8px !important;

            display: flex !important;

            align-items: center !important;

            justify-content: center !important;

            flex-shrink: 0;

            margin-bottom: 8px;

            box-shadow: none !important;

            transition: none !important;

        }

        
        
        .document-icon i {

            font-size: 20px;

            color: white;

        }

        
        
        .document-info h3 {

            margin: 0 0 6px 0;

            font-size: 0.95rem;

            color: #1f2937;

            font-weight: 600;

            line-height: 1.3;

            max-width: 100%;

        }

        
        
        .document-meta {

            font-size: 0.8rem;

            color: #6b7280;

            flex-grow: 1;

            margin-bottom: 4px;

        }

        
        
        .document-actions {

            display: flex;

            gap: 4px;

            margin-top: 8px;

            flex-wrap: wrap;

        }

        
        
        .btn-action {

            padding: 6px 10px;

            border: none;

            border-radius: 4px;

            cursor: pointer;

            font-size: 0.7rem;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            transition: all 0.2s;

            flex: 1;

            min-width: 0;

            height: 32px;

        }

        
        
        .btn-action i {

            font-size: 12px;

        }

        
        
        .btn-view {

            background: #dbeafe;

            color: #1d4ed8;

        }

        
        
        .btn-edit {

            background: #fef3c7;

            color: #d97706;

        }

        
        
        .btn-delete {

            background: #fee2e2;

            color: #dc2626;

        }

        
        
        .document-section {

            margin-bottom: 40px;

        }

        
        
        .section-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 25px;

            padding: 20px 25px;

            background: white;

            border-radius: 16px;

            border: 1px solid #f1f5f9;

            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);

            position: relative;

            overflow: hidden;

        }

        
        
        .section-header::before {

            content: '';

            position: absolute;

            top: 0;

            left: 0;

            right: 0;

            height: 4px;

            background: linear-gradient(135deg, #8B1538, #5D0E26);

            border-radius: 16px 16px 0 0;

        }

        
        
        .section-header h2 {

            margin: 0;

            color: #1e293b;

            font-size: 1.5rem;

            font-weight: 700;

            display: flex;

            align-items: center;

            gap: 12px;

        }

        
        
        .section-header h2 i {

            font-size: 1.3rem;

            color: #8B1538;

        }

        
        
        .section-count {

            background: linear-gradient(135deg, #8B1538, #5D0E26);

            color: white;

            padding: 8px 16px;

            border-radius: 25px;

            font-size: 0.85rem;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            box-shadow: 0 4px 12px rgba(139, 21, 56, 0.3);

        }

        
        
        .no-documents-message {

            text-align: center;

            padding: 80px 30px;

            background: white;

            border-radius: 16px;

            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);

            margin-bottom: 30px;

            border: 1px solid #f1f5f9;

            position: relative;

            overflow: hidden;

        }

        
        
        .no-documents-message::before {

            content: '';

            position: absolute;

            top: 0;

            left: 0;

            right: 0;

            height: 4px;

            background: linear-gradient(135deg, #8B1538, #5D0E26);

            border-radius: 16px 16px 0 0;

        }

        
        
        .uploader-info {

            background: #f8f9fa;

            border-radius: 6px;

            padding: 6px 8px;

            margin-top: 8px;

            font-size: 0.75rem;

            color: #6c757d;

            border-left: 3px solid #8B1538;

        }

        
        
        .uploader-name {

            font-weight: 600;

            color: #495057;

        }

        
        
        .uploader-role {

            color: #6c757d;

            font-style: italic;

        }

        
        
        .btn-action:hover {

            transform: translateY(-1px);

        }

        
        

        
        

        
        

        
        

        
        

        
        

        
        

        
        

        
        

        
        

        
        

        
        
        .filters-section {

            background: white;

            border-radius: 16px;

            padding: 30px;

            margin-bottom: 30px;

            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);

            border: 1px solid #f1f5f9;

            position: relative;

            overflow: hidden;

        }

        
        
        .filters-section::before {

            content: '';

            position: absolute;

            top: 0;

            left: 0;

            right: 0;

            height: 4px;

            background: linear-gradient(135deg, #8B1538, #5D0E26);

            border-radius: 16px 16px 0 0;

        }

        
        
        .filters-section h2 {

            color: #1e293b;

            font-size: 1.5rem;

            font-weight: 700;

            margin-bottom: 25px;

            display: flex;

            align-items: center;

            gap: 12px;

        }

        
        
        .filters-section h2 i {

            color: #8B1538;

            font-size: 1.3rem;

        }

        
        
        .filters-grid {

            display: grid;

            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));

            gap: 12px;

            margin-bottom: 20px;

        }

        
        
        .filter-group {

            display: flex;

            flex-direction: column;

            position: relative;

        }

        
        
        .filter-group label {

            font-weight: 600;

            margin-bottom: 6px;

            color: #374151;

            font-size: 0.75rem;

            text-transform: uppercase;

            letter-spacing: 0.3px;

        }

        
        
        .filter-group input,

        .filter-group select {

            padding: 8px 12px;

            border: 2px solid #e2e8f0;

            border-radius: 8px;

            font-size: 0.85rem;

            transition: all 0.3s ease;

            background: #fafbfc;

            color: #1e293b;

        }

        
        
        .filter-group input:focus,

        .filter-group select:focus {

            outline: none;

            border-color: #8B1538;

            background: white;

            box-shadow: 0 0 0 3px rgba(139, 21, 56, 0.1);

        }

        
        
        .filter-group input::placeholder {

            color: #94a3b8;

            font-style: italic;

        }

        
        
        .filter-actions {

            display: flex;

            gap: 15px;

            align-items: center;

            flex-wrap: wrap;

            padding-top: 20px;

            border-top: 1px solid #e2e8f0;

        }

        
        
        .btn-primary {

            background: linear-gradient(135deg, #8B1538, #5D0E26);

            color: white;

            padding: 12px 24px;

            border: none;

            border-radius: 10px;

            cursor: pointer;

            font-weight: 600;

            display: inline-flex;

            align-items: center;

            gap: 8px;

            transition: all 0.3s ease;

            box-shadow: 0 4px 12px rgba(139, 21, 56, 0.3);

            text-transform: uppercase;

            letter-spacing: 0.5px;

            font-size: 0.9rem;

        }

        
        
        .btn-primary:hover {

            transform: translateY(-2px);

            box-shadow: 0 6px 20px rgba(139, 21, 56, 0.4);

        }

        
        
        .btn-secondary {

            background: linear-gradient(135deg, #64748b, #475569);

            color: white;

            padding: 12px 24px;

            border: none;

            border-radius: 10px;

            cursor: pointer;

            font-weight: 600;

            display: inline-flex;

            align-items: center;

            gap: 8px;

            transition: all 0.3s ease;

            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);

            text-transform: uppercase;

            letter-spacing: 0.5px;

            font-size: 0.9rem;

            text-decoration: none;

        }

        
        
        .btn-secondary:hover {

            transform: translateY(-2px);

            box-shadow: 0 6px 20px rgba(100, 116, 139, 0.4);

            color: white;

        }

        
        
        .upload-section {

            background: white;

            border-radius: 16px;

            padding: 30px;

            margin-bottom: 30px;

            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);

            border: 1px solid #f1f5f9;

            position: relative;

            overflow: hidden;

        }

        
        

        
        
        .upload-section h2 {

            color: #1e293b;

            font-size: 1.5rem;

            font-weight: 700;

            margin-bottom: 25px;

            display: flex;

            align-items: center;

            gap: 12px;

        }

        
        
        .upload-section h2 i {

            color: #8B1538;

            font-size: 1.3rem;

        }

        
        
        .upload-area {

            border: 3px dashed #cbd5e1;

            border-radius: 16px;

            padding: 50px 30px;

            text-align: center;

            margin-bottom: 25px;

            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);

            position: relative;

            overflow: hidden;

        }

        
        
        .upload-area::before {

            content: '';

            position: absolute;

            top: 0;

            left: 0;

            right: 0;

            bottom: 0;

            background: linear-gradient(135deg, rgba(139, 21, 56, 0.05) 0%, rgba(93, 14, 38, 0.05) 100%);

            opacity: 0;

            transition: opacity 0.3s ease;

        }

        
        
        .upload-area.disabled {

            opacity: 0.6;

            pointer-events: none;

            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);

            border: 3px dashed #d1d5db;

        }

        
        
        .upload-area.disabled h3 {

            color: #9ca3af;

        }

        
        
        .upload-area.disabled p {

            color: #9ca3af;

        }

        
        
        .upload-area:hover {

            border-color: #8B1538;

            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);

            transform: translateY(-2px);

            box-shadow: 0 8px 25px rgba(139, 21, 56, 0.15);

        }

        
        
        .upload-area:hover::before {

            opacity: 1;

        }

        
        
        .upload-area.dragover {

            border-color: #8B1538;

            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);

            transform: scale(1.02);

            box-shadow: 0 12px 30px rgba(139, 21, 56, 0.2);

        }

        
        
        .upload-area.dragover::before {

            opacity: 1;

        }

        
        
        .file-preview {

            display: none;

            margin-top: 20px;

        }

        
        
        .preview-item {

            display: flex;

            flex-wrap: wrap;

            align-items: center;

            padding: 15px;

            background: #f9fafb;

            border-radius: 8px;

            margin-bottom: 15px;

            gap: 10px;

        }

        
        
        .preview-item i {

            margin-right: 10px;

            color: #6b7280;

            font-size: 1.2rem;

        }

        
        
        .preview-item span {

            font-weight: 600;

            color: #374151;

            min-width: 150px;

        }

        
        
        .preview-item input,

        .preview-item textarea {

            flex: 1;

            min-width: 120px;

            padding: 8px 12px;

            border: 1px solid #d1d5db;

            border-radius: 6px;

            font-size: 0.9rem;

        }

        
        
        /* Override for employee document upload form */

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

        
        
        .preview-item button {

            background: #dc2626;

            color: white;

            border: none;

            border-radius: 6px;

            padding: 8px 12px;

            cursor: pointer;

            font-size: 0.9rem;

            white-space: nowrap;

        }

        
        
        .modal {

            display: none;

            position: fixed;

            z-index: 1000;

            left: 0;

            top: 0;

            width: 100%;

            height: 100%;

            background: rgba(0, 0, 0, 0.6);

            backdrop-filter: blur(4px);

        }

        
        
        .modal-content {

            background-color: white;

            margin: 5% auto;

            padding: 20px;

            border-radius: 12px;

            width: 90%;

            max-width: 500px;

            position: relative;

        }



        /* Modern Edit Modal Styles */

        .modern-edit-modal {

            max-width: 500px;

            border-radius: 12px;

            box-shadow: 0 20px 60px rgba(93, 14, 38, 0.3);

            border: 1px solid rgba(93, 14, 38, 0.1);

            overflow: hidden;

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

            gap: 16px;

            justify-content: flex-end;

            align-items: center;

            margin-top: 20px;

            padding: 20px 24px;

            border-top: 1px solid #f0f0f0;

            background: #fafbfc;

        }



        .btn {

            padding: 12px 24px;

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

            height: 44px;

        }



        .btn-secondary {

            background: white !important;

            color: #6c757d !important;

            border: 1px solid #e0e0e0 !important;

            padding: 12px 24px;

            border-radius: 8px;

            font-weight: 600;

            transition: all 0.3s ease;

            height: 44px;

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



        .btn i {

            font-size: 0.9rem;

        }



        .modal-content.preview-modal {

            max-width: 90%;

            width: 90%;

            height: 90%;

            max-height: 90%;

            padding: 15px;

        }



        .modal-content.preview-modal #previewContent {

            width: 100%;

            height: calc(100% - 80px);

            overflow: auto;

            border: 1px solid #ddd;

            border-radius: 8px;

            background: #f9f9f9;

            display: flex;

            align-items: center;

            justify-content: center;

        }



        .view-modal .modal-header {

            background: linear-gradient(135deg, #5D0E26, #8B1538);

            color: white;

            padding: 24px 28px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            border-bottom: none;

            border-radius: 16px 16px 0 0;

        }

        

        .view-modal .modal-header h2 {

            margin: 0;

            font-size: 1.4rem;

            font-weight: 600;

            display: flex;

            align-items: center;

            gap: 12px;

        }

        

        .view-modal .modal-header h2 i {

            font-size: 1.2rem;

            opacity: 0.9;

        }

        

        .view-modal .close-modal-btn {

            background: rgba(255, 255, 255, 0.15);

            border: none;

            color: white;

            padding: 10px 12px;

            border-radius: 8px;

            cursor: pointer;

            font-size: 1rem;

            transition: all 0.3s ease;

            display: flex;

            align-items: center;

            justify-content: center;

        }

        

        .view-modal {

            max-width: 95vw;

            max-height: 90vh;

            border-radius: 16px;

            box-shadow: 0 25px 50px rgba(93, 14, 38, 0.25);

            border: none;

            overflow: hidden;

            display: flex;

            flex-direction: column;

        }

        

        .view-modal .modal-body {

            padding: 20px;

            background: white;

            flex: 1;

            overflow-y: auto;

            max-height: calc(90vh - 120px);

            display: flex;

            flex-direction: column;

        }

        .view-modal .modal-body::-webkit-scrollbar {

            width: 6px;

        }

        .view-modal .modal-body::-webkit-scrollbar-track {

            background: rgba(0, 0, 0, 0.05);

            border-radius: 3px;

        }

        .view-modal .modal-body::-webkit-scrollbar-thumb {

            background: linear-gradient(135deg, #5D0E26, #8B1538);

            border-radius: 3px;

        }

        .view-modal .modal-body::-webkit-scrollbar-thumb:hover {

            background: linear-gradient(135deg, #4a0b1f, #6b0f2a);

        }

        

        .document-details {

            background: #f8fafc;

            padding: 15px;

            border-radius: 8px;

            margin-bottom: 10px;

            border: 1px solid #e2e8f0;

            display: grid;

            grid-template-columns: 1fr;

            gap: 10px;

        }



        .detail-column {

            display: flex;

            flex-direction: column;

            gap: 12px;

        }



        .detail-row {

            display: flex;

            align-items: center;

            margin-bottom: 8px;

            gap: 8px;

            padding: 8px;

            background: white;

            border-radius: 6px;

            border: 1px solid #e5e7eb;

        }



        .detail-row:last-child {

            margin-bottom: 0;

        }



        .detail-row label {

            font-weight: 600;

            color: #374151;

            min-width: 100px;

            display: flex;

            align-items: center;

            gap: 6px;

            font-size: 0.85rem;

        }



        .detail-row span {

            color: #1f2937;

            font-weight: 500;

            font-size: 0.85rem;

            flex: 1;

            word-break: break-word;

        }



        .view-modal .document-preview {

            flex: 1;

            min-height: 350px;

            height: 100%;

            border: 1px solid #e5e7eb;

            border-radius: 8px;

            overflow: auto;

            background: #f9fafb;

            position: relative;

            margin-top: 10px;

        }

        .view-modal .document-preview img {

            width: 100%;

            height: 100%;

            object-fit: cover;

            display: block;

        }

        .view-modal .document-preview iframe {

            width: 100%;

            height: 100%;

            border: none;

            display: block;

            position: absolute;

            top: 0;

            left: 0;

        }

        .view-modal .document-preview::-webkit-scrollbar {

            width: 8px;

        }

        .view-modal .document-preview::-webkit-scrollbar-track {

            background: rgba(0, 0, 0, 0.05);

            border-radius: 4px;

        }

        .view-modal .document-preview::-webkit-scrollbar-thumb {

            background: linear-gradient(135deg, #5D0E26, #8B1538);

            border-radius: 4px;

        }

        .view-modal .document-preview::-webkit-scrollbar-thumb:hover {

            background: linear-gradient(135deg, #4a0b1f, #6b0f2a);

        }



        @media (max-width: 768px) {

            .view-modal {

                max-width: 95%;

                max-height: 90vh;

                margin: 20px auto;

            }

            

            .view-modal .modal-header {

                padding: 20px 24px;

            }

            

            .view-modal .modal-header h2 {

                font-size: 1.2rem;

            }

            

            .view-modal .modal-body {

                padding: 20px;

            }

            

            .document-details {

                grid-template-columns: 1fr;

                gap: 15px;

            }

            

            .view-modal .document-preview {

                min-height: 300px;

            }

        }

        
        
        .close {

            position: absolute;

            right: 15px;

            top: 15px;

            font-size: 24px;

            cursor: pointer;

            color: #6b7280;

        }

        
        
        .close:hover {

            color: #374151;

        }

        
        
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

            padding: 20px 25px;

            background: #8B1538;

            color: white;

            border-radius: 8px 8px 0 0;

        }

        

        .modal-header h2 {

            margin: 0;

            font-size: 1.5rem;

            font-weight: 600;

        }

        

        .close-btn {

            background: none;

            border: none;

            color: white;

            font-size: 1.2rem;

            cursor: pointer;

            padding: 5px;

            border-radius: 4px;

            transition: background-color 0.2s;

        }

        

        .close-btn:hover {

            background-color: rgba(255, 255, 255, 0.1);

        }

        

        .date-filter-section {

            padding: 20px 25px;

            border-bottom: 1px solid #e5e7eb;

        }

        

        .filter-tabs {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            align-items: center;

        }

        

        .filter-btn {

            padding: 8px 16px;

            border: 1px solid #d1d5db;

            background: white;

            color: #374151;

            border-radius: 6px;

            cursor: pointer;

            font-size: 0.9rem;

            transition: all 0.2s;

        }

        

        .filter-btn:hover {

            background: #f9fafb;

            border-color: #8B1538;

        }

        

        .filter-btn.active {

            background: #8B1538;

            color: white;

            border-color: #8B1538;

        }

        

        .custom-date-range {

            display: none;

            margin: 15px 0;

            padding: 15px;

            background: #f8f9fa;

            border: 1px solid #e2e8f0;

            border-radius: 8px;

        }

        

        .custom-date-range.show {

            display: block;

        }

        

        .date-inputs {

            display: flex;

            gap: 15px;

            align-items: center;

        }

        

        .date-inputs label {

            font-weight: 500;

            color: #374151;

        }

        

        .date-inputs input {

            padding: 6px 10px;

            border: 1px solid #d1d5db;

            border-radius: 4px;

            font-size: 0.9rem;

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

            padding: 15px 25px;

            background: #f9fafb;

            border-bottom: 1px solid #e5e7eb;

            font-weight: 500;

            color: #374151;

        }

        

        .download-list {

            flex: 1;

            overflow-y: auto;

            padding: 15px 25px;

        }

        

        .modal-footer {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding: 20px 25px;

            background: #f9fafb;

            border-top: 1px solid #e5e7eb;

            border-radius: 0 0 8px 8px;

        }

        

        .btn-select-all, .btn-clear, .btn-download {

            padding: 10px 20px;

            border: none;

            border-radius: 6px;

            cursor: pointer;

            font-size: 0.9rem;

            font-weight: 500;

            transition: all 0.2s;

            display: flex;

            align-items: center;

            gap: 8px;

        }

        

        .btn-select-all {

            background: #10b981;

            color: white;

        }

        

        .btn-select-all:hover {

            background: #059669;

        }

        

        .btn-clear {

            background: #ef4444;

            color: white;

        }

        

        .btn-clear:hover {

            background: #dc2626;

        }

        

        .btn-download {

            background: #8B1538;

            color: white;

        }

        

        .btn-download:hover:not(:disabled) {

            background: #6b1128;

        }

        

        .btn-download:disabled {

            background: #9ca3af;

            cursor: not-allowed;

        }

        
        
        .download-list {

            max-height: 400px;

            overflow-y: auto;

            border: 1px solid #e5e7eb;

            border-radius: 6px;

            padding: 15px 12px;

        }

        
        
        .download-item {

            display: grid;

            grid-template-columns: 40px 50px 1fr 200px;

            align-items: center;

            padding: 15px 12px;

            border-bottom: 2px solid #e2e8f0;

            background: white;

            transition: background-color 0.2s ease;

            gap: 12px;

        }

        
        
        .download-item:hover {

            background: #f8f9fa;

        }

        

        .download-item:last-child {

            border-bottom: none;

        }

        

        /* Column Styles for 4-column layout */

        .column-checkbox {

            display: flex;

            justify-content: center;

            align-items: center;

        }

        

        .column-icon {

            display: flex;

            justify-content: center;

            align-items: center;

        }

        

        .column-name {

            min-width: 0;

        }

        

        .column-meta {

            text-align: right;

        }

        

        .download-item input[type="checkbox"] {

            width: 18px;

            height: 18px;

            cursor: pointer;

        }

        

        .file-icon {

            width: 32px;

            height: 32px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #e3f2fd;

            border-radius: 6px;

            color: #1976d2;

        }

        

        .column-name h4 {

            margin: 0;

            font-size: 0.9rem;

            font-weight: 600;

            color: #1f2937;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }

        

        .meta-info {

            font-size: 0.8rem;

            color: #6b7280;

            font-weight: 500;

            margin-bottom: 2px;

        }

        

        .meta-date {

            font-size: 0.8rem;

            color: #6b7280;

            font-weight: 500;

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

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

            max-width: 150px;

        }

        
        
        .download-item-info p {

            margin: 0;

            font-size: 0.8rem;

            color: #6b7280;

        }

        

        /* New row-based layout styles */

        .document-name-row {

            margin-bottom: 4px;

        }

        

        .document-meta-row {

            margin-bottom: 4px;

        }

        

        .document-date-row {

            margin-bottom: 0;

        }

        

        .download-item-info h4 {

            margin: 0;

            font-size: 0.9rem;

            font-weight: 600;

            color: #1f2937;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

            max-width: 200px;

        }

        

        .doc-info {

            font-size: 0.8rem;

            color: #6b7280;

            font-weight: 500;

        }

        

        .upload-date {

            font-size: 0.8rem;

            color: #6b7280;

            font-weight: 500;

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

        
        
        /* Upload Alert Styles */

        .upload-alert {

            margin-bottom: 20px;

            border-radius: 8px;

            overflow: hidden;

            animation: slideDown 0.3s ease-out;

        }

        
        
        .upload-alert-content {

            display: flex;

            align-items: flex-start;

            padding: 15px;

            background: #fef2f2;

            border: 1px solid #fecaca;

            border-left: 4px solid #ef4444;

        }

        
        
        .upload-alert-icon {

            margin-right: 12px;

            color: #ef4444;

            font-size: 1.2rem;

            margin-top: 2px;

        }

        
        
        .upload-alert-message {

            flex: 1;

        }

        
        
        .upload-alert-message strong {

            color: #991b1b;

            font-size: 0.95rem;

            display: block;

            margin-bottom: 4px;

        }

        
        
        .upload-alert-message p {

            color: #7f1d1d;

            font-size: 0.9rem;

            margin: 0;

            line-height: 1.4;

            white-space: pre-line;

        }

        
        
        .upload-alert-close {

            background: none;

            border: none;

            color: #991b1b;

            cursor: pointer;

            padding: 4px;

            border-radius: 4px;

            transition: background-color 0.2s;

        }

        
        
        .upload-alert-close:hover {

            background: rgba(153, 27, 27, 0.1);

        }

        
        
        @keyframes slideDown {

            from {

                opacity: 0;

                transform: translateY(-10px);

            }

            to {

                opacity: 1;

                transform: translateY(0);

            }

        }

        
        
        .alert-error {

            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fca5a5;

        }

        
        
        .stats-grid {

            display: grid !important;

            grid-template-columns: repeat(4, 1fr) !important;

            gap: 20px;

            margin-bottom: 30px;

        }

        
        
        .stat-card {

            background: white;

            padding: 30px 25px;

            border-radius: 16px;

            text-align: center;

            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);

            border: 1px solid #f1f5f9;

            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

            position: relative;

            overflow: hidden;

        }

        
        

        
        
        .stat-card:hover {

            transform: translateY(-4px);

            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);

        }

        
        
        .stat-number {

            font-size: 2.5rem;

            font-weight: 800;

            color: #8B1538;

            margin-bottom: 8px;

            line-height: 1;

            text-shadow: 0 2px 4px rgba(139, 21, 56, 0.1);

        }

        
        
        .stat-label {

            color: #64748b;

            font-size: 0.85rem;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            margin-top: 5px;

        }

        
        
        /* Category Navigation Styles */

        .category-navigation {

            background: white;

            border-radius: 12px;

            padding: 15px 18px;

            margin-bottom: 20px;

            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);

            border: 1px solid #f1f5f9;

            position: relative;

            overflow: hidden;

        }

        
        
        .category-navigation::before {

            content: '';

            position: absolute;

            top: 0;

            left: 0;

            right: 0;

            height: 4px;

            background: linear-gradient(135deg, #8B1538, #5D0E26);

            border-radius: 16px 16px 0 0;

        }

        
        
        .category-header-main {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 12px;

            padding-bottom: 10px;

            border-bottom: 2px solid #f1f5f9;

        }

        
        
        .category-header-main h2 {

            margin: 0;

            color: #1e293b;

            font-size: 1.2rem;

            font-weight: 700;

            display: flex;

            align-items: center;

            gap: 8px;

        }

        
        
        .category-header-main h2 i {

            font-size: 1.1rem;

            color: #8B1538;

        }

        
        
        .category-header-main .section-count {

            background: linear-gradient(135deg, #8B1538, #5D0E26);

            color: white;

            padding: 8px 16px;

            border-radius: 25px;

            font-size: 0.85rem;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            box-shadow: 0 4px 12px rgba(139, 21, 56, 0.3);

        }

        
        
        .category-buttons {

            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            align-items: center;

        }

        
        
        .category-btn {

            background: linear-gradient(135deg, #f8fafc, #f1f5f9);

            color: #64748b;

            border: 2px solid #e2e8f0;

            padding: 8px 14px;

            border-radius: 10px;

            cursor: pointer;

            font-weight: 600;

            display: inline-flex;

            align-items: center;

            gap: 6px;

            transition: all 0.3s ease;

            font-size: 0.8rem;

            text-decoration: none;

            position: relative;

            overflow: hidden;

        }

        
        
        .category-btn::before {

            content: '';

            position: absolute;

            top: 0;

            left: -100%;

            width: 100%;

            height: 100%;

            background: linear-gradient(135deg, rgba(139, 21, 56, 0.1), rgba(93, 14, 38, 0.1));

            transition: left 0.3s ease;

        }

        
        
        .category-btn:hover {

            transform: translateY(-2px);

            box-shadow: 0 6px 20px rgba(139, 21, 56, 0.2);

            border-color: #8B1538;

            color: #8B1538;

        }

        
        
        .category-btn:hover::before {

            left: 0;

        }

        
        
        .category-btn.active {

            background: linear-gradient(135deg, #8B1538, #5D0E26);

            color: white;

            border-color: #8B1538;

            box-shadow: 0 4px 12px rgba(139, 21, 56, 0.3);

        }

        
        
        .category-btn.active::before {

            display: none;

        }

        
        
        .category-btn i {

            font-size: 1rem;

        }

        
        
        .category-content {

            animation: fadeIn 0.3s ease-in-out;

        }

        
        
        @keyframes fadeIn {

            from { opacity: 0; transform: translateY(10px); }

            to { opacity: 1; transform: translateY(0); }

        }

        
        
        .category-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 25px;

            padding: 20px 25px;

            background: white;

            border-radius: 16px;

            border: 1px solid #f1f5f9;

            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);

            position: relative;

            overflow: hidden;

        }

        
        
        .category-header::before {

            content: '';

            position: absolute;

            top: 0;

            left: 0;

            right: 0;

            height: 4px;

            background: linear-gradient(135deg, #8B1538, #5D0E26);

            border-radius: 16px 16px 0 0;

        }

        
        
        .category-header h3 {

            margin: 0;

            color: #1e293b;

            font-size: 1.4rem;

            font-weight: 700;

            display: flex;

            align-items: center;

            gap: 12px;

        }

        
        
        .category-header h3 i {

            font-size: 1.2rem;

            color: #8B1538;

        }

        
        
        .category-count {

            background: linear-gradient(135deg, #8B1538, #5D0E26);

            color: white;

            padding: 8px 16px;

            border-radius: 25px;

            font-size: 0.85rem;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            box-shadow: 0 4px 12px rgba(139, 21, 56, 0.3);

        }

        
        
        @media (max-width: 768px) {

            .category-buttons {

                flex-direction: column;

                align-items: stretch;

            }

            
            
            .category-btn {

                justify-content: center;

                text-align: center;

            }

            
            
            .category-header {

                flex-direction: column;

                gap: 15px;

                text-align: center;

            }

            
            
            .category-header-main {

                flex-direction: column;

                gap: 15px;

                text-align: center;

            }

        }
        
        
        
    </style>

</head>

<body>
    <!-- Hamburger Menu Button -->
    <button class="hamburger-menu" id="hamburgerMenu">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    

    <!-- Sidebar -->

    <div class="sidebar">

        <div class="sidebar-header">

            <img src="images/logo.jpg" alt="Logo">

            <h2>Opiña Law Office</h2>

        </div>

        <ul class="sidebar-menu">

            <li><a href="admin_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>

            <li><a href="admin_documents.php" class="active"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>

            <li><a href="admin_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generations</span></a></li>

            <li><a href="admin_schedule.php"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a></li>

            <li><a href="admin_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>

            <li><a href="admin_usermanagement.php"><i class="fas fa-users-cog"></i><span>User Management</span></a></li>

            <li><a href="admin_managecases.php"><i class="fas fa-gavel"></i><span>Case Management</span></a></li>

            <li><a href="admin_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>

            <li><a href="admin_messages.php"><i class="fas fa-comments"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>

            <li><a href="admin_audit.php"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>

        </ul>

    </div>



    <!-- Main Content -->

    <div class="main-content">

        <!-- Header -->
        <?php 
        $page_title = 'Advanced Document Management';
        $page_subtitle = 'Manage documents from all sources with full administrative access';
        include 'components/profile_header.php'; 
        ?>



        <!-- Statistics -->

        <div class="stats-grid" id="statsSection">

            <div class="stat-card">

                <div class="stat-number"><?= count($stats_documents) ?></div>

                <div class="stat-label">Total Documents</div>

            </div>

            <div class="stat-card">

                <div class="stat-number"><?= get_current_book_number() ?></div>

                <div class="stat-label">Current Book</div>

            </div>

            <div class="stat-card">

                <div class="stat-number"><?= count(array_filter($stats_documents, function($d) { return $d['source_type'] === 'attorney'; })) ?></div>

                <div class="stat-label">Attorney Documents</div>

            </div>

            <div class="stat-card">

                <div class="stat-number"><?= count(array_filter($stats_documents, function($d) { return $d['source_type'] === 'employee'; })) ?></div>

                <div class="stat-label">Employee Documents</div>

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

        <div class="upload-section" id="uploadSection">

            <h2><i class="fas fa-upload"></i> Upload Documents</h2>

            
            
            <!-- Inline Alert Container -->

            <div id="uploadAlert" class="upload-alert" style="display: none;">

                <div class="upload-alert-content">

                    <div class="upload-alert-icon">

                        <i class="fas fa-exclamation-triangle"></i>

                    </div>

                    <div class="upload-alert-message">

                        <strong>Upload Error</strong>

                        <p id="uploadAlertText"></p>

                    </div>

                    <button type="button" class="upload-alert-close" onclick="closeUploadAlert()">

                        <i class="fas fa-times"></i>

                    </button>

                </div>

            </div>

            
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">

                <div style="margin-bottom: 15px;">

                    <label>Select Role:</label>

                    <select name="source_type" id="sourceType" required style="width: 200px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">

                        <option value=""> Select Role </option>

                        <option value="attorney" <?= (isset($_GET['source_type']) && $_GET['source_type'] === 'attorney') || (isset($_GET['selected_role']) && $_GET['selected_role'] === 'attorney') ? 'selected' : '' ?>>Attorney</option>

                        <option value="employee" <?= (isset($_GET['source_type']) && $_GET['source_type'] === 'employee') || (isset($_GET['selected_role']) && $_GET['selected_role'] === 'employee') ? 'selected' : '' ?>>Employee</option>

                    </select>

                </div>

                
                
                <div class="upload-area" id="uploadArea" style="opacity: 0.5; pointer-events: none;">

                    <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: #6b7280; margin-bottom: 15px;"></i>

                    <h3>Select Source Type First</h3>

                    <p>Please select Attorney or Employee before uploading files</p>

                    <input type="file" name="documents[]" id="fileInput" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png" max="10" style="display: none;" disabled>

                </div>

                
                
                <div class="file-preview" id="filePreview">

                    <h4>Document Details</h4>

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

        <div class="filters-section" id="filtersSection" style="display: none;">

            <h2><i class="fas fa-filter"></i> Filters & Search</h2>

            <form method="GET" id="filterForm">

                <input type="hidden" name="selected_role" id="selectedRoleInput" value="">

                <div class="filters-grid">

                    <div class="filter-group" id="documentNameFilter">

                        <label>Document Name:</label>

                        <input type="text" name="name" value="<?= htmlspecialchars($filter_name) ?>" placeholder="Search by name">

                    </div>

                    <div class="filter-group" id="docNumberFilter">

                        <label>Doc Number:</label>

                        <input type="number" name="doc_number" value="<?= htmlspecialchars($filter_doc_number) ?>" placeholder="Enter doc number">

                    </div>

                    <div class="filter-group" id="bookNumberFilter">

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

                    <div class="filter-group" id="dateFromFilter">

                        <label>Date From:</label>

                        <input type="date" name="filter_from" value="<?= htmlspecialchars($filter_from) ?>">

                    </div>

                    <div class="filter-group" id="dateToFilter">

                        <label>Date To:</label>

                        <input type="date" name="filter_to" value="<?= htmlspecialchars($filter_to) ?>">

                    </div>

                    <div class="filter-group" id="sourceTypeFilter">

                        <label>Source Type:</label>

                        <select name="source_type">

                            <option value="">All Sources</option>

                            <option value="attorney" <?= $filter_source === 'attorney' ? 'selected' : '' ?>>Attorney</option>

                            <option value="employee" <?= $filter_source === 'employee' ? 'selected' : '' ?>>Employee</option>

                        </select>

                    </div>


                </div>

                
                
                <div class="filter-actions">

                    <button type="submit" class="btn-primary" id="applyFiltersBtn" style="display: none;">

                        <i class="fas fa-search"></i> Apply Filters

                    </button>

                    <a href="#" id="clearFiltersBtn" class="btn-secondary">

                        <i class="fas fa-times"></i> Clear Filters

                    </a>

                    <button type="button" class="btn-secondary" id="downloadSelectedBtn" onclick="openDownloadModal()">

                        <i class="fas fa-download"></i> Select Download

                    </button>

                </div>

            </form>

        </div>


        <!-- Documents Sections -->

        <div id="documentsResults" style="display: none;">

        <?php 

        // Separate documents by source type: Attorney+Admin together, Employee separate

        $attorney_admin_docs = array_filter($documents, function($doc) { 

            return $doc['source_type'] === 'admin' || $doc['source_type'] === 'attorney'; 

        });

        $employee_docs = array_filter($documents, function($doc) { 

            return $doc['source_type'] === 'employee'; 

        });

        
        
        // Separate employee documents by category

        $employee_notarized = array_filter($employee_docs, function($doc) { 

            return $doc['category'] === 'Notarized Documents'; 

        });

        $employee_law_office = array_filter($employee_docs, function($doc) { 

            return $doc['category'] === 'Law Office Files'; 

        });

        
        
        // Group attorney documents by category

        $attorney_categories = [

            'Case Files' => [],

            'Court Documents' => [],

            'Client Documents' => []

        ];

        
        
        foreach ($attorney_admin_docs as $doc) {

            $category = $doc['category'] ?? '';

            if (isset($attorney_categories[$category])) {

                $attorney_categories[$category][] = $doc;

            }

        }

        ?>

        <!-- Attorney Documents Section -->

        <div class="document-section" id="attorneyDocumentsSection">

            <!-- Category Navigation -->

            <div class="category-navigation">

                <div class="category-header-main">

                    <h2><i class="fas fa-gavel"></i> Attorney Documents</h2>

                    <span class="section-count"><?= count($attorney_admin_docs) ?> document(s)</span>

                </div>

                <div class="category-buttons">

                    <button class="category-btn active" data-category="all" onclick="filterByCategory('all')">

                        <i class="fas fa-folder-open"></i> All Documents (<?= count($attorney_admin_docs) ?>)

                    </button>

                    <?php foreach ($attorney_categories as $category => $docs): ?>

                        <button class="category-btn" data-category="<?= strtolower(str_replace(' ', '_', $category)) ?>" onclick="filterByCategory('<?= strtolower(str_replace(' ', '_', $category)) ?>')">

                            <i class="fas fa-folder"></i> <?= $category ?> (<?= count($docs) ?>)

                        </button>

                    <?php endforeach; ?>

                </div>

                <!-- Search Bar for Attorney Documents -->
                <div class="attorney-search-container" style="margin: 20px 0; display: flex; justify-content: center;">
                    <div class="attorney-search-box" style="position: relative; min-width: 450px; max-width: 650px; height: 50px; display: flex; align-items: center; background: white; border-radius: 12px; border: 2px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); transition: all 0.3s ease;">
                        <i class="fas fa-search" style="position: absolute; left: 15px; color: #8B1538; font-size: 1.1rem; z-index: 1;"></i>
                        <input type="text" id="attorneySearchInput" placeholder="Search attorney documents by name..." style="width: 100%; height: 46px; padding: 0 50px 0 50px; border-radius: 10px; border: none; font-size: 1rem; background: transparent; transition: all 0.2s ease; box-sizing: border-box; color: #374151;" onkeyup="filterAttorneyDocuments()">
                        <button type="button" onclick="clearAttorneySearch()" title="Clear search" style="position: absolute; right: 8px; background: #f3f4f6; border: none; border-radius: 6px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; color: #6b7280; font-size: 0.9rem;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

            </div>

            
            
            <!-- All Documents View -->

            <div class="category-content" id="category-all">

                <!-- Pagination Controls -->
                <div class="doc-pagination-controls" style="margin: 15px 0; padding: 12px 16px; background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); border: 1px solid #e9ecef; display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
                    <label for="attorneyItemsPerPage" style="font-weight: 600; color: #5D0E26; font-size: 0.9rem;">Show per page:</label>
                    <select id="attorneyItemsPerPage" style="padding: 8px 16px; border: 2px solid #5D0E26; border-radius: 8px; background: white; color: #5D0E26; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.3s ease;">
                        <option value="6">6</option>
                        <option value="12" selected>12</option>
                        <option value="18">18</option>
                        <option value="24">24</option>
                        <option value="30">30</option>
                        <option value="all">All</option>
                    </select>
                    <span class="attorney-items-info" style="font-weight: 500; color: #6c757d; font-size: 0.85rem;"></span>
                </div>

                <div class="document-grid">

                    <?php foreach ($attorney_admin_docs as $doc): ?>

                    <div class="document-card">

                        <div class="source-badge source-<?= $doc['source_type'] ?>">

                            <?= ucfirst($doc['source_type']) ?>

                        </div>

                        
                        
                        <div class="card-header">

                            <div class="document-icon">

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

                                <h3 title="<?= htmlspecialchars(pathinfo($doc['file_name'], PATHINFO_FILENAME)) ?>"><?= htmlspecialchars(pathinfo($doc['file_name'], PATHINFO_FILENAME)) ?></h3>

                                <div class="document-meta">

                                    <?php if ($doc['source_type'] === 'employee'): ?>

                                        <div><strong>Doc #<?= $doc['doc_number'] ?></strong> | Book #<?= $doc['book_number'] ?></div>

                                        <?php if (!empty($doc['affidavit_type'])): ?>

                                            <div style="font-size: 0.75rem; color: #5D0E26; font-weight: 500;"><?= htmlspecialchars($doc['affidavit_type']) ?></div>

                                        <?php endif; ?>

                                    <?php else: ?>

                                        <div><strong>Attorney Document</strong></div>

                                    <?php endif; ?>

                                    <div><?= date('M d, Y', strtotime($doc['upload_date'])) ?></div>

                                    <div><strong>Category:</strong> <?= htmlspecialchars($doc['category']) ?></div>

                                </div>

                                <?php if ($doc['name']): ?>

                                    <div class="uploader-info">

                                        <span class="uploader-name"><?= htmlspecialchars($doc['name']) ?></span>

                                        <span class="uploader-role">(<?= ucfirst($doc['user_type']) ?>)</span>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                        
                        
                        <div class="document-actions">

                            <button onclick="openViewModal(this)" data-file-path="<?= htmlspecialchars($doc['file_path'], ENT_QUOTES) ?>" data-file-name="<?= htmlspecialchars($doc['file_name'], ENT_QUOTES) ?>" class="btn-action btn-view" title="View">

                                <i class="fas fa-eye"></i>

                            </button>

                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" download onclick="return confirmDownload()" class="btn-action btn-view" title="Download">

                                <i class="fas fa-download"></i>

                            </a>

                            <button onclick="confirmEdit(<?= $doc['id'] ?>, '<?= htmlspecialchars(pathinfo($doc['file_name'], PATHINFO_FILENAME), ENT_QUOTES) ?>', <?= $doc['doc_number'] ?? 0 ?>, <?= $doc['book_number'] ?? 0 ?>, '<?= htmlspecialchars($doc['affidavit_type'] ?? '', ENT_QUOTES) ?>', '<?= $doc['source_type'] ?>', '<?= htmlspecialchars($doc['category'], ENT_QUOTES) ?>')" class="btn-action btn-edit" title="Edit">

                                <i class="fas fa-edit"></i>

                            </button>

                            <a href="?delete=<?= $doc['id'] ?>&source=<?= $doc['source_type'] ?>" onclick="return confirmDelete()" class="btn-action btn-delete" title="Delete">

                                <i class="fas fa-trash"></i>

                            </a>

                        </div>

                    </div>

                <?php endforeach; ?>

                </div>

            </div>

            
            
            <!-- Category-specific Views -->

            <?php foreach ($attorney_categories as $category => $docs): ?>

                <?php if (!empty($docs)): ?>

                    <div class="category-content" id="category-<?= strtolower(str_replace(' ', '_', $category)) ?>" style="display: none;">

                        <div class="category-header">

                            <h3><i class="fas fa-folder"></i> <?= $category ?></h3>

                            <span class="category-count"><?= count($docs) ?> document(s)</span>

                        </div>

                        <div class="document-grid">

                            <?php foreach ($docs as $doc): ?>

                                <div class="document-card">

                                    <div class="source-badge source-<?= $doc['source_type'] ?>">

                                        <?= ucfirst($doc['source_type']) ?>

                                    </div>

                                    
                                    
                                    <div class="card-header">

                                        <div class="document-icon">

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

                                            <h3 title="<?= htmlspecialchars(pathinfo($doc['file_name'], PATHINFO_FILENAME)) ?>"><?= htmlspecialchars(pathinfo($doc['file_name'], PATHINFO_FILENAME)) ?></h3>

                                            <div class="document-meta">

                                                <div><strong>Attorney Document</strong></div>

                                                <div><?= date('M d, Y', strtotime($doc['upload_date'])) ?></div>

                                                <div><strong>Category:</strong> <?= htmlspecialchars($doc['category']) ?></div>

                                            </div>

                                            <?php if ($doc['name']): ?>

                                                <div class="uploader-info">

                                                    <span class="uploader-name"><?= htmlspecialchars($doc['name']) ?></span>

                                                    <span class="uploader-role">(<?= ucfirst($doc['user_type']) ?>)</span>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                    
                                    
                                    <div class="document-actions">

                                        <button onclick="openViewModal(this)" data-file-path="<?= htmlspecialchars($doc['file_path'], ENT_QUOTES) ?>" data-file-name="<?= htmlspecialchars($doc['file_name'], ENT_QUOTES) ?>" class="btn-action btn-view" title="View">

                                            <i class="fas fa-eye"></i>

                                        </button>

                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" download onclick="return confirmDownload()" class="btn-action btn-view" title="Download">

                                            <i class="fas fa-download"></i>

                                        </a>

                                        <button onclick="confirmEdit(<?= $doc['id'] ?>, '<?= htmlspecialchars(pathinfo($doc['file_name'], PATHINFO_FILENAME), ENT_QUOTES) ?>', <?= $doc['doc_number'] ?? 0 ?>, <?= $doc['book_number'] ?? 0 ?>, '<?= htmlspecialchars($doc['affidavit_type'] ?? '', ENT_QUOTES) ?>', '<?= $doc['source_type'] ?>', '<?= htmlspecialchars($doc['category'], ENT_QUOTES) ?>')" class="btn-action btn-edit" title="Edit">

                                            <i class="fas fa-edit"></i>

                                        </button>

                                        <a href="?delete=<?= $doc['id'] ?>&source=<?= $doc['source_type'] ?>" onclick="return confirmDelete()" class="btn-action btn-delete" title="Delete">

                                            <i class="fas fa-trash"></i>

                                        </a>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php endif; ?>

            <?php endforeach; ?>

        </div>

        
        
        <!-- Employee Documents Section -->

        <div class="document-section" id="employeeDocumentsSection">

            <!-- Category Navigation -->

            <div class="category-navigation">

                <div class="category-header-main">

                    <h2><i class="fas fa-user-tie"></i> Employee Documents</h2>

                    <span class="section-count"><?= count($employee_docs) ?> document(s)</span>

                </div>

                <div class="category-buttons">

                    <button class="category-btn active" data-category="all" onclick="filterEmployeeByCategory('all')">

                        <i class="fas fa-folder-open"></i> All Documents (<?= count($employee_docs) ?>)

                    </button>

                    <button class="category-btn" data-category="notarized_documents" onclick="filterEmployeeByCategory('notarized_documents')">

                        <i class="fas fa-stamp"></i> Notarized Documents (<?= count($employee_notarized) ?>)

                    </button>

                    <button class="category-btn" data-category="law_office_files" onclick="filterEmployeeByCategory('law_office_files')">

                        <i class="fas fa-folder"></i> Law Office Files (<?= count($employee_law_office) ?>)

                    </button>

                </div>

            </div>

            
            
            <!-- All Documents View -->

            <div class="category-content" id="employee-category-all">

                <!-- Pagination Controls -->
                <div class="doc-pagination-controls" style="margin: 15px 0; padding: 12px 16px; background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); border: 1px solid #e9ecef; display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
                    <label for="employeeItemsPerPage" style="font-weight: 600; color: #5D0E26; font-size: 0.9rem;">Show per page:</label>
                    <select id="employeeItemsPerPage" style="padding: 8px 16px; border: 2px solid #5D0E26; border-radius: 8px; background: white; color: #5D0E26; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.3s ease;">
                        <option value="6">6</option>
                        <option value="12" selected>12</option>
                        <option value="18">18</option>
                        <option value="24">24</option>
                        <option value="30">30</option>
                        <option value="all">All</option>
                    </select>
                    <span class="employee-items-info" style="font-weight: 500; color: #6c757d; font-size: 0.85rem;"></span>
                </div>

                <div class="document-grid">

                    <?php foreach ($employee_docs as $doc): ?>

                    <div class="document-card">

                        <div class="source-badge source-<?= $doc['source_type'] ?>">

                            <?= ucfirst($doc['source_type']) ?>

                        </div>

                        
                        
                        <div class="card-header">

                            <div class="document-icon">

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

                                <h3 title="<?= htmlspecialchars($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME)) ?>"><?= htmlspecialchars(truncate_document_name($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME))) ?></h3>

                                <div class="document-meta">

                                    <?php 

                                    $category_colors = [

                                        'Notarized Documents' => ['bg' => '#5D0E26', 'text' => 'white'],

                                        'Law Office Files' => ['bg' => '#059669', 'text' => 'white']

                                    ];

                                    $colors = $category_colors[$doc['category']] ?? ['bg' => '#6b7280', 'text' => 'white'];

                                    ?>

                                    <div style="font-size: 0.7rem; color: <?= $colors['text'] ?>; font-weight: 600; background: <?= $colors['bg'] ?>; padding: 3px 6px; border-radius: 4px; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

                                        <?= htmlspecialchars($doc['category']) ?>

                                    </div>

                                    <div style="font-size: 0.75rem; color: #6b7280; font-weight: 500; margin-top: 4px;">

                                        <strong>Date Uploaded:</strong> <?= date('M d, Y', strtotime($doc['upload_date'])) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                        
                        
                        <div class="document-actions">

                            <button onclick="openEmployeeViewModal('<?= htmlspecialchars($doc['file_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME), ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['category'], ENT_QUOTES) ?>', '<?= $doc['doc_number'] ?>', '<?= $doc['book_number'] ?>', '<?= htmlspecialchars($doc['affidavit_type'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['name'] ?? 'Employee', ENT_QUOTES) ?>')" class="btn-action btn-view" title="View">

                                <i class="fas fa-eye"></i>

                            </button>

                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" download onclick="return confirmDownload()" class="btn-action btn-view" title="Download">

                                <i class="fas fa-download"></i>

                            </a>

                            <button onclick="confirmEdit(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME), ENT_QUOTES) ?>', <?= $doc['doc_number'] ?? 0 ?>, <?= $doc['book_number'] ?? 0 ?>, '<?= htmlspecialchars($doc['affidavit_type'] ?? '', ENT_QUOTES) ?>', '<?= $doc['source_type'] ?>', '<?= htmlspecialchars($doc['category'], ENT_QUOTES) ?>')" class="btn-action btn-edit" title="Edit">

                                <i class="fas fa-edit"></i>

                            </button>

                            <a href="?delete=<?= $doc['id'] ?>&source=<?= $doc['source_type'] ?>" onclick="return confirmDelete()" class="btn-action btn-delete" title="Delete">

                                <i class="fas fa-trash"></i>

                            </a>

                        </div>

                    </div>

                    <?php endforeach; ?>

                </div>

            </div>

            
            
            <!-- Notarized Documents View -->

            <div class="category-content" id="employee-category-notarized_documents" style="display: none;">

                <div class="category-header">

                    <h3><i class="fas fa-stamp"></i> Notarized Documents</h3>

                    <span class="category-count"><?= count($employee_notarized) ?> document(s)</span>

                </div>

                <div class="document-grid">

                    <?php foreach ($employee_notarized as $doc): ?>

                        <div class="document-card">

                            <div class="source-badge source-<?= $doc['source_type'] ?>">

                                <?= ucfirst($doc['source_type']) ?>

                            </div>

                            
                            
                            <div class="card-header">

                                <div class="document-icon">

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

                                    <h3 title="<?= htmlspecialchars($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME)) ?>"><?= htmlspecialchars(truncate_document_name($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME))) ?></h3>

                                    <div class="document-meta">

                                        <div style="font-size: 0.7rem; color: white; font-weight: 600; background: #5D0E26; padding: 3px 6px; border-radius: 4px; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

                                            <?= htmlspecialchars($doc['category']) ?>

                                        </div>

                                        <div style="font-size: 0.75rem; color: #6b7280; font-weight: 500; margin-top: 4px;">

                                            <strong>Date Uploaded:</strong> <?= date('M d, Y', strtotime($doc['upload_date'])) ?>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            
                            
                            <div class="document-actions">

                                <button onclick="openEmployeeViewModal('<?= htmlspecialchars($doc['file_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME), ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['category'], ENT_QUOTES) ?>', '<?= $doc['doc_number'] ?>', '<?= $doc['book_number'] ?>', '<?= htmlspecialchars($doc['affidavit_type'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['name'] ?? 'Employee', ENT_QUOTES) ?>')" class="btn-action btn-view" title="View">

                                    <i class="fas fa-eye"></i>

                                </button>

                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" download onclick="return confirmDownload()" class="btn-action btn-view" title="Download">

                                    <i class="fas fa-download"></i>

                                </a>

                                <button onclick="confirmEdit(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME), ENT_QUOTES) ?>', <?= $doc['doc_number'] ?? 0 ?>, <?= $doc['book_number'] ?? 0 ?>, '<?= htmlspecialchars($doc['affidavit_type'] ?? '', ENT_QUOTES) ?>', '<?= $doc['source_type'] ?>', '<?= htmlspecialchars($doc['category'], ENT_QUOTES) ?>')" class="btn-action btn-edit" title="Edit">

                                    <i class="fas fa-edit"></i>

                                </button>

                                <a href="?delete=<?= $doc['id'] ?>&source=<?= $doc['source_type'] ?>" onclick="return confirmDelete()" class="btn-action btn-delete" title="Delete">

                                    <i class="fas fa-trash"></i>

                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

            
            
            <!-- Law Office Files View -->

            <div class="category-content" id="employee-category-law_office_files" style="display: none;">

                <div class="category-header">

                    <h3><i class="fas fa-folder"></i> Law Office Files</h3>

                    <span class="category-count"><?= count($employee_law_office) ?> document(s)</span>

                </div>

                <div class="document-grid">

                    <?php foreach ($employee_law_office as $doc): ?>

                        <div class="document-card">

                            <div class="source-badge source-<?= $doc['source_type'] ?>">

                                <?= ucfirst($doc['source_type']) ?>

                            </div>

                            
                            
                            <div class="card-header">

                                <div class="document-icon">

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

                                    <h3 title="<?= htmlspecialchars($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME)) ?>"><?= htmlspecialchars(truncate_document_name($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME))) ?></h3>

                                    <div class="document-meta">

                                        <div style="font-size: 0.7rem; color: white; font-weight: 600; background: #059669; padding: 3px 6px; border-radius: 4px; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

                                            <?= htmlspecialchars($doc['category']) ?>

                                        </div>

                                        <div style="font-size: 0.75rem; color: #6b7280; font-weight: 500; margin-top: 4px;">

                                            <strong>Date Uploaded:</strong> <?= date('M d, Y', strtotime($doc['upload_date'])) ?>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            
                            
                            <div class="document-actions">

                                <button onclick="openEmployeeViewModal('<?= htmlspecialchars($doc['file_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME), ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['category'], ENT_QUOTES) ?>', '<?= $doc['doc_number'] ?>', '<?= $doc['book_number'] ?>', '<?= htmlspecialchars($doc['affidavit_type'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['name'] ?? 'Employee', ENT_QUOTES) ?>')" class="btn-action btn-view" title="View">

                                    <i class="fas fa-eye"></i>

                                </button>

                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" download onclick="return confirmDownload()" class="btn-action btn-view" title="Download">

                                    <i class="fas fa-download"></i>

                                </a>

                                <button onclick="confirmEdit(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['document_name'] ?? pathinfo($doc['file_name'], PATHINFO_FILENAME), ENT_QUOTES) ?>', <?= $doc['doc_number'] ?? 0 ?>, <?= $doc['book_number'] ?? 0 ?>, '<?= htmlspecialchars($doc['affidavit_type'] ?? '', ENT_QUOTES) ?>', '<?= $doc['source_type'] ?>', '<?= htmlspecialchars($doc['category'], ENT_QUOTES) ?>')" class="btn-action btn-edit" title="Edit">

                                    <i class="fas fa-edit"></i>

                                </button>

                                <a href="?delete=<?= $doc['id'] ?>&source=<?= $doc['source_type'] ?>" onclick="return confirmDelete()" class="btn-action btn-delete" title="Delete">

                                    <i class="fas fa-trash"></i>

                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

        
        
        <!-- No Documents Message -->

        <?php if (empty($documents)): ?>

            <div class="no-documents-message">

                <i class="fas fa-folder-open" style="font-size: 3rem; color: #d1d5db; margin-bottom: 15px;"></i>

                <h3 style="color: #6b7280;">No documents found</h3>

                <p style="color: #9ca3af;">Try adjusting your filters or upload some documents.</p>

            </div>

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

                <form method="POST" class="modern-form" onsubmit="return confirmSave()">

                    <input type="hidden" name="edit_id" id="edit_id">

                    <input type="hidden" name="edit_source_type" id="edit_source_type">

                    
                    
                    <div class="form-group">

                        <label for="edit_document_name">

                            <i class="fas fa-file-alt"></i> Document Name

                        </label>

                        <input type="text" name="edit_document_name" id="edit_document_name" required>

                    </div>

                    
                    
                    <div class="form-group" id="edit_category_field">

                        <label for="edit_category">

                            <i class="fas fa-folder"></i> Category

                        </label>

                        <select name="edit_category" id="edit_category">

                            <option value="">Select Category</option>

                            <option value="Case Files">Case Files</option>

                            <option value="Court Documents">Court Documents</option>

                            <option value="Client Documents">Client Documents</option>

                        </select>

                    </div>

                    
                    
                    <div class="form-group" id="edit_doc_number_group">

                        <label for="edit_doc_number">

                            <i class="fas fa-hashtag"></i> Doc Number

                        </label>

                        <input type="number" name="edit_doc_number" id="edit_doc_number" required>

                    </div>

                    
                    
                    <div class="form-group" id="edit_book_number_group">

                        <label for="edit_book_number">

                            <i class="fas fa-book"></i> Book Number

                        </label>

                        <input type="number" name="edit_book_number" id="edit_book_number" required>

                    </div>

                    
                    
                    <div class="form-group" id="edit_affidavit_type_group">

                        <label for="edit_affidavit_type">

                            <i class="fas fa-file-contract"></i> Type of Affidavit

                        </label>

                        <select name="edit_affidavit_type" id="edit_affidavit_type" style="width: 100%; padding: 12px 16px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #fafafa;">

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

                        <div class="detail-row">

                            <label><i class="fas fa-calendar"></i> Upload Date:</label>

                            <span id="viewUploadDate"></span>

                        </div>

                    </div>

                    <div class="detail-column">

                        <div class="detail-row">

                            <label><i class="fas fa-hdd"></i> File Size:</label>

                            <span id="viewFileSize"></span>

                        </div>

                        <div class="detail-row">

                            <label><i class="fas fa-file"></i> File Type:</label>

                            <span id="viewFileType"></span>

                        </div>

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

                    <iframe id="documentFrame" src=""></iframe>

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



    <!-- Preview Modal -->

    <div id="previewModal" class="modal">

        <div class="modal-content preview-modal">

            <span class="close" onclick="closePreviewModal()">&times;</span>

            <h2 id="previewTitle">Document Preview</h2>

            <div id="previewContent">

                <!-- Preview content will be loaded here -->

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

                    <?php if ($doc['source_type'] === 'employee' && $doc['category'] === 'Notarized Documents'): ?>

                    <div class="download-item" data-date="<?= date('Y-m-d', strtotime($doc['upload_date'])) ?>">

                        <div class="column-checkbox">

                            <input type="checkbox" value="<?= $doc['id'] ?>" onchange="updateSelectedCount()" 

                                   data-name="<?= htmlspecialchars($doc['file_name']) ?>" 

                                   data-path="<?= htmlspecialchars($doc['file_path']) ?>" 

                                   data-doc-number="<?= $doc['doc_number'] ?>" 

                                   data-book-number="<?= $doc['book_number'] ?>">

                        </div>

                        <div class="column-icon">

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

                        </div>

                        <div class="column-name">

                            <h4 title="<?= htmlspecialchars($doc['document_name'] ?? $doc['file_name']) ?>"><?= htmlspecialchars(truncate_document_name($doc['document_name'] ?? $doc['file_name'])) ?></h4>

                        </div>

                        <div class="column-meta">

                            <div class="meta-info">Doc #<?= $doc['doc_number'] ?> | Book #<?= $doc['book_number'] ?></div>

                            <div class="meta-date"><?= date('M d, Y', strtotime($doc['upload_date'])) ?></div>

                        </div>

                    </div>

                    <?php endif; ?>

                <?php endforeach; ?>

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



    <script>

        // Scroll to results after filter application

        document.addEventListener('DOMContentLoaded', function() {

            // Check if we have filter parameters in the URL

            const urlParams = new URLSearchParams(window.location.search);

            const hasFilters = urlParams.has('filter_from') || urlParams.has('filter_to') || 

                              urlParams.has('doc_number') || urlParams.has('book_number') || 

                              urlParams.has('source_type') || urlParams.has('name');
            
            
            
            // Check if we should scroll to documents section after upload/edit/delete

            const shouldScroll = urlParams.get('scroll') === 'documents';

            
            
            if (hasFilters || shouldScroll) {

                // Scroll to the documents results section

                setTimeout(function() {

                    const resultsSection = document.getElementById('documentsResults');

                    if (resultsSection) {

                        resultsSection.scrollIntoView({ 

                            behavior: 'smooth', 

                            block: 'start' 

                        });

                    }

                }, 150);

            }

        });



        // File upload handling

        const uploadArea = document.getElementById('uploadArea');

        const fileInput = document.getElementById('fileInput');

        const filePreview = document.getElementById('filePreview');

        const previewList = document.getElementById('previewList');

        const uploadBtn = document.getElementById('uploadBtn');



        // Initial setup - only if elements exist

        if (uploadArea && fileInput) {

            uploadArea.addEventListener('dragover', handleDragOver);

            uploadArea.addEventListener('dragleave', handleDragLeave);

            uploadArea.addEventListener('drop', handleDrop);

            fileInput.addEventListener('change', handleFileSelect);

        }



        function handleDragOver(e) {

            e.preventDefault();

            const uploadArea = document.getElementById('uploadArea');

            if (uploadArea && uploadArea.style.pointerEvents !== 'none') {

                uploadArea.classList.add('dragover');

            }

        }



        function handleDragLeave(e) {

            e.preventDefault();

            const uploadArea = document.getElementById('uploadArea');

            if (uploadArea) {

                uploadArea.classList.remove('dragover');

            }

        }



        function handleDrop(e) {

            e.preventDefault();

            const uploadArea = document.getElementById('uploadArea');

            if (uploadArea) {

                uploadArea.classList.remove('dragover');

            }

            const fileInput = document.getElementById('fileInput');

            if (fileInput && !fileInput.disabled) {

                const files = e.dataTransfer.files;

                handleFiles(files);

            }

        }



        function handleFileSelect(e) {

            const fileInput = document.getElementById('fileInput');

            if (fileInput && !fileInput.disabled) {

                const files = e.target.files;

                handleFiles(files);

            }

        }



        // Store file data for persistent preview

        let fileDataStore = new Map();



        function handleFiles(files) {

            if (files.length > 10) {

                alert('Maximum 10 files allowed');

                return;

            }



            previewList.innerHTML = '';

            fileDataStore.clear(); // Clear previous data

            
            
            const sourceType = document.getElementById('sourceType').value;

            
            
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

                
                
                // Different fields based on source type

                let formFields = '';

                if (sourceType === 'attorney') {

                    // Remove file extension from filename for document name

                    const docName = file.name.replace(/\.[^/.]+$/, "");

                    formFields = `

                        <input type="text" name="doc_names[]" value="${docName}" placeholder="Document Name" required style="flex: 1; margin: 0 5px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem;">

                        <select name="categories[]" required style="flex: 1; margin: 0 5px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem;">

                            <option value="">Select Category</option>

                            <option value="Case Files">Case Files</option>

                            <option value="Court Documents">Court Documents</option>

                            <option value="Client Documents">Client Documents</option>

                        </select>

                    `;

                } else if (sourceType === 'employee') {

                    const currentMonth = new Date().getMonth() + 1; // Get current month (1-12)

                    formFields = `

                        <div style="margin-bottom: 12px;">

                            <select name="category[]" required onchange="toggleFieldsBasedOnCategory(this)" style="width: 300px; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white;">

                                <option value="">Select Category *</option>

                                <option value="Notarized Documents">Notarized Documents</option>

                                <option value="Law Office Files">Law Office Files</option>

                            </select>

                        </div>

                        <!-- Notarized Documents Fields -->

                        <div id="notarizedFields" style="display: none;">

                            <div style="display: flex; align-items: center; gap: 8px; width: 100%; flex-wrap: nowrap;">

                                <input type="text" name="surnames[]" placeholder="Surname" style="flex: 1; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white; min-width: 0;">

                                <input type="text" name="first_names[]" placeholder="First Name" style="flex: 1; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white; min-width: 0;">

                                <input type="text" name="middle_names[]" placeholder="Middle Name" style="flex: 1; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white; min-width: 0;">

                                <input type="number" name="doc_numbers[]" placeholder="Doc #" style="flex: 0 0 80px; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white;">

                                <input type="number" name="book_numbers[]" value="${currentMonth}" min="1" max="12" placeholder="Book" style="flex: 0 0 80px; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white;" title="Book Number (1-12, represents month)">

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

                        <!-- Law Office Files Fields -->

                        <div id="lawOfficeFields" style="display: none;">

                            <div style="display: flex; align-items: center; gap: 8px; width: 100%; flex-wrap: nowrap;">

                                <input type="text" name="document_names[]" placeholder="Enter document name/description" style="flex: 1; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 36px; font-size: 0.85rem; background: white; min-width: 0;">

                            </div>

                        </div>

                    `;

                }

                
                
                if (sourceType === 'employee') {

                    previewItem.innerHTML = `

                        <div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 15px; margin-bottom: 10px;">

                            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 12px;">

                                ${previewContent.replace('<div style="position: relative; margin-right: 10px;">', '<div style="position: relative;">')}

                                <div style="flex: 1;">

                                    <div style="font-size: 0.8rem; color: #495057; font-weight: 500; margin-bottom: 2px;">Document Name:</div>

                                    <div style="font-size: 0.9rem; color: #212529; font-weight: 600;">${file.name}</div>

                                </div>

                                <button type="button" onclick="removePreviewItem(this)" style="background: #dc3545; color: white; border: none; border-radius: 6px; padding: 8px 12px; cursor: pointer; font-size: 0.8rem; font-weight: 500;">Remove</button>

                            </div>

                            <div style="width: 100%;">

                                ${formFields}

                            </div>

                        </div>

                    `;

                } else {

                    previewItem.innerHTML = `

                        <div style="display: flex; align-items: center; width: 100%; gap: 12px;">

                            <div style="position: relative;">

                                ${previewContent.replace('<div style="position: relative; margin-right: 10px;">', '<div style="position: relative;">')}

                            </div>

                            <div style="flex: 1; display: flex; flex-direction: column; gap: 8px;">

                                <div style="font-size: 0.7rem; color: #6b7280; word-break: break-all; line-height: 1.2;">${file.name}</div>

                                <div style="display: flex; gap: 8px; align-items: center;">

                                    ${formFields}

                                </div>

                            </div>

                            <div style="display: flex; flex-direction: column; gap: 8px; align-items: center;">

                                <button type="button" onclick="removePreviewItem(this)" style="background: #dc2626; color: white; border: none; border-radius: 4px; padding: 8px 12px; cursor: pointer; height: 36px; display: flex; align-items: center; font-size: 0.8rem; font-weight: 500;">Remove</button>

                            </div>

                        </div>

                    `;

                }

                previewList.appendChild(previewItem);

            }

            
            
            filePreview.style.display = 'block';

            uploadBtn.style.display = 'inline-flex';

        }



        function removePreviewItem(button) {

            const previewItem = button.closest('.preview-item');

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

            
            
            // Create new DataTransfer object

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

        
        
        function toggleFieldsBasedOnCategory(selectElement) {

            const category = selectElement.value;

            const previewItem = selectElement.closest('.preview-item');

            
            
            if (!previewItem) return;

            
            
            const notarizedFields = previewItem.querySelector('#notarizedFields');

            const lawOfficeFields = previewItem.querySelector('#lawOfficeFields');

            
            
            if (category === 'Notarized Documents') {

                if (notarizedFields) notarizedFields.style.display = 'block';

                if (lawOfficeFields) lawOfficeFields.style.display = 'none';

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



        // Confirmation functions

        function confirmEdit(id, name, docNumber, bookNumber, affidavitType, sourceType, category) {

            if (confirm('Are you sure you want to edit this document?')) {

                openEditModal(id, name, docNumber, bookNumber, affidavitType, sourceType, category);

            }

        }



        function confirmDelete() {

            if (confirm('Are you sure you want to delete this document?')) {

                const userInput = prompt('This action cannot be undone. Type "DELETE" to confirm:');

                if (userInput === 'DELETE') {

                    return true;

                } else {

                    alert('Delete cancelled. You must type "DELETE" exactly to confirm.');

                    return false;

                }

            }

            return false;

        }



        function confirmDownload() {

            if (confirm('Do you want to download this document?')) {

                return true;

            }

            return false;

        }



        function confirmSave() {

            if (confirm('Are you sure you want to save these changes?')) {

                if (confirm('This will update the document information. Continue?')) {

                    return true;

                }

            }

            return false;

        }



        // Modal functions

        function openEditModal(id, name, docNumber, bookNumber, affidavitType, sourceType, category) {

            document.getElementById('edit_id').value = id;

            document.getElementById('edit_document_name').value = name;

            document.getElementById('edit_doc_number').value = docNumber;

            document.getElementById('edit_book_number').value = bookNumber;

            document.getElementById('edit_affidavit_type').value = affidavitType || '';

            document.getElementById('edit_source_type').value = sourceType;

            document.getElementById('edit_category').value = category || '';

            
            
            // Show/hide fields based on source type and category

            const docNumberField = document.getElementById('edit_doc_number_group');

            const bookNumberField = document.getElementById('edit_book_number_group');

            const affidavitField = document.getElementById('edit_affidavit_type_group');

            const categoryField = document.getElementById('edit_category_field');

            
            
            if (sourceType === 'attorney') {

                // For attorney documents, show only document name and category

                docNumberField.style.display = 'none';

                bookNumberField.style.display = 'none';

                affidavitField.style.display = 'none';

                categoryField.style.display = 'block';

                document.getElementById('edit_category').required = true;

                document.getElementById('edit_affidavit_type').required = false;

            } else if (category === 'Law Office Files') {

                // For Law Office Files, show only document name

                docNumberField.style.display = 'none';

                bookNumberField.style.display = 'none';

                affidavitField.style.display = 'none';

                categoryField.style.display = 'none';

                document.getElementById('edit_category').required = false;

                document.getElementById('edit_affidavit_type').required = false;

                document.getElementById('edit_doc_number').required = false;

                document.getElementById('edit_book_number').required = false;

            } else {

                // For Notarized Documents, show all notarized fields

                docNumberField.style.display = 'block';

                bookNumberField.style.display = 'block';

                affidavitField.style.display = 'block';

                categoryField.style.display = 'none';

                document.getElementById('edit_category').required = false;

                document.getElementById('edit_affidavit_type').required = true;

                document.getElementById('edit_doc_number').required = true;

                document.getElementById('edit_book_number').required = true;

            }

            
            
            document.getElementById('editModal').style.display = 'block';

        }



        function closeEditModal() {

            document.getElementById('editModal').style.display = 'none';

        }



        function openPreviewModal(fileId) {

            // This function is only for previewing newly selected files before upload

            const fileData = fileDataStore.get(fileId);

            if (!fileData) {

                alert('File data not found. Please reselect the files.');

                return;

            }

            
            
            document.getElementById('previewTitle').textContent = `Preview: ${fileData.name}`;

            const previewContent = document.getElementById('previewContent');

            
            
            if (fileData.type.startsWith('image/')) {

                previewContent.innerHTML = `<img src="${fileData.url}" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">`;

            } else if (fileData.type === 'application/pdf') {

                previewContent.innerHTML = `<iframe src="${fileData.url}" style="width: 100%; height: 70vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></iframe>`;

            } else {

                previewContent.innerHTML = `

                    <div style="padding: 40px; text-align: center;">

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

        }



        function openEmployeeViewModal(filePath, documentName, category, docNumber, bookNumber, affidavitType, uploader) {

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



        function openViewModal(button) {

            // This function is for viewing uploaded files in a modal

            const filePath = button.getAttribute('data-file-path');

            const fileName = button.getAttribute('data-file-name');

            
            
            // Debug: Log the file path

            console.log('File path from database:', filePath);

            console.log('File name from database:', fileName);

        }
            
        function openViewModal(button) {
            // This function is for viewing uploaded files in a modal
            const filePath = button.getAttribute('data-file-path');
            const fileName = button.getAttribute('data-file-name');
            
            // Get document details from the card
            const card = button.closest('.document-card');
            const docName = card.querySelector('.document-info h3')?.textContent || fileName;
            const category = card.querySelector('.document-meta')?.textContent || 'Unknown';
            const sourceBadge = card.querySelector('.source-badge');
            const source = sourceBadge ? sourceBadge.textContent : 'Unknown';
            
            // Set document details in view modal
            document.getElementById('viewDocumentName').textContent = docName;
            document.getElementById('viewCategory').textContent = category;
            document.getElementById('viewUploader').textContent = source;
            document.getElementById('viewUploadDate').textContent = new Date().toLocaleDateString();
            document.getElementById('viewFileSize').textContent = 'Unknown';
            document.getElementById('viewFileType').textContent = fileName.split('.').pop()?.toUpperCase() || 'Unknown';
            
            // Set document preview
            document.getElementById('documentFrame').src = filePath;
            
            // Show view modal
            document.getElementById('viewModal').style.display = 'block';
        }

        



        function openDownloadModal() {

            document.getElementById('downloadModal').style.display = 'block';

            updateDocumentCount();

            updateSelectedCount();

        }



        function closeDownloadModal() {

            document.getElementById('downloadModal').style.display = 'none';

        }

        

        // Date filter functions

        function setDateFilter(filter) {

            // Remove active class from all buttons

            document.querySelectorAll('.filter-btn').forEach(btn => {

                btn.classList.remove('active');

            });

            

            // Add active class to clicked button

            event.target.classList.add('active');

            

            // Show/hide custom date range inputs

            const customDateRange = document.getElementById('customDateRange');

            if (filter === 'custom') {

                customDateRange.style.display = 'block';

            } else {

                customDateRange.style.display = 'none';

            }

            

            const downloadItems = document.querySelectorAll('.download-item');

            

            downloadItems.forEach(item => {

                const itemDate = new Date(item.dataset.date);

                let showItem = false;

                

                switch(filter) {

                    case 'all':

                        showItem = true;

                        break;

                    case 'custom':

                        const dateFrom = document.getElementById('dateFrom').value;

                        const dateTo = document.getElementById('dateTo').value;

                        

                        if (dateFrom && dateTo) {

                            const fromDate = new Date(dateFrom);

                            const toDate = new Date(dateTo);

                            showItem = itemDate >= fromDate && itemDate <= toDate;

                        } else {

                            showItem = true;

                        }

                        break;

                }

                

                item.style.display = showItem ? 'grid' : 'none';

            });

            

            updateDocumentCount();

            updateSelectedCount();

        }

        

        function filterByCustomDate() {

            setDateFilter('custom');

        }

        

        // Document count functions

        function updateDocumentCount() {

            const visibleItems = document.querySelectorAll('.download-item[style*="grid"], .download-item:not([style*="none"])');

            const count = visibleItems.length;

            document.getElementById('docCount').textContent = count;

        }

        

        function updateSelectedCount() {

            const checkedBoxes = document.querySelectorAll('.download-item input[type="checkbox"]:checked');

            const count = checkedBoxes.length;

            document.getElementById('selectedCount').textContent = count;

            

            // Enable/disable download button

            const downloadBtn = document.getElementById('downloadBtn');

            if (downloadBtn) {

                downloadBtn.disabled = count === 0;

            }

        }

        

        // Selection functions

        function selectAllDownloads() {

            const visibleItems = document.querySelectorAll('.download-item[style*="grid"], .download-item:not([style*="none"])');

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

            form.action = 'download_selected_documents_admin.php';

            
            
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

            if (event.target === previewModal) {

                closePreviewModal();

            }

        }

        
        
        // Category filtering functionality

        function filterByCategory(category) {

            // Hide all category contents

            const allContents = document.querySelectorAll('.category-content');

            allContents.forEach(content => {

                content.style.display = 'none';

            });

            
            
            // Remove active class from all buttons

            const allButtons = document.querySelectorAll('.category-btn');

            allButtons.forEach(btn => {

                btn.classList.remove('active');

            });

            
            
            // Show selected category content

            const targetContent = document.getElementById('category-' + category);

            if (targetContent) {

                targetContent.style.display = 'block';

            }

            
            
            // Add active class to clicked button

            const clickedButton = document.querySelector(`[data-category="${category}"]`);

            if (clickedButton) {

                clickedButton.classList.add('active');

            }

            
            
            // Scroll to the documents section

            const documentsSection = document.getElementById('documentsResults');

            if (documentsSection) {

                documentsSection.scrollIntoView({ 

                    behavior: 'smooth', 

                    block: 'start' 

                });

            }

        }

        
        
        // Attorney Documents Search Functionality

        function filterAttorneyDocuments() {

            const searchTerm = document.getElementById('attorneySearchInput').value.toLowerCase();

            const attorneyDocuments = document.querySelectorAll('#attorneyDocumentsSection .document-card');

            attorneyDocuments.forEach(card => {

                const docName = card.querySelector('.document-info h3') || card.querySelector('.document-title') || card.querySelector('h3');

                if (docName) {

                    const name = docName.textContent.toLowerCase();

                    if (name.includes(searchTerm)) {

                        card.style.display = 'block';

                    } else {

                        card.style.display = 'none';

                    }

                }

            });

        }

        

        function clearAttorneySearch() {

            document.getElementById('attorneySearchInput').value = '';

            filterAttorneyDocuments();

        }

        
        
        // Employee category filtering functionality

        function filterEmployeeByCategory(category) {

            // Hide all employee category contents

            const allEmployeeContents = document.querySelectorAll('#employee-category-all, #employee-category-notarized_documents, #employee-category-law_office_files');

            allEmployeeContents.forEach(content => {

                content.style.display = 'none';

            });

            
            
            // Remove active class from all employee buttons

            const allEmployeeButtons = document.querySelectorAll('.category-btn[data-category="all"], .category-btn[data-category="notarized_documents"], .category-btn[data-category="law_office_files"]');

            allEmployeeButtons.forEach(btn => {

                btn.classList.remove('active');

            });

            
            
            // Show selected category content

            const targetContent = document.getElementById('employee-category-' + category);

            if (targetContent) {

                targetContent.style.display = 'block';

            }

            
            
            // Add active class to clicked button

            const clickedButton = document.querySelector(`[data-category="${category}"]`);

            if (clickedButton) {

                clickedButton.classList.add('active');

            }

            
            
            // Scroll to the document grid specifically

            setTimeout(() => {

                const documentGrid = targetContent.querySelector('.document-grid');

                if (documentGrid) {

                    documentGrid.scrollIntoView({ 

                        behavior: 'smooth', 

                        block: 'start',

                        inline: 'nearest'

                    });

                }

            }, 150);

        }

    </script>



    <!-- Dynamic Category Dropdown Script -->

    <script>

        document.addEventListener('DOMContentLoaded', function() {

            const sourceTypeSelect = document.getElementById('sourceType');

            const filterSourceTypeSelect = document.getElementById('filterSourceType');

            

            // Function to show/hide sections based on role selection

            function toggleSectionsBasedOnRole(selectedRole) {

                const statsSection = document.getElementById('statsSection');

                const uploadSection = document.getElementById('uploadSection');

                const filtersSection = document.getElementById('filtersSection');

                const documentsResults = document.getElementById('documentsResults');

                

                if (selectedRole) {

                    // Show all sections when role is selected

                    if (statsSection) statsSection.style.display = 'block';

                    if (uploadSection) uploadSection.style.display = 'block';

                    // Only show filters section for Employee role
                    if (selectedRole === 'employee') {
                        if (filtersSection) filtersSection.style.display = 'block';
                    } else {
                        if (filtersSection) filtersSection.style.display = 'none';
                    }

                    if (documentsResults) documentsResults.style.display = 'block';

                } else {

                    // Hide all sections except stats and upload section when no role selected

                    if (statsSection) statsSection.style.display = 'block';

                    if (uploadSection) uploadSection.style.display = 'block';

                    if (filtersSection) filtersSection.style.display = 'none';

                    if (documentsResults) documentsResults.style.display = 'none';

                }

            }

            

            // Function to toggle document sections visibility

            function toggleDocumentSections(selectedRole) {

                const attorneySection = document.getElementById('attorneyDocumentsSection');

                const employeeSection = document.getElementById('employeeDocumentsSection');

                

                // Hide all sections first

                if (attorneySection) attorneySection.style.display = 'none';

                if (employeeSection) employeeSection.style.display = 'none';

                

                // Show relevant sections based on selected role

                if (selectedRole === 'attorney') {

                    if (attorneySection) attorneySection.style.display = 'block';

                    // Update filter source type to attorney

                    if (filterSourceTypeSelect) {

                        filterSourceTypeSelect.value = 'attorney';

                    }

                } else if (selectedRole === 'employee') {

                    if (employeeSection) employeeSection.style.display = 'block';

                    // Update filter source type to employee

                    if (filterSourceTypeSelect) {

                        filterSourceTypeSelect.value = 'employee';

                    }

                } else {

                    // Show all sections when no role is selected

                    if (attorneySection) attorneySection.style.display = 'block';

                    if (employeeSection) employeeSection.style.display = 'block';

                    // Reset filter source type

                    if (filterSourceTypeSelect) {

                        filterSourceTypeSelect.value = '';

                    }

                }

            }

            

            // Function to toggle filter fields visibility

            function toggleFilterFields(selectedRole) {

                const filtersSection = document.getElementById('filtersSection');
                const applyFiltersBtn = document.getElementById('applyFiltersBtn');
                const dateFromFilter = document.getElementById('dateFromFilter');

                const dateToFilter = document.getElementById('dateToFilter');

                const docNumberFilter = document.getElementById('docNumberFilter');

                const bookNumberFilter = document.getElementById('bookNumberFilter');

                const categoryFilter = document.getElementById('categoryFilter');

                const sourceTypeFilter = document.getElementById('sourceTypeFilter');

                const documentNameFilter = document.getElementById('documentNameFilter');

                const categorySelect = document.getElementById('categorySelect');

                const downloadSelectedBtn = document.getElementById('downloadSelectedBtn');

                

                // Hide/show entire filters section based on role
                if (selectedRole === 'attorney') {
                    // Hide entire filters section for Attorney role
                    if (filtersSection) filtersSection.style.display = 'none';
                } else if (selectedRole === 'employee') {
                    // Show entire filters section for Employee role
                    if (filtersSection) filtersSection.style.display = 'block';
                    if (applyFiltersBtn) applyFiltersBtn.style.display = 'inline-flex';
                } else {
                    // Default: hide filters section
                    if (filtersSection) filtersSection.style.display = 'none';
                    if (applyFiltersBtn) applyFiltersBtn.style.display = 'none';
                }

                // Hide all filter fields first

                if (dateFromFilter) dateFromFilter.style.display = 'none';

                if (dateToFilter) dateToFilter.style.display = 'none';

                if (docNumberFilter) docNumberFilter.style.display = 'none';

                if (bookNumberFilter) bookNumberFilter.style.display = 'none';

                if (categoryFilter) categoryFilter.style.display = 'none';

                if (sourceTypeFilter) sourceTypeFilter.style.display = 'none';

                if (documentNameFilter) documentNameFilter.style.display = 'none';

                

                // Show/hide category options based on role

                if (categorySelect) {

                    const attorneyOptions = categorySelect.querySelectorAll('.attorney-category');

                    const employeeOptions = categorySelect.querySelectorAll('.employee-category');

                    

                    // Hide all category options first

                    attorneyOptions.forEach(option => option.style.display = 'none');

                    employeeOptions.forEach(option => option.style.display = 'none');

                    

                    if (selectedRole === 'attorney') {

                        // Show attorney categories

                        attorneyOptions.forEach(option => option.style.display = 'block');

                    } else if (selectedRole === 'employee') {

                        // Show employee categories

                        employeeOptions.forEach(option => option.style.display = 'block');

                    } else {

                        // Show all categories when no role selected

                        attorneyOptions.forEach(option => option.style.display = 'block');

                        employeeOptions.forEach(option => option.style.display = 'block');

                    }

                }

                

                // Show relevant filter fields based on selected role

                if (selectedRole === 'attorney') {

                    // Attorney: Show Category, Document Name, Date From, Date To

                    if (categoryFilter) categoryFilter.style.display = 'block';

                    if (documentNameFilter) documentNameFilter.style.display = 'block';

                    if (dateFromFilter) dateFromFilter.style.display = 'block';

                    if (dateToFilter) dateToFilter.style.display = 'block';

                    // Hide Download Selected button for attorney

                    if (downloadSelectedBtn) downloadSelectedBtn.style.display = 'none';

                    // Set source type filter to attorney only

                    if (filterSourceTypeSelect) {

                        filterSourceTypeSelect.value = 'attorney';

                    }

                } else if (selectedRole === 'employee') {

                    // Employee: Show Category, Document Name, Doc Number, Book Number

                    if (categoryFilter) categoryFilter.style.display = 'block';

                    if (documentNameFilter) documentNameFilter.style.display = 'block';

                    if (docNumberFilter) docNumberFilter.style.display = 'block';

                    if (bookNumberFilter) bookNumberFilter.style.display = 'block';

                    // Show Download Selected button for employee

                    if (downloadSelectedBtn) downloadSelectedBtn.style.display = 'inline-flex';

                    // Set source type filter to employee only

                    if (filterSourceTypeSelect) {

                        filterSourceTypeSelect.value = 'employee';

                    }

                } else {

                    // No role selected: Show all filter fields

                    if (dateFromFilter) dateFromFilter.style.display = 'block';

                    if (dateToFilter) dateToFilter.style.display = 'block';

                    if (docNumberFilter) docNumberFilter.style.display = 'block';

                    if (bookNumberFilter) bookNumberFilter.style.display = 'block';

                    if (categoryFilter) categoryFilter.style.display = 'block';

                    if (sourceTypeFilter) sourceTypeFilter.style.display = 'block';

                    if (documentNameFilter) documentNameFilter.style.display = 'block';

                    // Show Download Selected button when no role selected

                    if (downloadSelectedBtn) downloadSelectedBtn.style.display = 'inline-flex';

                    // Reset source type filter

                    if (filterSourceTypeSelect) {

                        filterSourceTypeSelect.value = '';

                    }

                }

                

                // Adjust grid layout based on visible fields

                const filtersGrid = document.querySelector('.filters-grid');

                if (filtersGrid) {

                    const visibleFields = Array.from(filtersGrid.children).filter(child => 

                        child.style.display !== 'none' && child.id

                    );

                    

                    if (visibleFields.length <= 2) {

                        filtersGrid.style.gridTemplateColumns = 'repeat(2, 1fr)';

                    } else if (visibleFields.length <= 3) {

                        filtersGrid.style.gridTemplateColumns = 'repeat(3, 1fr)';

                    } else {

                        filtersGrid.style.gridTemplateColumns = 'repeat(4, 1fr)';

                    }

                }

            }

            
            
            // Handle source type change

            sourceTypeSelect.addEventListener('change', function() {

                const selectedSourceType = this.value;

                const uploadArea = document.getElementById('uploadArea');

                const filePreview = document.getElementById('filePreview');

                const uploadBtn = document.getElementById('uploadBtn');

                

                // Toggle sections based on role selection

                toggleSectionsBasedOnRole(selectedSourceType);

                

                // Toggle document sections visibility

                toggleDocumentSections(selectedSourceType);

                

                // Toggle filter fields visibility

                toggleFilterFields(selectedSourceType);

                

                // Update hidden input with current role selection

                const selectedRoleInput = document.getElementById('selectedRoleInput');

                if (selectedRoleInput) {

                    selectedRoleInput.value = selectedSourceType;

                }

                
                
                if (selectedSourceType) {

                    // Enable upload area

                    if (uploadArea) {

                        uploadArea.style.opacity = '1';

                        uploadArea.style.pointerEvents = 'auto';

                        uploadArea.innerHTML = `

                            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: #6b7280; margin-bottom: 15px;"></i>

                            <h3>Drag & Drop Files Here</h3>

                            <p>or click to select files (up to 10 documents)</p>

                            <input type="file" name="documents[]" id="fileInput" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png" max="10" style="display: none;">

                        `;

                        
                        
                        // Reattach event listeners to the new file input

                        const fileInput = document.getElementById('fileInput');

                        if (fileInput) {

                            fileInput.disabled = false;

                            fileInput.addEventListener('change', handleFileSelect);

                        }

                        
                        
                        // Reattach click listener to upload area

                        uploadArea.addEventListener('click', function() {

                            const fileInput = document.getElementById('fileInput');

                            if (fileInput && !fileInput.disabled) {

                                fileInput.click();

                            }

                        });

                        
                        
                        // Reattach drag and drop listeners

                        uploadArea.addEventListener('dragover', handleDragOver);

                        uploadArea.addEventListener('dragleave', handleDragLeave);

                        uploadArea.addEventListener('drop', handleDrop);

                    }
                    
                    
                    
                } else {

                    // Disable upload area

                    if (uploadArea) {

                        uploadArea.style.opacity = '0.5';

                        uploadArea.style.pointerEvents = 'none';

                        uploadArea.innerHTML = `

                            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: #6b7280; margin-bottom: 15px;"></i>

                            <h3>Select Source Type First</h3>

                            <p>Please select Attorney or Employee before uploading files</p>

                            <input type="file" name="documents[]" id="fileInput" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png" max="10" style="display: none;" disabled>

                        `;

                    }

                    
                    
                    // Hide file preview and upload button

                    if (filePreview) {

                        filePreview.style.display = 'none';

                    }

                    if (uploadBtn) {

                        uploadBtn.style.display = 'none';

                    }

                }

            });

            

            // Initialize visibility on page load

            toggleSectionsBasedOnRole(sourceTypeSelect.value);

            toggleDocumentSections(sourceTypeSelect.value);

            toggleFilterFields(sourceTypeSelect.value);

            

            // Update hidden input with current role selection

            const selectedRoleInput = document.getElementById('selectedRoleInput');

            if (selectedRoleInput) {

                selectedRoleInput.value = sourceTypeSelect.value;

            }

            

            // Handle Clear Filters button

            const clearFiltersBtn = document.getElementById('clearFiltersBtn');

            if (clearFiltersBtn) {

                clearFiltersBtn.addEventListener('click', function(e) {

                    e.preventDefault();

                    const currentRole = sourceTypeSelect.value;

                    if (currentRole) {

                        // Redirect to admin_documents.php with the current role preserved

                        window.location.href = 'admin_documents.php?source_type=' + currentRole;

                    } else {

                        // If no role selected, go to admin_documents.php without parameters

                        window.location.href = 'admin_documents.php';

                    }

                });

            }

            
            
            // AJAX Form submission

            document.getElementById('uploadForm').addEventListener('submit', function(e) {

                e.preventDefault();

                console.log('AJAX form submission started');

                
                
                // Basic validation

                if (!sourceTypeSelect.value) {

                    alert('Please select a Source Type');

                    return false;

                }

                
                
                const fileInput = document.getElementById('fileInput');

                if (fileInput.files.length === 0) {

                    alert('Please select at least one document to upload');

                    return false;

                }

                
                
                // Create FormData

                const formData = new FormData(this);

                
                
                // Show loading state

                const uploadBtn = document.getElementById('uploadBtn');

                const originalText = uploadBtn.innerHTML;

                uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

                uploadBtn.disabled = true;

                
                
                // AJAX request

                fetch('admin_documents.php', {

                    method: 'POST',

                    body: formData

                })

                .then(response => response.json())

                .then(data => {

                    // Reset button

                    uploadBtn.innerHTML = originalText;

                    uploadBtn.disabled = false;

                    
                    
                    if (data.success) {

                        alert(data.message);

                        // Reload page but preserve the selected role

                        const selectedRole = sourceTypeSelect.value;

                        window.location.href = 'admin_documents.php?source_type=' + selectedRole;

                    } else {

                        showUploadAlert(data.message);

                        // Keep form data intact - files remain selected

                    }

                })

                .catch(error => {

                    // Reset button

                    uploadBtn.innerHTML = originalText;

                    uploadBtn.disabled = false;

                    alert('Upload failed: ' + error.message);

                });

                
                
                return false;

            });

            
            
            // Upload Alert Functions

            function showUploadAlert(message) {

                const alertElement = document.getElementById('uploadAlert');

                const alertText = document.getElementById('uploadAlertText');

                
                
                if (alertElement && alertText) {

                    alertText.textContent = message;

                    alertElement.style.display = 'block';

                    
                    
                    // Scroll to alert

                    alertElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                }

            }

            
            
            function closeUploadAlert() {

                const alertElement = document.getElementById('uploadAlert');

                if (alertElement) {

                    alertElement.style.display = 'none';

                }

            }

        });
        
        // Pagination functionality for documents
        (function() {
            function initDocumentPagination() {
                const attorneyItemsPerPageSelect = document.getElementById('attorneyItemsPerPage');
                const employeeItemsPerPageSelect = document.getElementById('employeeItemsPerPage');
                
                if (!attorneyItemsPerPageSelect || !employeeItemsPerPageSelect) {
                    setTimeout(initDocumentPagination, 100);
                    return;
                }
                
                // Attorney Documents Pagination
                function updateAttorneyDisplay() {
                    const value = attorneyItemsPerPageSelect.value;
                    const attorneyCards = document.querySelectorAll('#attorneyDocumentsSection .document-card');
                    const attorneyInfo = document.querySelector('.attorney-items-info');
                    
                    const totalItems = attorneyCards.length;
                    let showCount = totalItems;
                    
                    if (value !== 'all') {
                        showCount = parseInt(value);
                    }
                    
                    // Update display
                    attorneyCards.forEach((card, index) => {
                        if (value === 'all') {
                            card.style.display = '';
                        } else {
                            card.style.display = index < showCount ? '' : 'none';
                        }
                    });
                    
                    // Update info text
                    const displayCount = value === 'all' ? totalItems : Math.min(showCount, totalItems);
                    if (attorneyInfo) {
                        attorneyInfo.textContent = `Showing ${displayCount} of ${totalItems} documents`;
                    }
                }
                
                // Employee Documents Pagination
                function updateEmployeeDisplay() {
                    const value = employeeItemsPerPageSelect.value;
                    const employeeCards = document.querySelectorAll('#employeeDocumentsSection .document-card');
                    const employeeInfo = document.querySelector('.employee-items-info');
                    
                    const totalItems = employeeCards.length;
                    let showCount = totalItems;
                    
                    if (value !== 'all') {
                        showCount = parseInt(value);
                    }
                    
                    // Update display
                    employeeCards.forEach((card, index) => {
                        if (value === 'all') {
                            card.style.display = '';
                        } else {
                            card.style.display = index < showCount ? '' : 'none';
                        }
                    });
                    
                    // Update info text
                    const displayCount = value === 'all' ? totalItems : Math.min(showCount, totalItems);
                    if (employeeInfo) {
                        employeeInfo.textContent = `Showing ${displayCount} of ${totalItems} documents`;
                    }
                }
                
                // Add event listeners
                attorneyItemsPerPageSelect.addEventListener('change', updateAttorneyDisplay);
                employeeItemsPerPageSelect.addEventListener('change', updateEmployeeDisplay);
                
                // Initial call
                updateAttorneyDisplay();
                updateEmployeeDisplay();
                
                // Add hover effects
                [attorneyItemsPerPageSelect, employeeItemsPerPageSelect].forEach(select => {
                    select.addEventListener('mouseenter', function() {
                        this.style.borderColor = '#8B1538';
                        this.style.boxShadow = '0 2px 8px rgba(93, 14, 38, 0.2)';
                    });
                    
                    select.addEventListener('mouseleave', function() {
                        this.style.borderColor = '#5D0E26';
                        this.style.boxShadow = 'none';
                    });
                });
            }
            
            // Start initialization
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initDocumentPagination);
            } else {
                initDocumentPagination();
            }
        })();

    </script>

<script src="assets/js/unread-messages.js?v=1761540569"></script>
</body>

</html>

