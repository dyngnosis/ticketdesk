<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!isAdmin()) {
    http_response_code(403);
    exit('Access denied: agent access required.');
}

$ticket_id  = (int)($_POST['ticket_id'] ?? 0);
$body       = trim($_POST['body'] ?? '');
$is_internal = isset($_POST['is_internal']) ? 1 : 0;

if (!$ticket_id || strlen($body) === 0) {
    http_response_code(400);
    exit('Missing required fields.');
}

// Verify ticket exists
$stmt = $pdo->prepare("SELECT id FROM tickets WHERE id = ?");
$stmt->execute([$ticket_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    exit('Ticket not found.');
}

$pdo->prepare("
    INSERT INTO ticket_notes (ticket_id, agent_id, body, is_internal)
    VALUES (?, ?, ?, ?)
")->execute([$ticket_id, currentUserId(), $body, $is_internal]);

$pdo->prepare("UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")
    ->execute([$ticket_id]);

header("Location: /tickets.php?action=view&id={$ticket_id}&msg=note_added");
exit;
