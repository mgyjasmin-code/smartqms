<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$bhcName = getSetting($conn, 'bhc_name', 'Barangay Health Center');
$displayToken = trim((string) ($_GET['token'] ?? ''));
$statusUrl = APP_URL . '/modules/queue/status.php?token=' . rawurlencode($displayToken);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Queue Display -- SmartQMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&family=Inter:wght@400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/display.css">
</head>
<body>
  <div id="display-board" data-display-root data-status-url="<?= htmlspecialchars($statusUrl, ENT_QUOTES) ?>">
    <header class="board-header">
      <h1><?= htmlspecialchars($bhcName) ?></h1>
      <div class="datetime" id="live-clock"></div>
    </header>
    <main class="window-grid" id="window-grid"></main>
    <footer>
      <div class="ticker">Next: <span id="next-ticker">Loading...</span></div>
      <div class="last-updated" id="last-updated"></div>
    </footer>
  </div>
  <script src="<?= APP_URL ?>/assets/js/display.js"></script>
</body>
</html>
