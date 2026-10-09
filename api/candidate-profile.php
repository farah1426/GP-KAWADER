<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

session_start();

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
    if (!$path || !preg_match('/^photos\/[a-f0-9]{32}\.jpg$/', $path)) {
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
            "photoUrl" => "../api/candidate-profile.php?action=photo&v="
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
            ? "../api/candidate-profile.php?action=photo&v="
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