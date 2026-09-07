<?php
require_once 'config.php';
require_once 'security.php';
require_once 'auth.php';

// Require full authentication.
require_login();
require_mfa_verified();

// Resolve the indirect reference.
$ref            = sanitize_input($_GET['ref'] ?? '');
$resolved_id    = get_user_from_reference($ref);

if (!$resolved_id) {
    header("Location: dashboard.php?error=invalid_reference");
    exit();
}

if (!verify_user_access($resolved_id)) {
    // Log invalid access.
    error_log("IDOR attempt: session user {$_SESSION['user_id']} tried to access user {$resolved_id} via ref={$ref}");
    header("Location: dashboard.php?error=unauthorized");
    exit();
}

$user = get_logged_in_user();

// Set pagination.
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;

// Count transactions.
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
$count_stmt->execute([$user['id']]);
$total      = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

// Load the current page.
$stmt = $pdo->prepare("
    SELECT * FROM transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$user['id'], $per_page, $offset]);
$transactions = $stmt->fetchAll();

// Refresh the navigation reference.
$ref = generate_reference($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureBank — Transaction History</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-brand"><img src="css/securebank_logo.svg" alt="SecureBank Logo"></div>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="transaction_history.php?ref=<?= encode_output($ref) ?>" class="active">Transactions</a>
        <a href="transfer.php">Transfer</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php" class="nav-logout">Logout</a>
    </div>
</nav>

<div class="main-container">

    <div class="page-header">
        <h2>Transaction History</h2>
        <p>Account: <?= encode_output($user['account_number']) ?> &nbsp;|&nbsp;
           Balance: <strong>₱<?= number_format((float)$user['account_balance'], 2) ?></strong>
        </p>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
        <div class="alert alert-error">
            ⛔ Access Denied — You are not authorized to view another user's transactions.
        </div>
    <?php endif; ?>

    <div class="table-wrapper">
        <?php if (empty($transactions)): ?>
            <p class="empty-state">No transactions found.</p>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date & Time</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $i => $tx): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td><?= encode_output(date('M d, Y h:i A', strtotime($tx['created_at']))) ?></td>
                    <td>
                        <span class="badge badge-<?= encode_output($tx['transaction_type']) ?>">
                            <?= encode_output(ucfirst($tx['transaction_type'])) ?>
                        </span>
                    </td>
                    <td><?= encode_output($tx['description']) ?></td>
                    <td class="<?= $tx['transaction_type'] === 'deposit' ? 'text-green' : 'text-red' ?>">
                        <?= $tx['transaction_type'] === 'deposit' ? '+' : '-' ?>
                        ₱<?= number_format((float)$tx['amount'], 2) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="transaction_history.php?ref=<?= encode_output($ref) ?>&page=<?= $p ?>"
                   class="page-link <?= $p === $page ? 'active' : '' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>