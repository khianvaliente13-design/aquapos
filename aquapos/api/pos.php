<?php
define('IS_API', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';
startCashierSession();
requireLogin();

header('Content-Type: application/json');
set_error_handler(function($errno, $errstr) {
    echo json_encode(['success' => false, 'message' => "PHP Error: $errstr"]);
    exit();
});

$conn   = getConnection();
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ── Get all active products ───────────────────────
    case 'get_products':
        $category = $_GET['category'] ?? '';
        $search   = $_GET['search']   ?? '';

        $sql    = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = 'active'";
        $params = []; $types = '';

        if ($category) { $sql .= " AND p.category_id = ?"; $params[] = $category; $types .= 'i'; }
        if ($search)   { $sql .= " AND p.name LIKE ?";     $params[] = "%$search%"; $types .= 's'; }
        $sql .= " ORDER BY p.name ASC";

        $stmt = safePrepare($conn, $sql);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        echo json_encode(['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

    // ── Get all categories ────────────────────────────
    case 'get_categories':
        $result = $conn->query("SELECT * FROM categories ORDER BY name");
        echo json_encode(['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
        break;

    // ── Process transaction ───────────────────────────
    case 'process_transaction':
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
            break;
        }

        $customer_id     = $data['customer_id']     ?? null;
        $items           = $data['items']            ?? [];
        $payment_method  = $data['payment_method']  ?? 'cash';
        $amount_paid     = floatval($data['amount_paid']     ?? 0);
        $discount        = floatval($data['discount']        ?? 0);
        $loyalty_discount= floatval($data['loyalty_discount']?? 0);
        $points_used     = intval($data['points_used']       ?? 0);
        $type            = $data['type']             ?? 'walk-in';

        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'No items in cart.']);
            break;
        }

        // Calculate totals
        $subtotal      = 0;
        foreach ($items as $item) {
            $subtotal += floatval($item['price']) * intval($item['quantity']);
        }
        $total        = max(0, $subtotal - $discount - $loyalty_discount);
        $change_amount= $amount_paid - $total;

        if ($amount_paid < $total) {
            echo json_encode(['success' => false, 'message' => 'Insufficient payment amount.']);
            break;
        }

        // Points earned: 1 point per ₱10 spent
        $points_earned = $customer_id ? floor($total / 10) : 0;

        $conn->begin_transaction();
        try {
            // Unique transaction code
            $code = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // Insert transaction
            $stmt = safePrepare($conn,
                "INSERT INTO transactions
                    (transaction_code, customer_id, cashier_id, type,
                     subtotal, discount, loyalty_discount, total,
                     amount_paid, change_amount, payment_method,
                     points_earned, points_used, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
            $stmt->bind_param("siisddddddsii",
                $code, $customer_id, $_SESSION['user_id'], $type,
                $subtotal, $discount, $loyalty_discount, $total,
                $amount_paid, $change_amount, $payment_method,
                $points_earned, $points_used);
            $stmt->execute();
            $transaction_id = $conn->insert_id;
            $stmt->close();

            // Insert items & update stock
            foreach ($items as $item) {
                $pid      = !empty($item['product_id']) ? intval($item['product_id']) : null;
                $qty      = intval($item['quantity']);
                $price    = floatval($item['price']);
                $item_sub = $price * $qty;
                $name     = $item['name'];
                $is_refill= ($item['type'] ?? 'product') === 'refill';

                $si = safePrepare($conn,
                    "INSERT INTO transaction_items (transaction_id, product_id, product_name, quantity, unit_price, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?)");
                $si->bind_param("iisidd", $transaction_id, $pid, $name, $qty, $price, $item_sub);
                $si->execute();
                $si->close();

                // Only deduct stock and log inventory for real products (not refills)
                if (!$is_refill && $pid) {
                    $conn->query("UPDATE products SET stock = stock - $qty WHERE id = $pid AND stock >= $qty");

                    $logtype = 'sale';
                    $il = safePrepare($conn,
                        "INSERT INTO inventory_logs (product_id, type, quantity, reference) VALUES (?, ?, ?, ?)");
                    $il->bind_param("isis", $pid, $logtype, $qty, $code);
                    $il->execute();
                    $il->close();
                }
            }

            // Update customer loyalty points
            if ($customer_id) {
                $conn->query("UPDATE customers SET
                    loyalty_points = loyalty_points + $points_earned - $points_used,
                    total_purchases = total_purchases + 1
                    WHERE id = $customer_id");

                // Log loyalty
                if ($points_earned > 0) {
                    $note = "Earned from $code";
                    $ltype = 'earned';
                    $ll = safePrepare($conn, "INSERT INTO loyalty_logs (customer_id, type, points, reference, notes) VALUES (?, ?, ?, ?, ?)");
                    $ll->bind_param("isiss", $customer_id, $ltype, $points_earned, $code, $note);
                    $ll->execute();
                    $ll->close();
                }
                if ($points_used > 0) {
                    $note2 = "Used on $code";
                    $ltype2 = 'used';
                    $ll2 = safePrepare($conn, "INSERT INTO loyalty_logs (customer_id, type, points, reference, notes) VALUES (?, ?, ?, ?, ?)");
                    $ll2->bind_param("isiss", $customer_id, $ltype2, $points_used, $code, $note2);
                    $ll2->execute();
                    $ll2->close();
                }
            }

            $conn->commit();

            echo json_encode([
                'success'        => true,
                'message'        => 'Transaction completed!',
                'transaction_id' => $transaction_id,
                'code'           => $code,
                'total'          => $total,
                'change'         => $change_amount,
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
        }
        break;

    // ── Get receipt data ──────────────────────────────
    case 'get_receipt':
        $id = intval($_GET['id'] ?? 0);
        $stmt = safePrepare($conn,
            "SELECT t.*, u.full_name as cashier_name,
                    COALESCE(c.name, 'Walk-in') as customer_name
             FROM transactions t
             LEFT JOIN users u ON t.cashier_id = u.id
             LEFT JOIN customers c ON t.customer_id = c.id
             WHERE t.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $si = safePrepare($conn, "SELECT * FROM transaction_items WHERE transaction_id = ?");
        $si->bind_param("i", $id);
        $si->execute();
        $items = $si->get_result()->fetch_all(MYSQLI_ASSOC);
        $si->close();

        echo json_encode(['success' => true, 'transaction' => $transaction, 'items' => $items]);
        break;

    // ── Price version for real-time sync ──────────────
    case 'price_version':
        $r = $conn->query("SELECT MAX(updated_at) as ts FROM products");
        echo json_encode(['success' => true, 'ts' => $r->fetch_assoc()['ts']]);
        break;

    // ── Get active refill options ─────────────────────
    case 'get_refills':
        $result = $conn->query("SELECT * FROM refills WHERE status='active' ORDER BY sort_order ASC, id ASC");
        echo json_encode(['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
        break;

    // ── Refill version for real-time sync ─────────────
    case 'refill_version':
        $r = $conn->query("SELECT MAX(updated_at) as ts FROM refills");
        echo json_encode(['success' => true, 'ts' => $r->fetch_assoc()['ts']]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
}

$conn->close();
?>
