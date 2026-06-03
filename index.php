<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';
$page   = $_GET['page']   ?? 'home';

// Handle logout
if ($action === 'logout') {
    session_destroy();
    header('Location: /index.php?page=login');
    exit;
}

// Handle login POST
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        header('Location: /tickets.php');
        exit;
    } else {
        $loginError = 'Invalid username or password.';
    }
}

// Handle register POST
$registerError   = '';
$registerSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    if (strlen($username) < 3) {
        $registerError = 'Username must be at least 3 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $registerError = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $registerError = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)")
                ->execute([$username, $hash, $email]);
            $registerSuccess = 'Account created! You can now log in.';
            $page = 'login';
        } catch (PDOException $e) {
            $registerError = 'Username already taken.';
        }
    }
}

// Redirect logged-in users away from login/register
if (isLoggedIn() && in_array($page, ['login', 'register'])) {
    header('Location: /tickets.php');
    exit;
}

// Default redirect to tickets if logged in
if (isLoggedIn() && $page === 'home') {
    header('Location: /tickets.php');
    exit;
}

$pageTitle = 'Welcome';
require __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">

<?php if ($page === 'register'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-person-plus"></i> Create Account</h5>
            </div>
            <div class="card-body">
                <?php if ($registerError): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($registerError) ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required minlength="3"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm" class="form-control" required>
                    </div>
                    <button type="submit" name="register" class="btn btn-dark w-100">Register</button>
                </form>
                <hr>
                <p class="text-center mb-0">Already have an account? <a href="/index.php?page=login">Login</a></p>
            </div>
        </div>

<?php else: ?>
        <!-- Login form (default) -->
        <div class="text-center mb-4">
            <h1 class="display-6 fw-bold"><i class="bi bi-headset"></i> Ticketdesk</h1>
            <p class="text-muted">IT Support Ticket Manager</p>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-box-arrow-in-right"></i> Sign In</h5>
            </div>
            <div class="card-body">
                <?php if ($loginError): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($loginError) ?></div>
                <?php endif; ?>
                <?php if ($registerSuccess): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($registerSuccess) ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-dark w-100">Login</button>
                </form>
                <hr>
                <p class="text-center mb-0">No account? <a href="/index.php?page=register">Register here</a></p>
            </div>
        </div>
        <p class="text-center text-muted mt-3 small">Default admin: <code>admin</code> / <code>admin123</code></p>
<?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
