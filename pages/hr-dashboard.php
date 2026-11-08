<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// HR pages are private: candidates and signed-out visitors cannot open this page.
if (empty($_SESSION['user_id']) || strtolower((string)($_SESSION['role'] ?? '')) !== 'hr') {
    header('Location: signin.php');
    exit;
}

$hrName = trim((string)($_SESSION['full_name'] ?? 'HR Professional'));
$firstName = preg_split('/\s+/', $hrName)[0] ?: 'there';
$companyName = '';
$dbAvailable = false;
$jobsTableReady = false;
$jobs = [];
$activeJobs = 0;
$jobLoadError = false;
$flashSuccess = (string)($_SESSION['hr_flash_success'] ?? '');
unset($_SESSION['hr_flash_success']);

try {
    require_once __DIR__ . '/../Database_kawader/db.php';
    $profileStmt = $pdo->prepare('SELECT company_name FROM hr_profile WHERE user_id = :user_id LIMIT 1');
    $profileStmt->execute(['user_id' => (int)$_SESSION['user_id']]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
    if ($profile && !empty($profile['company_name'])) {
        $companyName = trim((string)$profile['company_name']);
    }
    $dbAvailable = true;

    try {
        $jobsStmt = $pdo->prepare(
            'SELECT job_id, job_title, department, location, employment_type, work_arrangement, status, created_at
             FROM jobs WHERE hr_user_id = :hr_user_id ORDER BY created_at DESC, job_id DESC'
        );
        $jobsStmt->execute(['hr_user_id' => (int)$_SESSION['user_id']]);
        $jobs = $jobsStmt->fetchAll(PDO::FETCH_ASSOC);
        $jobsTableReady = true;
        foreach ($jobs as $job) {
            if (($job['status'] ?? '') === 'published') $activeJobs++;
        }
    } catch (Throwable $e) {
        // A separate migration is required for existing databases; do not pretend jobs were loaded.
        $jobLoadError = true;
        error_log('Kawader HR jobs lookup: ' . $e->getMessage());
    }
} catch (Throwable $e) {
    $jobLoadError = true;
    error_log('Kawader HR dashboard database lookup: ' . $e->getMessage());
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
  <link rel="stylesheet" href="hr-dashboard.css?v=1">
</head>
<body class="hr-dashboard-page">
  <div class="hr-app-shell">
    <aside class="hr-sidebar" id="hrSidebar" aria-label="HR navigation">
      <a class="hr-brand" href="hr-dashboard.php" aria-label="Kawader HR home">
        <img class="hr-brand-logo" src="../images/KawaderLogoLight.png" alt="Kawader">
        <span class="hr-brand-workspace">HR WORKSPACE</span>
      </a>

      <div class="hr-sidebar-label">WORKSPACE</div>
      <nav class="hr-side-nav">
        <a class="hr-nav-link is-active" href="hr-dashboard.php" aria-current="page">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
          <span>Dashboard</span>
        </a>
        <a class="hr-nav-link" href="#jobPostings">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
          <span>Job postings</span>
        </a>
        <div class="hr-nav-link is-disabled" aria-disabled="true" title="Coming in the next development step">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.5-3.5 2.7-5.3 6.5-5.3s6 1.8 6.5 5.3M16 5.5a3.5 3.5 0 0 1 0 6.8M17 15c2.6.4 4.1 2 4.5 5"/></svg>
          <span>Candidates</span><small>Next</small>
        </div>
        <div class="hr-nav-link is-disabled" aria-disabled="true" title="Coming in the next development step">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16M4 12h16M4 19h16"/><circle cx="8" cy="5" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="11" cy="19" r="2"/></svg>
          <span>Compare candidates</span><small>Next</small>
        </div>
      </nav>

      <div class="hr-sidebar-bottom">
        <div class="hr-help-card">
          <span class="hr-help-icon">✦</span>
          <strong>AI-assisted hiring</strong>
          <p>Kawader supports your review with matching insights and evidence. The final decision stays with HR.</p>
        </div>
        <a class="hr-signout" href="home.html">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
          <span>Sign out</span>
        </a>
      </div>
    </aside>

    <main class="hr-main">
      <header class="hr-topbar">
        <button class="hr-menu-toggle" id="sidebarToggle" type="button" aria-label="Open navigation" aria-controls="hrSidebar" aria-expanded="false">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="hr-breadcrumb"><span>Workspace</span><b>/</b><strong>Dashboard</strong></div>
        <div class="hr-topbar-right">
          <span class="hr-topbar-date" id="todayDate"></span>
          <div class="hr-user-chip">
            <span class="hr-avatar"><?= h(strtoupper(substr($firstName, 0, 1))) ?></span>
            <span class="hr-user-meta"><strong><?= h($hrName) ?></strong><small>HR Professional</small></span>
          </div>
        </div>
      </header>

      <div class="hr-content">
        <section class="hr-welcome-row">
          <div>
            <div class="hr-eyebrow"><span class="hr-eyebrow-dot"></span> YOUR RECRUITMENT OVERVIEW</div>
            <h1>Good to see you, <?= h($firstName) ?> <span class="hr-wave">✦</span></h1>
            <p>Here’s an overview of your hiring workspace<?= $companyName !== '' ? ' at <strong>' . h($companyName) . '</strong>' : '' ?>.</p>
          </div>
          <div class="hr-welcome-actions">
            <span class="hr-status-pill"><i></i> Workspace ready</span>
          </div>
        </section>

        <section class="hr-stat-grid" aria-label="Recruitment overview">
          <article class="hr-stat-card">
            <div class="hr-stat-top"><span class="hr-stat-label">Active jobs</span><span class="hr-stat-icon lavender"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/></svg></span></div>
            <div class="hr-stat-value"><?= $jobsTableReady ? (int)$activeJobs : '—' ?></div><div class="hr-stat-foot"><?= $jobsTableReady ? 'Published job postings' : 'Available after database setup' ?></div>
          </article>
          <article class="hr-stat-card">
            <div class="hr-stat-top"><span class="hr-stat-label">Total applicants</span><span class="hr-stat-icon mint"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.5-3.5 2.7-5.3 6.5-5.3s6 1.8 6.5 5.3M17 8h5M19.5 5.5v5"/></svg></span></div>
            <div class="hr-stat-value">—</div><div class="hr-stat-foot">Available after Applications are connected</div>
          </article>
          <article class="hr-stat-card">
            <div class="hr-stat-top"><span class="hr-stat-label">Candidates to review</span><span class="hr-stat-icon peach"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3h8l4 4v14H4V3zM8 12h8M8 16h5M16 3v5h4"/></svg></span></div>
            <div class="hr-stat-value">—</div><div class="hr-stat-foot">Requires candidate evaluation data</div>
          </article>
          <article class="hr-stat-card">
            <div class="hr-stat-top"><span class="hr-stat-label">Shortlisted</span><span class="hr-stat-icon blue"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14v18l-7-4-7 4z"/><path d="M9 9l2 2 4-4"/></svg></span></div>
            <div class="hr-stat-value">—</div><div class="hr-stat-foot">Available after AI ranking is integrated</div>
          </article>
        </section>

        <section class="hr-primary-grid">
          <article class="hr-panel hr-jobs-panel" id="jobPostings">
            <div class="hr-panel-heading">
              <div><span class="hr-section-kicker">YOUR WORK</span><h2>Job postings</h2><p>Manage the opportunities you share with candidates.</p></div>
              <a class="hr-create-job-link" href="hr-create-job.php"><span>+</span> Create a job</a>
            </div>
            <?php if ($flashSuccess !== ''): ?>
              <div class="hr-dashboard-success" role="status"><?= h($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if (!$jobsTableReady): ?>
              <div class="hr-empty-state">
                <div class="hr-empty-illustration"><span class="hr-empty-file"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span><span class="hr-empty-plus">!</span></div>
                <h3>Database setup is required</h3>
                <p>Import <strong>Database_kawader/add_jobs_table.sql</strong> into your kawader_db database to enable job saving and listing.</p>
              </div>
            <?php elseif (count($jobs) === 0): ?>
              <div class="hr-empty-state">
                <div class="hr-empty-illustration"><span class="hr-empty-file"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span><span class="hr-empty-plus">+</span></div>
                <h3>No job postings yet</h3>
                <p>Create your first job posting. Saved drafts will appear here automatically and stay visible only in your HR workspace.</p>
                <a class="hr-outline-note hr-empty-cta" href="hr-create-job.php"><i></i> Start your first job posting →</a>
              </div>
            <?php else: ?>
              <div class="hr-job-list">
                <?php foreach ($jobs as $job): ?>
                  <?php
                    $status = (string)($job['status'] ?? 'draft');
                    $statusLabel = ucfirst($status);
                    $jobDate = !empty($job['created_at']) ? date('M j, Y', strtotime((string)$job['created_at'])) : '';
                    $jobMeta = array_filter([(string)($job['department'] ?? ''), (string)($job['location'] ?? ''), (string)($job['employment_type'] ?? '')], static fn($v) => trim($v) !== '');
                  ?>
                  <div class="hr-job-row">
                    <span class="hr-job-row-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                    <div class="hr-job-row-main"><strong><?= h((string)$job['job_title']) ?></strong><small><?= h(implode(' · ', $jobMeta)) ?></small><span class="hr-job-row-date">Created <?= h($jobDate) ?> · JOB-<?= (int)$job['job_id'] ?></span></div>
                    <span class="hr-job-status <?= h($status) ?>"><?= h($statusLabel) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </article>

          <article class="hr-panel hr-journey-panel">
            <div class="hr-panel-heading"><div><span class="hr-section-kicker">KAWADER WORKFLOW</span><h2>Hiring journey</h2><p>How your recruitment process will flow.</p></div></div>
            <div class="hr-journey-list">
              <div class="hr-journey-item"><span class="hr-journey-number">01</span><span class="hr-journey-symbol"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span><div><strong>Create a job</strong><small>Define skills and mandatory requirements</small></div><span class="hr-journey-state">Planned</span></div>
              <div class="hr-journey-item"><span class="hr-journey-number">02</span><span class="hr-journey-symbol"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m-5 5 5-5 5 5M4 20h16"/></svg></span><div><strong>Receive applications</strong><small>Candidates submit their CVs</small></div><span class="hr-journey-state">Planned</span></div>
              <div class="hr-journey-item"><span class="hr-journey-number">03</span><span class="hr-journey-symbol"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5M8 10h5M10.5 7.5v5"/></svg></span><div><strong>Evaluate &amp; rank</strong><small>Review matching results and requirement flags</small></div><span class="hr-journey-state">Planned</span></div>
              <div class="hr-journey-item"><span class="hr-journey-number">04</span><span class="hr-journey-symbol"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="9"/></svg></span><div><strong>Make the decision</strong><small>Review evidence and record your decision</small></div><span class="hr-journey-state">HR-led</span></div>
            </div>
          </article>
        </section>

        <section class="hr-bottom-grid">
          <article class="hr-panel hr-activity-panel">
            <div class="hr-panel-heading"><div><span class="hr-section-kicker">LATEST UPDATES</span><h2>Recent activity</h2></div><span class="hr-muted-label">Live activity will appear here</span></div>
            <div class="hr-activity-empty"><span class="hr-activity-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l2.5 2.5M20 12a8 8 0 1 1-2.34-5.66L20 8"/></svg></span><div><strong>No activity to show yet</strong><p>Job and application updates will appear here as your workspace becomes connected.</p></div></div>
          </article>
          <article class="hr-panel hr-principle-panel">
            <div class="hr-principle-icon">✦</div><span class="hr-section-kicker">BUILT FOR RESPONSIBLE HIRING</span><h2>AI supports.<br>HR decides.</h2><p>Kawader provides candidate matching, explanations and requirement flags to support your review—not to replace your professional judgment.</p><div class="hr-principle-foot"><span></span> Final hiring decisions stay with HR</div>
          </article>
        </section>

        <footer class="hr-footer"><span>© 2026 Kawader</span><span>AI-powered recruitment workspace</span></footer>
      </div>
    </main>
  </div>
  <div class="hr-mobile-backdrop" id="mobileBackdrop"></div>
  <script src="hr-dashboard.js?v=1"></script>
</body>
</html>
