<?php
require 'auth.php';
require 'db.php';
requireLogin();

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $responses = $pdo->query("SELECT cr.*, u.username AS author FROM canned_responses cr LEFT JOIN users u ON cr.created_by = u.id ORDER BY cr.name")->fetchAll();
    $pageTitle = 'Canned Responses';
    require 'templates/header.php';
    echo '<div class="container mt-4">';
    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<h4><i class="bi bi-lightning"></i> Canned Responses</h4>';
    if (isAdmin()) echo '<a href="?action=new" class="btn btn-primary btn-sm">+ New</a>';
    echo '</div>';
    echo '<div class="card"><table class="table table-hover mb-0">';
    echo '<thead class="table-light"><tr><th>Name</th><th>Preview</th><th>Author</th><th></th></tr></thead><tbody>';
    foreach ($responses as $r) {
        echo '<tr>';
        echo '<td class="fw-semibold">' . htmlspecialchars($r['name']) . '</td>';
        echo '<td class="small text-muted">' . htmlspecialchars(substr($r['body'], 0, 80)) . '&#8230;</td>';
        echo '<td class="small">' . htmlspecialchars($r['author'] ?? '') . '</td>';
        echo '<td>';
        if (isAdmin()) {
            echo '<a href="?action=edit&id=' . $r['id'] . '" class="btn btn-sm btn-outline-secondary">Edit</a>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div></div>';
    require 'templates/footer.php';
    exit;
}

if ($action === 'new' || $action === 'edit') {
    requireAdmin();
    $response = ['id' => '', 'name' => '', 'body' => ''];
    if ($action === 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM canned_responses WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $response = $stmt->fetch() ?: $response;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $body = trim($_POST['body']);
        if ($response['id']) {
            $pdo->prepare("UPDATE canned_responses SET name=?, body=? WHERE id=?")->execute([$name, $body, $response['id']]);
        } else {
            $pdo->prepare("INSERT INTO canned_responses (name, body, created_by) VALUES (?,?,?)")->execute([$name, $body, $_SESSION['user_id']]);
        }
        header('Location: canned_responses.php'); exit;
    }
    $pageTitle = ($action === 'edit' ? 'Edit' : 'New') . ' Canned Response';
    require 'templates/header.php';
    echo '<div class="container mt-4" style="max-width:600px">';
    echo '<div class="card"><div class="card-header">' . ($action === 'edit' ? 'Edit' : 'New') . ' Canned Response</div>';
    echo '<div class="card-body"><form method="post">';
    echo '<div class="mb-3"><label class="form-label">Name</label>';
    echo '<input type="text" name="name" class="form-control" value="' . htmlspecialchars($response['name']) . '" required></div>';
    echo '<div class="mb-3"><label class="form-label">Body</label>';
    echo '<p class="form-text">Variables: <code>{{submitter_name}}</code>, <code>{{ticket_id}}</code>, <code>{{agent_name}}</code></p>';
    echo '<textarea name="body" class="form-control" rows="6" required>' . htmlspecialchars($response['body']) . '</textarea></div>';
    echo '<button type="submit" class="btn btn-primary">Save</button> <a href="canned_responses.php" class="btn btn-outline-secondary">Cancel</a>';
    echo '</form></div></div></div>';
    require 'templates/footer.php';
    exit;
}
