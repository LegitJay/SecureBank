<?php
require_once 'config.php';
require_once 'security.php';
require_once 'auth.php';

// Require full authentication
require_login();
require_mfa_verified();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_mfa_notice'])) {
    if (validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['mfa_notice_dismissed'] = true;
        http_response_code(204);
        exit();
    }

    http_response_code(403);
    exit('Invalid request.');
}

// Load the current user's data
$user = get_logged_in_user();
if (!$user) {
    logout_user();
}

// Confirm resource ownership
if (!verify_user_access($user['id'])) {
    header("Location: index.php?error=unauthorized");
    exit();
}

// Use an indirect reference in URLs
$ref = generate_reference($user['id']);

// Load recent transactions
$stmt = $pdo->prepare("
    SELECT * FROM transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recent_transactions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureBank — Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <input type="hidden" id="dashboardCsrf" value="<?= encode_output(generate_csrf_token()) ?>">

    <nav class="navbar">
        <div class="nav-brand"><img src="css/securebank_logo.svg" alt="SecureBank Logo"></div>
        <div class="nav-links">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="transaction_history.php?ref=<?= encode_output($ref) ?>">Transactions</a>
            <a href="transfer.php">Transfer</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="nav-logout">Logout</a>
        </div>
    </nav>

    <?php if (!$user['is_mfa_enabled'] && !isset($_SESSION['mfa_notice_dismissed'])): ?>
        <div class="mfa-notice" id="mfaNotice">
            <div class="mfa-notice-content">
                <div class="mfa-notice-icon">&#9888;</div>
                <div class="mfa-notice-text">
                    <strong>Your account is not fully secured.</strong>
                    <span>Enable Multi-Factor Authentication to protect your account from unauthorized access.</span>
                </div>
                <a href="profile.php" class="btn btn-primary"
                    style="white-space:nowrap; font-size:13px; padding:8px 16px;">Enable MFA</a>
                <button type="button" class="mfa-notice-close" onclick="dismissMfaNotice()">&times;</button>
            </div>
        </div>
    <?php endif; ?>

    <div class="main-container">

        <div class="welcome-banner">
            <div>
                <h2>Welcome back, <?= encode_output($user['username']) ?>!</h2>
                <p>Account No: <?= encode_output($user['account_number']) ?></p>
            </div>
            <div class="last-login">
                <?= date('F d, Y h:i A') ?>
            </div>
        </div>

        <div class="cards-row">
            <div class="card card-balance">
                <div class="card-label">Account Balance</div>
                <div class="card-value">
                    ₱<?= number_format((float) $user['account_balance'], 2) ?>
                </div>
                <div class="card-sub">Available Balance</div>
            </div>

            <div class="card card-info">
                <div class="card-label">Account Details</div>
                <div class="info-row">
                    <span>Username</span>
                    <strong><?= encode_output($user['username']) ?></strong>
                </div>
                <div class="info-row">
                    <span>Email</span>
                    <strong><?= encode_output($user['email']) ?></strong>
                </div>
                <div class="info-row">
                    <span>MFA Status</span>
                    <strong class="<?= $user['is_mfa_enabled'] ? 'text-green' : 'text-red' ?>">
                        <?= $user['is_mfa_enabled'] ? '✔ Enabled (' . encode_output($user['mfa_method']) . ')' : '✘ Disabled' ?>
                    </strong>
                </div>
            </div>
        </div>

        <div class="section-title">Recent Transactions</div>
        <div class="table-wrapper">
            <?php if (empty($recent_transactions)): ?>
                <p class="empty-state">No transactions yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $tx): ?>
                            <tr>
                                <td><?= encode_output(date('M d, Y', strtotime($tx['created_at']))) ?></td>
                                <td>
                                    <span class="badge badge-<?= encode_output($tx['transaction_type']) ?>">
                                        <?= encode_output(ucfirst($tx['transaction_type'])) ?>
                                    </span>
                                </td>
                                <td><?= encode_output($tx['description']) ?></td>
                                <td class="<?= $tx['transaction_type'] === 'deposit' ? 'text-green' : 'text-red' ?>">
                                    <?= $tx['transaction_type'] === 'deposit' ? '+' : '-' ?>
                                    ₱<?= number_format((float) $tx['amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>

    <script>
        function dismissMfaNotice() {
            const notice = document.getElementById('mfaNotice');
            if (notice) {
                notice.remove();
            }

            fetch('dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'dismiss_mfa_notice=1&csrf_token=' + document.getElementById('dashboardCsrf').value
            });
        }
    </script>
</body>

</html>