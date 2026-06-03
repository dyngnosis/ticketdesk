<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$ticket_id = (int)($_POST['ticket_id'] ?? 0);
if (!$ticket_id) {
    http_response_code(400);
    exit('Invalid ticket ID');
}

// Verify ticket ownership or admin
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found');
}
if ($ticket['user_id'] !== currentUserId() && !isAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}

if (empty($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    header("Location: /tickets.php?action=view&id={$ticket_id}&error=upload_failed");
    exit;
}

$file      = $_FILES['attachment'];
$orig_name = basename($file['name']);
$size      = $file['size'];

// Max 10MB
if ($size > 10 * 1024 * 1024) {
    header("Location: /tickets.php?action=view&id={$ticket_id}&error=too_large");
    exit;
}

// Generate a unique stored filename
$ext         = pathinfo($orig_name, PATHINFO_EXTENSION);
$stored_name = uniqid('att_', true) . ($ext ? '.' . $ext : '');
$dest        = '/var/uploads/' . $stored_name;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    header("Location: /tickets.php?action=view&id={$ticket_id}&error=move_failed");
    exit;
}

$pdo->prepare("INSERT INTO attachments (ticket_id, user_id, original_name, stored_name, size) VALUES (?, ?, ?, ?, ?)")
    ->execute([$ticket_id, currentUserId(), $orig_name, $stored_name, $size]);

header("Location: /tickets.php?action=view&id={$ticket_id}&msg=uploaded");
exit;
