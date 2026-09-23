<?php

// Test database connection
// Run this file in a browser or CLI to verify PDO connection

require_once __DIR__ . '/database.php';

echo "<h2>Testing Database Connection</h2>";

$pdo = getPDO();

if ($pdo) {
    echo "<p>Database connection successful!</p>";
    echo "<p>Server Info: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "</p>";
    echo "<p>Client Info: " . $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION) . "</p>";

    // Test a simple query
    try {
        $stmt = $pdo->query("SELECT DATABASE()");
        $dbName = $stmt->fetchColumn();
        echo "<p>Connected to database: $dbName</p>";
    } catch (PDOException $e) {
        echo "<p>Query failed: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>Database connection failed.</p>";
}

?>