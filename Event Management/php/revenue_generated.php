<?php
require_once 'db_connect.php';

try {
    $stmt = $pdo->query("SELECT SUM(Amount_Paid) as total FROM registrations WHERE Payment_Status = 'Paid'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "$" . number_format($result['total'] ?? 0, 2);
} catch(PDOException $e) {
    echo "$0.00";
    error_log("Error calculating revenue: " . $e->getMessage());
}
?>