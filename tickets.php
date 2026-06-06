<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$msg    = $_GET['msg']    ?? '';
$error  = $_GET['error']  ?? '';

// ── Helper: priority badge ──────────────────────────────────────────────────
function priorityBadge(string $p): string {
    $map = [
        'low'    => 'success',
        'medium' => 'warning text-dark',
        'high'   => 'danger',
        'urgent' => 'purple',
    ];
    $cls = $map[$p] ?? 'secondary';
    return "<span class=\"badge bg-{$cls}\">" . htmlspecialchars(ucfirst($p)) . "</span>";
}

function statusBadge(string $s): string {
    $map = [
        'open'        => 'primary',
        'in-progress' => 'warning text-dark',
        'resolved'    => 'success',
        'closed'      => 'secondary',
    ];
    $cls = $map[$s] ?? 'secondary';
    return "<span class=\"badge bg-{$cls}\">" . htmlspecialchars(ucwords(str_replace('-', ' ', $s))) . "</span>";
}

// ── CREATE TICKET ────────────────────────────────────────────────────────────
$formError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority']         ?? 'medium';
    $category    = trim($_POST['category']    ?? '');

    $allowed_priorities = ['low', 'medium', 'high', 'urgent'];
    if (!in_array($priority, $allowed_priorities)) $priority = 'medium';

    if (strlen($title) < 3) {
        $formError = 'Title must be at least 3 characters.';
        $action = 'new';
    } elseif (strlen($description) < 10) {
        $formError = 'Description must be at least 10 characters.';
        $action = 'new';
    } else {
        $pdo->prepare("INSERT INTO tickets (user_id, title, description, priority, category) VALUES (?, ?, ?, ?, ?)")
            ->execute([currentUserId(), $title, $description, $priority, $category]);
        $newId = $pdo->lastInsertId();
        header("Location: /tickets.php?action=view&id={$newId}&msg=created");
        exit;
    }
}

// ── ADD COMMENT ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $tid  = (int)($_POST['ticket_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');

    if ($tid && strlen($body) > 0) {
        // Ensure access
        $chk = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $chk->execute([$tid]);
        $chkTicket = $chk->fetch();
        if ($chkTicket && ($chkTicket['user_id'] === currentUserId() || isAdmin())) {
            $pdo->prepare("INSERT INTO comments (ticket_id, user_id, body) VALUES (?, ?, ?)")
                ->execute([$tid, currentUserId(), $body]);
            // Update ticket timestamp
            $pdo->prepare("UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$tid]);
        }
    }
    header("Location: /tickets.php?action=view&id={$tid}&msg=commented");
    exit;
}

// ── VIEWS ────────────────────────────────────────────────────────────────────

