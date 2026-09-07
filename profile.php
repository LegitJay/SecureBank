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
    $action = $_POST['action'] ?? '';

    // Update profile details.
    if ($action === 'update_profile') {
      $email = sanitize_input($_POST['email'] ?? '');
      $phone = sanitize_input($_POST['phone'] ?? '');

      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
      } else {
        $pdo->prepare("UPDATE users SET email = ?, phone_number = ? WHERE id = ?")
          ->execute([$email, $phone, $user['id']]);
        $success = 'Profile updated successfully.';
        $user = get_logged_in_user();
      }
    }

    // Update MFA settings.
    if ($action === 'update_mfa') {
      $mfa_enabled = isset($_POST['mfa_enabled']) ? 1 : 0;
      $mfa_method = sanitize_input($_POST['mfa_method'] ?? 'email');

      if (!in_array($mfa_method, ['sms', 'email'])) {
        $error = 'Invalid MFA method.';
      } else {
        $pdo->prepare("UPDATE users SET is_mfa_enabled = ?, mfa_method = ? WHERE id = ?")
          ->execute([$mfa_enabled, $mfa_method, $user['id']]);
        $success = 'MFA settings updated.';
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
  <title>SecureBank — Profile</title>
  <link rel="stylesheet" href="css/style.css">
</head>

<body>
  <nav class="navbar">
    <div class="nav-brand"><img src="css/securebank_logo.svg" alt="SecureBank Logo"></div>
    <div class="nav-links">
      <a href="dashboard.php">Dashboard</a>
      <a href="transaction_history.php?ref=<?= encode_output($ref) ?>">Transactions</a>
      <a href="transfer.php">Transfer</a>
      <a href="profile.php" class="active">Profile</a>
      <a href="logout.php" class="nav-logout">Logout</a>
    </div>
  </nav>

  <div class="main-container">
    <div class="page-header">
      <h2>Profile & MFA Settings</h2>
      <p>Account: <?= encode_output($user['account_number']) ?></p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= encode_output($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= encode_output($success) ?></div>
    <?php endif; ?>

    <div class="profile-grid">

      <!-- Left: Account Info -->
      <div class="form-card">
        <div class="form-card-title">Account Information</div>
        <form method="POST" action="profile.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">

          <div class="form-group">
            <label>Username</label>
            <input type="text" value="<?= encode_output($user['username']) ?>" disabled>
          </div>

          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" value="<?= encode_output($user['email']) ?>" required>
          </div>

          <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" value="<?= encode_output($user['phone_number']) ?>"
              placeholder="+639XXXXXXXXX">
          </div>

          <button type="submit" class="btn btn-primary btn-full">Update Profile</button>
        </form>
      </div>

      <!-- Right: MFA Settings -->
      <div class="form-card">
        <div class="form-card-title">Multi-Factor Authentication</div>
        <form method="POST" action="profile.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_mfa">

          <div class="mfa-status-row">
            <div class="mfa-status-info">
              <span class="mfa-status-label">MFA Status</span>
              <span class="mfa-badge <?= $user['is_mfa_enabled'] ? 'mfa-badge-on' : 'mfa-badge-off' ?>">
                <?= $user['is_mfa_enabled'] ? 'Enabled' : 'Disabled' ?>
              </span>
            </div>
            <label class="switch">
              <input type="checkbox" name="mfa_enabled" value="1" <?= $user['is_mfa_enabled'] ? 'checked' : '' ?>>
              <span class="switch-slider"></span>
            </label>
          </div>

          <div class="form-group" style="margin-top: 22px;">
            <label>Preferred MFA Method</label>
            <select name="mfa_method">
              <option value="email" <?= $user['mfa_method'] === 'email' ? 'selected' : '' ?>>
                📧 Email OTP
              </option>
              <option value="sms" <?= $user['mfa_method'] === 'sms' ? 'selected' : '' ?>>
                📱 SMS OTP
              </option>
            </select>
          </div>

          <div class="mfa-info-box">
            <strong>What is MFA?</strong>
            <p>Multi-factor authentication adds a one-time password step after login, protecting your account even if
              your password is compromised.</p>
            <p style="margin-top: 6px;">Make sure your email and phone number above are up to date.</p>
          </div>

          <button type="submit" class="btn btn-primary btn-full" style="margin-top: 6px;">Save MFA Settings</button>
        </form>
      </div>

    </div>
  </div>
</body>

</html>