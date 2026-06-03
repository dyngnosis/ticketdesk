<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$msg    = $_GET['msg']    ?? '';
$error  = $_GET['error']  ?? '';

// AJAX: expand a canned response with ticket variables
if (isset($_GET['canned_expand']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    $ticket_id   = (int)$_POST['ticket_id'];
    $response_id = (int)$_POST['response_id'];

    $stmt = $pdo->prepare("SELECT * FROM canned_responses WHERE id = ?");
    $stmt->execute([$response_id]);
    $canned = $stmt->fetch();

    $stmt2 = $pdo->prepare("SELECT t.*, u.username AS submitter_name FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $stmt2->execute([$ticket_id]);
    $ticket = $stmt2->fetch();

    if ($canned && $ticket) {
        $agentStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $agentStmt->execute([$_SESSION['user_id']]);
        $agent_name = $agentStmt->fetchColumn();

        $expanded = $canned['body'];
        $expanded = str_replace('{{submitter_name}}', $ticket['submitter_name'], $expanded);
        $expanded = str_replace('{{ticket_id}}',      (string)$ticket_id,        $expanded);
        $expanded = str_replace('{{agent_name}}',     $agent_name,               $expanded);

        header('Content-Type: application/json');
        echo json_encode(['body' => $expanded]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'not found']);
    }
    exit;
}

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
            // If a canned response was used, the body was expanded server-side via
            // the canned_expand endpoint; store and render it escaped.
            $storeBody = htmlspecialchars($body);
            $pdo->prepare("INSERT INTO comments (ticket_id, user_id, body) VALUES (?, ?, ?)")
                ->execute([$tid, currentUserId(), $storeBody]);
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
                        <p class="mb-0 mt-1" style="white-space:pre-wrap"><?= $c['body'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Add comment -->
            <div class="card mt-3 shadow-sm">
                <div class="card-header">Add a Comment</div>
                <div class="card-body">
                    <form method="post" id="comment-form">
                        <input type="hidden" name="ticket_id" value="<?= $id ?>">
                        <?php
                        $cannedStmt = $pdo->query("SELECT id, name FROM canned_responses ORDER BY name");
                        $cannedList = $cannedStmt->fetchAll();
                        if (!empty($cannedList)):
                        ?>
                        <div class="mb-2">
                            <label class="form-label small text-muted"><i class="bi bi-lightning"></i> Use canned response</label>
                            <div class="input-group input-group-sm">
                                <select id="canned-select" class="form-select form-select-sm">
                                    <option value="">— select a canned response —</option>
                                    <?php foreach ($cannedList as $cr): ?>
                                    <option value="<?= $cr['id'] ?>"><?= htmlspecialchars($cr['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" id="canned-apply" class="btn btn-outline-secondary btn-sm">Apply</button>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <textarea name="body" id="comment-body" class="form-control" rows="3" placeholder="Write your comment..." required></textarea>
                        </div>
                        <button type="submit" name="add_comment" class="btn btn-dark btn-sm">
                            <i class="bi bi-send"></i> Post Comment
                        </button>
                    </form>
                </div>
            </div>

            <script>
            (function () {
                var applyBtn = document.getElementById('canned-apply');
                if (!applyBtn) return;
                applyBtn.addEventListener('click', function () {
                    var sel = document.getElementById('canned-select');
                    var rid = sel.value;
                    if (!rid) return;
                    var fd = new FormData();
                    fd.append('ticket_id', <?= $id ?>);
                    fd.append('response_id', rid);
                    fetch('/tickets.php?canned_expand=1', { method: 'POST', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.body !== undefined) {
                                document.getElementById('comment-body').value = data.body;
                            } else {
                                alert('Could not load canned response.');
                            }
                        })
                        .catch(function () { alert('Error loading canned response.'); });
                });
            })();
            </script>
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
    <?php require __DIR__ . '/templates/footer.php';
} else {
    header('Location: /tickets.php');
    exit;
}
