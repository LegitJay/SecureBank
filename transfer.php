<?php
require_once 'config.php';
require_once 'security.php';
require_once 'auth.php';

require_login();
require_mfa_verified();

$user = get_logged_in_user();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    $error = 'Invalid or expired form token. Please try again.';
  } else {
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = sanitize_input($_POST['description'] ?? '');
    $to_account = sanitize_input($_POST['to_account'] ?? '');

    if ($amount <= 0) {
      $error = 'Amount must be greater than zero.';
    } elseif ($amount > $user['account_balance']) {
      $error = 'Insufficient balance.';
    } elseif (empty($to_account)) {
      $error = 'Recipient account number is required.';
    } else {
      // Find the recipient.
      $stmt = $pdo->prepare("SELECT id FROM users WHERE account_number = ?");
      $stmt->execute([$to_account]);
      $recipient = $stmt->fetch();

      if (!$recipient) {
        $error = 'Recipient account not found.';
      } elseif ($recipient['id'] === $user['id']) {
        $error = 'You cannot transfer to your own account.';
      } else {
        $pdo->prepare("UPDATE users SET account_balance = account_balance - ? WHERE id = ?")
          ->execute([$amount, $user['id']]);

        $pdo->prepare("UPDATE users SET account_balance = account_balance + ? WHERE id = ?")
          ->execute([$amount, $recipient['id']]);

        $pdo->prepare("INSERT INTO transactions (user_id, transaction_type, amount, description) VALUES (?, 'transfer', ?, ?)")
          ->execute([$user['id'], $amount, $description]);

        $pdo->prepare("INSERT INTO transactions (user_id, transaction_type, amount, description) VALUES (?, 'deposit', ?, ?)")
          ->execute([$recipient['id'], $amount, "Transfer from " . $user['account_number']]);

        $success = 'Transfer successful!';
        $user = get_logged_in_user();
      }
    }
  }
}

$ref = generate_reference($user['id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SecureBank — Transfer</title>
  <link rel="icon" type="image/png" href="css/favicon.svg">
  <link rel="stylesheet" href="css/style.css">
</head>

<body>
  <nav class="navbar">
    <div class="nav-brand"><img src="css/securebank_logo.svg" alt="SecureBank Logo"></div>
    <div class="nav-links">
      <a href="dashboard.php">Dashboard</a>
      <a href="transaction_history.php?ref=<?= encode_output($ref) ?>">Transactions</a>
      <a href="transfer.php" class="active">Transfer</a>
      <a href="profile.php">Profile</a>
      <a href="logout.php" class="nav-logout">Logout</a>
    </div>
  </nav>

  <div class="main-container">
    <div class="page-header">
      <h2>Money Transfer</h2>
      <p>Balance: <strong>₱<?= number_format((float) $user['account_balance'], 2) ?></strong></p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= encode_output($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= encode_output($success) ?></div><?php endif; ?>

    <div class="form-card">
      <form method="POST" action="transfer.php">
        <?= csrf_field() ?>

        <div class="form-group">
          <label>Recipient Account Number</label>
          <input type="text" name="to_account" placeholder="ACC-XXXXXXX" required>
        </div>

        <div class="form-group">
          <label>Amount (₱)</label>
          <input type="number" name="amount" min="1" step="0.01" placeholder="0.00" required>
        </div>

        <div class="form-group">
          <label>Description</label>
          <input type="text" name="description" placeholder="e.g. Payment for services" maxlength="255">
        </div>

        <button type="submit" class="btn btn-primary btn-full">Send Transfer</button>
      </form>
    </div>
  </div>
</body>

</html>