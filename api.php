<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$pdo = getDB();

switch($action) {
    
    case 'register':
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'];
        $email = $data['email'];
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Email already registered']);
            break;
        }
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        if ($stmt->execute([$name, $email, $password])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Registration failed']);
        }
        break;
        
    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'];
        $password = $data['password'];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        }
        break;
        
    case 'getAllServices':
        $services = getServices();
        $formatted = [];
        foreach($services as $code => $info) {
            $formatted[] = [
                'code' => $code,
                'name' => $info['name'],
                'price' => $info['price'],
                'available' => $info['available']
            ];
        }
        echo json_encode(['success' => true, 'services' => $formatted]);
        break;
        
    case 'buyNumber':
        $service = $_GET['service'];
        $userId = $_GET['user_id'];
        
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $services = getServices();
        $price = $services[$service]['price'] ?? 0;
        
        if ($user['balance'] < $price) {
            echo json_encode(['success' => false, 'error' => 'Insufficient balance']);
            break;
        }
        
        $result = buyNumber($service);
        
        if ($result['success']) {
            $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$price, $userId]);
            $pdo->prepare("INSERT INTO orders (user_id, order_id, phone_number, service, amount) VALUES (?, ?, ?, ?, ?)")
                ->execute([$userId, $result['activation_id'], $result['number'], $service, $price]);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
        break;
        
    case 'checkOTP':
        $id = $_GET['id'];
        $result = checkOTP($id);
        if ($result['success']) {
            $pdo->prepare("UPDATE orders SET otp_code = ?, status = 'completed' WHERE order_id = ?")
                ->execute([$result['code'], $id]);
        }
        echo json_encode($result);
        break;
        
    case 'requestDeposit':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, transaction_id) VALUES (?, ?, ?)");
        $stmt->execute([$data['user_id'], $data['amount'], $data['transaction_id']]);
        echo json_encode(['success' => true]);
        break;
        
    case 'getAllDeposits':
        $stmt = $pdo->query("SELECT d.*, u.email as user_email FROM deposits d JOIN users u ON d.user_id = u.id ORDER BY d.id DESC");
        echo json_encode(['success' => true, 'deposits' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;
        
    case 'approveDeposit':
        $id = $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ?");
        $stmt->execute([$id]);
        $deposit = $stmt->fetch();
        
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$deposit['amount'], $deposit['user_id']]);
        $pdo->prepare("UPDATE deposits SET status = 'approved' WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    case 'rejectDeposit':
        $pdo->prepare("UPDATE deposits SET status = 'rejected' WHERE id = ?")->execute([$_GET['id']]);
        echo json_encode(['success' => true]);
        break;
        
    case 'getAllUsers':
        $stmt = $pdo->query("SELECT id, name, email, balance, created_at FROM users");
        echo json_encode(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;
        
    case 'getAllOrders':
        $stmt = $pdo->query("SELECT o.*, u.email as user_email FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC");
        echo json_encode(['success' => true, 'orders' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;
        
    case 'getStats':
        $totalDeposits = $pdo->query("SELECT SUM(amount) as total FROM deposits WHERE status = 'approved'")->fetch()['total'] ?? 0;
        $todayCollection = $pdo->query("SELECT SUM(amount) as total FROM deposits WHERE status = 'approved' AND DATE(created_at) = CURDATE()")->fetch()['total'] ?? 0;
        echo json_encode(['success' => true, 'total_deposits' => $totalDeposits, 'today_collection' => $todayCollection]);
        break;
        
    case 'addAdmin':
        $data = json_decode(file_get_contents('php://input'), true);
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, 1)")
            ->execute([$data['name'], $data['email'], $password]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>