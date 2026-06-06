<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

$ticket_id = (int)($_GET['ticket_id'] ?? 0);

if ($ticket_id <= 0) {
    header('Location: /tickets.php');
    exit;
}

// Fetch the ticket summary for display context.
$tStmt = $pdo->prepare("
    SELECT t.id, t.title, t.status, t.priority, u.username AS owner_name
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
");
$tStmt->execute([$ticket_id]);
$ticket = $tStmt->fetch();

if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found.');
}

// Fetch full audit log for this ticket, joined with agent username.
$auditStmt = $pdo->prepare("
    SELECT a.id, a.action, a.old_value, a.new_value, a.created_at,
           u.username AS agent_name
    FROM ticket_audit a
    LEFT JOIN users u ON a.agent_id = u.id
    WHERE a.ticket_id = ?
    ORDER BY a.created_at ASC
");
$auditStmt->execute([$ticket_id]);
$entries = $auditStmt->fetchAll();

// Human-readable action labels.
$actionLabels = [
    'ticket_created'    => 'Ticket opened',
    'status_change'     => 'Status changed',
    'priority_change'   => 'Priority changed',
    'assignment_change' => 'Assignment changed',
    'subject_change'    => 'Subject edited',
];

$pageTitle = "Audit Trail — Ticket #$ticket_id";
require __DIR__ . '/templates/header.php';
?>

<div class="mb-3">
    <a href="/tickets.php?action=view&id=<?= $ticket_id ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Ticket
    </a>
    <?php if (isAdmin()): ?>
    <a href="/admin.php?section=tickets" class="btn btn-sm btn-outline-dark ms-1">
        <i class="bi bi-shield-lock"></i> Admin Panel
    </a>
    <?php endif; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-clock-history"></i> Audit Trail</h4>
        <small class="text-muted">
            Ticket #<?= $ticket_id ?> &mdash; <?= htmlspecialchars($ticket['title']) ?>
            &mdash; Owner: <?= htmlspecialchars($ticket['owner_name']) ?>
        </small>
    </div>
    <div>
        <span class="badge bg-secondary"><?= count($entries) ?> event<?= count($entries) !== 1 ? 's' : '' ?></span>
    </div>
</div>

<?php if (empty($entries)): ?>
    <div class="card text-center py-5">
        <div class="card-body">
            <i class="bi bi-journal-x display-4 text-muted"></i>
            <p class="mt-3 text-muted">No audit entries recorded for this ticket yet.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Timestamp</th>
                        <th>Agent</th>
                        <th>Event</th>
                        <th>Previous value</th>
                        <th>New value</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $i => $entry): ?>
                    <tr>
                        <td class="text-muted small"><?= $i + 1 ?></td>
                        <td><small><?= htmlspecialchars($entry['created_at']) ?></small></td>
                        <td><?= htmlspecialchars($entry['agent_name'] ?? '—') ?></td>
                        <td>
                            <span class="badge bg-dark">
                                <?= htmlspecialchars($actionLabels[$entry['action']] ?? $entry['action']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($entry['old_value'] !== '' && $entry['old_value'] !== null): ?>
                                <span class="text-muted"><?= htmlspecialchars($entry['old_value']) ?></span>
                            <?php else: ?>
                                <span class="text-muted fst-italic">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($entry['new_value']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
