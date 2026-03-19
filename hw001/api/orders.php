<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php';

function json_response(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $params = [];
    $where = '';
    if ($status !== '') {
        $where = 'WHERE o.status = ?';
        $params[] = $status;
    }

    $sql = "
        SELECT o.id, o.status, o.created_at, o.total_amount,
               i.item_id, i.item_name, i.price, i.qty
        FROM orders o
        LEFT JOIN order_items i ON i.order_id = o.id
        {$where}
        ORDER BY o.id DESC, i.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $orders = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        if (!isset($orders[$id])) {
            $orders[$id] = [
                'id' => $id,
                'status' => $row['status'],
                'time' => date('H:i:s', strtotime($row['created_at'])),
                'total' => (int)$row['total_amount'],
                'items' => [],
            ];
        }
        if ($row['item_name'] !== null) {
            $orders[$id]['items'][] = [
                'item_id' => (int)$row['item_id'],
                'name' => $row['item_name'],
                'price' => (int)$row['price'],
                'qty' => (int)$row['qty'],
            ];
        }
    }

    json_response(200, array_values($orders));
}

if ($method !== 'POST') {
    json_response(405, ['error' => 'Method not allowed']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    json_response(400, ['error' => 'Invalid JSON']);
}

if (($data['action'] ?? '') === 'complete') {
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) {
        json_response(400, ['error' => 'Invalid order id']);
    }

    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute(['completed', $id]);
    json_response(200, ['ok' => true]);
}

$items = $data['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
    json_response(400, ['error' => 'No items']);
}

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO orders (status, created_at, total_amount) VALUES (?, NOW(), ?)')
        ->execute(['pending', 0]);
    $orderId = (int)$pdo->lastInsertId();

    $stmtItem = $pdo->prepare('INSERT INTO order_items (order_id, item_id, item_name, price, qty) VALUES (?, ?, ?, ?, ?)');
    $stmtMenu = $pdo->prepare('SELECT name, price FROM menu_items WHERE id = ?');

    $total = 0;
    foreach ($items as $item) {
        $itemId = isset($item['item_id']) ? (int)$item['item_id'] : 0;
        $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
        if ($itemId <= 0 || $qty <= 0) {
            continue;
        }

        $stmtMenu->execute([$itemId]);
        $menu = $stmtMenu->fetch();
        if (!$menu) {
            continue;
        }

        $name = (string)$menu['name'];
        $price = (int)$menu['price'];
        $stmtItem->execute([$orderId, $itemId, $name, $price, $qty]);
        $total += $price * $qty;
    }

    $pdo->prepare('UPDATE orders SET total_amount = ? WHERE id = ?')
        ->execute([$total, $orderId]);

    $pdo->commit();
    json_response(201, ['ok' => true, 'id' => $orderId]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(500, ['error' => 'Server error']);
}
