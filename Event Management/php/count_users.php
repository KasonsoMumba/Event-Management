<?php
require_once 'db_connect.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $result['total'];
} catch(PDOException $e) {
    echo "0";
    error_log("Error counting users: " . $e->getMessage());
}
?>