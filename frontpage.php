<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Use central DB connection and future-proof for audit logging
require_once __DIR__ . '/db_connect.php';

// Fetch audit trail logs (limit to last 100 for performance)
$stmt = $pdo->prepare("SELECT * FROM audit_trail ORDER BY created_at DESC, id DESC LIMIT 100");
$stmt->execute();
$audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$username = $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Audit Trail</title>
    <link rel="stylesheet" href="dashboard.css" />
    <style>
  html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    overflow-y: auto;
    font-family: sans-serif;
    background: #f8f9fa;
  }

  .audit-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
  }

  .audit-table th,
  .audit-table td {
    border: 1px solid #ddd;
    padding: 8px;
  }

  .audit-table th {
    background: #007bff;
    color: #fff;
  }

  .audit-table tr:nth-child(even) {
    background: #f9f9f9;
  }

  .audit-table tr:hover {
    background: #f1f7ff;
  }

  .top-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: var(--surface-color, #fff);
    padding: 1rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border-color, #dee2e6);
    z-index: 999;
  }

  .top-bar h1 {
    font-size: 1.4rem;
    margin: 0;
  }

  .dropdown {
    position: relative;
    display: inline-block;
  }

  .dropdown-toggle {
    background: var(--primary-color, #007bff);
    color: #fff;
    border: none;
    padding: 0.5em 1.1em;
    border-radius: 0.5em;
    font-size: 1em;
    cursor: pointer;
  }

  .dropdown-toggle:hover,
  .dropdown-toggle:focus {
    background: var(--accent-color, #17a2b8);
  }

  .dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    background: var(--surface-color, #fff);
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
    border: 1px solid var(--border-color, #dee2e6);
    border-radius: 0.5em;
    z-index: 1001;
    margin-top: 0.5em;
    min-width: 170px;
  }

  .dropdown-menu.show {
    display: block;
  }

  .dropdown-menu a {
    display: block;
    padding: 0.8em 1.2em;
    color: var(--text-color, #212529);
    text-decoration: none;
    border-bottom: 1px solid var(--border-color, #dee2e6);
    background: none;
    font-size: 1em;
  }

  .dropdown-menu a:last-child {
    border-bottom: none;
  }

  .dropdown-menu a:hover {
    background: var(--bg-color, #f4f6f9);
    color: var(--primary-color, #007bff);
  }

  .main-content {
    margin-top: 90px;
    padding: 2rem;
    box-sizing: border-box;
  }

  .content-area {
    max-width: 1000px;
    margin: 0 auto;
  }

  @media (max-width: 900px) {
    .main-content {
      padding: 1rem;
    }
  }
</style>
</head>
<body>
  <header class="top-bar">
    <h1>Audit Trail</h1>
    <div class="dropdown">
      <button class="dropdown-toggle" id="dropdownBtn">Audit Trail ▾</button>
      <div class="dropdown-menu" id="dropdownMenu">
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
      </div>
    </div>
  </header>
  <main class="main-content">
    <div class="content-area">
      <h2 style="text-align:center; margin-bottom:1.5rem;">Recent User Actions</h2>
      <table class="audit-table">
        <thead>
          <tr>
            <th>Date/Time</th>
            <th>User</th>
            <th>Action</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($audit_logs)): ?>
          <tr><td colspan="4" style="text-align:center;">No audit logs found.</td></tr>
        <?php else: ?>
          <?php foreach ($audit_logs as $log): ?>
            <tr>
              <td><?= htmlspecialchars($log['created_at']) ?></td>
              <td><?= htmlspecialchars($log['username'] ?? $log['user_id']) ?></td>
              <td><?= htmlspecialchars($log['action']) ?></td>
              <td><?= nl2br(htmlspecialchars($log['description'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
  <script>
    // Dropdown menu logic
    document.addEventListener('DOMContentLoaded', function() {
      const btn = document.getElementById('dropdownBtn');
      const menu = document.getElementById('dropdownMenu');
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        menu.classList.toggle('show');
      });
      document.addEventListener('click', function(e) {
        if (!menu.contains(e.target) && e.target !== btn) {
          menu.classList.remove('show');
        }
      });
    });
  </script>
</body>
</html>