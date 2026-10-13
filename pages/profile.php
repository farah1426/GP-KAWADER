<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Requests with an action return data or an image. Normal GET renders the page.
$isApiRequest = array_key_exists("action", $_GET)
    || ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "GET";

if ($isApiRequest) {
header("Content-Type: application/json; charset=utf-8");

header("Cache-Control: no-store");

function sendJson(array $data, int $status = 200): void

{

    http_response_code($status);

    echo json_encode($data, JSON_UNESCAPED_UNICODE);

    exit;

}

function checkCsrf(): void

{

    $token = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";

    if (!hash_equals($_SESSION["profile_csrf"], $token)) {

        sendJson(["message" => "Please refresh the page and try again."], 403);

    }

}

function textLength(string $text): int

{

    $length = preg_match_all('/./us', $text);

    return $length === false ? PHP_INT_MAX : $length;

}

function getSavedCV(PDO $pdo, int $profileId): ?array

{

    $statement = $pdo->prepare(

        "SELECT cv_id, file_name, file_size

         FROM cv_file

         WHERE profile_id = ?

         ORDER BY cv_id DESC

         LIMIT 1"

    );

    $statement->execute([$profileId]);

    $cv = $statement->fetch(PDO::FETCH_ASSOC);

    return $cv ? [

        "cvId" => (int) $cv["cv_id"],

        "originalName" => $cv["file_name"],

        "sizeBytes" => (int) $cv["file_size"]

    ] : null;

}

function photoFile(string $root, ?string $path): ?string

{

    if (!$path || !preg_match('~^photos/[a-f0-9]{32}\.jpg$~', $path)) {

        return null;

    }

    return $root . DIRECTORY_SEPARATOR

        . str_replace("/", DIRECTORY_SEPARATOR, $path);

}

function getUpload(string $field, int $maxSize): array

{

    $file = $_FILES[$field] ?? null;

    if (

        !$file ||

        !is_array($file) ||

        !isset($file["error"]) ||

        is_array($file["error"]) ||

        $file["error"] !== UPLOAD_ERR_OK

    ) {

        sendJson(["message" => "Upload failed. Check the file size."], 400);

    }

    if (!is_uploaded_file($file["tmp_name"])) {

        sendJson(["message" => "Invalid upload."], 400);

    }

    $size = filesize($file["tmp_name"]);

    if ($size === false || $size === 0 || $size > $maxSize) {

        sendJson(["message" => "The file is empty or too large."], 422);

    }

    $file["size"] = $size;

    return $file;

}

function createFolder(string $folder): void

{

    if (

        !is_dir($folder) &&

        !mkdir($folder, 0700, true) &&

        !is_dir($folder)

    ) {

        throw new RuntimeException("Unable to create storage folder.");

    }

}

// Login must set this session value after verifying the password.

$userId = filter_var(

    $_SESSION["user_id"] ?? null,

    FILTER_VALIDATE_INT,

    ["options" => ["min_range" => 1]]

);

if ($userId === false || $userId === null) {

    sendJson(["message" => "Please sign in to access your profile."], 401);

}

$pdo = null;

$newFile = null;

$fileSaved = false;

try {

    require __DIR__ . "/../Database_kawader/db.php";

    $statement = $pdo->prepare(

        "SELECT u.full_name, u.email,

                p.profile_id, p.phone, p.photo_path

         FROM `user` u

         LEFT JOIN candidate_profile p ON p.user_id = u.user_id

         WHERE u.user_id = ? AND u.role = 'candidate'"

    );

    $statement->execute([$userId]);

    $candidate = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$candidate) {

        sendJson(["message" => "Candidate access is required."], 403);

    }

    if (!$candidate["profile_id"]) {

        sendJson(["message" => "Your candidate profile has not been created."], 409);

    }

    $profileId = (int) $candidate["profile_id"];

    if (empty($_SESSION["profile_csrf"])) {

        $_SESSION["profile_csrf"] = bin2hex(random_bytes(32));

    }

    $method = $_SERVER["REQUEST_METHOD"];

    $action = $_GET["action"] ?? "profile";

    if (

        !is_string($action) ||

        !in_array($action, ["profile", "cv", "photo"], true)

    ) {

        sendJson(["message" => "Unknown action."], 404);

    }

    $allowed = [

        "profile" => ["GET", "PUT"],

        "cv" => ["GET", "POST"],

        "photo" => ["GET", "POST", "DELETE"]

    ];

    if (!in_array($method, $allowed[$action], true)) {

        header("Allow: " . implode(", ", $allowed[$action]));

        sendJson(["message" => "Method not allowed."], 405);

    }

    if ($method !== "GET") {

        checkCsrf();

    }

    $projectRoot = dirname(__DIR__);

    $storageRoot = getenv("KAWADER_STORAGE_DIR")

        ?: dirname($projectRoot) . DIRECTORY_SEPARATOR

            . basename($projectRoot) . "-storage";

    // ---------- Saved CV ----------

    if ($action === "cv") {

        if ($method === "GET") {

            sendJson(["cv" => getSavedCV($pdo, $profileId)]);

        }

        $file = getUpload("cv", 5 * 1024 * 1024);

        $temporaryPath = $file["tmp_name"];

        $originalName = basename(

            str_replace("\\", "/", (string) $file["name"])

        );

        if (

            strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== "pdf" ||

            textLength($originalName) > 255 ||

            preg_match('/[\x00-\x1F\x7F]/', $originalName)

        ) {

            sendJson(["message" => "Choose a PDF with a valid file name."], 422);

        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);

        $signature = file_get_contents($temporaryPath, false, null, 0, 5);

        if ($mime !== "application/pdf" || $signature !== "%PDF-") {

            sendJson(["message" => "Please choose a valid PDF."], 422);

        }

        $folder = $storageRoot . DIRECTORY_SEPARATOR . "cv";

        createFolder($folder);

        $storedName = bin2hex(random_bytes(16)) . ".pdf";

        $newFile = $folder . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($temporaryPath, $newFile)) {

            throw new RuntimeException("Unable to save CV.");

        }

        // Keep separate versions for previous job applications.

        $statement = $pdo->prepare(

            "INSERT INTO cv_file

                (profile_id, file_name, file_path, file_size)

             VALUES (?, ?, ?, ?)"

        );

        $statement->execute([

            $profileId,

            $originalName,

            "cv/" . $storedName,

            $file["size"]

        ]);

        $fileSaved = true;

        sendJson([

            "cv" => [

                "cvId" => (int) $pdo->lastInsertId(),

                "originalName" => $originalName,

                "sizeBytes" => $file["size"]

            ]

        ], 201);

    }

    // ---------- Profile photo ----------

    if ($action === "photo") {

        $oldFile = photoFile($storageRoot, $candidate["photo_path"]);

        if ($method === "GET") {

            if (!$oldFile || !is_file($oldFile)) {

                sendJson(["message" => "Photo not found."], 404);

            }

            header("Content-Type: image/jpeg");

            header("X-Content-Type-Options: nosniff");

            readfile($oldFile);

            exit;

        }

        if ($method === "DELETE") {

            $statement = $pdo->prepare(

                "UPDATE candidate_profile

                 SET photo_path = NULL

                 WHERE profile_id = ? AND user_id = ?"

            );

            $statement->execute([$profileId, $userId]);

            if ($oldFile && is_file($oldFile) && !unlink($oldFile)) {

                error_log("Unable to delete old profile photo.");

            }

            sendJson(["photoUrl" => null]);

        }

        $file = getUpload("photo", 2 * 1024 * 1024);

        $info = getimagesize($file["tmp_name"]);

        if (

            !$info ||

            !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)

        ) {

            sendJson(["message" => "Choose a valid JPG or PNG."], 422);

        }

        if (

            $info[0] > 6000 ||

            $info[1] > 6000 ||

            $info[0] * $info[1] > 12000000

        ) {

            sendJson(["message" => "Choose an image with smaller dimensions."], 422);

        }

        if (!function_exists("imagecreatefromstring")) {

            throw new RuntimeException("PHP GD extension is required.");

        }

        $image = imagecreatefromstring(

            file_get_contents($file["tmp_name"])

        );

        if (!$image) {

            sendJson(["message" => "This image could not be opened."], 422);

        }

        $scale = min(1, 800 / max($info[0], $info[1]));

        $width = max(1, (int) round($info[0] * $scale));

        $height = max(1, (int) round($info[1] * $scale));

        $output = imagecreatetruecolor($width, $height);

        imagefill(

            $output, 0, 0,

            imagecolorallocate($output, 255, 255, 255)

        );

        imagecopyresampled(

            $output, $image,

            0, 0, 0, 0,

            $width, $height, $info[0], $info[1]

        );

        $folder = $storageRoot . DIRECTORY_SEPARATOR . "photos";

        createFolder($folder);

        $storedName = bin2hex(random_bytes(16)) . ".jpg";

        $relativePath = "photos/" . $storedName;

        $newFile = $folder . DIRECTORY_SEPARATOR . $storedName;

        $written = imagejpeg($output, $newFile, 85);

        imagedestroy($image);

        imagedestroy($output);

        if (!$written) {

            throw new RuntimeException("Unable to save photo.");

        }

        $statement = $pdo->prepare(

            "UPDATE candidate_profile

             SET photo_path = ?

             WHERE profile_id = ? AND user_id = ?"

        );

        $statement->execute([$relativePath, $profileId, $userId]);

        $fileSaved = true;

        if ($oldFile && is_file($oldFile) && !unlink($oldFile)) {

            error_log("Unable to delete old profile photo.");

        }

        sendJson([

            "photoUrl" => "profile.php?action=photo&v="

                . hash("sha256", $relativePath)

        ]);

    }

    // ---------- Contact information ----------

    if ($method === "PUT") {

        $rawBody = file_get_contents("php://input");

        if ($rawBody === false || strlen($rawBody) > 8192) {

            sendJson(["message" => "Invalid request."], 400);

        }

        try {

            $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

        } catch (JsonException $error) {

            sendJson(["message" => "Invalid request."], 400);

        }

        if (

            !is_array($data) ||

            !isset($data["fullName"], $data["email"], $data["phone"]) ||

            !is_string($data["fullName"]) ||

            !is_string($data["email"]) ||

            !is_string($data["phone"])

        ) {

            sendJson(["message" => "Please complete all fields."], 422);

        }

        $fullName = trim($data["fullName"]);

        $email = trim($data["email"]);

        $phone = trim($data["phone"]);

        if ($fullName === "" || textLength($fullName) > 100) {

            sendJson(["message" => "Enter a name of up to 100 characters."], 422);

        }

        if (

            textLength($email) > 100 ||

            !filter_var($email, FILTER_VALIDATE_EMAIL)

        ) {

            sendJson(["message" => "Enter a valid email address."], 422);

        }

        $digits = preg_replace('/\D/', '', $phone);

        if (

            !preg_match('/^\+?[0-9 ()-]+$/', $phone) ||

            strlen($phone) > 20 ||

            strlen($digits) < 7 ||

            strlen($digits) > 15

        ) {

            sendJson(["message" => "Enter a valid phone number."], 422);

        }

        $pdo->beginTransaction();

        $statement = $pdo->prepare(

            "UPDATE `user`

             SET full_name = ?, email = ?

             WHERE user_id = ? AND role = 'candidate'"

        );

        $statement->execute([$fullName, $email, $userId]);

        $statement = $pdo->prepare(

            "UPDATE candidate_profile

             SET phone = ?

             WHERE profile_id = ? AND user_id = ?"

        );

        $statement->execute([$phone, $profileId, $userId]);

        $pdo->commit();

        $candidate["full_name"] = $fullName;

        $candidate["email"] = $email;

        $candidate["phone"] = $phone;

    }

    sendJson([

        "fullName" => $candidate["full_name"],

        "email" => $candidate["email"],

        "phone" => $candidate["phone"] ?? "",

        "photoUrl" => !empty($candidate["photo_path"])

            ? "profile.php?action=photo&v="

                . hash("sha256", $candidate["photo_path"])

            : null,

        "csrfToken" => $_SESSION["profile_csrf"],

        "cv" => getSavedCV($pdo, $profileId)

    ]);

} catch (Throwable $error) {

    if ($pdo instanceof PDO && $pdo->inTransaction()) {

        $pdo->rollBack();

    }

    if (!$fileSaved && $newFile && is_file($newFile)) {

        unlink($newFile);

    }

    error_log($error->getMessage());

    if (

        $error instanceof PDOException &&

        ($error->errorInfo[1] ?? null) === 1062

    ) {

        sendJson(["message" => "This email is already used by another account."], 409);

    }

    sendJson(["message" => "Unable to complete your request. Please try again."], 500);

}
}

