
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// HR pages are private
if (
    empty($_SESSION['user_id']) ||
    strtolower((string)($_SESSION['role'] ?? '')) !== 'hr'
) {
    header('Location: signin.php');
    exit;
}

$hrName = trim((string)($_SESSION['full_name'] ?? 'HR Professional'));
$firstName = preg_split('/\s+/', $hrName)[0] ?: 'there';

$companyName = '';
$jobs = [];
$activeJobs = 0;
$dbAvailable = false;
$jobsTableReady = false;
$jobLoadError = false;

$flashSuccess = (string)($_SESSION['hr_flash_success'] ?? '');
unset($_SESSION['hr_flash_success']);

try {
    require_once __DIR__ . '/../Database_kawader/db.php';

    // Get HR profile
    $profileStmt = $pdo->prepare(
        'SELECT hr_id, company_name
         FROM hr_profile
         WHERE user_id = :user_id
         LIMIT 1'
    );

    $profileStmt->execute([
        'user_id' => (int)$_SESSION['user_id']
    ]);

    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

    if ($profile && !empty($profile['company_name'])) {
        $companyName = trim((string)$profile['company_name']);
    }

    $dbAvailable = true;

    if (!$profile) {
        throw new RuntimeException('HR profile not found.');
    }

    $hrId = (int)$profile['hr_id'];

    // Job management actions are handled here to keep the project file set small.
    if (empty($_SESSION['hr_manage_csrf'])) {
        $_SESSION['hr_manage_csrf'] = bin2hex(random_bytes(32));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_action'])) {
        $token = (string)($_POST['csrf_token'] ?? '');
        if ($token === '' || !hash_equals((string)$_SESSION['hr_manage_csrf'], $token)) {
            throw new RuntimeException('Your session expired. Refresh the page and try again.');
        }

        $jobIdAction = trim((string)($_POST['job_id'] ?? ''));
        if ($jobIdAction === '' || strlen($jobIdAction) > 5) {
            throw new RuntimeException('Invalid job reference.');
        }

        $action = (string)$_POST['job_action'];
        if ($action === 'publish' || $action === 'close' || $action === 'reopen') {
            $newStatus = ['publish' => 'published', 'close' => 'closed', 'reopen' => 'draft'][$action];
            $allowedCurrent = $action === 'publish' ? 'draft' : ($action === 'close' ? 'published' : 'closed');
            $update = $pdo->prepare('UPDATE job_posting SET status = :new_status WHERE job_id = :job_id AND hr_id = :hr_id AND status = :current_status');
            $update->execute(['new_status' => $newStatus, 'job_id' => $jobIdAction, 'hr_id' => $hrId, 'current_status' => $allowedCurrent]);
            $_SESSION['hr_flash_success'] = $update->rowCount() ? 'Job status updated successfully.' : 'The job could not be updated. Refresh and check its current status.';
        } elseif ($action === 'edit') {
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $deadline = trim((string)($_POST['ends_at'] ?? ''));
            $date = DateTime::createFromFormat('Y-m-d', $deadline);
            if ($title === '' || mb_strlen($title) > 200) throw new RuntimeException('Enter a job title (up to 200 characters).');
            if ($description === '') throw new RuntimeException('Job description cannot be empty.');
            if (!$date || $date->format('Y-m-d') !== $deadline) throw new RuntimeException('Choose a valid application deadline.');
            $update = $pdo->prepare('UPDATE job_posting SET title = :title, description = :description, ends_at = :ends_at WHERE job_id = :job_id AND hr_id = :hr_id');
            $update->execute(['title' => $title, 'description' => $description, 'ends_at' => $deadline, 'job_id' => $jobIdAction, 'hr_id' => $hrId]);
            $_SESSION['hr_flash_success'] = 'Job details saved successfully.';
        } else {
            throw new RuntimeException('Unknown job action.');
        }

        header('Location: hr-dashboard.php#jobPostings');
        exit;
    }

    // Load jobs from the actual job_posting table
    $jobsStmt = $pdo->prepare(
        'SELECT
            job_id,
            title,
            department,
            location,
            employment_type,
            work_arrangement,
            status,
            created_at,
            ends_at
         FROM job_posting
         WHERE hr_id = :hr_id
         ORDER BY created_at DESC, job_id DESC'
    );

    $jobsStmt->execute([
        'hr_id' => $hrId
    ]);

    $jobs = $jobsStmt->fetchAll(PDO::FETCH_ASSOC);
    $jobsTableReady = true;

    // Count published/open jobs as active
    foreach ($jobs as $job) {
        $status = strtolower((string)($job['status'] ?? ''));

        if (in_array($status, ['published', 'open'], true)) {
            $activeJobs++;
        }
    }

} catch (Throwable $e) {
    $jobLoadError = true;
    error_log('Kawader HR dashboard error: ' . $e->getMessage());
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#241044">

    <title>HR Dashboard | Kawader</title>

<link rel="stylesheet" href="style.css?v=20261121">
</head>

<body class="hr-dashboard-page">

<div class="hr-app-shell">

    <!-- SIDEBAR -->
    <aside class="hr-sidebar" id="hrSidebar" aria-label="HR navigation">

        <a class="hr-brand"
           href="hr-dashboard.php"
           aria-label="Kawader HR home">

            <img class="hr-brand-logo"
                 src="../images/KawaderLogoLight.png"
                 alt="Kawader">

            <span class="hr-brand-workspace">HR WORKSPACE</span>
        </a>

        <div class="hr-sidebar-label">WORKSPACE</div>

        <nav class="hr-side-nav">

            <a class="hr-nav-link is-active"
               href="hr-dashboard.php"
               aria-current="page">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                    <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                    <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                    <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                </svg>

                <span>Dashboard</span>
            </a>

            <a class="hr-nav-link" href="hr-create-job.php">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                    <path d="M16 3v4M8 3v4M3 10h18"/>
                </svg>

                <span>Create a Job</span>
            </a>

            <div class="hr-nav-link is-disabled"
                 aria-disabled="true"
                 title="Coming in the next development step">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="9" cy="8" r="3.5"/>
                    <path d="M2.5 20c.5-3.5 2.7-5.3 6.5-5.3s6 1.8 6.5 5.3M16 5.5a3.5 3.5 0 0 1 0 6.8M17 15c2.6.4 4.1 2 4.5 5"/>
                </svg>

                <span>Candidates</span>
                <small>Next</small>
            </div>

            <div class="hr-nav-link is-disabled"
                 aria-disabled="true"
                 title="Coming in the next development step">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 5h16M4 12h16M4 19h16"/>
                    <circle cx="8" cy="5" r="2"/>
                    <circle cx="15" cy="12" r="2"/>
                    <circle cx="11" cy="19" r="2"/>
                </svg>

                <span>Compare candidates</span>
                <small>Next</small>
            </div>

        </nav>

        <div class="hr-sidebar-bottom">

            <div class="hr-help-card">
                <span class="hr-help-icon">✦</span>

                <strong>AI-assisted hiring</strong>

                <p>
                    Kawader supports your review with matching insights
                    and evidence. The final decision stays with HR.
                </p>
            </div>

            <a class="hr-signout" href="home.html">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>
                </svg>

                <span>Sign out</span>
            </a>

        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="hr-main">

        <!-- TOPBAR -->
        <header class="hr-topbar">

            <button class="hr-menu-toggle"
                    id="sidebarToggle"
                    type="button"
                    aria-label="Open navigation"
                    aria-controls="hrSidebar"
                    aria-expanded="false">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="hr-breadcrumb">
                <span>Workspace</span>
                <b>/</b>
                <strong>Dashboard</strong>
            </div>

            <div class="hr-topbar-right">

                <span class="hr-topbar-date" id="todayDate"></span>

                <div class="hr-user-chip">

                    <span class="hr-avatar">
                        <?= h(strtoupper(substr($firstName, 0, 1))) ?>
                    </span>

                    <span class="hr-user-meta">
                        <strong><?= h($hrName) ?></strong>
                        <small>HR Professional</small>
                    </span>

                </div>

            </div>

        </header>

        <div class="hr-content">

            <!-- WELCOME -->
            <section class="hr-welcome-row">

                <div>

                    <div class="hr-eyebrow">
                        <span class="hr-eyebrow-dot"></span>
                        YOUR RECRUITMENT OVERVIEW
                    </div>

                    <h1>
                        Good to see you, <?= h($firstName) ?>
                        <span class="hr-wave">✦</span>
                    </h1>

                    <p>
                        Here’s an overview of your hiring workspace
                        <?= $companyName !== ''
                            ? ' at <strong>' . h($companyName) . '</strong>'
                            : '' ?>.
                    </p>

                </div>

                <div class="hr-welcome-actions">
                    <span class="hr-status-pill">
                        <i></i> Workspace ready
                    </span>
                </div>

            </section>

            <!-- STATISTICS -->
            <section class="hr-stat-grid" aria-label="Recruitment overview">

                <article class="hr-stat-card">

                    <div class="hr-stat-top">
                        <span class="hr-stat-label">Active jobs</span>

                        <span class="hr-stat-icon lavender">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="3" y="7" width="18" height="14" rx="2"/>
                                <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/>
                            </svg>
                        </span>
                    </div>

                    <div class="hr-stat-value">
                        <?= $jobsTableReady ? (int)$activeJobs : '—' ?>
                    </div>

                    <div class="hr-stat-foot">
                        <?= $jobsTableReady
                            ? 'Published job postings'
                            : 'Could not load job postings' ?>
                    </div>

                </article>

                <article class="hr-stat-card">

                    <div class="hr-stat-top">
                        <span class="hr-stat-label">Total applicants</span>

                        <span class="hr-stat-icon mint">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="9" cy="8" r="3.5"/>
                                <path d="M2.5 20c.5-3.5 2.7-5.3 6.5-5.3s6 1.8 6.5 5.3M17 8h5M19.5 5.5v5"/>
                            </svg>
                        </span>
                    </div>

                    <div class="hr-stat-value">—</div>

                    <div class="hr-stat-foot">
                        Available after applications are connected
                    </div>

                </article>

                <article class="hr-stat-card">

                    <div class="hr-stat-top">
                        <span class="hr-stat-label">Candidates to review</span>

                        <span class="hr-stat-icon peach">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M8 3h8l4 4v14H4V3zM8 12h8M8 16h5M16 3v5h4"/>
                            </svg>
                        </span>
                    </div>

                    <div class="hr-stat-value">—</div>

                    <div class="hr-stat-foot">
                        Requires candidate evaluation data
                    </div>

                </article>

                <article class="hr-stat-card">

                    <div class="hr-stat-top">
                        <span class="hr-stat-label">Shortlisted</span>

                        <span class="hr-stat-icon blue">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M5 3h14v18l-7-4-7 4z"/>
                                <path d="M9 9l2 2 4-4"/>
                            </svg>
                        </span>
                    </div>

                    <div class="hr-stat-value">—</div>

                    <div class="hr-stat-foot">
                        Available after AI ranking is integrated
                    </div>

                </article>

            </section>

            <!-- JOB POSTINGS AND WORKFLOW -->
            <section class="hr-primary-grid">

                <article class="hr-panel hr-jobs-panel" id="jobPostings">

                    <div class="hr-panel-heading">

                        <div>
                            <span class="hr-section-kicker">YOUR WORK</span>
                            <h2>Job postings</h2>
                            <p>Manage the opportunities you share with candidates.</p>
                        </div>

                        <a class="hr-create-job-link" href="hr-create-job.php">
                            <span>+</span> Create a job
                        </a>

                    </div>

                    <?php if ($flashSuccess !== ''): ?>

                        <div class="hr-dashboard-success" role="status">
                            <?= h($flashSuccess) ?>
                        </div>

                    <?php endif; ?>

                    <?php if (!$jobsTableReady): ?>

                        <div class="hr-empty-state">

                            <div class="hr-empty-illustration">
                                <span class="hr-empty-file">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="4" y="3" width="16" height="18" rx="2"/>
                                        <path d="M8 8h8M8 12h8M8 16h5"/>
                                    </svg>
                                </span>

                                <span class="hr-empty-plus">!</span>
                            </div>

                            <h3>Unable to load job postings</h3>

                            <p>
                                We couldn't retrieve your job postings.
                                Please check your database connection or try again.
                            </p>

                        </div>

                    <?php elseif (count($jobs) === 0): ?>

                        <div class="hr-empty-state">

                            <div class="hr-empty-illustration">
                                <span class="hr-empty-file">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="4" y="3" width="16" height="18" rx="2"/>
                                        <path d="M8 8h8M8 12h8M8 16h5"/>
                                    </svg>
                                </span>

                                <span class="hr-empty-plus">+</span>
                            </div>

                            <h3>No job postings yet</h3>

                            <p>
                                Create your first job posting.
                                Saved drafts will appear here automatically
                                and stay visible only in your HR workspace.
                            </p>

                            <a class="hr-outline-note hr-empty-cta"
                               href="hr-create-job.php">
                                <i></i> Start your first job posting →
                            </a>

                        </div>

                    <?php else: ?>

                        <?php
                            $statusFilter = (string)($_GET['status'] ?? 'all');
                            if (!in_array($statusFilter, ['all', 'draft', 'published', 'closed'], true)) $statusFilter = 'all';
                            $visibleJobs = array_values(array_filter($jobs, static function ($item) use ($statusFilter) {
                                return $statusFilter === 'all' || strtolower((string)$item['status']) === $statusFilter;
                            }));
                            $draftCount = count(array_filter($jobs, static fn($j) => strtolower((string)$j['status']) === 'draft'));
                            $publishedCount = count(array_filter($jobs, static fn($j) => strtolower((string)$j['status']) === 'published'));
                            $closedCount = count(array_filter($jobs, static fn($j) => strtolower((string)$j['status']) === 'closed'));
                        ?>

                        <div class="hr-job-filters" aria-label="Filter job postings">
                            <a class="<?= $statusFilter === 'all' ? 'active' : '' ?>" href="hr-dashboard.php?status=all#jobPostings">All <b><?= count($jobs) ?></b></a>
                            <a class="<?= $statusFilter === 'published' ? 'active' : '' ?>" href="hr-dashboard.php?status=published#jobPostings">Published <b><?= $publishedCount ?></b></a>
                            <a class="<?= $statusFilter === 'draft' ? 'active' : '' ?>" href="hr-dashboard.php?status=draft#jobPostings">Drafts <b><?= $draftCount ?></b></a>
                            <a class="<?= $statusFilter === 'closed' ? 'active' : '' ?>" href="hr-dashboard.php?status=closed#jobPostings">Closed <b><?= $closedCount ?></b></a>
                        </div>

                        <?php if (!$visibleJobs): ?>
                            <div class="hr-empty-state"><h3>No jobs in this view</h3><p>Try another status filter or create a new job posting.</p></div>
                        <?php else: ?>
                            <div class="hr-job-list">
                                <?php foreach ($visibleJobs as $job): ?>
                                    <?php
                                        $status = strtolower((string)($job['status'] ?? 'draft'));
                                        $jobDate = !empty($job['created_at']) ? date('M j, Y', strtotime((string)$job['created_at'])) : '';
                                        $deadline = !empty($job['ends_at']) ? date('M j, Y', strtotime((string)$job['ends_at'])) : 'Not specified';
                                        $jobMeta = array_filter([(string)($job['department'] ?? ''), (string)($job['location'] ?? ''), (string)($job['employment_type'] ?? '')], static fn($v) => trim($v) !== '');
                                        $jobId = (string)$job['job_id'];
                                    ?>
                                    <div class="hr-job-row hr-job-row-managed">
                                        <span class="hr-job-row-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                                        <div class="hr-job-row-main">
                                            <strong><?= h((string)$job['title']) ?></strong>
                                            <small><?= h(implode(' · ', $jobMeta)) ?></small>
                                            <span class="hr-job-row-date">Created <?= h($jobDate) ?> · JOB-<?= h($jobId) ?> · Deadline <?= h($deadline) ?></span>
                                            <details class="hr-inline-edit">
                                                <summary>Edit details</summary>
                                                <form method="post" class="hr-inline-edit-form">
                                                    <input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['hr_manage_csrf']) ?>">
                                                    <input type="hidden" name="job_id" value="<?= h($jobId) ?>">
                                                    <input type="hidden" name="job_action" value="edit">
                                                    <label>Job title<input name="title" maxlength="200" required value="<?= h((string)$job['title']) ?>"></label>
                                                    <label>Job description<textarea name="description" rows="3" required><?= h((string)($job['description'] ?? '')) ?></textarea></label>
                                                    <label>Application deadline<input type="date" name="ends_at" required value="<?= h((string)($job['ends_at'] ?? '')) ?>"></label>
                                                    <button type="submit">Save changes</button>
                                                </form>
                                            </details>
                                        </div>
                                        <span class="hr-job-status <?= h($status) ?>"><?= h(ucfirst($status)) ?></span>
                                        <div class="hr-job-actions">
                                            <?php if ($status === 'draft'): ?>
                                                <form method="post"><input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['hr_manage_csrf']) ?>"><input type="hidden" name="job_id" value="<?= h($jobId) ?>"><input type="hidden" name="job_action" value="publish"><button class="hr-job-action publish" type="submit">Publish</button></form>
                                            <?php elseif ($status === 'published'): ?>
                                                <form method="post" onsubmit="return confirm('Close this job posting?')"><input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['hr_manage_csrf']) ?>"><input type="hidden" name="job_id" value="<?= h($jobId) ?>"><input type="hidden" name="job_action" value="close"><button class="hr-job-action close" type="submit">Close</button></form>
                                            <?php elseif ($status === 'closed'): ?>
                                                <form method="post"><input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['hr_manage_csrf']) ?>"><input type="hidden" name="job_id" value="<?= h($jobId) ?>"><input type="hidden" name="job_action" value="reopen"><button class="hr-job-action reopen" type="submit">Move to draft</button></form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                </article>

                <!-- HIRING WORKFLOW -->
                <article class="hr-panel hr-journey-panel">

                    <div class="hr-panel-heading">
                        <div>
                            <span class="hr-section-kicker">KAWADER WORKFLOW</span>
                            <h2>Hiring journey</h2>
                            <p>How your recruitment process will flow.</p>
                        </div>
                    </div>

                    <div class="hr-journey-list">

                        <div class="hr-journey-item">
                            <span class="hr-journey-number">01</span>

                            <span class="hr-journey-symbol">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="4" y="3" width="16" height="18" rx="2"/>
                                    <path d="M8 8h8M8 12h8M8 16h5"/>
                                </svg>
                            </span>

                            <div>
                                <strong>Create a job</strong>
                                <small>Define skills and mandatory requirements</small>
                            </div>

                            <span class="hr-journey-state">Planned</span>
                        </div>

                        <div class="hr-journey-item">
                            <span class="hr-journey-number">02</span>

                            <span class="hr-journey-symbol">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 16V4m-5 5 5-5 5 5M4 20h16"/>
                                </svg>
                            </span>

                            <div>
                                <strong>Receive applications</strong>
                                <small>Candidates submit their CVs</small>
                            </div>

                            <span class="hr-journey-state">Planned</span>
                        </div>

                        <div class="hr-journey-item">
                            <span class="hr-journey-number">03</span>

                            <span class="hr-journey-symbol">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="10.5" cy="10.5" r="6.5"/>
                                    <path d="m16 16 5 5M8 10h5M10.5 7.5v5"/>
                                </svg>
                            </span>

                            <div>
                                <strong>Evaluate &amp; rank</strong>
                                <small>Review matching results and requirement flags</small>
                            </div>

                            <span class="hr-journey-state">Planned</span>
                        </div>

                        <div class="hr-journey-item">
                            <span class="hr-journey-number">04</span>

                            <span class="hr-journey-symbol">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="m5 12 4 4L19 6"/>
                                    <circle cx="12" cy="12" r="9"/>
                                </svg>
                            </span>

                            <div>
                                <strong>Make the decision</strong>
                                <small>Review evidence and record your decision</small>
                            </div>

                            <span class="hr-journey-state">HR-led</span>
                        </div>

                    </div>

                </article>

            </section>

            <!-- BOTTOM SECTION -->
            <section class="hr-bottom-grid">

                <article class="hr-panel hr-activity-panel">

                    <div class="hr-panel-heading">
                        <div>
                            <span class="hr-section-kicker">LATEST UPDATES</span>
                            <h2>Recent activity</h2>
                        </div>

                        <span class="hr-muted-label">
                            Live activity will appear here
                        </span>
                    </div>

                    <div class="hr-activity-empty">

                        <span class="hr-activity-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 8v4l2.5 2.5M20 12a8 8 0 1 1-2.34-5.66L20 8"/>
                            </svg>
                        </span>

                        <div>
                            <strong>No activity to show yet</strong>
                            <p>
                                Job and application updates will appear here
                                as your workspace becomes connected.
                            </p>
                        </div>

                    </div>

                </article>

                <article class="hr-panel hr-principle-panel">

                    <div class="hr-principle-icon">✦</div>

                    <span class="hr-section-kicker">
                        BUILT FOR RESPONSIBLE HIRING
                    </span>

                    <h2>AI supports.<br>HR decides.</h2>

                    <p>
                        Kawader provides candidate matching, explanations
                        and requirement flags to support your review—not
                        to replace your professional judgment.
                    </p>

                    <div class="hr-principle-foot">
                        <span></span>
                        Final hiring decisions stay with HR
                    </div>

                </article>

            </section>

            <footer class="hr-footer">
                <span>© 2026 Kawader</span>
                <span>AI-powered recruitment workspace</span>
            </footer>

        </div>

    </main>

</div>

<div class="hr-mobile-backdrop" id="mobileBackdrop"></div>

<script>
(function () {
  const dateNode = document.getElementById('todayDate');
  if (dateNode) {
    dateNode.textContent = new Intl.DateTimeFormat(undefined, {
      weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
    }).format(new Date());
  }

  const sidebar = document.getElementById('hrSidebar');
  const toggle = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('mobileBackdrop');

  function closeSidebar() {
    if (!sidebar || !toggle || !backdrop) return;
    sidebar.classList.remove('is-open');
    backdrop.classList.remove('is-visible');
    toggle.setAttribute('aria-expanded', 'false');
  }

  if (sidebar && toggle && backdrop) {
    toggle.addEventListener('click', function () {
      const isOpen = sidebar.classList.toggle('is-open');
      backdrop.classList.toggle('is-visible', isOpen);
      toggle.setAttribute('aria-expanded', String(isOpen));
    });
    backdrop.addEventListener('click', closeSidebar);
    window.addEventListener('resize', function () {
      if (window.innerWidth > 760) closeSidebar();
    });
  }
})();
</script>
</body>
</html>
