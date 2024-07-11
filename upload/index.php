<?php

define('ALLOWED_EXTENSIONS', ['csv', 'xls', 'zip']);
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . "/uploads/");
define('SPECIFICATION_PATH', $_SERVER['DOCUMENT_ROOT'] . '/includes/specification.json');

/* Main handler for file upload and processing */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $response = handleFileUpload($file);

    echo json_encode($response);
} else {
    echo json_encode(['error' => 'No file uploaded.']);
}

/**
 * Handle the file upload process
 *
 * @param array $file The uploaded file information
 * @return array Response with success/error message
 */
function handleFileUpload($file)
{
    $extension_check = checkExtension($file);

    if (!$extension_check['success']) {
        return ['error' => $extension_check['message']];
    }

    $upload_id = generateUploadId();
    $upload_dir = UPLOAD_DIR . $upload_id;

    if (!file_exists(SPECIFICATION_PATH)) {
        return ['error' => 'Specification file not found.'];
    }

    switch ($extension_check['extension']) {
        case 'zip':
            $zip_file_path = $file['tmp_name'];
            $csv_file = extractCsvFromZip($zip_file_path, $upload_dir);

            if ($csv_file) {
                $json_response = convertCsvToJson($csv_file);

                if ($json_response['success']) {
                    file_put_contents($upload_dir . '/output.json', $json_response['content']);
                    $image_extraction_result = extractImagesFromZip($zip_file_path, json_decode($json_response['content'], true), $upload_dir);

                    if ($image_extraction_result['success']) {
                        return ['success' => 'JSON file and images created and moved successfully.', 'path' => $upload_dir . '/output.json'];
                    } else {
                        return ['error' => $image_extraction_result['error']];
                    }
                } else {
                    return ['error' => $json_response['error']];
                }
            } else {
                return ['error' => 'No CSV file found in the zip archive.'];
            }
            break;

        case 'csv':
            $json_response = convertCsvToJson($file['tmp_name']);

            if ($json_response['success']) {
                file_put_contents($upload_dir . '/output.json', $json_response['content']);
                return ['success' => 'JSON file created successfully.', 'path' => $upload_dir . '/output.json'];
            } else {
                return ['error' => $json_response['error']];
            }
            break;

        default:
            return ['error' => 'Unsupported file type.'];
    }
}

/**
 * Check the file extension against allowed extensions
 *
 * @param array $file The uploaded file information
 * @return array Response with success/error message and extension
 */
function checkExtension($file)
{
    $file_name = $file['name'];
    $file_error = $file['error'];
    $response = [
        'success' => false,
        'extension' => 'none',
        'message' => 'Check extension started'
    ];

    if ($file_error === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($file_extension, ALLOWED_EXTENSIONS)) {
            $response['success'] = true;
            $response['extension'] = $file_extension;
            $response['message'] = 'File extension check completed successfully. File extension: ' . $file_extension;
        } else {
            $response['message'] = "Unsupported file type: " . $file_extension . ". Please upload a supported file.";
        }
    } else {
        $response['message'] = "Error uploading file: " . $file_error;
    }

    return $response;
}

/**
 * Generate unique upload ID
 *
 * @return string Unique upload ID
 */
function generateUploadId()
{
    return uniqid('upload_', true);
}

/**
 * Extract CSV file from ZIP archive
 *
 * @param string $zipFilePath Path to ZIP file
 * @param string $uploadDir Directory to extract CSV file
 * @return string|false Path to extracted CSV file or false on failure
 */
function extractCsvFromZip($zipFilePath, $uploadDir)
{
    $zip = new ZipArchive;

    if ($zip->open($zipFilePath) === true) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            $fileName = $fileInfo['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($fileExtension === 'csv') {
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $extractedPath = $uploadDir . '/' . basename($fileName);
                copy("zip://{$zipFilePath}#{$fileName}", $extractedPath);
                $zip->close();
                return $extractedPath;
            }
        }

        $zip->close();
    }

    return false;
}

/**
 * Convert CSV file to JSON based on specification
 *
 * @param string $csvFilePath Path to CSV file
 * @return array Response with success status and JSON content or error message
 */
function convertCsvToJson($csvFilePath)
{
    $specification = json_decode(file_get_contents(SPECIFICATION_PATH), true);

    if (!$specification) {
        return ['success' => false, 'error' => 'Failed to read specification'];
    }

    $jsonData = [];

    if (($handle = fopen($csvFilePath, "r")) !== false) {
        while (($data = fgetcsv($handle, 0, ";")) !== false) {
            $row = [];
            foreach ($data as $index => $value) {
                if (isset($specification[$index])) {
                    $key = $specification[$index];
                    $row[$key] = utf8_encode($value);
                }
            }

            $jsonData[] = $row;
        }
        fclose($handle);

        if (count($jsonData) > 0) {
            $content = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($content === false) {
                return ['success' => false, 'error' => 'Failed to encode JSON: ' . json_last_error_msg()];
            }

            return ['success' => true, 'content' => $content];
        } else {
            return ['success' => false, 'error' => 'No valid data found in CSV file'];
        }
    } else {
        return ['success' => false, 'error' => 'Failed to open CSV file'];
    }
}

/**
 * Extract images from ZIP archive based on image IDs and move them to 'images' directory
 *
 * @param string $zipFilePath Path to ZIP archive containing images
 * @param array $jsonData Array of data with image IDs
 * @param string $uploadDir Directory where ZIP archive is extracted
 * @return array Response with success status and error message if any
 */
function extractImagesFromZip($zipFilePath, $jsonData, $uploadDir)
{
    $zip = new ZipArchive;
    $imagesDir = $uploadDir . '/images';

    if (!file_exists($imagesDir)) {
        mkdir($imagesDir, 0777, true);
    }

    if ($zip->open($zipFilePath) === true) {
        $extracted = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            $fileName = $fileInfo['name'];

            foreach ($jsonData as $data) {
                if (isset($data['image_id'])) {
                    $imageId = $data['image_id'];

                    $expectedFileNamePrefix = $imageId . '_';

                    if (strpos($fileName, $expectedFileNamePrefix) === 0 && isImageFile($zipFilePath, $fileName)) {
                        $extractedFilePath = $imagesDir . '/' . $fileName;
                        copy("zip://{$zipFilePath}#{$fileName}", $extractedFilePath);
                        $extracted = true;
                        break;
                    }
                }
            }
        }

        $zip->close();

        if ($extracted) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'No images found in the ZIP archive for the provided image IDs.'];
        }
    } else {
        return ['success' => false, 'error' => 'Failed to open ZIP archive.'];
    }
}

/**
 * Check if a file in a ZIP archive is an image
 *
 * @param string $zipFilePath Path to ZIP archive containing the file
 * @param string $fileName Name of the file to check within the ZIP archive
 * @return bool True if the file is an image, false otherwise
 */
function isImageFile($zipFilePath, $fileName)
{
    $imageTypes = [
        IMAGETYPE_GIF,
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_WEBP,
        IMAGETYPE_BMP,
        IMAGETYPE_ICO,
        IMAGETYPE_TIFF_II,
        IMAGETYPE_TIFF_MM,
        IMAGETYPE_JPEG2000,
        IMAGETYPE_WBMP,
    ];

    $imageInfo = getimagesize("zip://{$zipFilePath}#{$fileName}");

    if ($imageInfo !== false && in_array($imageInfo[2], $imageTypes)) {
        return true;
    }

    return false;
}
