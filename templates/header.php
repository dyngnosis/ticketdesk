<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Ticketdesk') ?> — Ticketdesk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .navbar-brand { font-weight: 700; letter-spacing: 1px; }
        .sidebar { min-height: calc(100vh - 56px); background: #fff; border-right: 1px solid #dee2e6; }
        .ticket-card { transition: box-shadow .2s; }
        .ticket-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); }
        .priority-low    { border-left: 4px solid #198754; }
        .priority-medium { border-left: 4px solid #fd7e14; }
        .priority-high   { border-left: 4px solid #dc3545; }
        .priority-urgent { border-left: 4px solid #6f42c1; }
        .badge-open        { background-color: #0d6efd; }
        .badge-in-progress { background-color: #fd7e14; }
        .badge-resolved    { background-color: #198754; }
        .badge-closed      { background-color: #6c757d; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/index.php"><i class="bi bi-headset"></i> Ticketdesk</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navmenu">
            <ul class="navbar-nav me-auto">
                <?php if (isLoggedIn()): ?>
                <li class="nav-item"><a class="nav-link" href="/tickets.php"><i class="bi bi-ticket-perforated"></i> My Tickets</a></li>
                <li class="nav-item"><a class="nav-link" href="/tickets.php?action=new"><i class="bi bi-plus-circle"></i> New Ticket</a></li>
                <?php if (isAdmin()): ?>
                <li class="nav-item"><a class="nav-link" href="/admin.php"><i class="bi bi-shield-lock"></i> Admin</a></li>
                <li class="nav-item"><a class="nav-link" href="/canned_responses.php"><i class="bi bi-lightning"></i> Canned Responses</a></li>
                <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isLoggedIn()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars(currentUsername()) ?>
                        <?php if (isAdmin()): ?><span class="badge bg-warning text-dark ms-1">Admin</span><?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/index.php?action=logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item"><a class="nav-link" href="/index.php?page=login">Login</a></li>
                <li class="nav-item"><a class="nav-link" href="/index.php?page=register">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container-fluid py-4">
