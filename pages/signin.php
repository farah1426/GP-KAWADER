<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../Database_kawader/db.php';

$signinError = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $signinError = 'Please enter a valid email and your password.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT user_id, email, password_hash, full_name, role FROM `user` WHERE email = :email LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = strtolower(trim($user['role']));

                if ($_SESSION['role'] === 'hr') {
                    header('Location: hr_portal.php');
                    exit;
                }

                if ($_SESSION['role'] === 'candidate') {
                    header('Location: candidate_portal.php');
                    exit;
                }

                // Do not leave a logged-in session for an unsupported role.
                $_SESSION = [];
                session_destroy();
                $signinError = 'Your account role is not supported. Please contact support.';
            } else {
                // Keep the message generic so the page does not reveal whether an email exists.
                $signinError = 'Incorrect email or password. Please try again.';
            }
        } catch (PDOException $e) {
            error_log('Kawader sign-in error: ' . $e->getMessage());
            $signinError = 'Something went wrong while signing in. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign In - Kawader</title>
  <link rel="stylesheet" href="style.css?v=3">
</head>
<body class="auth-page">
  <div class="auth-layout">
    <section class="auth-visual">
      <div class="visual-shape shape-one"></div>
      <div class="visual-shape shape-two"></div>
      <div class="visual-shape shape-three"></div>
      <div class="visual-content">
        <a href="home.html">
          <img src="../images/KawaderLogoLight.png" alt="Kawader" class="auth-logo">
        </a>
        <div class="visual-text">
          <span class="auth-tagline">Fairer, clearer, more efficient</span>
          <h1>Welcome <strong>back</strong></h1>
          <p>Continue your journey with Kawader and discover the right match</p>
        </div>
      </div>
    </section>

    <section class="auth-form-side">
      <div class="auth-form-wrapper">
        <div class="mobile-logo">
          <img src="../images/KawaderLogo.png" alt="Kawader">
        </div>

        <div class="form-heading">
          <span class="small-title">WELCOME BACK</span>
          <h2>Sign in to Kawader</h2>
        </div>

        <?php if ($signinError !== ''): ?>
          <div class="signup-alert signup-alert-error" role="alert">
            <span class="alert-icon">✕</span>
            <div class="alert-content">
              <strong>Sign In Failed</strong>
              <p><?= htmlspecialchars($signinError, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>
        <?php endif; ?>

        <form class="signup-form" id="signinForm" method="POST" action="signin.php" novalidate>
          <div class="form-group">
            <label for="email">Email</label>
            <input
              type="email"
              id="email"
              name="email"
              placeholder="you@example.com"
              value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
              autocomplete="email"
              required
            >
          </div>

          <div class="form-group">
            <div class="password-row">
              <label for="password">Password</label>
              <a href="forgot_password.php">Forgot password?</a>
            </div>
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Enter your password"
              autocomplete="current-password"
              required
            >
          </div>

          <button type="submit" class="create-btn">
            <span>Sign In</span><span>→</span>
          </button>
        </form>

        <p class="signin-link">Don't have an account? <a href="signup.php">Create Account</a></p>
        <a href="home.html" class="back-link">← Back to home</a>
      </div>
    </section>
  </div>

  <script>
    const signinForm = document.getElementById('signinForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    signinForm.addEventListener('submit', function (event) {
      let isValid = true;
      emailInput.classList.remove('field-invalid');
      passwordInput.classList.remove('field-invalid');

      if (!emailInput.value.trim() || !emailInput.validity.valid) {
        emailInput.classList.add('field-invalid');
        isValid = false;
      }

      if (!passwordInput.value) {
        passwordInput.classList.add('field-invalid');
        isValid = false;
      }

      if (!isValid) {
        event.preventDefault();
      }
    });

    [emailInput, passwordInput].forEach(function (input) {
      input.addEventListener('input', function () {
        input.classList.remove('field-invalid');
      });
    });
  </script>
</body>
</html>