if ($action === 'list') {
    $pageTitle = 'My Tickets';
    require __DIR__ . '/templates/header.php';

    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([currentUserId()]);
    $tickets = $stmt->fetchAll();
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-ticket-perforated"></i> My Tickets</h2>
        <a href="/tickets.php?action=new" class="btn btn-dark"><i class="bi bi-plus-circle"></i> New Ticket</a>
    </div>

    <?php if ($msg === 'created'): ?>
        <div class="alert alert-success alert-dismissible fade show">Ticket created successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if (empty($tickets)): ?>
        <div class="card text-center py-5">
            <div class="card-body">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <p class="mt-3 text-muted">You have no tickets yet.</p>
                <a href="/tickets.php?action=new" class="btn btn-dark">Submit your first ticket</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
        <?php foreach ($tickets as $t): ?>
            <div class="col-12">
                <div class="card ticket-card priority-<?= htmlspecialchars($t['priority']) ?>">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1">
                                <a href="/tickets.php?action=view&id=<?= $t['id'] ?>" class="text-decoration-none text-dark">
                                    #<?= $t['id'] ?> — <?= htmlspecialchars($t['title']) ?>
                                </a>
                            </h5>
                            <small class="text-muted">
                                <?= htmlspecialchars($t['category'] ?: 'Uncategorized') ?> &middot;
                                Opened <?= htmlspecialchars($t['created_at']) ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <?= priorityBadge($t['priority']) ?>
                            <?= statusBadge($t['status']) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/templates/footer.php';

} elseif ($action === 'new') {
    $pageTitle = 'New Ticket';
    require __DIR__ . '/templates/header.php';
    ?>
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Submit New Ticket</h5>
                </div>
                <div class="card-body">
                    <?php if ($formError): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required minlength="3"
                                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="5" required minlength="10"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select">
                                    <?php foreach (['low','medium','high','urgent'] as $p): ?>
                                    <option value="<?= $p ?>" <?= (($_POST['priority'] ?? 'medium') === $p) ? 'selected' : '' ?>>
                                        <?= ucfirst($p) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">Select category...</option>
                                    <?php foreach (['Hardware','Software','Network','Account','Email','Other'] as $cat): ?>
                                    <option value="<?= $cat ?>" <?= (($_POST['category'] ?? '') === $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="create_ticket" class="btn btn-dark w-100">
                            <i class="bi bi-send"></i> Submit Ticket
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php require __DIR__ . '/templates/footer.php';

} elseif ($action === 'view') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT t.*, u.username AS owner_name, a.username AS agent_name
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN users a ON t.assigned_to = a.id
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();

    if (!$ticket || ($ticket['user_id'] !== currentUserId() && !isAdmin())) {
        http_response_code(403);
        exit('Access denied.');
    }

    // Fetch comments
    $cStmt = $pdo->prepare("
        SELECT c.*, u.username, u.role FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.ticket_id = ?
        ORDER BY c.created_at ASC
    ");
    $cStmt->execute([$id]);
    $comments = $cStmt->fetchAll();

    // Fetch attachments
    $aStmt = $pdo->prepare("SELECT * FROM attachments WHERE ticket_id = ?");
    $aStmt->execute([$id]);
    $attachments = $aStmt->fetchAll();

    // Fetch time entries for this ticket (current user only)
    $teStmt = $pdo->prepare("
        SELECT te.*, u.username AS agent_name
        FROM time_entries te
        JOIN users u ON te.agent_id = u.id
        WHERE te.ticket_id = ? AND te.agent_id = ?
        ORDER BY te.started_at DESC
    ");
    $teStmt->execute([$id, currentUserId()]);
    $timeEntries  = $teStmt->fetchAll();
    $totalMinutes = array_sum(array_column($timeEntries, 'duration_minutes'));

    $pageTitle = "Ticket #$id";
    require __DIR__ . '/templates/header.php';
    ?>
    <div class="mb-3">
        <a href="/tickets.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Tickets</a>
    </div>

    <?php if ($msg === 'uploaded'): ?>
        <div class="alert alert-success alert-dismissible fade show">File uploaded successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($msg === 'commented'): ?>
        <div class="alert alert-success alert-dismissible fade show">Comment added. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($error === 'too_large'): ?>
        <div class="alert alert-danger alert-dismissible fade show">File too large (max 10MB). <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($error === 'upload_failed'): ?>
        <div class="alert alert-danger alert-dismissible fade show">Upload failed. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-8">
            <!-- Ticket detail -->
            <div class="card shadow-sm mb-4 priority-<?= htmlspecialchars($ticket['priority']) ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">#<?= $ticket['id'] ?> — <?= htmlspecialchars($ticket['title']) ?></h5>
                    <div><?= priorityBadge($ticket['priority']) ?> <?= statusBadge($ticket['status']) ?></div>
                </div>
                <div class="card-body">
                    <p class="mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($ticket['description']) ?></p>
                </div>
            </div>

            <!-- Comments -->
            <h5><i class="bi bi-chat-left-text"></i> Comments (<?= count($comments) ?>)</h5>
            <?php if (empty($comments)): ?>
                <p class="text-muted">No comments yet.</p>
            <?php else: ?>
                <?php foreach ($comments as $c): ?>
                <div class="card mb-2 <?= $c['role'] === 'admin' ? 'border-warning' : '' ?>">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between">
                            <strong><?= htmlspecialchars($c['username']) ?>
                                <?php if ($c['role'] === 'admin'): ?><span class="badge bg-warning text-dark">Support</span><?php endif; ?>
                            </strong>
                            <small class="text-muted"><?= htmlspecialchars($c['created_at']) ?></small>
                        </div>
                        <p class="mb-0 mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($c['body']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Add comment -->
            <div class="card mt-3 shadow-sm">
                <div class="card-header">Add a Comment</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="ticket_id" value="<?= $id ?>">
                        <div class="mb-3">
                            <textarea name="body" class="form-control" rows="3" placeholder="Write your comment..." required></textarea>
                        </div>
                        <button type="submit" name="add_comment" class="btn btn-dark btn-sm">
                            <i class="bi bi-send"></i> Post Comment
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── Time Tracking ──────────────────────────────────────────── -->
            <div class="card mt-4 shadow-sm" id="time-tracking-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-stopwatch"></i> Time Tracking</span>
                    <span class="badge bg-secondary" id="total-time-badge">
                        <?php
                        $totalHours = floor($totalMinutes / 60);
                        $totalMins  = $totalMinutes % 60;
                        echo $totalHours > 0
                            ? "{$totalHours}h {$totalMins}m total"
                            : "{$totalMins}m total";
                        ?>
                    </span>
                </div>
                <div class="card-body">

                    <!-- Timer controls -->
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <button class="btn btn-success btn-sm" id="btn-start-timer" onclick="startTimer()">
                            <i class="bi bi-play-fill"></i> Start Timer
                        </button>
                        <button class="btn btn-danger btn-sm d-none" id="btn-stop-timer" onclick="stopTimer()">
                            <i class="bi bi-stop-fill"></i> Stop Timer
                        </button>
                        <span class="text-muted small" id="timer-display"></span>
                    </div>

                    <!-- Manual entry form -->
                    <form id="manual-entry-form" class="border-top pt-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label form-label-sm mb-1">Duration (min)</label>
                                <input type="number" id="entry-duration" class="form-control form-control-sm"
                                       min="1" placeholder="e.g. 30" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form-label-sm mb-1">Note</label>
                                <input type="text" id="entry-note" class="form-control form-control-sm"
                                       placeholder="What did you work on?">
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-dark btn-sm w-100" onclick="logManualEntry()">
                                    <i class="bi bi-plus-circle"></i> Log Time
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Time log list -->
                    <div class="mt-3" id="time-log-list">
                    <?php if (empty($timeEntries)): ?>
                        <p class="text-muted small mb-0" id="no-entries-msg">No time logged yet.</p>
                    <?php else: ?>
                        <?php foreach ($timeEntries as $te): ?>
                        <div class="d-flex justify-content-between align-items-center border-top py-2 time-entry-row"
                             data-entry-id="<?= (int)$te['id'] ?>">
                            <div>
                                <span class="fw-semibold small">
                                    <?= $te['duration_minutes'] !== null
                                        ? floor($te['duration_minutes'] / 60) . 'h ' . ($te['duration_minutes'] % 60) . 'm'
                                        : '—' ?>
                                </span>
                                <?php if (!empty($te['note'])): ?>
                                    <span class="text-muted small ms-2">— <?= htmlspecialchars($te['note']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted"><?= htmlspecialchars(substr($te['started_at'], 0, 16)) ?></small>
                                <button class="btn btn-outline-secondary btn-sm py-0 px-1"
                                        title="Edit"
                                        onclick="editEntry(<?= (int)$te['id'] ?>, <?= (int)$te['duration_minutes'] ?>, <?= htmlspecialchars(json_encode($te['note'] ?? '')) ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Ticket info -->
            <div class="card shadow-sm mb-3">
                <div class="card-header">Ticket Info</div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between"><span>Status</span><?= statusBadge($ticket['status']) ?></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Priority</span><?= priorityBadge($ticket['priority']) ?></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Category</span><span><?= htmlspecialchars($ticket['category'] ?: 'N/A') ?></span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Submitted by</span><span><?= htmlspecialchars($ticket['owner_name']) ?></span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Assigned to</span><span><?= htmlspecialchars($ticket['agent_name'] ?: 'Unassigned') ?></span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Created</span><small><?= htmlspecialchars($ticket['created_at']) ?></small></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Updated</span><small><?= htmlspecialchars($ticket['updated_at']) ?></small></li>
                </ul>
            </div>

            <!-- Attachments -->
            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-paperclip"></i> Attachments</span>
                    <?php if (!empty($attachments)): ?>
                        <a href="/export.php?ticket_id=<?= $id ?>" class="btn btn-sm btn-outline-dark" title="Export as ZIP">
                            <i class="bi bi-file-zip"></i> Export ZIP
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($attachments)): ?>
                        <p class="text-muted small mb-2">No attachments.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-2">
                        <?php foreach ($attachments as $a): ?>
                            <li class="mb-1">
                                <i class="bi bi-file-earmark"></i>
                                <?= htmlspecialchars($a['original_name']) ?>
                                <small class="text-muted">(<?= number_format($a['size'] / 1024, 1) ?> KB)</small>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <!-- Upload form -->
                    <form action="/upload.php" method="post" enctype="multipart/form-data" class="mt-2">
                        <input type="hidden" name="ticket_id" value="<?= $id ?>">
                        <div class="input-group input-group-sm">
                            <input type="file" name="attachment" class="form-control" required>
                            <button type="submit" class="btn btn-dark btn-sm"><i class="bi bi-upload"></i> Upload</button>
                        </div>
                        <small class="text-muted">Max 10MB</small>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
    const TICKET_ID = <?= (int)$id ?>;
    let timerInterval = null;
    let timerStartedAt = null;

    function fmtMinutes(m) {
        const h = Math.floor(m / 60), mm = m % 60;
        return h > 0 ? `${h}h ${mm}m` : `${mm}m`;
    }

    function startTimer() {
        timerStartedAt = new Date();
        document.getElementById('btn-start-timer').classList.add('d-none');
        document.getElementById('btn-stop-timer').classList.remove('d-none');
        const display = document.getElementById('timer-display');
        timerInterval = setInterval(() => {
            const elapsed = Math.floor((new Date() - timerStartedAt) / 60000);
            display.textContent = `Running: ${fmtMinutes(elapsed)}`;
        }, 15000);
        display.textContent = 'Running: 0m';
    }

    function stopTimer() {
        clearInterval(timerInterval);
        const durationMinutes = Math.max(1, Math.round((new Date() - timerStartedAt) / 60000));
        document.getElementById('btn-stop-timer').classList.add('d-none');
        document.getElementById('btn-start-timer').classList.remove('d-none');
        document.getElementById('timer-display').textContent = '';

        const note = prompt('Add a note for this session (optional):') ?? '';
        createEntry(durationMinutes, note, timerStartedAt.toISOString().slice(0, 19).replace('T', ' '));
        timerStartedAt = null;
    }

    function logManualEntry() {
        const durInput = document.getElementById('entry-duration');
        const duration = parseInt(durInput.value, 10);
        if (isNaN(duration) || duration < 1) {
            durInput.classList.add('is-invalid');
            return;
        }
        durInput.classList.remove('is-invalid');
        const note = document.getElementById('entry-note').value.trim();
        createEntry(duration, note, null);
        durInput.value = '';
        document.getElementById('entry-note').value = '';
    }

    function createEntry(durationMinutes, note, startedAt) {
        const payload = {
            ticket_id:        TICKET_ID,
            duration_minutes: durationMinutes,
            note:             note,
        };
        if (startedAt) payload.started_at = startedAt;

        fetch('/time_entry.php', {
            method:  'POST',
            headers: {'Content-Type': 'application/json'},
            body:    JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
        })
        .catch(() => alert('Failed to save time entry.'));
    }

    function editEntry(entryId, currentDuration, currentNote) {
        const newDuration = parseInt(prompt('Duration (minutes):', currentDuration), 10);
        if (isNaN(newDuration) || newDuration < 0) return;
        const newNote = prompt('Note:', currentNote ?? '') ?? currentNote;

        fetch('/time_entry.php', {
            method:  'POST',
            headers: {'Content-Type': 'application/json'},
            body:    JSON.stringify({
                entry_id:         entryId,
                duration_minutes: newDuration,
                note:             newNote,
            }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
        })
        .catch(() => alert('Failed to update time entry.'));
    }
    </script>
    <?php require __DIR__ . '/templates/footer.php';
} else {
    header('Location: /tickets.php');
    exit;
}
