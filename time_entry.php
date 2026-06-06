<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list time entries for a ticket ──────────────────────────────────────
if ($method === 'GET') {
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    if (!$ticketId) {
        http_response_code(400);
        echo json_encode(['error' => 'ticket_id required']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT te.*, u.username AS agent_name
        FROM time_entries te
        JOIN users u ON te.agent_id = u.id
        WHERE te.ticket_id = ? AND te.agent_id = ?
        ORDER BY te.started_at DESC
    ");
    $stmt->execute([$ticketId, currentUserId()]);
    $entries = $stmt->fetchAll();

    $totalMinutes = array_sum(array_column($entries, 'duration_minutes'));

    echo json_encode([
        'entries'       => $entries,
        'total_minutes' => $totalMinutes,
    ]);
    exit;
}

// ── POST: create or update ───────────────────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        // fall back to form POST
        $input = $_POST;
    }

    $entryId = isset($input['entry_id']) ? (int)$input['entry_id'] : 0;

    // UPDATE an existing entry
    if ($entryId > 0) {
        $duration = isset($input['duration_minutes']) ? (int)$input['duration_minutes'] : null;
        $note     = isset($input['note']) ? trim($input['note']) : null;

        if ($duration === null || $duration < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'duration_minutes must be a non-negative integer']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE time_entries
            SET duration_minutes = ?,
                note             = ?,
                updated_at       = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$duration, $note, $entryId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Entry not found']);
            exit;
        }

        echo json_encode(['success' => true, 'entry_id' => $entryId]);
        exit;
    }

    // CREATE a new entry
    $ticketId  = (int)($input['ticket_id'] ?? 0);
    $startedAt = trim($input['started_at'] ?? date('Y-m-d H:i:s'));
    $duration  = isset($input['duration_minutes']) ? (int)$input['duration_minutes'] : 0;
    $note      = trim($input['note'] ?? '');

    if (!$ticketId) {
        http_response_code(400);
        echo json_encode(['error' => 'ticket_id required']);
        exit;
    }

    // Validate that the ticket exists and the user has access to it
    $chk = $pdo->prepare("SELECT id FROM tickets WHERE id = ?");
    $chk->execute([$ticketId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Ticket not found']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO time_entries (ticket_id, agent_id, started_at, duration_minutes, note)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$ticketId, currentUserId(), $startedAt, $duration ?: null, $note ?: null]);
    $newId = (int)$pdo->lastInsertId();

    http_response_code(201);
    echo json_encode(['success' => true, 'entry_id' => $newId]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
