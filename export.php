<?php
require 'auth.php';
require 'db.php';
requireLogin();

$ticket_id = (int)$_GET['ticket_id'];

// Verify the ticket belongs to the current user (or user is admin)
$ticket = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
$ticket->execute([$ticket_id]);
$ticket = $ticket->fetch();
if (!$ticket || ($ticket['user_id'] !== $_SESSION['user_id'] && $_SESSION['role'] !== 'admin')) {
    http_response_code(403); exit('Forbidden');
}

// Gather attachments for this ticket
$stmt = $pdo->prepare("SELECT * FROM attachments WHERE ticket_id = ?");
$stmt->execute([$ticket_id]);
$attachments = $stmt->fetchAll();

if (empty($attachments)) {
    exit('No attachments to export.');
}

$zip_path = sys_get_temp_dir() . "/ticket_{$ticket_id}_export.zip";

// Build the file list for zip. Use basename() to prevent path traversal.
$file_list = '';
foreach ($attachments as $att) {
    $safe_name = basename($att['stored_name']);
    $full_path = '/var/uploads/' . $safe_name;
    $file_list .= ' ' . $full_path;
}

$cmd = "zip -j {$zip_path}{$file_list}";
system($cmd);

header('Content-Type: application/zip');
header("Content-Disposition: attachment; filename=ticket_{$ticket_id}.zip");
readfile($zip_path);
@unlink($zip_path);
