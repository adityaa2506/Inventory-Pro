<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Manage Staff';
$activePage = 'users';

$error = '';
$success = '';

// Add User
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] === 'admin' ? 'admin' : 'staff';
    
    if ($email && $password) {
        // Check if email exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (email, password, role) VALUES (?, ?, ?)');
            if ($stmt->execute([$email, $hashed, $role])) {
                $success = 'User added successfully.';
            } else {
                $error = 'Failed to add user.';
            }
        }
    } else {
        $error = 'Email and password are required.';
    }
}

// Delete User
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $deleteId = (int)$_POST['user_id'];
    if ($deleteId === $_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$deleteId]);
        $success = 'User deleted successfully.';
    }
}

$users = $pdo->query('SELECT id, email, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-users me-2"></i>Manage Staff</h3>
    <button class="btn btn-is2" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-plus me-1"></i> Add User
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="ps-3 fw-bold"><?= e($u['email']) ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge bg-primary">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Staff</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(format_indian_datetime((string)$u['created_at'])) ?></td>
                        <td class="text-end pe-3">
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                            <form method="post" action="users.php" class="d-inline-block" onsubmit="return confirm('Delete this user?');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php else: ?>
                                <span class="text-muted small">Current User</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="users.php" class="modal-content">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="staff">Staff (Limited Access)</option>
                        <option value="admin">Admin (Full Access)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-is2">Create User</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>