
<?php
session_start();

$verifiedEmail = $_SESSION['verified_otp_email']['password_reset'] ?? '';

if (!is_string($verifiedEmail) || !filter_var($verifiedEmail, FILTER_VALIDATE_EMAIL)) {
    header('Location: forgot_password.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Kawader</title>
    <link rel="stylesheet" href="style.css?v=4">
    <style>
        .reset-message {
            margin: 12px 0;
            padding: 10px 14px;
            border-radius: 9px;
            font-size: 13px;
            line-height: 1.6;
        }
        .reset-message.error {
            color: #B42318;
            background: #FFF1F0;
            border: 1px solid #FECACA;
        }
        .reset-message.success {
            color: #286B50;
            background: #F0F8F3;
            border: 1px solid #CDE5D6;
        }
        
/* Reset password strength bar fix */
.auth-form-wrapper .strength-bar {
    display: block !important;
    width: 590px !important;
    max-width: 100% !important;
    height: 6px;
    margin-top: 8px;
    overflow: hidden;
    box-sizing: border-box;
    background: #e8e2ee;
    border-radius: 10px;
}

.auth-form-wrapper .strength-bar #strengthProgress {
    display: block;
    height: 100%;
    max-width: 100%;
    box-sizing: border-box;
    border-radius: 10px;
}

    </style>
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
               
                <h1>Choose a <strong>new password</strong></h1>
                <p>Create a strong password to secure your account.</p>
            </div>
        </div>
    </section>

    <section class="auth-form-side">
        <div class="auth-form-wrapper">
            <div class="mobile-logo">
                <img src="../images/KawaderLogo.png" alt="Kawader">
            </div>

            <div class="form-heading">
                <span class="small-title">ACCOUNT RECOVERY</span>
                <h2>Reset your password</h2>
                <p>Enter a new password for your account.</p>
            </div>

            <form class="signup-form" id="resetPasswordForm">
                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <input type="password" id="newPassword" autocomplete="new-password"
                           placeholder="Enter a new password" required>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm New Password</label>
                    <input type="password" id="confirmPassword" autocomplete="new-password"
                           placeholder="Confirm your new password" required>
                           <div id="confirmMessage"></div>
                </div>

               
<div class="strength-bar">
    <div id="strengthProgress"></div>
</div>
<div id="strengthText" class="strength-text"></div>

<div id="passwordRules" class="password-rules">
    <div>○ At least 8 characters</div>
    <div>○ One uppercase letter</div>
    <div>○ One lowercase letter</div>
    <div>○ One number</div>
    <div>○ One special character</div>
</div>


                <div id="resetMessage" class="reset-message" role="status" hidden></div>

                <button type="submit" class="create-btn" id="savePasswordBtn">
                    <span>Update Password</span><span>→</span>
                </button>
            </form>

            <a href="signin.php" class="back-link">← Back to sign in</a>
        </div>
    </section>
</div>

<script>
const form = document.getElementById('resetPasswordForm');
const newPassword = document.getElementById('newPassword');
const confirmPassword = document.getElementById('confirmPassword');
const message = document.getElementById('resetMessage');
const saveButton = document.getElementById('savePasswordBtn');

const strengthProgress = document.getElementById('strengthProgress');
const strengthText = document.getElementById('strengthText');
const passwordRules = document.getElementById('passwordRules');
const confirmMessage = document.getElementById('confirmMessage');

newPassword.addEventListener('input', function () {
    const value = newPassword.value;

    const checks = [
        value.length >= 8,
        /[A-Z]/.test(value),
        /[a-z]/.test(value),
        /[0-9]/.test(value),
        /[^A-Za-z0-9]/.test(value)
    ];

    passwordRules.classList.toggle('show', value.length > 0);

    const labels = [
        'At least 8 characters',
        'One uppercase letter',
        'One lowercase letter',
        'One number',
        'One special character'
    ];

    passwordRules.querySelectorAll('div').forEach(function (item, i) {
        item.textContent = (checks[i] ? '✓ ' : '○ ') + labels[i];
        item.className = checks[i] ? 'valid' : 'invalid';
    });

    const score = checks.filter(Boolean).length;

    if (value.length === 0) {
        strengthProgress.style.width = '0%';
        strengthText.textContent = '';
    } else if (score <= 2) {
        strengthProgress.style.width = '33%';
        strengthProgress.style.background = '#e53935';
        strengthText.textContent = 'Weak';
        strengthText.style.color = '#e53935';
    } else if (score <= 4) {
        strengthProgress.style.width = '66%';
        strengthProgress.style.background = '#f39c12';
        strengthText.textContent = 'Medium';
        strengthText.style.color = '#f39c12';
    } else {
        strengthProgress.style.width = '100%';
        strengthProgress.style.background = '#29965a';
        strengthText.textContent = 'Strong';
        strengthText.style.color = '#29965a';
    }

    checkPasswordMatch();
});

function checkPasswordMatch() {
    if (!confirmPassword.value) {
        confirmMessage.textContent = '';
        confirmMessage.style.color = '';
    } else if (newPassword.value === confirmPassword.value) {
        confirmMessage.textContent = '✓ Passwords match';
        confirmMessage.style.color = '#29965a';
    } else {
        confirmMessage.textContent = '✕ Passwords do not match';
        confirmMessage.style.color = '#e53935';
    }
}

confirmPassword.addEventListener('input', checkPasswordMatch);


function showMessage(text, type) {
    message.textContent = text;
    message.className = 'reset-message ' + type;
    message.hidden = false;
}

form.addEventListener('submit', async function (event) {
    event.preventDefault();

    const password = newPassword.value;
    const confirmation = confirmPassword.value;

    const validPassword =
        password.length >= 8 &&
        /[A-Z]/.test(password) &&
        /[a-z]/.test(password) &&
        /[0-9]/.test(password) &&
        /[^A-Za-z0-9]/.test(password);

    if (!validPassword) {
        showMessage('Use at least 8 characters, including uppercase, lowercase, a number, and a special character.', 'error');
        return;
    }

    if (password !== confirmation) {
        showMessage('Passwords do not match.', 'error');
        return;
    }

    saveButton.disabled = true;

    try {
        const data = new URLSearchParams();
        data.append('password', password);
        data.append('confirm_password', confirmation);

        const response = await fetch('../api/reset_password_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: data.toString()
        });

        const result = await response.json();

        if (!result.success) {
            showMessage(result.message, 'error');
            return;
        }

        showMessage(result.message, 'success');
        form.reset();

        setTimeout(function () {
            window.location.href = 'signin.php';
        }, 4000);

    } catch (error) {
        showMessage('Something went wrong. Please try again.', 'error');
    } finally {
        saveButton.disabled = false;
    }
});
</script>
</body>
</html>