// Require sign-in before displaying the candidate page.
$pageUserId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]);
if (!$pageUserId) {
    header("Location: signin.php");
    exit;
}
header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-store");
?>
<!DOCTYPE html>

<html lang="en">

<head>

  <meta charset="UTF-8">

  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Candidate Profile | Kawader</title>

  <link rel="stylesheet" href="style.css">

  <style>

    .candidate-profile {

      max-width: 820px;

      margin: 48px auto 64px;

      padding: 0 20px;

      color: #202052;

    }

    .candidate-profile * {

      box-sizing: border-box;

    }

    .candidate-profile [hidden] {

      display: none !important;

    }

    .candidate-profile .page-heading {

      margin-bottom: 24px;

    }

    .candidate-profile .page-heading h1 {

      margin: 0 0 8px;

      font-size: 30px;

    }

    .candidate-profile .muted {

      color: #716d83;

      line-height: 1.6;

    }

    .candidate-profile .profile-card {

      margin-bottom: 24px;

      padding: 28px;

      border: 1px solid #e7e2ee;

      border-radius: 18px;

      background: #fff;

      box-shadow: 0 6px 24px rgba(32, 32, 82, 0.04);

    }

    .candidate-profile .profile-top {

      display: flex;

      align-items: center;

      gap: 18px;

      margin-bottom: 28px;

    }

    .candidate-profile .avatar {

      width: 76px;

      height: 76px;

      flex-shrink: 0;

      display: grid;

      place-items: center;

      overflow: hidden;

      border-radius: 50%;

      background: #eee7fa;

      color: #6735ae;

      font-size: 25px;

      font-weight: 700;

    }

    .candidate-profile .avatar img {

      width: 100%;

      height: 100%;

      object-fit: cover;

    }

    .candidate-profile .identity {

      flex: 1;

      min-width: 0;

    }

    .candidate-profile .identity h2 {

      margin: 0 0 5px;

      font-size: 21px;

      overflow-wrap: anywhere;

    }

    .candidate-profile .identity p {

      margin: 0;

    }

    .candidate-profile .photo-actions {

      display: flex;

      flex-wrap: wrap;

      gap: 12px;

      margin-top: 8px;

    }

    .candidate-profile button {

      font: inherit;

      cursor: pointer;

    }

    .candidate-profile button:disabled {

      opacity: 0.6;

      cursor: wait;

    }

    .candidate-profile .outline-button,

    .candidate-profile .primary-button {

      min-height: 44px;

      padding: 10px 18px;

      border-radius: 10px;

      font-size: 14px;

      font-weight: 600;

    }

    .candidate-profile .outline-button {

      border: 1px solid #ddd5e9;

      background: #fff;

      color: #202052;

    }

    .candidate-profile .outline-button:hover {

      background: #f7f3fc;

    }

    .candidate-profile .primary-button {

      border: 1px solid #6735ae;

      background: #6735ae;

      color: #fff;

    }

    .candidate-profile .primary-button:hover {

      background: #542990;

    }

    .candidate-profile .text-button {

      padding: 0;

      border: 0;

      background: transparent;

      color: #6735ae;

      font-size: 12px;

      text-decoration: underline;

      text-underline-offset: 3px;

    }

    .candidate-profile button:focus-visible,

    .candidate-profile input:focus-visible {

      outline: 3px solid #b8a0e4;

      outline-offset: 3px;

    }

    .candidate-profile .details {

      display: grid;

      grid-template-columns: 120px minmax(0, 1fr);

      gap: 18px;

      margin: 0;

    }

    .candidate-profile .details dt {

      color: #716d83;

    }

    .candidate-profile .details dd {

      margin: 0;

      overflow-wrap: anywhere;

    }

    .candidate-profile .edit-form {

      padding-top: 24px;

      border-top: 1px solid #eee8f5;

    }

    .candidate-profile .field {

      margin-bottom: 18px;

    }

    .candidate-profile .field label {

      display: block;

      margin-bottom: 8px;

      font-size: 14px;

      font-weight: 600;

    }

    .candidate-profile .field input {

      width: 100%;

      min-height: 46px;

      padding: 11px 13px;

      border: 1px solid #d8cfe5;

      border-radius: 10px;

      background: #fff;

      color: #202052;

      font: inherit;

    }

    .candidate-profile .form-actions {

      display: flex;

      justify-content: flex-end;

      gap: 12px;

    }

    .candidate-profile .cv-heading {

      margin: 0 0 22px;

      font-size: 21px;

    }

    .candidate-profile .cv-row {

      display: flex;

      align-items: center;

      gap: 16px;

    }

    .candidate-profile .pdf-icon {

      width: 48px;

      height: 58px;

      flex-shrink: 0;

      display: grid;

      place-items: center;

      border-radius: 10px;

      background: #fae8ed;

      color: #a34862;

      font-size: 12px;

      font-weight: 700;

    }

    .candidate-profile .cv-info {

      flex: 1;

      min-width: 0;

    }

    .candidate-profile .cv-info strong {

      display: block;

      margin-bottom: 5px;

      overflow-wrap: anywhere;

    }

    .candidate-profile .cv-info p {

      margin: 0;

      font-size: 13px;

    }

    .candidate-profile .cv-note {

      margin: 20px 0 0;

      font-size: 13px;

    }

    .candidate-profile .status-message {

      margin: 16px 0;

      padding: 14px 16px;

      border-radius: 10px;

      background: #f1ebfa;

      color: #543181;

      line-height: 1.6;

    }

    .candidate-profile .status-message.error {

      background: #fff0f0;

      color: #a12e2e;

    }

    .candidate-profile .status-message.success {

      background: #edf8f1;

      color: #24663f;

    }

    .candidate-profile .preview-note {

      margin-top: 20px;

      font-size: 13px;

    }

    @media (max-width: 600px) {

      .candidate-profile {

        margin-top: 28px;

        padding: 0 16px;

      }

      .candidate-profile .profile-card {

        padding: 20px;

      }

      .candidate-profile .profile-top,

      .candidate-profile .cv-row {

        flex-wrap: wrap;

      }

      .candidate-profile .identity {

        flex-basis: calc(100% - 94px);

      }

      .candidate-profile #editButton,

      .candidate-profile #uploadButton {

        width: 100%;

      }

      .candidate-profile .details {

        grid-template-columns: 80px minmax(0, 1fr);

        gap: 16px;

        font-size: 14px;

      }

    }

  </style>

