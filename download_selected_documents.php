<?php
require_once 'session_manager.php';
validateUserAccess('employee');
require_once 'config.php';

// Handle ZIP download of selected documents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_docs'])) {
    $selected_docs = $_POST['selected_docs'];
    
    if (empty($selected_docs)) {
        die('No documents selected');
    }
    
    // Create ZIP file
    $zip_filename = 'documents_' . date('Y-m-d_H-i-s') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        die('Cannot create ZIP file');
    }
    
    // Fetch document details
    $placeholders = str_repeat('?,', count($selected_docs) - 1) . '?';
    $stmt = $conn->prepare("SELECT id, doc_number, book_number, document_name, file_name, file_path, affidavit_type FROM employee_documents WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($selected_docs)), ...$selected_docs);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $added_files = 0;
    while ($row = $result->fetch_assoc()) {
        if (file_exists($row['file_path'])) {
            // Create filename in format: AffidavitType_Name_DocNo_BookNo.ext
            $file_info = pathinfo($row['file_path']);
            $extension = isset($file_info['extension']) ? '.' . $file_info['extension'] : '';
            $doc_name = $row['document_name'] ?: pathinfo($row['file_name'], PATHINFO_FILENAME);
            $affidavit_type = $row['affidavit_type'] ?: 'General';
            $zip_filename_internal = "{$affidavit_type}_{$doc_name}_Doc{$row['doc_number']}_Book{$row['book_number']}{$extension}";
            
            // Add file to ZIP
            if ($zip->addFile($row['file_path'], $zip_filename_internal)) {
                $added_files++;
            }
        }
    }
    
    $zip->close();
    
    if ($added_files > 0) {
        // Set headers for download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
        header('Content-Length: ' . filesize($zip_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Output file
        readfile($zip_path);
        
        // Clean up
        unlink($zip_path);
        exit();
    } else {
        die('No valid files found to download');
    }
}

// Handle download all based on current filters
if (isset($_GET['download_all'])) {
    // Build same filter conditions as main page
    $where_conditions = [];
    $where_params = [];
    $where_types = '';
    
    $filter_from = isset($_GET['filter_from']) ? $_GET['filter_from'] : '';
    $filter_to = isset($_GET['filter_to']) ? $_GET['filter_to'] : '';
    $filter_doc_number = isset($_GET['doc_number']) ? $_GET['doc_number'] : '';
    $filter_book_number = isset($_GET['book_number']) ? $_GET['book_number'] : '';
    $filter_name = isset($_GET['name']) ? $_GET['name'] : '';
    
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
    
    if ($filter_doc_number) {
        $where_conditions[] = "doc_number = ?";
        $where_params[] = $filter_doc_number;
        $where_types .= 'i';
    }
    
    if ($filter_book_number) {
        $where_conditions[] = "book_number = ?";
        $where_params[] = $filter_book_number;
        $where_types .= 'i';
    }
    
    if ($filter_name) {
        $where_conditions[] = "(document_name LIKE ? OR file_name LIKE ?)";
        $where_params[] = '%' . $filter_name . '%';
        $where_params[] = '%' . $filter_name . '%';
        $where_types .= 'ss';
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Create ZIP file
    $zip_filename = 'all_documents_' . date('Y-m-d_H-i-s') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        die('Cannot create ZIP file');
    }
    
    // Fetch all documents matching filters
    $query = "SELECT id, doc_number, book_number, document_name, file_name, file_path, affidavit_type FROM employee_documents $where_clause ORDER BY book_number DESC, doc_number ASC";
    $stmt = $conn->prepare($query);
    
    if (!empty($where_params)) {
        $stmt->bind_param($where_types, ...$where_params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $added_files = 0;
    while ($row = $result->fetch_assoc()) {
        if (file_exists($row['file_path'])) {
            // Create filename in format: AffidavitType_Name_DocNo_BookNo.ext
            $file_info = pathinfo($row['file_path']);
            $extension = isset($file_info['extension']) ? '.' . $file_info['extension'] : '';
            $doc_name = $row['document_name'] ?: pathinfo($row['file_name'], PATHINFO_FILENAME);
            $affidavit_type = $row['affidavit_type'] ?: 'General';
            $zip_filename_internal = "{$affidavit_type}_{$doc_name}_Doc{$row['doc_number']}_Book{$row['book_number']}{$extension}";
            
            // Add file to ZIP
            if ($zip->addFile($row['file_path'], $zip_filename_internal)) {
                $added_files++;
            }
        }
    }
    
    $zip->close();
    
    if ($added_files > 0) {
        // Set headers for download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
        header('Content-Length: ' . filesize($zip_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Output file
        readfile($zip_path);
        
        // Clean up
        unlink($zip_path);
        exit();
    } else {
        die('No documents found matching the current filters');
    }
}

// If no action specified, redirect back
header('Location: employee_documents.php');
exit();
?>
