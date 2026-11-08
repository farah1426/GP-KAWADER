<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || strtolower((string)($_SESSION['role'] ?? '')) !== 'hr') {
    header('Location: signin.php'); exit;
}
$hrName = trim((string)($_SESSION['full_name'] ?? 'HR Professional'));
$firstName = preg_split('/\s+/', $hrName)[0] ?: 'there';
$formError = '';
if (empty($_SESSION['job_csrf_token'])) {
    $_SESSION['job_csrf_token'] = bin2hex(random_bytes(32));
}
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function job_skill_list(string $json): string {
    $items = json_decode($json, true);
    if (!is_array($items)) {
        throw new RuntimeException('Please review the skills you entered and try again.');
    }
    $clean = [];
    foreach ($items as $item) {
        if (!is_string($item)) continue;
        $item = trim(preg_replace('/\s+/', ' ', $item));
        if ($item === '') continue;
        if (mb_strlen($item) > 80) continue;
        $key = mb_strtolower($item);
        $clean[$key] = $item;
        if (count($clean) >= 50) break;
    }
    return json_encode(array_values($clean), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = (string)($_POST['csrf_token'] ?? '');
        if ($token === '' || !hash_equals((string)$_SESSION['job_csrf_token'], $token)) {
            throw new RuntimeException('Your session expired. Refresh the page and submit the job again.');
        }

        $jobTitle = trim((string)($_POST['job_title'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $employmentType = trim((string)($_POST['employment_type'] ?? ''));
        $workArrangement = trim((string)($_POST['work_arrangement'] ?? ''));
        $jobDescription = trim((string)($_POST['job_description'] ?? ''));
        $responsibilities = trim((string)($_POST['responsibilities'] ?? ''));
        $experienceRaw = trim((string)($_POST['min_experience_years'] ?? ''));
        $education = trim((string)($_POST['education_level'] ?? ''));
        $mandatoryCriteria = trim((string)($_POST['mandatory_criteria'] ?? ''));

        if ($jobTitle === '' || mb_strlen($jobTitle) > 120) throw new RuntimeException('Enter a job title (up to 120 characters).');
        if ($jobDescription === '' || mb_strlen($jobDescription) > 5000) throw new RuntimeException('Enter a job description (up to 5,000 characters).');
        if (!in_array($employmentType, ['Full-time', 'Part-time', 'Contract', 'Internship', 'Temporary'], true)) throw new RuntimeException('Select a valid employment type.');
        if ($workArrangement !== '' && !in_array($workArrangement, ['On-site', 'Hybrid', 'Remote'], true)) throw new RuntimeException('Select a valid work arrangement.');
        if ($education !== '' && !in_array($education, ['High school', 'Diploma', "Bachelor's degree", "Master's degree", 'Doctorate'], true)) throw new RuntimeException('Select a valid education level.');
        if ($experienceRaw !== '' && (!ctype_digit($experienceRaw) || (int)$experienceRaw > 50)) throw new RuntimeException('Minimum experience must be between 0 and 50 years.');
        if (mb_strlen($department) > 100 || mb_strlen($location) > 120 || mb_strlen($responsibilities) > 3000 || mb_strlen($mandatoryCriteria) > 1500) throw new RuntimeException('One or more fields exceed the allowed length.');

        $requiredSkills = job_skill_list((string)($_POST['required_skills_json'] ?? '[]'));
        $preferredSkills = job_skill_list((string)($_POST['preferred_skills_json'] ?? '[]'));

        require_once __DIR__ . '/../Database_kawader/db.php';
        $insert = $pdo->prepare(
            'INSERT INTO jobs
            (hr_user_id, job_title, department, location, employment_type, work_arrangement,
             job_description, responsibilities, min_experience_years, education_level,
             required_skills, preferred_skills, mandatory_experience, mandatory_education,
             mandatory_certification, mandatory_criteria, status)
            VALUES
            (:hr_user_id, :job_title, :department, :location, :employment_type, :work_arrangement,
             :job_description, :responsibilities, :min_experience_years, :education_level,
             :required_skills, :preferred_skills, :mandatory_experience, :mandatory_education,
             :mandatory_certification, :mandatory_criteria, :status)'
        );
        $insert->execute([
            'hr_user_id' => (int)$_SESSION['user_id'],
            'job_title' => $jobTitle,
            'department' => $department !== '' ? $department : null,
            'location' => $location !== '' ? $location : null,
            'employment_type' => $employmentType,
            'work_arrangement' => $workArrangement !== '' ? $workArrangement : null,
            'job_description' => $jobDescription,
            'responsibilities' => $responsibilities !== '' ? $responsibilities : null,
            'min_experience_years' => $experienceRaw !== '' ? (int)$experienceRaw : null,
            'education_level' => $education !== '' ? $education : null,
            'required_skills' => $requiredSkills,
            'preferred_skills' => $preferredSkills,
            'mandatory_experience' => isset($_POST['mandatory_experience']) ? 1 : 0,
            'mandatory_education' => isset($_POST['mandatory_education']) ? 1 : 0,
            'mandatory_certification' => isset($_POST['mandatory_certification']) ? 1 : 0,
            'mandatory_criteria' => $mandatoryCriteria !== '' ? $mandatoryCriteria : null,
            'status' => 'draft',
        ]);

        $_SESSION['hr_flash_success'] = 'Your job draft has been saved and added to Job postings.';
        header('Location: hr-dashboard.php?job_created=1');
        exit;
    } catch (Throwable $e) {
        error_log('Kawader create job error: ' . $e->getMessage());
        if ($e instanceof RuntimeException && !($e instanceof PDOException)) {
            $formError = $e->getMessage();
        } else {
            $formError = 'We could not save this job. Please make sure the jobs database migration has been imported, then try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#241044">
  <title>Create Job | Kawader</title>
  <link rel="stylesheet" href="hr-dashboard.css?v=1">
  <link rel="stylesheet" href="hr-create-job.css?v=1">
</head>
<body class="hr-dashboard-page hr-create-job-page">
<div class="hr-app-shell">
  <aside class="hr-sidebar" id="hrSidebar" aria-label="HR navigation">
    <a class="hr-brand" href="hr-dashboard.php" aria-label="Kawader HR home"><img class="hr-brand-logo" src="../images/KawaderLogoLight.png" alt="Kawader"><span class="hr-brand-workspace">HR WORKSPACE</span></a>
    <div class="hr-sidebar-label">WORKSPACE</div>
    <nav class="hr-side-nav">
      <a class="hr-nav-link" href="hr-dashboard.php"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg><span>Dashboard</span></a>
      <a class="hr-nav-link is-active" href="hr-create-job.php" aria-current="page"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M12 13v5M9.5 15.5h5"/></svg><span>Job postings</span></a>
      <div class="hr-nav-link is-disabled" aria-disabled="true"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.5-3.5 2.7-5.3 6.5-5.3s6 1.8 6.5 5.3M16 5.5a3.5 3.5 0 0 1 0 6.8M17 15c2.6.4 4.1 2 4.5 5"/></svg><span>Candidates</span><small>Next</small></div>
      <div class="hr-nav-link is-disabled" aria-disabled="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16M4 12h16M4 19h16"/><circle cx="8" cy="5" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="11" cy="19" r="2"/></svg><span>Compare candidates</span><small>Next</small></div>
    </nav>
    <div class="hr-sidebar-bottom"><div class="hr-help-card"><span class="hr-help-icon">✦</span><strong>AI-assisted hiring</strong><p>Kawader supports your review with matching insights and evidence. The final decision stays with HR.</p></div><a class="hr-signout" href="logout.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg><span>Sign out</span></a></div>
  </aside>
  <main class="hr-main">
    <header class="hr-topbar">
      <button class="hr-menu-toggle" id="sidebarToggle" type="button" aria-label="Open navigation" aria-controls="hrSidebar" aria-expanded="false"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
      <div class="hr-breadcrumb"><a href="hr-dashboard.php">Workspace</a><b>/</b><strong>Job postings</strong><b>/</b><strong>Create job</strong></div>
      <div class="hr-topbar-right"><span class="hr-topbar-date" id="todayDate"></span><div class="hr-user-chip"><span class="hr-avatar"><?= h(strtoupper(substr($firstName, 0, 1))) ?></span><span class="hr-user-meta"><strong><?= h($hrName) ?></strong><small>HR Professional</small></span></div></div>
    </header>
    <div class="hr-content job-content">
      <div class="job-page-heading"><div><div class="hr-eyebrow"><span class="hr-eyebrow-dot"></span> BUILD YOUR NEXT OPPORTUNITY</div><h1>Create a job posting</h1><p>Define the role and its requirements. Kawader will use this information to match relevant candidate profiles.</p></div><a class="job-back-link" href="hr-dashboard.php">← Back to dashboard</a></div>
      <div class="job-progress-row"><div class="job-progress-copy"><span class="job-progress-dot"></span><span>Job details</span><span class="job-progress-separator">/</span><span class="muted">Candidate requirements</span></div><span class="job-step-label">STEP 1 OF 1</span></div>
      <form id="jobForm" method="post" action="hr-create-job.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['job_csrf_token']) ?>">
        <input type="hidden" name="required_skills_json" id="requiredSkillsJson" value="[]">
        <input type="hidden" name="preferred_skills_json" id="preferredSkillsJson" value="[]">
        <?php if ($formError !== ''): ?><div class="job-form-error" role="alert"><?= h($formError) ?></div><?php endif; ?>
        <div class="job-layout">
          <div class="job-form-column">
            <section class="hr-panel job-section">
              <div class="job-section-heading"><span class="job-section-icon lavender"><svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"/></svg></span><div><h2>Basic information</h2><p>Start with the key details candidates need to know.</p></div></div>
              <div class="job-field-grid">
                <label class="job-field job-field-full"><span>Job title <i>*</i></span><input id="jobTitle" name="job_title" type="text" placeholder="e.g. Software Engineer" maxlength="120" required><small>Use a clear title that describes the role.</small></label>
                <label class="job-field"><span>Department</span><input id="department" name="department" type="text" placeholder="e.g. Engineering" maxlength="100"></label>
                <label class="job-field"><span>Location</span><input id="location" name="location" type="text" placeholder="e.g. Riyadh or Remote" maxlength="120"></label>
                <label class="job-field"><span>Employment type <i>*</i></span><select id="employmentType" name="employment_type" required><option value="">Select employment type</option><option>Full-time</option><option>Part-time</option><option>Contract</option><option>Internship</option><option>Temporary</option></select></label>
                <label class="job-field"><span>Work arrangement</span><select id="workArrangement" name="work_arrangement"><option value="">Select arrangement</option><option>On-site</option><option>Hybrid</option><option>Remote</option></select></label>
              </div>
            </section>
            <section class="hr-panel job-section">
              <div class="job-section-heading"><span class="job-section-icon peach"><svg viewBox="0 0 24 24"><path d="M5 3h14v18H5zM8 8h8M8 12h8M8 16h5"/></svg></span><div><h2>Role description</h2><p>Describe what the role involves and what the successful candidate will do.</p></div></div>
              <label class="job-field"><span>Job description <i>*</i></span><textarea id="jobDescription" name="job_description" rows="5" maxlength="5000" placeholder="Introduce the role, its purpose, and how it contributes to the team..." required></textarea><small><span id="descriptionCount">0</span>/5,000 characters</small></label>
              <label class="job-field job-field-spaced"><span>Key responsibilities</span><textarea id="responsibilities" name="responsibilities" rows="4" maxlength="3000" placeholder="List the main responsibilities, one per line..."></textarea><small>Separate responsibilities with line breaks.</small></label>
            </section>
            <section class="hr-panel job-section">
              <div class="job-section-heading"><span class="job-section-icon mint"><svg viewBox="0 0 24 24"><path d="m12 3 2.2 5.8L20 11l-5.8 2.2L12 19l-2.2-5.8L4 11l5.8-2.2zM19 16v5M16.5 18.5h5"/></svg></span><div><h2>Candidate requirements</h2><p>Specify the qualifications and skills that are relevant to this role.</p></div></div>
              <div class="job-field-grid">
                <label class="job-field"><span>Minimum experience (years)</span><input id="experience" name="min_experience_years" type="number" min="0" max="50" step="1" placeholder="e.g. 2"></label>
                <label class="job-field"><span>Education level</span><select id="education" name="education_level"><option value="">No specific requirement</option><option>High school</option><option>Diploma</option><option>Bachelor's degree</option><option>Master's degree</option><option>Doctorate</option></select></label>
              </div>
              <div class="job-field job-field-spaced"><span>Required skills</span><div class="job-chip-input"><div class="job-chips" id="requiredSkills"><span class="job-chip">Java<button type="button" aria-label="Remove Java">×</button></span><span class="job-chip">SQL<button type="button" aria-label="Remove SQL">×</button></span><span class="job-chip">Problem solving<button type="button" aria-label="Remove Problem solving">×</button></span></div><input id="requiredSkillInput" type="text" placeholder="Type a skill and press Enter"></div><small>Core skills used for candidate matching.</small></div>
              <div class="job-field job-field-spaced"><span>Preferred skills <small class="job-inline-muted">(optional)</small></span><div class="job-chip-input"><div class="job-chips" id="preferredSkills"></div><input id="preferredSkillInput" type="text" placeholder="Add an additional skill and press Enter"></div><small>Helpful skills that are not mandatory.</small></div>
            </section>
            <section class="hr-panel job-section">
              <div class="job-section-heading"><span class="job-section-icon blue"><svg viewBox="0 0 24 24"><path d="M12 3 4 7v5c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7zM9 12l2 2 4-4"/></svg></span><div><h2>Mandatory requirements</h2><p>Flag essential criteria for HR review. A missing or unverifiable requirement should not automatically reject a candidate.</p></div></div>
              <div class="job-check-list">
                <label class="job-check"><input type="checkbox" name="mandatory_experience" value="1"><span><strong>Minimum experience is required</strong><small>Flag candidates whose CV does not show the required experience.</small></span></label>
                <label class="job-check"><input type="checkbox" name="mandatory_education" value="1"><span><strong>Education level is required</strong><small>Flag candidates whose education cannot be verified against the requirement.</small></span></label>
                <label class="job-check"><input type="checkbox" name="mandatory_certification" value="1"><span><strong>Specific certification is required</strong><small>Specify the certification in the additional criteria field.</small></span></label>
              </div>
              <label class="job-field job-field-spaced"><span>Additional mandatory criteria</span><textarea id="mandatoryCriteria" name="mandatory_criteria" rows="2" maxlength="1500" placeholder="e.g. Professional certification in ..."></textarea></label>
            </section>
</div>
          <aside class="job-side-column">
            <section class="hr-panel job-preview-card"><div class="job-preview-top"><span class="hr-section-kicker">LIVE PREVIEW</span><span class="job-preview-badge">Draft</span></div><div class="job-preview-mark">K</div><h2 id="previewTitle">Your job title</h2><p id="previewMeta">Department · Location</p><div class="job-preview-divider"></div><div class="job-preview-label">ROLE OVERVIEW</div><p class="job-preview-description" id="previewDescription">Your job description will appear here as you write it.</p><div class="job-preview-label">REQUIRED SKILLS</div><div class="job-preview-skills" id="previewSkills"><span>Java</span><span>SQL</span><span>Problem solving</span></div></section>
            <section class="job-ai-note"><span class="job-ai-note-icon">✦</span><div><strong>Ready for AI matching</strong><p>Kawader will use the job description and relevant requirements as input for semantic matching when the AI service is connected.</p></div></section>
            <section class="hr-panel job-side-checklist"><span class="hr-section-kicker">BEFORE YOU CONTINUE</span><h3>Posting checklist</h3><div class="job-checklist-item"><span class="job-checklist-icon">1</span><span>Clear job title and description</span></div><div class="job-checklist-item"><span class="job-checklist-icon">2</span><span>Relevant skills and qualifications</span></div><div class="job-checklist-item"><span class="job-checklist-icon">3</span><span>Mandatory criteria reviewed</span></div></section>
          </aside>
        </div>
        <div class="job-form-actions"><a href="hr-dashboard.php" class="job-cancel-btn">Cancel</a><button type="button" class="job-secondary-btn" id="previewButton">Preview details</button><button type="submit" class="job-primary-btn">Save job draft <span>→</span></button></div>
        <p class="job-prototype-note">Your job will be saved as a draft in your HR workspace. AI matching will be connected in a later step.</p>
      </form>
      <footer class="hr-footer"><span>© 2026 Kawader</span><span>AI-powered recruitment workspace</span></footer>
    </div>
  </main>
</div>
<div class="hr-mobile-backdrop" id="mobileBackdrop"></div>
<div class="job-toast" id="jobToast" role="status" aria-live="polite"></div>
<script src="hr-dashboard.js?v=1"></script><script src="hr-create-job.js?v=1"></script>
</body></html>
