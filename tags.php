<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$msg    = $_GET['msg']    ?? '';
$error  = '';

// ── CREATE TAG ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tag'])) {
    $name  = trim($_POST['name']  ?? '');
    $color = trim($_POST['color'] ?? '#6c757d');

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '#6c757d';
    }

    if (strlen($name) < 1 || strlen($name) > 32) {
        $error  = 'Tag name must be between 1 and 32 characters.';
        $action = 'new';
    } else {
        try {
            $pdo->prepare("INSERT INTO tags (name, color) VALUES (?, ?)")
                ->execute([$name, $color]);
            header('Location: /tags.php?msg=created');
            exit;
        } catch (PDOException $e) {
            $error  = 'A tag with that name already exists.';
            $action = 'new';
        }
    }
}

// ── DELETE TAG ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tag'])) {
    $tid = (int)($_POST['tag_id'] ?? 0);
    if ($tid) {
        $pdo->prepare("DELETE FROM tags WHERE id = ?")->execute([$tid]);
    }
    header('Location: /tags.php?msg=deleted');
    exit;
}

// ── ASSIGN TAG TO TICKET ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_tag'])) {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $tagId    = (int)($_POST['tag_id']    ?? 0);

    if ($ticketId && $tagId) {
        // Verify the ticket exists
        $chk = $pdo->prepare("SELECT id FROM tickets WHERE id = ?");
        $chk->execute([$ticketId]);
        if ($chk->fetch()) {
            try {
                $pdo->prepare("INSERT INTO ticket_tags (ticket_id, tag_id) VALUES (?, ?)")
                    ->execute([$ticketId, $tagId]);
            } catch (PDOException $e) {
                // Already assigned — silently ignore duplicate
            }
        }
    }
    header("Location: /tickets.php?action=view&id={$ticketId}&msg=tagged");
    exit;
}

// ── REMOVE TAG FROM TICKET ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_tag'])) {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $tagId    = (int)($_POST['tag_id']    ?? 0);

    if ($ticketId && $tagId) {
        $pdo->prepare("DELETE FROM ticket_tags WHERE ticket_id = ? AND tag_id = ?")
            ->execute([$ticketId, $tagId]);
    }
    header("Location: /tickets.php?action=view&id={$ticketId}&msg=tag_removed");
    exit;
}

// ── LIST TAGS ────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $tags = $pdo->query("
        SELECT tg.*, COUNT(tt.ticket_id) AS usage_count
        FROM tags tg
        LEFT JOIN ticket_tags tt ON tg.id = tt.tag_id
        GROUP BY tg.id
        ORDER BY tg.name ASC
    ")->fetchAll();

    $pageTitle = 'Tag Management';
    require __DIR__ . '/templates/header.php';
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-tags"></i> Tag Management</h2>
        <a href="/tags.php?action=new" class="btn btn-dark"><i class="bi bi-plus-circle"></i> New Tag</a>
    </div>

    <?php if ($msg === 'created'): ?>
        <div class="alert alert-success alert-dismissible fade show">Tag created successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show">Tag deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header"><h6 class="mb-0">Tags (<?= count($tags) ?>)</h6></div>
        <?php if (empty($tags)): ?>
            <div class="card-body text-muted">No tags yet. <a href="/tags.php?action=new">Create the first tag.</a></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Label</th>
                        <th>Color</th>
                        <th>In use</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tags as $tag): ?>
                    <tr>
                        <td><?= $tag['id'] ?></td>
                        <td>
                            <span class="badge" style="background-color:<?= htmlspecialchars($tag['color']) ?>">
                                <?= htmlspecialchars($tag['name']) ?>
                            </span>
                        </td>
                        <td><code><?= htmlspecialchars($tag['color']) ?></code></td>
                        <td><?= (int)$tag['usage_count'] ?> ticket<?= $tag['usage_count'] != 1 ? 's' : '' ?></td>
                        <td><small><?= htmlspecialchars(substr($tag['created_at'], 0, 10)) ?></small></td>
                        <td class="text-end">
                            <form method="post" style="display:inline"
                                  onsubmit="return confirm('Delete tag \'<?= htmlspecialchars(addslashes($tag['name'])) ?>\'?');">
                                <input type="hidden" name="tag_id" value="<?= $tag['id'] ?>">
                                <button type="submit" name="delete_tag" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/templates/footer.php';

} elseif ($action === 'new') {
    $pageTitle = 'New Tag';
    require __DIR__ . '/templates/header.php';
    ?>
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create New Tag</h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Tag name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="32"
                                   placeholder="e.g. billing, urgent, bug"
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Color</label>
                            <div class="input-group">
                                <input type="color" name="color" class="form-control form-control-color"
                                       value="<?= htmlspecialchars($_POST['color'] ?? '#6c757d') ?>">
                                <span class="input-group-text">Hex color</span>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="create_tag" class="btn btn-dark">
                                <i class="bi bi-check-lg"></i> Create Tag
                            </button>
                            <a href="/tags.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php require __DIR__ . '/templates/footer.php';

} else {
    header('Location: /tags.php');
    exit;
}
