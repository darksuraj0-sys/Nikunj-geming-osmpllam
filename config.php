<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'otp_website');

// API Configuration
define('API_KEY', 'stp_8c391608fc688dbb1028ce30bfc9a9e86ccd8d6983a769f6');
define('API_URL', 'https://sastaotp.com/stubs/handler_api.php');

// Session
session_start();

// Database connection
function getDB() {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// API Functions
function apiRequest($params) {
    $params['api_key'] = API_KEY;
    $params['format'] = 'json';
    
    $url = API_URL . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function getBalance() {
    $result = apiRequest(['action' => 'getBalance']);
    return $result['balance'] ?? 0;
}

function buyNumber($service, $country = '91') {
    $result = apiRequest([
        'action' => 'getNumber',
        'service' => $service,
        'country' => $country
    ]);
    
    if ($result['status'] == 'OK') {
        return [
            'success' => true,
            'activation_id' => $result['activation_id'],
            'number' => $result['number'],
            'price' => $result['price']
        ];
    }
    return ['success' => false, 'error' => $result['status'] ?? 'Unknown error'];
}

function checkOTP($activationId) {
    $result = apiRequest([
        'action' => 'getStatus',
        'id' => $activationId
    ]);
    
    if (isset($result['sms']['code'])) {
        return ['success' => true, 'code' => $result['sms']['code']];
    }
    return ['success' => false];
}
?>