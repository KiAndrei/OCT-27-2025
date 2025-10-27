<?php
require_once 'session_manager.php';
validateUserAccess('admin');
require_once 'config.php';

// Handle ZIP download of selected documents from all sources
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_docs'])) {
    $selected_docs = $_POST['selected_docs'];
    
    if (empty($selected_docs)) {
        die('No documents selected');
    }
    
    // Create ZIP file
    $zip_filename = 'all_documents_' . date('Y-m-d_H-i-s') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        die('Cannot create ZIP file');
    }
    
    $added_files = 0;
    
    // Process documents from all sources
    foreach ($selected_docs as $doc_id) {
        // Check admin documents
        $stmt = $conn->prepare("SELECT id, doc_number, book_number, document_name, file_name, file_path FROM admin_documents WHERE id = ?");
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            if (file_exists($row['file_path'])) {
                $file_info = pathinfo($row['file_path']);
                $extension = isset($file_info['extension']) ? '.' . $file_info['extension'] : '';
                $doc_name = $row['document_name'] ?: pathinfo($row['file_name'], PATHINFO_FILENAME);
                $zip_filename_internal = "Doc{$row['doc_number']}_Book{$row['book_number']}_{$doc_name}{$extension}";
                
                if ($zip->addFile($row['file_path'], "Admin/{$zip_filename_internal}")) {
                    $added_files++;
                }
            }
            continue;
        }
        
        // Check attorney documents
        $stmt = $conn->prepare("SELECT id, doc_number, book_number, document_name, file_name, file_path FROM attorney_documents WHERE id = ?");
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            if (file_exists($row['file_path'])) {
                $file_info = pathinfo($row['file_path']);
                $extension = isset($file_info['extension']) ? '.' . $file_info['extension'] : '';
                $doc_name = $row['document_name'] ?: pathinfo($row['file_name'], PATHINFO_FILENAME);
                $zip_filename_internal = "Doc{$row['doc_number']}_Book{$row['book_number']}_{$doc_name}{$extension}";
                
                if ($zip->addFile($row['file_path'], "Attorney/{$zip_filename_internal}")) {
                    $added_files++;
                }
            }
            continue;
        }
        
        // Check employee documents
        $stmt = $conn->prepare("SELECT id, doc_number, book_number, document_name, file_name, file_path FROM employee_documents WHERE id = ?");
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            if (file_exists($row['file_path'])) {
                $file_info = pathinfo($row['file_path']);
                $extension = isset($file_info['extension']) ? '.' . $file_info['extension'] : '';
                $doc_name = $row['document_name'] ?: pathinfo($row['file_name'], PATHINFO_FILENAME);
                $zip_filename_internal = "Doc{$row['doc_number']}_Book{$row['book_number']}_{$doc_name}{$extension}";
                
                if ($zip->addFile($row['file_path'], "Employee/{$zip_filename_internal}")) {
                    $added_files++;
                }
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
    $filter_source = isset($_GET['source_type']) ? $_GET['source_type'] : '';
    
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
    
    $added_files = 0;
    
    // Fetch documents from all sources based on filters
    $sources = ['admin', 'attorney', 'employee'];
    
    foreach ($sources as $source) {
        if ($filter_source && $filter_source !== $source) {
            continue;
        }
        
        $table = $source . '_documents';
        $query = "SELECT id, doc_number, book_number, document_name, file_name, file_path FROM $table $where_clause ORDER BY book_number DESC, doc_number ASC";
        $stmt = $conn->prepare($query);
        
        if (!empty($where_params)) {
            $stmt->bind_param($where_types, ...$where_params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (file_exists($row['file_path'])) {
                $file_info = pathinfo($row['file_path']);
                $extension = isset($file_info['extension']) ? '.' . $file_info['extension'] : '';
                $doc_name = $row['document_name'] ?: pathinfo($row['file_name'], PATHINFO_FILENAME);
                $zip_filename_internal = "Doc{$row['doc_number']}_Book{$row['book_number']}_{$doc_name}{$extension}";
                
                if ($zip->addFile($row['file_path'], ucfirst($source) . "/{$zip_filename_internal}")) {
                    $added_files++;
                }
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
header('Location: admin_documents.php');
exit();
?>
