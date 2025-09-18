<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $phoneNumber = isset($input['phone']) ? $input['phone'] : '';
    
    // Basic validation
    if (empty($phoneNumber)) {
        $response['status'] = 'error';
        $response['message'] = 'Nomor HP tidak boleh kosong!';
        echo json_encode($response);
        exit();
    }
    
    // Clean phone number (remove non-numeric characters except +)
    $cleanPhone = preg_replace('/[^\d+]/', '', $phoneNumber);
    
    if (strlen($cleanPhone) < 10) {
        $response['status'] = 'error';
        $response['message'] = 'Nomor HP harus minimal 10 digit!';
        echo json_encode($response);
        exit();
    }
    
    // Create data entry
    $entry = array(
        'id' => uniqid(),
        'phone_number' => $cleanPhone,
        'original_input' => $phoneNumber,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    );
    
    // Read existing data from text files
    $dataFilename = 'phone_numbers_data.txt';
    $notesFilename = 'phone_numbers.txt';
    $data = array();
    
    if (file_exists($dataFilename)) {
        $lines = file($dataFilename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $data[] = json_decode($line, true);
        }
    }
    
    // Check if phone number already exists
    foreach ($data as $existingEntry) {
        if ($existingEntry['phone_number'] === $cleanPhone) {
            $response['status'] = 'warning';
            $response['message'] = 'Nomor HP ini sudah pernah disubmit sebelumnya!';
            $response['data'] = $entry;
            echo json_encode($response);
            exit();
        }
    }
    
    // Add new entry to data array
    $data[] = $entry;
    
    // Save detailed data to data file (one JSON object per line)
    $dataLine = json_encode($entry) . "\n";
    if (file_put_contents($dataFilename, $dataLine, FILE_APPEND | LOCK_EX)) {
        // Also append to the simple notes file for easy download
        $noteLine = $cleanPhone . " - " . date('Y-m-d H:i:s') . " (" . $phoneNumber . ")\n";
        file_put_contents($notesFilename, $noteLine, FILE_APPEND | LOCK_EX);
        
        $response['status'] = 'success';
        $response['message'] = 'Nomor HP berhasil disimpan!';
        $response['data'] = $entry;
        
        // Log successful save
        error_log("New phone number saved: " . $cleanPhone . " at " . date('Y-m-d H:i:s'));
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Gagal menyimpan nomor HP!';
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if requesting data for admin panel
    if (isset($_GET['action']) && $_GET['action'] === 'get_data') {
        // Return all data for admin panel
        $dataFilename = 'phone_numbers_data.txt';
        $data = array();
        
        if (file_exists($dataFilename)) {
            $lines = file($dataFilename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry) {
                    $data[] = $entry;
                }
            }
        }
        
        $response['status'] = 'success';
        $response['data'] = $data;
    } else {
        // Return count of saved numbers (for basic stats)
        $dataFilename = 'phone_numbers_data.txt';
        $count = 0;
        
        if (file_exists($dataFilename)) {
            $lines = file($dataFilename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $count = count($lines);
        }
        
        $response['status'] = 'success';
        $response['total_numbers'] = $count;
        $response['message'] = "Total nomor tersimpan: $count";
    }
    
} else {
    $response['status'] = 'error';
    $response['message'] = 'Method tidak diizinkan!';
    http_response_code(405);
}

echo json_encode($response);
?>