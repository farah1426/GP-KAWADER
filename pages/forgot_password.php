```php
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - Kawader</title>
    <link rel="stylesheet" href="style.css?v=4">
    <style>
        #resetOtpSection[hidden] {
            display: none !important;
        }

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

        #sendResetCode {
            margin-bottom: 12px;
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
                <img src="../images/KawaderLogoLight.png"
                     alt="Kawader" class="auth-logo">
            </a>

            <div class="visual-text">
                <span class="auth-tagline">Fairer, clearer, more efficient</span>
                <h1>Forgot your <strong>password?</strong></h1>
                <p>Don't worry. We'll help you get back to your account.</p>
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
                <p>Enter your email address to receive a verification code.</p>
            </div>

            <form class="signup-form" id="resetForm" novalidate>

                <div class="form-group">
                    <label for="resetEmail">Email Address</label>
                    <input type="email" id="resetEmail"
                           placeholder="you@example.com"
                           autocomplete="email" required>
                </div>

                <button type="button" class="create-btn" id="sendResetCode">
                    <span>Send Verification Code</span>
                    <span>→</span>
                </button>

                <div id="resetOtpSection" hidden>
                    <div class="form-group">
                        <label for="resetOtp">Email Verification Code</label>
                        <input type="text" id="resetOtp"
                               inputmode="numeric" maxlength="6"
                               placeholder="Enter the 6-digit code">
                    </div>

                    <button type="button" class="create-btn" id="verifyResetCode">
                        <span>Verify Email</span>
                        <span>→</span>
                    </button>
                </div>

                <div id="resetMessage" class="reset-message"
                     role="status" hidden></div>

            </form>

            <p class="signin-link">
                Remember your password?
                <a href="signin.php">Sign In</a>
            </p>

            <a href="home.html" class="back-link">← Back to home</a>
        </div>
    </section>
</div>

<script>
const resetEmail = document.getElementById('resetEmail');
const sendResetCode = document.getElementById('sendResetCode');
const resetOtpSection = document.getElementById('resetOtpSection');
const resetOtp = document.getElementById('resetOtp');
const verifyResetCode = document.getElementById('verifyResetCode');
const resetMessage = document.getElementById('resetMessage');

function showResetMessage(message, type) {
    resetMessage.textContent = message;
    resetMessage.className = 'reset-message ' + type;
    resetMessage.hidden = false;
}

sendResetCode.addEventListener('click', async function () {
    const email = resetEmail.value.trim();

    if (!email || !resetEmail.checkValidity()) {
        showResetMessage('Please enter a valid email address.', 'error');
        return;
    }

    sendResetCode.disabled = true;

    try {
        const data = new URLSearchParams();
        data.append('email', email);
        data.append('purpose', 'password_reset');

        const response = await fetch('../api/request_otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: data.toString()
        });

        const result = await response.json();

        if (!result.success) {
            showResetMessage(result.message, 'error');
            return;
        }

        resetOtpSection.hidden = false;
        resetOtp.value = '';
        showResetMessage(result.message, 'success');

    } catch (error) {
        showResetMessage('Unable to send the code. Please try again.', 'error');
    } finally {
        sendResetCode.disabled = false;
    }
});

verifyResetCode.addEventListener('click', async function () {
    const email = resetEmail.value.trim();
    const code = resetOtp.value.trim();

    if (!/^\d{6}$/.test(code)) {
        showResetMessage('Please enter the six-digit code.', 'error');
        return;
    }

    verifyResetCode.disabled = true;

    try {
        const data = new URLSearchParams();
        data.append('email', email);
        data.append('purpose', 'password_reset');
        data.append('otp', code);

        const response = await fetch('../api/verify_otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: data.toString()
        });

        const result = await response.json();

        if (!result.success) {
            showResetMessage(result.message, 'error');
            return;
        }

        showResetMessage('Email verified successfully!', 'success');

        window.location.href = 'reset_password.php';

    } catch (error) {
        showResetMessage('Unable to verify the code. Please try again.', 'error');
    } finally {
        verifyResetCode.disabled = false;
    }
});
</script>
</body>
</html>
