<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireAdmin();

$section = $_GET['section'] ?? 'tickets';
$msg     = $_GET['msg']     ?? '';

// ── HANDLE TICKET UPDATE ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $tid       = (int)($_POST['ticket_id'] ?? 0);
    $status    = $_POST['status']      ?? '';
    $assigned  = (int)($_POST['assigned_to'] ?? 0);

    $allowed_statuses = ['open', 'in-progress', 'resolved', 'closed'];
    if (!in_array($status, $allowed_statuses)) $status = 'open';

    $pdo->prepare("UPDATE tickets SET status = ?, assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$status, $assigned ?: null, $tid]);

    header("Location: /admin.php?section=tickets&msg=updated");
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function priorityBadge(string $p): string {
    $map = ['low'=>'success','medium'=>'warning text-dark','high'=>'danger','urgent'=>'purple'];
    $cls = $map[$p] ?? 'secondary';
    return "<span class=\"badge bg-{$cls}\">" . htmlspecialchars(ucfirst($p)) . "</span>";
}
function statusBadge(string $s): string {
    $map = ['open'=>'primary','in-progress'=>'warning text-dark','resolved'=>'success','closed'=>'secondary'];
    $cls = $map[$s] ?? 'secondary';
    return "<span class=\"badge bg-{$cls}\">" . htmlspecialchars(ucwords(str_replace('-', ' ', $s))) . "</span>";
}

$pageTitle = 'Admin Panel';
require __DIR__ . '/templates/header.php';

// Fetch agents (admins) for assignment dropdown
$agents = $pdo->query("SELECT id, username FROM users WHERE role = 'admin' ORDER BY username")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-shield-lock"></i> Admin Panel</h2>
    <div class="btn-group">
        <a href="/admin.php?section=tickets" class="btn btn-sm <?= $section === 'tickets' ? 'btn-dark' : 'btn-outline-dark' ?>">
            <i class="bi bi-ticket-perforated"></i> All Tickets
        </a>
        <a href="/admin.php?section=users" class="btn btn-sm <?= $section === 'users' ? 'btn-dark' : 'btn-outline-dark' ?>">
            <i class="bi bi-people"></i> Users
        </a>
    </div>
</div>

<?php if ($msg === 'updated'): ?>
    <div class="alert alert-success alert-dismissible fade show">Ticket updated successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($section === 'users'): ?>
    <!-- ── USER LIST ─────────────────────────────────────────────────────── -->
    <?php
    $users = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM tickets t WHERE t.user_id = u.id) AS ticket_count FROM users u ORDER BY u.created_at DESC")->fetchAll();
    ?>
    <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0">Users (<?= count($users) ?>)</h5></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Tickets</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge bg-warning text-dark">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">User</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $u['ticket_count'] ?></td>
                        <td><small><?= htmlspecialchars($u['created_at']) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- ── ALL TICKETS ───────────────────────────────────────────────────── -->
    <?php
    // Filters
    $filterStatus   = $_GET['filter_status']   ?? '';
    $filterPriority = $_GET['filter_priority'] ?? '';
    $filterSearch   = trim($_GET['search']     ?? '');

    $where  = [];
    $params = [];

    if ($filterStatus) {
        $where[]  = "t.status = ?";
        $params[] = $filterStatus;
    }
    if ($filterPriority) {
        $where[]  = "t.priority = ?";
        $params[] = $filterPriority;
    }
    if ($filterSearch) {
        $where[]  = "(t.title LIKE ? OR t.description LIKE ?)";
        $params[] = "%$filterSearch%";
        $params[] = "%$filterSearch%";
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $tickets = $pdo->prepare("
        SELECT t.*, u.username AS owner_name, a.username AS agent_name
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN users a ON t.assigned_to = a.id
        $whereSql
        ORDER BY t.created_at DESC
    ");
    $tickets->execute($params);
    $tickets = $tickets->fetchAll();

    // Stats
    $stats = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status='open') AS open_count,
            SUM(status='in-progress') AS inprog_count,
            SUM(status='resolved') AS resolved_count,
            SUM(status='closed') AS closed_count
        FROM tickets
    ")->fetch();
    ?>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-primary"><?= $stats['total'] ?></div>
                    <small>Total</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-primary"><?= $stats['open_count'] ?></div>
                    <small>Open</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-warning"><?= $stats['inprog_count'] ?></div>
                    <small>In Progress</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-success">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-success"><?= $stats['resolved_count'] ?></div>
                    <small>Resolved</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter bar -->
    <form method="get" class="row g-2 mb-3">
        <input type="hidden" name="section" value="tickets">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search tickets..."
                   value="<?= htmlspecialchars($filterSearch) ?>">
        </div>
        <div class="col-md-3">
            <select name="filter_status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <?php foreach (['open','in-progress','resolved','closed'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucwords(str_replace('-',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="filter_priority" class="form-select form-select-sm">
                <option value="">All priorities</option>
                <?php foreach (['low','medium','high','urgent'] as $p): ?>
                <option value="<?= $p ?>" <?= $filterPriority === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-dark btn-sm w-100"><i class="bi bi-search"></i> Filter</button>
        </div>
    </form>

    <!-- Tickets table -->
    <div class="card shadow-sm">
        <div class="card-header"><h6 class="mb-0">Tickets (<?= count($tickets) ?>)</h6></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Owner</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><?= $t['id'] ?></td>
                        <td>
                            <a href="/tickets.php?action=view&id=<?= $t['id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars(mb_strimwidth($t['title'], 0, 45, '…')) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($t['owner_name']) ?></td>
                        <td><?= priorityBadge($t['priority']) ?></td>
                        <td><?= statusBadge($t['status']) ?></td>
                        <td><?= htmlspecialchars($t['agent_name'] ?: '—') ?></td>
                        <td><small><?= htmlspecialchars(substr($t['created_at'], 0, 10)) ?></small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal"
                                    data-bs-target="#editModal"
                                    data-ticket-id="<?= $t['id'] ?>"
                                    data-status="<?= htmlspecialchars($t['status']) ?>"
                                    data-assigned="<?= (int)$t['assigned_to'] ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Update Ticket</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="ticket_id" id="modal_ticket_id">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="modal_status" class="form-select">
                                <?php foreach (['open','in-progress','resolved','closed'] as $s): ?>
                                <option value="<?= $s ?>"><?= ucwords(str_replace('-',' ',$s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign to Agent</label>
                            <select name="assigned_to" id="modal_assigned" class="form-select">
                                <option value="0">— Unassigned —</option>
                                <?php foreach ($agents as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_ticket" class="btn btn-dark">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget;
        document.getElementById('modal_ticket_id').value = btn.dataset.ticketId;
        document.getElementById('modal_status').value    = btn.dataset.status;
        document.getElementById('modal_assigned').value  = btn.dataset.assigned;
    });
    </script>

<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
