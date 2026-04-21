<?php
define('IS_API', true);
require_once '../includes/config.php';

header('Content-Type: application/json');
set_error_handler(function($errno, $errstr) {
    echo json_encode(['success' => false, 'message' => "PHP Error: $errstr"]);
    exit();
});

if (session_status() === PHP_SESSION_NONE) session_start();

$conn   = getConnection();
$action = $_REQUEST['action'] ?? '';

define('POINTS_VALUE',   0.5);
define('MAX_POINTS_PCT', 0.20);

switch ($action) {

    // ── SEARCH (used by POS cashier) ──────────────────
    case 'search':
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { echo json_encode(['success' => true, 'data' => []]); break; }
        $like = "%$q%";
        $stmt = safePrepare($conn, "SELECT id,name,phone,address,loyalty_points FROM customers WHERE (name LIKE ? OR phone LIKE ?) AND status='active' LIMIT 6");
        $stmt->bind_param("ss", $like, $like);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $results]);
        break;

    // ── CALCULATE LOYALTY DISCOUNT (used by POS) ──────
    case 'calc_loyalty':
        $cid    = intval($_GET['customer_id'] ?? 0);
        $total  = floatval($_GET['total'] ?? 0);
        $points = intval($_GET['points_to_use'] ?? 0);

        if (!$cid) { echo json_encode(['success' => false]); break; }

        $stmt = safePrepare($conn, "SELECT loyalty_points FROM customers WHERE id=?");
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $c = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $available    = intval($c['loyalty_points'] ?? 0);
        $points       = min($points, $available);
        $max_discount = $total * MAX_POINTS_PCT;
        $discount     = min($points * POINTS_VALUE, $max_discount);
        $points_used  = (int)ceil($discount / POINTS_VALUE);

        echo json_encode([
            'success'      => true,
            'available'    => $available,
            'points_used'  => $points_used,
            'discount'     => round($discount, 2),
            'points_value' => POINTS_VALUE,
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

$conn->close();
