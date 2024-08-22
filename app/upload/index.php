<?php

// Проверка запроса OPTIONS для CORS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require $_SERVER['DOCUMENT_ROOT'] . '/app/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

define('ALLOWED_EXTENSIONS', ['csv', 'xlsx', 'xls', 'zip']);
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . "/uploads/");
define('SPECIFICATION_PATH', $_SERVER['DOCUMENT_ROOT'] . '/app/upload/specification.json');

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
    $relative_upload_dir = "uploads/" . $upload_id;

    if (!file_exists(SPECIFICATION_PATH)) {
        return ['error' => 'Specification file not found.'];
    }

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
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
                        $images = removeBackground($upload_id);
                        
                        return ['success' => 'JSON file and images created and moved successfully.', 'path' => $relative_upload_dir . '/output.json', 'upload_id' => $upload_id];
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
                return ['success' => 'JSON file created successfully.', 'path' => $relative_upload_dir . '/output.json', 'upload_id' => $upload_id];
            } else {
                return ['error' => $json_response['error']];
            }
            break;

        case 'xlsx':
            $json_response = convert_xlsx_to_json($file['tmp_name']);

            foreach ($json_response['data'] as $key => $value) {
                $parse_urls[$value["car_id"]] = $value["SWE Link"];
            }

            $parse_input = [
                'upload_id' => $upload_id,
                'urls' => $parse_urls
            ];

            $parse_data = getParse($parse_input);

            if (is_string($parse_data)) {
                $parse_data = json_decode($parse_data, true);
            }

            $new_array = mergeArraysByCarId($parse_data, $json_response['data']);

            $to_write = [];
            if ($new_array) {
                $to_write = json_encode($new_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $to_write = $json_response['content'];
            }

            $images = removeBackground($upload_id);

            if (file_put_contents($upload_dir . '/output.json', $to_write) === false) {
                return ['error' => 'Failed to write JSON file to disk.'];
            } else {
                return ['success' => 'JSON file created successfully.', 'path' => $relative_upload_dir . '/output.json', 'parse' => $parse_data, 'data' => $new_array, 'ready_images' => $images, 'upload_id' => $upload_id];
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

function convert_xlsx_to_json($xlsxFilePath)
{
    try {
        $spreadsheet = IOFactory::load($xlsxFilePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (empty($rows) || !is_array($rows)) {
            return ['success' => false, 'error' => 'No valid data found in XLSX file'];
        }

        $keys = array_shift($rows);
        $jsonData = array_map(function ($row) use ($keys) {
            $item = array_combine($keys, $row);
            $item['car_id'] = bin2hex(random_bytes(8)); // 16-символьный уникальный идентификатор
            return $item;
        }, $rows);

        $content = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($content === false) {
            return ['success' => false, 'error' => 'Failed to encode JSON: ' . json_last_error_msg()];
        }

        return ['success' => true, 'content' => $content, 'data' => $jsonData];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to read XLSX file: ' . $e->getMessage()];
    }
}

function mergeArraysByCarId($array1, $array2)
{
    $array2Assoc = [];
    foreach ($array2 as $item) {
        if (isset($item['car_id'])) {
            $array2Assoc[$item['car_id']] = $item;
        }
    }

    $mergedArray = [];
    foreach ($array1 as $item) {
        if (isset($item['car_id'])) {
            $carId = $item['car_id'];
            if (isset($array2Assoc[$carId])) {
                $mergedItem = array_merge($item, $array2Assoc[$carId]);
                $mergedArray[] = $mergedItem;
            } else {
                $mergedArray[] = $item;
            }
        }
    }
    return $mergedArray;
}


function getParse($input_data)
{
    $flask_url = 'http://194.58.121.90:5001/run-script';
    $data = json_encode($input_data);

    $ch = curl_init($flask_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return 'Curl error: ' . curl_error($ch);
    } else {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code >= 400) {
            return "HTTP Error: " . $http_code . " Response: " . $response;
        } else {
            $response_array = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return "JSON Decode Error: " . json_last_error_msg();
            }
            return $response_array['output']; // Возвращаем массив
        }
    }

    curl_close($ch);
}

function sendImage($url)
{
    $curl = curl_init();
    $payload = "image_url=" . $url;

    curl_setopt_array($curl, [
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/x-www-form-urlencoded",
            "api_token: 2da7580cef7d4500bbace38e73583837"
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_PORT => "",
        CURLOPT_URL => "https://engine.prod.bria-api.com/v1/background/remove",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
    ]);

    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) {
        return "cURL Error #:" . $error;
    } else {
        $result = json_decode($response, true);
        if (is_array($result) && isset($result['result_url'])) {
            return $result['result_url'];
        } else {
            return "Error: Invalid response from API or missing 'result_url' key.";
        }
    }
}

function removeBackground($upload_id)
{
    $source_folder = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $upload_id . '/images/';
    if (!is_dir($source_folder)) {
        return ['error' => 'Ошибка: Исходная папка не существует.'];
    }

    $destination_folder = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $upload_id . '/optimaized/';
    if (!is_dir($destination_folder)) {
        if (!mkdir($destination_folder, 0755, true)) {
            return ['error' => 'Ошибка: Не удалось создать целевую папку.'];
        }
    }

    $images = glob($source_folder . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);

    foreach ($images as $imagePath) {
        $imageName = basename($imagePath);
        $imageUrl = 'http://194-58-121-90.cloudvps.regruhosting.ru/uploads/' . $upload_id . "/images/" . $imageName;
        $sendImage = sendImage($imageUrl);

        if (filter_var($sendImage, FILTER_VALIDATE_URL)) {
            $getImage = file_get_contents($sendImage);
            file_put_contents($destination_folder . $imageName, $getImage);
            //return "Изображение {$imageName} успешно обработано и сохранено.\n";
        } else {
            //return "Ошибка при обработке изображения {$imageName}: {$sendImage}\n";
        }
    }
}