</head>

<body>

  <!-- Header supplied by layout.js -->

  <div

    id="site-header"

    data-menu="candidate"

    data-active="profile"

  ></div>

  <main class="candidate-profile">

    <div class="page-heading">

      <h1>My Profile</h1>

      <p class="muted">

        Manage your contact information and saved CV.

      </p>

    </div>

    <section class="profile-card" aria-labelledby="candidateName">

      <div class="profile-top">

        <div class="avatar">

          <span id="avatarInitials">?</span>

          <img id="avatarImage" alt="Your profile picture" hidden>

        </div>

        <div class="identity">

          <h2 id="candidateName">Candidate Profile</h2>

          <p class="muted">Your personal information</p>

          <div class="photo-actions">

            <button

              type="button"

              class="text-button"

              id="photoButton"

            >

              Upload photo

            </button>

            <button

              type="button"

              class="text-button"

              id="removePhotoButton"

              hidden

            >

              Remove photo

            </button>

          </div>

          <input

            type="file"

            id="photoInput"

            accept="image/jpeg,image/png"

            hidden

          >

        </div>

        <button

          type="button"

          class="outline-button"

          id="editButton"

        >

          Edit profile

        </button>

      </div>

      <dl class="details" id="profileDetails">

        <dt>Email</dt>

        <dd id="candidateEmail">—</dd>

        <dt>Phone</dt>

        <dd id="candidatePhone">—</dd>

      </dl>

      <form id="profileForm" class="edit-form" hidden>

        <div class="field">

          <label for="fullName">Full name</label>

          <input

            type="text"

            id="fullName"

            name="fullName"

            autocomplete="name"

            maxlength="100"

            required

          >

        </div>

        <div class="field">

          <label for="email">Email</label>

          <input

            type="email"

            id="email"

            name="email"

            autocomplete="email"

            maxlength="100"

            required

          >

        </div>

        <div class="field">

          <label for="phone">Phone number</label>

          <input

            type="tel"

            id="phone"

            name="phone"

            autocomplete="tel"

            maxlength="20"

            placeholder="+966 5X XXX XXXX"

            required

          >

        </div>

        <div class="form-actions">

          <button

            type="button"

            class="outline-button"

            id="cancelButton"

          >

            Cancel

          </button>

          <button

            type="submit"

            class="primary-button"

            id="saveButton"

          >

            Save changes

          </button>

        </div>

      </form>

    </section>

    <section class="profile-card" aria-labelledby="cvHeading">

      <h2 class="cv-heading" id="cvHeading">My CV</h2>

      <div class="cv-row">

        <div class="pdf-icon" aria-hidden="true">PDF</div>

        <div class="cv-info">

          <strong id="cvName">No CV uploaded yet</strong>

          <p class="muted" id="cvDetails">PDF only · Up to 5 MB</p>

        </div>

        <button

          type="button"

          class="outline-button"

          id="uploadButton"

        >

          Upload CV

        </button>

      </div>

      <input

        type="file"

        id="cvInput"

        accept=".pdf,application/pdf"

        hidden

      >

      <p class="muted cv-note">

        You can use your saved CV when applying for jobs.

      </p>

    </section>

    <p

      id="message"

      class="status-message"

      role="status"

      aria-live="polite"

      hidden

    ></p>

    <p class="muted preview-note" id="previewNote" hidden>

      Preview mode: changes are temporary and reset when you refresh.

      Permanent saving requires the PHP backend.

    </p>

  </main>

  <!-- Footer supplied by layout.js -->

  <div id="site-footer"></div>

  <script src="layout.js"></script>

  <script>

    (() => {

      "use strict";

      // The page and its API are handled by this same PHP file.

      const API = {

  profile: "profile.php?action=profile",

  cv: "profile.php?action=cv",

  photo: "profile.php?action=photo"

      };

      const previewMode = location.protocol === "file:";

      const MAX_CV_SIZE = 5 * 1024 * 1024;

      const MAX_PHOTO_SIZE = 2 * 1024 * 1024;

      let currentProfile = null;

      let previewPhotoURL = null;

      const ids = [

        "candidateName", "candidateEmail", "candidatePhone",

        "avatarInitials", "avatarImage", "photoButton",

        "removePhotoButton", "photoInput", "editButton",

        "profileDetails", "profileForm", "fullName", "email",

        "phone", "cancelButton", "saveButton", "uploadButton",

        "cvInput", "cvName", "cvDetails", "message", "previewNote"

      ];

      const elements = Object.fromEntries(

        ids.map(id => [id, document.getElementById(id)])

      );

      function showMessage(text, type = "") {

        elements.message.textContent = text;

        elements.message.className = "status-message " + type;

        elements.message.hidden = !text;

      }

      function getInitials(name) {

        return String(name || "")

          .trim()

          .split(/\s+/)

          .filter(Boolean)

          .slice(0, 2)

          .map(part => Array.from(part)[0])

          .join("")

          .toUpperCase() || "?";

      }

      function formatSize(bytes) {

        return bytes >= 1024 * 1024

          ? (bytes / (1024 * 1024)).toFixed(1) + " MB"

          : Math.ceil(bytes / 1024) + " KB";

      }

      function formatDate(value) {

        const date = new Date(String(value).replace(" ", "T"));

        return Number.isNaN(date.getTime())

          ? ""

          : date.toLocaleDateString("en-GB", {

              day: "numeric",

              month: "short",

              year: "numeric"

            });

      }

      function renderPhoto() {

        const photoURL = currentProfile.photoUrl || "";

        elements.avatarInitials.textContent =

          getInitials(currentProfile.fullName);

        elements.avatarInitials.hidden = Boolean(photoURL);

        elements.avatarImage.hidden = !photoURL;

        if (photoURL) {

          elements.avatarImage.src = photoURL;

        } else {

          elements.avatarImage.removeAttribute("src");

        }

        elements.photoButton.textContent =

          photoURL ? "Change photo" : "Upload photo";

        elements.removePhotoButton.hidden = !photoURL;

      }

      function renderCV(cv) {

        elements.cvName.textContent =

          cv ? cv.originalName : "No CV uploaded yet";

        elements.uploadButton.textContent =

          cv ? "Replace CV" : "Upload CV";

        if (!cv) {

          elements.cvDetails.textContent = "PDF only · Up to 5 MB";

          return;

        }

        const details = [];

        if (cv.uploadedAt) {

          const date = formatDate(cv.uploadedAt);

          if (date) details.push("Uploaded " + date);

        }

        if (cv.sizeBytes != null) {

          const size = Number(cv.sizeBytes);

          if (Number.isFinite(size)) details.push(formatSize(size));

        }

        elements.cvDetails.textContent = details.join(" · ");

      }

      function renderProfile(profile) {

        currentProfile = profile;

        elements.candidateName.textContent =

          profile.fullName || "Candidate Profile";

        elements.candidateEmail.textContent = profile.email || "—";

        elements.candidatePhone.textContent = profile.phone || "—";

        renderPhoto();

        renderCV(profile.cv || null);

      }

      function setBusy(busy) {

        [

          "editButton", "saveButton", "cancelButton",

          "uploadButton", "photoButton", "removePhotoButton"

        ].forEach(id => {

          elements[id].disabled = busy;

        });

      }

      function csrfHeaders() {

        return currentProfile && currentProfile.csrfToken

          ? { "X-CSRF-Token": currentProfile.csrfToken }

          : {};

      }

      async function request(url, options = {}) {

        const response = await fetch(url, {

          credentials: "same-origin",

          ...options

        });

        let data;

        try {

          data = await response.json();

        } catch {

          throw new Error(

            "The server returned an unexpected response. Check the PHP backend."

          );

        }

        if (!response.ok) {

          throw new Error(data.message || "The request failed.");

        }

        return data;

      }

      function openEditor() {

        if (!currentProfile) {

          showMessage("Please wait until your profile loads.", "error");

          return;

        }

        elements.fullName.value = currentProfile.fullName || "";

        elements.email.value = currentProfile.email || "";

        elements.phone.value = currentProfile.phone || "";

        elements.profileDetails.hidden = true;

        elements.profileForm.hidden = false;

        elements.editButton.hidden = true;

        showMessage("");

        elements.fullName.focus();

      }

      function closeEditor() {

        elements.profileDetails.hidden = false;

        elements.profileForm.hidden = true;

        elements.editButton.hidden = false;

      }

      elements.editButton.addEventListener("click", openEditor);

      elements.cancelButton.addEventListener("click", closeEditor);

      elements.profileForm.addEventListener("submit", async event => {

        event.preventDefault();

        const profile = {

          fullName: elements.fullName.value.trim(),

          email: elements.email.value.trim(),

          phone: elements.phone.value.trim()

        };

        if (!profile.fullName || !profile.email || !profile.phone) {

          showMessage("Please complete all fields.", "error");

          return;

        }

        if (!elements.profileForm.reportValidity()) return;

        if (previewMode) {

          renderProfile({ ...currentProfile, ...profile });

          closeEditor();

          showMessage(

            "Preview updated. These changes are not saved to the database.",

            "success"

          );

          return;

        }

        setBusy(true);

        showMessage("Saving your changes...");

        try {

          const data = await request(API.profile, {

            method: "PUT",

            headers: {

              "Content-Type": "application/json",

              ...csrfHeaders()

            },

            body: JSON.stringify(profile)

          });

          renderProfile(data);

          closeEditor();

          showMessage("Your profile has been updated.", "success");

        } catch (error) {

          showMessage(error.message, "error");

        } finally {

          setBusy(false);

        }

      });

      elements.uploadButton.addEventListener("click", () => {

        if (currentProfile) elements.cvInput.click();

      });

      elements.cvInput.addEventListener("change", async event => {

        const file = event.target.files[0];

        if (!file) return;

        setBusy(true);

        try {

          if (!/\.pdf$/i.test(file.name)) {

            throw new Error("Please choose a PDF file.");

          }

          if (file.size === 0 || file.size > MAX_CV_SIZE) {

            throw new Error("Choose a non-empty PDF no larger than 5 MB.");

          }

          const signature = await file.slice(0, 5).text();

          if (signature !== "%PDF-") {

            throw new Error("This file does not appear to be a valid PDF.");

          }

          if (previewMode) {

            currentProfile.cv = {

              originalName: file.name,

              sizeBytes: file.size,

              uploadedAt: new Date().toISOString()

            };

            renderCV(currentProfile.cv);

            showMessage(

              "CV preview updated. The file has not been uploaded or linked to Jobs.",

              "success"

            );

            return;

          }

          showMessage("Uploading your CV...");

          const formData = new FormData();

          formData.append("cv", file);

          const data = await request(API.cv, {

            method: "POST",

            headers: csrfHeaders(),

            body: formData

          });

          currentProfile.cv = data.cv;

          renderCV(data.cv);

          showMessage("Your CV has been saved.", "success");

        } catch (error) {

          showMessage(error.message, "error");

        } finally {

          elements.cvInput.value = "";

          setBusy(false);

        }

      });

      elements.photoButton.addEventListener("click", () => {

        if (currentProfile) elements.photoInput.click();

      });

      async function validatePhoto(file) {

        if (!["image/jpeg", "image/png"].includes(file.type)) {

          throw new Error("Please choose a JPG or PNG image.");

        }

        if (file.size === 0 || file.size > MAX_PHOTO_SIZE) {

          throw new Error("Choose a non-empty image no larger than 2 MB.");

        }

        const url = URL.createObjectURL(file);

        const img = new Image();

        try {

          await new Promise((resolve, reject) => {

            img.onload = resolve;

            img.onerror = () => reject(

              new Error("This image could not be opened.")

            );

            img.src = url;

          });

        } finally {

          URL.revokeObjectURL(url);

        }

      }

      function clearPreviewPhoto() {

        if (previewPhotoURL) {

          URL.revokeObjectURL(previewPhotoURL);

          previewPhotoURL = null;

        }

      }

      elements.photoInput.addEventListener("change", async event => {

        const file = event.target.files[0];

        if (!file) return;

        setBusy(true);

        try {

          await validatePhoto(file);

          if (previewMode) {

            clearPreviewPhoto();

            previewPhotoURL = URL.createObjectURL(file);

            currentProfile.photoUrl = previewPhotoURL;

            renderPhoto();

            showMessage(

              "Photo preview updated. The image is not permanently saved.",

              "success"

            );

            return;

          }

          showMessage("Uploading your photo...");

          const formData = new FormData();

          formData.append("photo", file);

          const data = await request(API.photo, {

            method: "POST",

            headers: csrfHeaders(),

            body: formData

          });

          currentProfile.photoUrl = data.photoUrl;

          renderPhoto();

          showMessage("Your photo has been updated.", "success");

        } catch (error) {

          showMessage(error.message, "error");

        } finally {

          elements.photoInput.value = "";

          setBusy(false);

        }

      });

      elements.removePhotoButton.addEventListener("click", async () => {

        if (!currentProfile) return;

        setBusy(true);

        try {

          if (!previewMode) {

            await request(API.photo, {

              method: "DELETE",

              headers: csrfHeaders()

            });

          }

          clearPreviewPhoto();

          currentProfile.photoUrl = null;

          renderPhoto();

          showMessage(

            previewMode

              ? "Photo removed from the preview."

              : "Your photo has been removed.",

            "success"

          );

        } catch (error) {

          showMessage(error.message, "error");

        } finally {

          setBusy(false);

        }

      });

      elements.avatarImage.addEventListener("error", () => {

        elements.avatarImage.hidden = true;

        elements.avatarInitials.hidden = false;

      });

      async function loadProfile() {

        elements.previewNote.hidden = !previewMode;

        if (previewMode) {

          elements.saveButton.textContent = "Update preview";

          renderProfile({

            fullName: "",

            email: "",

            phone: "",

            photoUrl: null,

            cv: null

          });

          return;

        }

        setBusy(true);

        showMessage("Loading your profile...");

        try {

          const data = await request(API.profile);

          renderProfile(data);

          showMessage("");

        } catch (error) {

          showMessage(error.message, "error");

        } finally {

          setBusy(false);

          if (!currentProfile) {

            elements.editButton.disabled = true;

            elements.uploadButton.disabled = true;

            elements.photoButton.disabled = true;

          }

        }

      }

      loadProfile();

    })();

  </script>

</body>

</html>