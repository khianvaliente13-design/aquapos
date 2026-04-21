<?php
define('IS_API', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireRole('admin');

header('Content-Type: application/json');
$conn = getConnection();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── Get all products (with category) ─────────────
    case 'get_products':
        $sql = "SELECT p.*, c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                ORDER BY c.name, p.name";
        $result = $conn->query($sql);
        echo json_encode(['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
        break;

    // ── Update product price ──────────────────────────
    case 'update_price':
        $id    = intval($_POST['id'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        if ($id <= 0 || $price < 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
            break;
        }
        $stmt = safePrepare($conn, "UPDATE products SET price = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("di", $price, $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Price updated successfully.']);
        break;

    // ── Update full product ───────────────────────────
    case 'update_product':
        $id     = intval($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $price  = floatval($_POST['price'] ?? 0);
        $stock  = intval($_POST['stock'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (!$id || !$name) { echo json_encode(['success'=>false,'message'=>'Invalid data.']); break; }

        $stmt = safePrepare($conn, "UPDATE products SET name=?, price=?, stock=?, status=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("sdisi", $name, $price, $stock, $status, $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Product updated.']);
        break;

    // ── Add new product ───────────────────────────────
    case 'add_product':
        $cat_id = intval($_POST['category_id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $price  = floatval($_POST['price'] ?? 0);
        $stock  = intval($_POST['stock'] ?? 0);
        $unit   = trim($_POST['unit'] ?? 'piece');

        if (!$name || $price < 0) { echo json_encode(['success'=>false,'message'=>'Invalid data.']); break; }

        $stmt = safePrepare($conn, "INSERT INTO products (category_id, name, price, stock, unit) VALUES (?,?,?,?,?)");
        $stmt->bind_param("isdis", $cat_id, $name, $price, $stock, $unit);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Product added.', 'id' => $conn->insert_id]);
        break;

    // ── Toggle product status ─────────────────────────
    case 'toggle_status':
        $id = intval($_POST['id'] ?? 0);
        $stmt = safePrepare($conn, "UPDATE products SET status = IF(status='active','inactive','active'), updated_at=NOW() WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $r = $conn->query("SELECT status FROM products WHERE id=$id")->fetch_assoc();
        echo json_encode(['success' => true, 'status' => $r['status']]);
        break;

    // ── Get categories ────────────────────────────────
    case 'get_categories':
        $result = $conn->query("SELECT * FROM categories ORDER BY name");
        echo json_encode(['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
        break;

    // ── Get dashboard stats ───────────────────────────
    // ── Delete transaction ────────────────────────────
    case 'delete_transaction':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); break; }

        // Delete items first (FK constraint), then transaction
        $di = safePrepare($conn, "DELETE FROM transaction_items WHERE transaction_id = ?");
        $di->bind_param("i", $id);
        $di->execute();
        $di->close();

        $dt = safePrepare($conn, "DELETE FROM transactions WHERE id = ?");
        $dt->bind_param("i", $id);
        $dt->execute();
        $dt->close();

        echo json_encode(['success' => true]);
        break;

    // ── Get transactions (with filters) ──────────────
    case 'get_transactions':
        $page     = max(1, intval($_GET['page'] ?? 1));
        $limit    = 20;
        $offset   = ($page - 1) * $limit;
        $search   = trim($_GET['search'] ?? '');
        $date_from= trim($_GET['date_from'] ?? '');
        $date_to  = trim($_GET['date_to'] ?? '');
        $status   = trim($_GET['status'] ?? '');

        $where = ["1=1"];
        $params = [];
        $types  = "";

        if ($search) {
            $like = "%$search%";
            $where[] = "(t.transaction_code LIKE ? OR c.name LIKE ? OR u.full_name LIKE ?)";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $types .= "sss";
        }
        if ($date_from) { $where[] = "DATE(t.created_at) >= ?"; $params[] = $date_from; $types .= "s"; }
        if ($date_to)   { $where[] = "DATE(t.created_at) <= ?"; $params[] = $date_to;   $types .= "s"; }
        if ($status)    { $where[] = "t.status = ?";             $params[] = $status;    $types .= "s"; }

        $whereStr = implode(" AND ", $where);

        // Total count
        $countSql = "SELECT COUNT(*) as c FROM transactions t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.cashier_id = u.id
            WHERE $whereStr";
        $cStmt = safePrepare($conn, $countSql);
        if ($types) $cStmt->bind_param($types, ...$params);
        $cStmt->execute();
        $total = $cStmt->get_result()->fetch_assoc()['c'];
        $cStmt->close();

        // Rows
        $sql = "SELECT t.*, 
                COALESCE(c.name, 'Walk-in') as customer_name,
                u.full_name as cashier_name
            FROM transactions t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.cashier_id = u.id
            WHERE $whereStr
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?";
        $params[] = $limit; $params[] = $offset;
        $types .= "ii";

        $stmt = safePrepare($conn, $sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Attach items to each transaction
        foreach ($rows as &$row) {
            $is = safePrepare($conn, "SELECT * FROM transaction_items WHERE transaction_id = ?");
            $is->bind_param("i", $row['id']);
            $is->execute();
            $row['items'] = $is->get_result()->fetch_all(MYSQLI_ASSOC);
            $is->close();
        }

        // Summary totals for filtered set
        $sumSql = "SELECT 
                COUNT(*) as count,
                SUM(t.total) as revenue,
                SUM(t.discount + t.loyalty_discount) as discounts
            FROM transactions t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.cashier_id = u.id
            WHERE $whereStr AND t.status = 'completed'";
        $sumParams = array_slice($params, 0, -2); // remove limit/offset
        $sumTypes  = substr($types, 0, -2);
        $sStmt = safePrepare($conn, $sumSql);
        if ($sumTypes) $sStmt->bind_param($sumTypes, ...$sumParams);
        $sStmt->execute();
        $summary = $sStmt->get_result()->fetch_assoc();
        $sStmt->close();

        echo json_encode([
            'success' => true,
            'data'    => $rows,
            'total'   => (int)$total,
            'pages'   => (int)ceil($total / $limit),
            'summary' => $summary,
        ]);
        break;

    // ── Get single transaction detail ─────────────────
    case 'get_transaction':
        $id = intval($_GET['id'] ?? 0);
        $stmt = safePrepare($conn, "SELECT t.*, 
            COALESCE(c.name, 'Walk-in') as customer_name,
            c.phone as customer_phone,
            u.full_name as cashier_name
            FROM transactions t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.cashier_id = u.id
            WHERE t.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $tx = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$tx) { echo json_encode(['success' => false, 'message' => 'Not found']); break; }

        $is = safePrepare($conn, "SELECT * FROM transaction_items WHERE transaction_id = ?");
        $is->bind_param("i", $id);
        $is->execute();
        $tx['items'] = $is->get_result()->fetch_all(MYSQLI_ASSOC);
        $is->close();

        echo json_encode(['success' => true, 'data' => $tx]);
        break;

    case 'get_stats':
        $today = date('Y-m-d');
        $stats = [];

        // Today's sales
        $r = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM transactions WHERE DATE(created_at)='$today' AND status='completed'");
        $row = $r->fetch_assoc();
        $stats['today_sales'] = $row['cnt'];
        $stats['today_revenue'] = $row['total'];

        // This month
        $month = date('Y-m');
        $r = $conn->query("SELECT COALESCE(SUM(total),0) as total FROM transactions WHERE DATE_FORMAT(created_at,'%Y-%m')='$month' AND status='completed'");
        $stats['month_revenue'] = $r->fetch_assoc()['total'];

        // Total products
        $r = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE status='active'");
        $stats['total_products'] = $r->fetch_assoc()['cnt'];

        // Low stock
        $r = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE stock <= 10 AND status='active'");
        $stats['low_stock'] = $r->fetch_assoc()['cnt'];

        // Recent transactions
        $r = $conn->query("SELECT t.*, COALESCE(c.name,'Walk-in') as customer_name, u.full_name as cashier
                           FROM transactions t
                           LEFT JOIN customers c ON t.customer_id=c.id
                           LEFT JOIN users u ON t.cashier_id=u.id
                           ORDER BY t.created_at DESC LIMIT 8");
        $stats['recent'] = $r->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success' => true, 'data' => $stats]);
        break;

    // ── Timestamp for real-time sync ──────────────────
    case 'price_version':
        $r = $conn->query("SELECT MAX(updated_at) as ts FROM products");
        echo json_encode(['success' => true, 'ts' => $r->fetch_assoc()['ts']]);
        break;

    // ── Get all customers ─────────────────────────────
    case 'get_customers':
        $result = $conn->query("SELECT * FROM customers ORDER BY created_at DESC");
        $customers = $result->fetch_all(MYSQLI_ASSOC);
        // Stats
        $total  = count($customers);
        $active = count(array_filter($customers, fn($c) => $c['status'] === 'active'));
        $pts    = array_sum(array_column($customers, 'loyalty_points'));
        // Remove passwords
        foreach ($customers as &$c) unset($c['password']);
        echo json_encode(['success' => true, 'data' => $customers, 'stats' => ['total'=>$total,'active'=>$active,'total_points'=>$pts]]);
        break;

    // ── Toggle customer status ────────────────────────
    case 'toggle_customer':
        $id = intval($_POST['id'] ?? 0);
        $conn->query("UPDATE customers SET status = IF(status='active','inactive','active') WHERE id=$id");
        $r = $conn->query("SELECT status FROM customers WHERE id=$id")->fetch_assoc();
        echo json_encode(['success' => true, 'status' => $r['status']]);
        break;

    // ── Add customer (admin only) ─────────────────────
    case 'add_customer':
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $email   = trim($_POST['email'] ?? '');

        if (!$name || !$phone || !$address) {
            echo json_encode(['success' => false, 'message' => 'Name, phone, and address are required.']);
            break;
        }

        // Check duplicate phone
        $chk = safePrepare($conn, "SELECT id FROM customers WHERE phone = ?");
        $chk->bind_param("s", $phone);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This phone number is already registered.']);
            $chk->close();
            break;
        }
        $chk->close();

        $ins = safePrepare($conn, "INSERT INTO customers (name, phone, address, email) VALUES (?, ?, ?, ?)");
        $ins->bind_param("ssss", $name, $phone, $address, $email);

        if ($ins->execute()) {
            echo json_encode(['success' => true, 'message' => 'Customer added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add customer: ' . $ins->error]);
        }
        $ins->close();
        break;

    // ── Edit customer ─────────────────────────────────
    case 'edit_customer':
        $id      = intval($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $email   = trim($_POST['email'] ?? '');

        if (!$id || !$name || !$address) {
            echo json_encode(['success' => false, 'message' => 'Name and address are required.']);
            break;
        }

        $stmt = safePrepare($conn, "UPDATE customers SET name=?, address=?, email=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("sssi", $name, $address, $email, $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Customer updated.']);
        break;
    // ── Adjust loyalty points ─────────────────────────
    case 'adjust_points':
        $id   = intval($_POST['id'] ?? 0);
        $pts  = intval($_POST['points'] ?? 0);
        $type = $_POST['type'] ?? 'add'; // 'add' or 'subtract'

        if (!$id || $pts <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid input.']);
            break;
        }

        // Get current points
        $cur = safePrepare($conn, "SELECT loyalty_points FROM customers WHERE id=?");
        $cur->bind_param("i", $id);
        $cur->execute();
        $row = $cur->get_result()->fetch_assoc();
        $cur->close();

        $current = intval($row['loyalty_points'] ?? 0);
        $new_pts = $type === 'add' ? $current + $pts : max(0, $current - $pts);

        $upd = safePrepare($conn, "UPDATE customers SET loyalty_points=? WHERE id=?");
        $upd->bind_param("ii", $new_pts, $id);
        $upd->execute();
        $upd->close();

        // Log the adjustment
        $log_type = $type === 'add' ? 'earned' : 'used';
        $note = 'Admin adjustment';
        $log = safePrepare($conn, "INSERT INTO loyalty_logs (customer_id, type, points, notes) VALUES (?,?,?,?)");
        $log->bind_param("iiss", $id, $log_type, $pts, $note);
        $log->execute();
        $log->close();

        echo json_encode(['success' => true, 'new_points' => $new_pts]);
        break;

    // ── Get customer transactions ─────────────────────
    case 'get_customer_transactions':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'data' => []]); break; }

        $stmt = safePrepare($conn, "SELECT t.id, t.transaction_code, t.total, t.points_earned, t.created_at
            FROM transactions t
            WHERE t.customer_id = ? AND t.status = 'completed'
            ORDER BY t.created_at DESC LIMIT 10");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $txs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $txs]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
$conn->close();
?>
