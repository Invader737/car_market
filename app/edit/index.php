<?php

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $upload_id = $_GET['folderId'] ?? null;
    if($upload_id){
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $upload_id;
    }
    
    if ($upload_id && is_dir($target_dir)) {
        $jsonFile = $target_dir . '/output.json';
        
        if (file_exists($jsonFile)) {
            // Return the content of the JSON file
            echo file_get_contents($jsonFile);
        } else {
            // JSON file doesn't exist
            echo json_encode(['error' => 'JSON file not found.']);
        }
    } else {
        // Folder doesn't exist or folderId is invalid
        echo json_encode(['error' => 'Invalid folder ID.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the folderId from the URL
    $upload_id = $_GET['folderId'] ?? null;
    if($upload_id){
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $upload_id;
    } 
    if ($upload_id && is_dir($target_dir)) {
        $jsonFile = $target_dir . '/output_modified.json';
        $jsonData = file_get_contents('php://input'); // Get the raw POST data

        if (json_decode($jsonData) !== null) {
            // Save the updated JSON data back to the file
            file_put_contents($jsonFile, $jsonData);
            echo json_encode(['success' => 'Data saved successfully.']);
        } else {
            echo json_encode(['error' => 'Invalid JSON data.']);
        }
    } else {
        // Folder doesn't exist or folderId is invalid
        echo json_encode(['error' => 'Invalid folder ID.']);
    }
} else {
    // Unsupported method
    echo json_encode(['error' => 'Unsupported request method.']);
}