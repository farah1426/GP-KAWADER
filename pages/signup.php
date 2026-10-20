<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../Database_kawader/db.php';
$signupError = '';
$signupSuccess = '';
$savedFilePath = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';

    try {
        if (
            $fullName === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !in_array($role, ['candidate', 'hr'], true)
        ) {
            throw new Exception('Please check your information.');
        }
        if (
            strlen($password) < 8 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            throw new Exception('Please choose a valid password.');
        }
        if ($password !== $confirmPassword) {
            throw new Exception('Passwords do not match.');
        }

        if ($role === 'candidate') {
            $phone = trim($_POST['phone'] ?? '');
            $dob = trim($_POST['date_of_birth'] ?? '');
            $cv = $_FILES['cv'] ?? null;

            if ($phone === '' || $dob === '') {
                throw new Exception('Please complete all required candidate fields.');
            }

            $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
            $dateErrors = DateTimeImmutable::getLastErrors();
            if (
                !$birthDate ||
                ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
                $birthDate->format('Y-m-d') !== $dob ||
                $birthDate > new DateTimeImmutable('today')
            ) {
                throw new Exception('Please enter a valid date of birth.');
            }
            $today = new DateTimeImmutable('today');
            if ($birthDate->diff($today)->y < 18) {
                throw new Exception('You must be at least 18 years old to create a Kawader account.');
            }

        }
        $check = $pdo->prepare(
            'SELECT user_id FROM user WHERE email = ?'
        );
        $check->execute([$email]);
        if ($check->fetch()) {
            throw new Exception('This email is already registered.');
        }
        // Require email verification before creating the account.
$verifiedEmail = $_SESSION['verified_otp_email']['signup'] ?? '';

if (
    !is_string($verifiedEmail) ||
    strcasecmp($verifiedEmail, $email) !== 0
) {
    throw new Exception('Please verify your email before creating your account.');
}

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO user (email, password_hash, full_name, role)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $fullName,
            $role
        ]);
        $userId = (int) $pdo->lastInsertId();
        if ($role === 'candidate') {
            $phone = trim($_POST['phone'] ?? '');
            $dob = $_POST['date_of_birth'] ?? '';
            $dob = $dob !== '' ? $dob : null;
            $stmt = $pdo->prepare(
                'INSERT INTO candidate_profile
                 (user_id, phone, date_of_birth)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([
    $userId,
    $phone !== '' ? $phone : null,
    $dob
]);
$profileId = (int) $pdo->lastInsertId();

$cv = $_FILES['cv'] ?? null;

if ($cv && $cv['error'] !== UPLOAD_ERR_NO_FILE) {

    if ($cv['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Unable to upload your CV. Please try again.');
    }

    if ($cv['size'] > 5 * 1024 * 1024) {
        throw new Exception('CV size must not exceed 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($cv['tmp_name']);

    if (
        $mimeType !== 'application/pdf' ||
        strtolower(pathinfo($cv['name'], PATHINFO_EXTENSION)) !== 'pdf'
    ) {
        throw new Exception('Only PDF files are allowed.');
    }

    $uploadDir = __DIR__ . '/../uploads/cvs/';

    if (!is_dir($uploadDir)) {
        throw new Exception('CV upload folder was not found.');
    }

    $savedFileName = bin2hex(random_bytes(16)) . '.pdf';
    $destination = $uploadDir . $savedFileName;

    if (!move_uploaded_file($cv['tmp_name'], $destination)) {
        throw new Exception('Unable to save your CV. Please try again.');
    }

    $savedFilePath = $destination;
    $relativePath = 'uploads/cvs/' . $savedFileName;

    $stmt = $pdo->prepare(
        'INSERT INTO cv_file (profile_id, file_name, file_path, file_size)
         VALUES (?, ?, ?, ?)'
    );

    $stmt->execute([
        $profileId,
        basename($cv['name']),
        $relativePath,
        $cv['size']
    ]);
}
        } else {
            $companyName = trim($_POST['company_name'] ?? '');
            if ($companyName === '') {
                throw new Exception('Please enter your company name.');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO hr_profile (user_id, company_name)
                 VALUES (?, ?)'
            );
            $stmt->execute([$userId, $companyName]);
        }

        $pdo->commit();
        $signupSuccess = 'Account created successfully!';

        // A new candidate is signed in automatically and sent straight to the jobs page.
        if ($role === 'candidate') {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['role'] = 'candidate';
            header('Location: jobs.html');
            exit;
        }
    
} catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
            if (
        $savedFilePath !== null &&
        is_file($savedFilePath)
    ) {
        unlink($savedFilePath);
        $savedFilePath = null;
    }
        if ($e instanceof PDOException &&
            $e->getCode() === '23000') {
            $signupError = 'This email is already registered.';
        } elseif ($e instanceof PDOException) {
            error_log($e->getMessage());
            $signupError = 'Unable to create your account. Please try again.';
        } else {
            $signupError = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Account - Kawader</title>
  <link rel="stylesheet" href="style.css?v=3">
</head>

<body class="auth-page">
  <div class="auth-layout">
    <!-- =====================================================
         LEFT VISUAL SECTION
         ===================================================== -->
    <section class="auth-visual">
      <div class="visual-shape shape-one"></div>
      <div class="visual-shape shape-two"></div>
      <div class="visual-shape shape-three"></div>
      <div class="visual-content">
        <a href="home.html">
          <img
            src="../images/KawaderLogoLight.png"
            alt="Kawader"
            class="auth-logo"
          >
        </a>
        <div class="visual-text">
          
          <h1>
            Find the right
            <strong>match</strong>
          </h1>
          <p>
            A smarter way to connect talented candidates
            with the right opportunities.
          </p>
        </div>
    </section>
    <!-- =====================================================
         RIGHT FORM SECTION
         ===================================================== -->
    <section class="auth-form-side">
      <div class="auth-form-wrapper">
        <div class="mobile-logo">
          <img
            src="../images/KawaderLogo.png"
            alt="Kawader"
          >
        </div>
        <div class="form-heading">
          <span class="small-title">
            GET STARTED
          </span>
          <h2>
            Create your account
          </h2>
        </div>
        <!-- =================================================
             ROLE SELECTION
             ================================================= -->
        <div class="role-selection">
          <button
            type="button"
            class="role-card"
            id="candidateRole"
          >
            <div class="role-icon">
  <svg viewBox="0 0 24 24" aria-hidden="true">
    <circle cx="12" cy="8" r="3.5"></circle>
    <path d="M5 20c.8-4 3.1-6 7-6s6.2 2 7 6"></path>
  </svg>
</div>
            <div>
              <strong>Candidate</strong>
              <span>Find the right opportunity</span>
            </div>
            <div class="role-check">
              ✓
            </div>
          </button>
          <button
            type="button"
            class="role-card"
            id="hrRole"
          >
           <div class="role-icon">
  <svg viewBox="0 0 24 24" aria-hidden="true">
    <rect x="3" y="7" width="18" height="13" rx="2"></rect>
    <path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7"></path>
    <path d="M3 12h18"></path>
    <path d="M10 12v2h4v-2"></path>
  </svg>
</div>
            <div>
              <strong>HR Professional</strong>
              <span>Find the right candidates</span>
            </div>
            <div class="role-check">
              ✓
            </div>
          </button>
        </div>
        <!-- =================================================
             SIGN UP FORM
             ================================================= -->
<?php if (!empty($signupError)): ?>
  <div class="signup-alert signup-alert-error" role="alert">
    <span class="alert-icon">✕</span>
    <div class="alert-content">
      <strong>Registration Failed</strong>
      <p><?= htmlspecialchars($signupError, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>
<?php endif; ?>
<?php if (!empty($signupSuccess)): ?>
  <div class="signup-alert signup-alert-success" role="status">
    <span class="alert-icon">✓</span>
    <div class="alert-content">
      <strong>Account Created Successfully!</strong>
      <p>Your account is ready. You can now continue.</p>
    </div>
  </div>
<?php endif; ?>
        <form
          id="signupForm"
          class="signup-form"
          novalidate
          method="POST"
          action=""
          enctype="multipart/form-data"
        >
          <input
            type="hidden"
            id="role"
            name="role"
          >
          <!-- COMMON INFORMATION -->
          <div
            class="form-section"
            id="commonFields"
          >
            <div class="form-group">
              <label for="fullName">
                Full Name
              </label>
              <input
                type="text"
                id="fullName"
                name="full_name"
                placeholder="Enter your full name"
                required
              >
            </div>
            
<div class="form-group">
  <label for="email">
    Email
  </label>
  <input
    type="email"
    id="email"
    name="email"
    placeholder="you@example.com"
    required
  >
  <p id="emailMessage" class="otp-message" role="alert" hidden></p>
</div>

            
<!-- EMAIL OTP VERIFICATION -->
<div class="otp-container">
  <button type="button" id="sendOtpBtn" class="create-btn">
    Send Verification Code
  </button>

  <div class="form-group" id="otpSection" hidden>
    <label for="otpCode">Email Verification Code</label>
    <input
      type="text"
      id="otpCode"
      inputmode="numeric"
      maxlength="6"
      pattern="[0-9]{6}"
      placeholder="Enter the 6-digit code"
    >

    <button type="button" id="verifyOtpBtn" class="create-btn">
      Verify Email
    </button>

    <div id="otpMessage" class="otp-message" role="status" hidden></div>
  </div>
</div>

            <div class="form-row">
              <div class="form-group">
                <label for="password">

                  Password
                </label>
               <input
  type="password"
  id="password"
  name="password"
  placeholder="Create a password"
  minlength="8"
  pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
  required
\>
              </div>
              <div class="form-group">
                <label for="confirmPassword">
                  Confirm Password
                </label>
                <input
                  type="password"
                  id="confirmPassword"
                  name="confirm_password"
                  placeholder="Confirm password"
                  required
                >
                <div id="confirmMessage"></div>
              </div>
            </div>
            <div class="signup-password-strength">
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
            </div>
          </div>
          <!-- =================================================
               CANDIDATE INFORMATION
               ================================================= -->
          <div
            class="conditional-fields"
            id="candidateFields"
          >
            <div class="section-label">
              <span>01</span>
              Candidate information
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="phone">
                  Phone
                </label>
                <input
                  type="tel"
                  id="phone"
                  name="phone"
                  placeholder="+966 5X XXX XXXX"
                >
              </div>
              <div class="form-group">
                <label for="dob">
                  Date of Birth
                </label>
<input
  type="date"
  id="dob"
  name="date_of_birth"
  max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
  required
>
<p id="dobMessage" class="otp-message" role="alert" hidden></p>
              </div>
            </div>
            <div class="form-row">
              <div class="file-box">
                <label for="photo">
                  Profile Photo
                </label>
                <input
                  type="file"
                  id="photo"
                  name="photo"
                  accept="image/*"
                >
              </div>
              <div class="file-box">
                <label for="cv">
                  CV
                </label>
                <input
                  type="file"
                  id="cv"
                  name="cv"
                  accept=".pdf,application/pdf"
                >
                <small>
                  PDF only
                </small>
              </div>
            </div>
          </div>
          <!-- =================================================
               HR INFORMATION
               ================================================= -->
          <div
            class="conditional-fields"
            id="hrFields"
          >
            <div class="section-label">
              <span>01</span>
              HR Professional information
            </div>
            <div class="form-group">
              <label for="companyName">
                Company Name
              </label>
              <input
                type="text"
                id="companyName"
                name="company_name"
                placeholder="Enter your company name"
              >
            </div>
          </div>
          <!-- =================================================
               BUTTON
               ================================================= -->
          <button
            type="submit"
            class="create-btn"
            id="createBtn"
          >
            <span>
              Create Account
            </span>
            <span>
              →
            </span>
          </button>
        </form>
        <p class="signin-link">
          Already have an account?
          <a href="signin.php">
            Sign in
          </a>
        </p>
        <a
          href="home.html"
          class="back-link"
        >
          ← Back to home
        </a>
      </div>
    </section>
  </div>
  <!-- =====================================================
       SIGN UP JAVASCRIPT
       ===================================================== -->
 
<script>

const sendOtpBtn = document.getElementById("sendOtpBtn");
const verifyOtpBtn = document.getElementById("verifyOtpBtn");
const otpSection = document.getElementById("otpSection");
const otpCode = document.getElementById("otpCode");
const otpMessage = document.getElementById("otpMessage");
const emailMessage = document.getElementById("emailMessage");

function showOtpMessage(element, message, type) {
    element.textContent = message;
    element.className = "otp-message " + type;
    element.hidden = false;
}
let emailVerified = false;
let verifiedEmail = "";

sendOtpBtn.addEventListener("click", async function () {
    const emailValue = email.value.trim();

    if (!emailValue || !email.checkValidity()) {
        otpMessage.textContent = "Please enter a valid email first.";
        return;
    }

    sendOtpBtn.disabled = true;
    otpMessage.textContent = "Sending verification code...";

    try {
        const data = new URLSearchParams();
        data.append("email", emailValue);
        data.append("purpose", "signup");

        const response = await fetch("../api/request_otp.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: data.toString()
        });

       
const result = await response.json();

if (!result.success) {
    showOtpMessage(emailMessage, result.message, "error");
    return;
}

emailMessage.hidden = true;
otpSection.hidden = false;
otpCode.value = "";
showOtpMessage(otpMessage, result.message, "success");

    } catch (error) {
        otpMessage.textContent = "Unable to send the code. Please try again.";
    } finally {
        sendOtpBtn.disabled = false;
    }
});

verifyOtpBtn.addEventListener("click", async function () {
    const emailValue = email.value.trim();
    const code = otpCode.value.trim();

    if (!/^\d{6}$/.test(code)) {
        otpMessage.textContent = "Enter the six-digit code.";
        return;
    }

    verifyOtpBtn.disabled = true;
    otpMessage.textContent = "Verifying code...";

    try {
        const data = new URLSearchParams();
        data.append("email", emailValue);
        data.append("purpose", "signup");
        data.append("otp", code);

        const response = await fetch("../api/verify_otp.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: data.toString()
        });

        const result = await response.json();
        showOtpMessage(
    otpMessage,
    result.message,
    result.success ? "success" : "error"
);

        if (result.success) {
            emailVerified = true;
            verifiedEmail = emailValue;
            showOtpMessage(
    otpMessage,
    "✓ Email verified successfully!",
    "success"
);
        }
    } catch (error) {
        otpMessage.textContent = "Unable to verify the code. Please try again.";
    } finally {
        verifyOtpBtn.disabled = false;
    }
});



function showSignupMessage(type, title, message) {
    let alertBox = document.getElementById("signupDynamicAlert");
    if (!alertBox) {
        alertBox = document.createElement("div");
        alertBox.id = "signupDynamicAlert";
        alertBox.className = "signup-alert";
        const formHeading = document.querySelector(".form-heading");
        formHeading.insertAdjacentElement("afterend", alertBox);
    }
    const isSuccess = type === "success";
    alertBox.className = isSuccess
        ? "signup-alert signup-alert-success"
        : "signup-alert signup-alert-error";
    alertBox.setAttribute("role", "alert");
    alertBox.innerHTML = "";
    const icon = document.createElement("span");
    icon.className = "alert-icon";
    icon.textContent = isSuccess ? "✓" : "✕";
    const content = document.createElement("div");
    content.className = "alert-content";
    const heading = document.createElement("strong");
    heading.textContent = title;
    const description = document.createElement("p");
    description.textContent = message;
    content.append(heading, description);
    alertBox.append(icon, content);
}

function clearSignupMessage(expectedTitle) {
    const alertBox = document.getElementById("signupDynamicAlert");
    const heading = alertBox ? alertBox.querySelector("strong") : null;

    if (heading && heading.textContent === expectedTitle) {
        alertBox.remove();
    }
}

  const candidateRole =
    document.getElementById("candidateRole");
  const hrRole =
    document.getElementById("hrRole");
  const candidateFields =
    document.getElementById("candidateFields");
  const hrFields =
    document.getElementById("hrFields");
  const roleInput =
    document.getElementById("role");
  const fullName = document.getElementById("fullName");
  const email = document.getElementById("email");
  const password =
    document.getElementById("password");
  const confirmPassword =
    document.getElementById("confirmPassword");
  const confirmMessage =
  document.getElementById("confirmMessage");

function checkPasswordMatch() {
    if (confirmPassword.value === "") {
        confirmMessage.textContent = "";
        confirmMessage.style.color = "";
    } else if (confirmPassword.value === password.value) {
    confirmMessage.textContent = "✓ Passwords match";
    confirmMessage.style.color = "#29965a";
    clearSignupMessage("Passwords Don't Match");
    } else {
        confirmMessage.textContent = "✕ Passwords do not match";
        confirmMessage.style.color = "#e53935";
    }
}
confirmPassword.addEventListener("input", checkPasswordMatch);
password.addEventListener("input", checkPasswordMatch);
  const passwordRules =
    document.getElementById("passwordRules");
    const strengthText =
  document.getElementById("strengthText");
  const phone =
    document.getElementById("phone");
  const dob =
    document.getElementById("dob");
/* Location collection has been removed from signup. */
  const dobMessage = document.getElementById("dobMessage");

function validateDob(showMessage = true) {
    if (!dob.value) {
        dobMessage.hidden = true;
        dob.classList.remove("field-invalid");
        return false;
    }

    const birthDate = new Date(dob.value + "T00:00:00");
    const today = new Date();
    const cutoff = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
    const validAge = birthDate <= cutoff;

    dob.classList.toggle("field-invalid", !validAge);
    dobMessage.textContent = validAge
        ? ""
        : "You must be at least 18 years old to create a Kawader account.";
    dobMessage.hidden = validAge || !showMessage;
    return validAge;
}

dob.addEventListener("change", function () {
    validateDob(true);
    if (!dobMessage.hidden) dobMessage.scrollIntoView({ behavior: "smooth", block: "nearest" });
});

  const cv =
    document.getElementById("cv");
  const companyName =
    document.getElementById("companyName");
  
function selectRole(role) {
    roleInput.value = role;
    [fullName, email, phone, dob, cv, companyName].forEach(function (field) {
        field.classList.remove("field-invalid");
    });
clearSignupMessage("Select Your Account Type");
    phone.required = role === "candidate";
    dob.required = role === "candidate";
    
    cv.required = false;
    companyName.required = role === "hr";
    candidateRole.classList.remove("selected");
    hrRole.classList.remove("selected");
    candidateFields.classList.remove("show");
    hrFields.classList.remove("show");
    if (role === "candidate") {
      candidateRole.classList.add("selected");
      candidateFields.classList.add("show");
    }
    else {
      hrRole.classList.add("selected");
      hrFields.classList.add("show");
    }
  }

cv.addEventListener("change", function () {
    if (cv.files.length > 0 && cv.files[0].size > 5 * 1024 * 1024) {
        showSignupMessage(
            "error",
            "CV File Too Large",
            "Your CV must be smaller than 5 MB. Please choose another file."
        );
        cv.value = "";
    } else if (cv.files.length > 0) {
        clearSignupMessage("CV File Too Large");
    }
});
  candidateRole.addEventListener(
    "click",
    function () {
      selectRole("candidate");
    }
  );
  hrRole.addEventListener(
    "click",
    function () {
      selectRole("hr");
    }
  );
  password.addEventListener(
  "input",
  function () {
    if (password.value.length > 0) {
      passwordRules.classList.add("show");
    } else {
      passwordRules.classList.remove("show");
    }
    const value = password.value;
    const strengthProgress =
  document.getElementById("strengthProgress");
    const rules = passwordRules.querySelectorAll("div");
   rules[0].textContent =
  (value.length >= 8 ? "✓" : "○") +
  " At least 8 characters";
rules[0].className =
  value.length >= 8 ? "valid" : "invalid";
rules[1].textContent =
  (/[A-Z]/.test(value) ? "✓" : "○") +
  " One uppercase letter";
rules[1].className =
  /[A-Z]/.test(value) ? "valid" : "invalid";
rules[2].textContent =
  (/[a-z]/.test(value) ? "✓" : "○") +
  " One lowercase letter";
rules[2].className =
  /[a-z]/.test(value) ? "valid" : "invalid";
rules[3].textContent =
  (/[0-9]/.test(value) ? "✓" : "○") +
  " One number";
rules[3].className =
  /[0-9]/.test(value) ? "valid" : "invalid";
rules[4].textContent =
  (/[^A-Za-z0-9]/.test(value) ? "✓" : "○") +
  " One special character";
rules[4].textContent =
  (/[^A-Za-z0-9]/.test(value) ? "✓" : "○") +
  " One special character";
rules[4].className =
  /[^A-Za-z0-9]/.test(value) ? "valid" : "invalid";
const score =
  (value.length >= 8 ? 1 : 0) +
  (/[A-Z]/.test(value) ? 1 : 0) +
  (/[a-z]/.test(value) ? 1 : 0) +
  (/[0-9]/.test(value) ? 1 : 0) +
  (/[^A-Za-z0-9]/.test(value) ? 1 : 0);
if (value.length < 8) {
  strengthText.textContent = "Weak";
  strengthText.style.color = "#e53935";
} else if (score <= 4) {
  strengthText.textContent = "Medium";
  strengthText.style.color = "#f39c12";
} else {
  strengthText.textContent = "Strong";
  strengthText.style.color = "#29965a";
}
if (value.length < 8) {
  strengthProgress.style.width = "33%";
  strengthProgress.style.background = "#e53935";
} else if (score <= 4) {
  strengthProgress.style.width = "66%";
  strengthProgress.style.background = "#f39c12";
} else {
  strengthProgress.style.width = "100%";
  strengthProgress.style.background = "#29965a";
}
  }
);
  
const fieldsThatCanBeMarked = [fullName, email, phone, dob, cv, companyName];

fieldsThatCanBeMarked.forEach(function (field) {
    const eventName = field.type === "file" ? "change" : "input";

    field.addEventListener(eventName, function () {
        const hasValue = field.type === "file"
            ? field.files.length > 0
            : field.value.trim() !== "";

        if (hasValue) {
            field.classList.remove("field-invalid");
        }
    });
});

document
    .getElementById("signupForm")
    .addEventListener(
      "submit",
      function (e) {
        e.preventDefault();
if (!roleInput.value) {
    showSignupMessage(
        "error",
        "Select Your Account Type",
        "Please select Candidate or HR Professional."
    );
    return;
}

if (password.value === "") {
    showSignupMessage(
        "error",
        "Password Required",
        "Please enter your password to continue."
    );
    password.focus();
    return;
}

if (confirmPassword.value === "") {
    showSignupMessage(
        "error",
        "Confirm Your Password",
        "Please confirm your password to continue."
    );
    confirmPassword.focus();
    return;
}

if (roleInput.value === "candidate" && !validateDob(true)) {
    showSignupMessage(
        "error",
        "Age Requirement",
        dob.value
            ? "You must be at least 18 years old to create a Kawader account."
            : "Please enter your date of birth."
    );
    dob.focus();
    return;
}

const requiredFields = roleInput.value === "candidate"
    ? [fullName, email, phone, dob]
    : [fullName, email, companyName];

let hasMissingRequiredField = false;

requiredFields.forEach(function (field) {
    const isMissing = field.type === "file"
        ? field.files.length === 0
        : field.value.trim() === "";

    field.classList.toggle("field-invalid", isMissing);
    hasMissingRequiredField = hasMissingRequiredField || isMissing;
});

if (hasMissingRequiredField) {
    return;
}

if (!this.checkValidity()) {
    this.reportValidity();
    return;
}
if (
    password.value !== confirmPassword.value
) {
    showSignupMessage(
        "error",
        "Passwords Don't Match",
        "Please make sure both passwords are identical."
    );
    return;
}
        this.submit();
      }
    );
</script>
</body>
</html>

<style>
/* Signup password strength spans both password fields */
#signupForm .signup-password-strength {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    grid-column: 1 / -1;
    margin-top: -32px;
    position: relative;
}

#signupForm .signup-password-strength .strength-bar {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    margin-top: 0;
}

#signupForm .signup-password-strength .strength-text {
    margin-top: 8px;
}

#signupForm .signup-password-strength .password-rules {
    margin-top: 6px;
}
/* Move password match message only */
#signupForm #confirmMessage {
    position: relative;
    top: 10px;
    z-index: 5;
}
</style>
