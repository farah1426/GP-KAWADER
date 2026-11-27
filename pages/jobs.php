<?php
session_start();
$action = $_GET['action'] ?? '';
if (in_array($action, ['parse','apply'], true)) {
 header('Content-Type: application/json; charset=utf-8');
 header('Cache-Control: no-store');
 function fail($code,$msg) { http_response_code($code); echo json_encode(['error'=>$msg]); exit; }
 if($_SERVER['REQUEST_METHOD']!=='POST') fail(405,'Invalid method.');
 if(empty($_SESSION['user_id']) || ($_SESSION['role']??'')!=='candidate') fail(401,'Sign in as a candidate.');
 if(empty($_SESSION['apply_csrf']) || !hash_equals($_SESSION['apply_csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??'')) fail(403,'Refresh the page and try again.');
 $data=$action==='parse'?$_POST:json_decode(file_get_contents('php://input'),true);
 $job=$data['jobId']??'';
 if(!is_string($job)||!$job||strlen($job)>5) fail(400,'Invalid job.');
 $folder=dirname(__DIR__).'/uploads/cvs';
 $newFile=null;
 try {
  require __DIR__.'/../Database_kawader/db.php';
  $q=$pdo->prepare('SELECT p.profile_id,u.full_name FROM candidate_profile p JOIN user u ON u.user_id=p.user_id WHERE p.user_id=?'); $q->execute([$_SESSION['user_id']]); $candidate=$q->fetch(PDO::FETCH_ASSOC);
  if(!$candidate) fail(403,'Candidate profile not found.');
  $profile=$candidate['profile_id'];
  $q=$pdo->prepare("SELECT job_id FROM job_posting WHERE job_id=? AND status IN ('open','published') AND ends_at>=CURDATE()"); $q->execute([$job]); if(!$q->fetchColumn()) fail(409,'This job is no longer open.');
  $q=$pdo->prepare('SELECT application_id FROM application WHERE profile_id=? AND job_id=?'); $q->execute([$profile,$job]); if($q->fetchColumn()) fail(409,'You already applied for this job.');
  if($action==='parse') {
   $f=$_FILES['cv']??null;
   if(!$f || $f['error']!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size']>5242880 || $f['size']<5) fail(422,'Choose a PDF up to 5 MB.');
   if((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])!=='application/pdf' || file_get_contents($f['tmp_name'],false,null,0,5)!=='%PDF-') fail(422,'Invalid PDF.');
   $original=basename(str_replace(chr(92),'/',$f['name'])); if(strlen($original)>255 || preg_match('/[[:cntrl:]]/',$original)) fail(422,'Invalid file name.');
   if(!is_dir($folder)&&!mkdir($folder,0700,true)&&!is_dir($folder)) throw new RuntimeException('Storage unavailable');
   $name=bin2hex(random_bytes(16)).'.pdf'; $newFile=$folder.'/'.$name;
   if(!move_uploaded_file($f['tmp_name'],$newFile)) throw new RuntimeException('Upload failed');
   $ch=curl_init('http://127.0.0.1:8001/api/cv/process');
   curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>90,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['file_name'=>$name,'full_name'=>$candidate['full_name']])]);
   $raw=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
   $result=json_decode((string)$raw,true);
   if($status!==200 || ($result['parse_status']??'')!=='success') { unlink($newFile); fail(422,'CV processing failed. Ensure the AI API is running and the PDF contains selectable text.'); }
   $sections=[]; foreach(['experience','education','skills','certifications','languages'] as $key) $sections[$key]=array_values(array_filter($result[$key]??[],'is_string'));
   $old=$_SESSION['job_cv_preview']??null; if($old && preg_match('/^[a-f0-9]{32}\.pdf$/',$old['name'])) @unlink($folder.'/'.$old['name']);
   $token=bin2hex(random_bytes(32)); $_SESSION['job_cv_preview']=['token'=>$token,'job'=>$job,'profile'=>$profile,'name'=>$name,'original'=>$original,'size'=>$f['size'],'sections'=>$sections,'time'=>time()];
   echo json_encode(['token'=>$token,'sections'=>$sections],JSON_INVALID_UTF8_SUBSTITUTE); exit;
  }
  $preview=$_SESSION['job_cv_preview']??null;
  if(!$preview || !hash_equals($preview['token'],(string)($data['token']??'')) || $preview['job']!==$job || $preview['profile']!==$profile || time()-$preview['time']>1800 || !is_file($folder.'/'.$preview['name'])) fail(422,'Please upload and review your CV again.');
  $pdo->beginTransaction();
  $q=$pdo->prepare('SELECT profile_id FROM candidate_profile WHERE profile_id=? FOR UPDATE'); $q->execute([$profile]);
  $q=$pdo->prepare("SELECT job_id FROM job_posting WHERE job_id=? AND status IN ('open','published') AND ends_at>=CURDATE() FOR UPDATE"); $q->execute([$job]); if(!$q->fetchColumn()){ $pdo->rollBack(); fail(409,'This job is no longer open.'); }
  $q=$pdo->prepare('SELECT application_id FROM application WHERE profile_id=? AND job_id=?'); $q->execute([$profile,$job]); if($q->fetchColumn()){ $pdo->rollBack(); fail(409,'You already applied for this job.'); }
  $q=$pdo->prepare("INSERT INTO cv_file (profile_id,file_name,file_path,file_size,parse_status) VALUES (?,?,?,?,'success')"); $q->execute([$profile,$preview['original'],'uploads/cvs/'.$preview['name'],$preview['size']]); $cv=$pdo->lastInsertId();
  $mapping=['skills'=>['skill','skill_name',150],'languages'=>['language','language_name',100],'certifications'=>['certification','certification_name',200],'education'=>['education','description',null],'experience'=>['experience','description',null]];
  foreach($mapping as $key=>[$table,$column,$limit]) {
   $q=$pdo->prepare("INSERT INTO `$table` (cv_id,`$column`) VALUES (?,?)");
   foreach($preview['sections'][$key] as $item) { if($limit!==null) $item=mb_substr($item,0,$limit); $q->execute([$cv,$item]); }
  }
  $q=$pdo->prepare("INSERT INTO application (profile_id,cv_id,job_id,status) VALUES (?,?,?,'pending')"); $q->execute([$profile,$cv,$job]); $pdo->commit(); unset($_SESSION['job_cv_preview']); echo json_encode(['success'=>true]);
 } catch(Throwable $e) { if(isset($pdo)&&$pdo->inTransaction()) $pdo->rollBack(); if($newFile && is_file($newFile)) unlink($newFile); error_log($e->getMessage()); fail(500,'Unable to process your request.'); }
 exit;
}
if ($action === 'list') {


header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    require __DIR__.'/../Database_kawader/db.php';
    if (empty($_SESSION['apply_csrf'])) $_SESSION['apply_csrf'] = bin2hex(random_bytes(32));
    function items($v) { $a=json_decode((string)$v,true); return is_array($a) ? array_values(array_filter($a,'is_string')) : array_values(array_filter(preg_split('/\R/',(string)$v),'strlen')); }
    function val($v) { return $v === null || $v === '' ? 'Not specified' : (string)$v; }
    $rows=$pdo->query("SELECT j.*, h.company_name FROM job_posting j JOIN hr_profile h ON h.hr_id=j.hr_id WHERE j.status IN ('open','published') AND j.ends_at >= CURDATE() ORDER BY j.created_at DESC,j.job_id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $jobs=[];
    foreach($rows as $r) {
        $mandatory=items($r['mandatory_criteria']);
        if($r['mandatory_experience']) $mandatory[]='Minimum experience: '.val($r['min_experience_years']).' years';
        if($r['mandatory_education']) $mandatory[]='Education: '.val($r['education_level']);
        if($r['mandatory_certification']) $mandatory[]='Required certifications must be met';
        $salary=[]; if($r['salary_min']!==null) $salary[]='Min: '.$r['salary_min']; if($r['salary_max']!==null) $salary[]='Max: '.$r['salary_max'];
        $jobs[]=['id'=>$r['job_id'],'title'=>$r['title'],'company'=>val($r['company_name']),'category'=>val($r['department']),'status'=>'active','postedDate'=>$r['created_at'],'deadline'=>$r['ends_at'],'location'=>val($r['location']),'employmentType'=>val($r['employment_type']),'workMode'=>val($r['work_arrangement']),'salary'=>implode(' / ',$salary),'description'=>$r['description'],'responsibilities'=>items($r['responsibilities']),'requiredSkills'=>items($r['required_skills']),'preferredSkills'=>items($r['preferred_skills']),'minimumExperience'=>$r['min_experience_years']===null?'Not specified':$r['min_experience_years'].' years','educationRequirement'=>val($r['education_level']),'certifications'=>items($r['required_certifications']),'mandatoryRequirements'=>$mandatory];
    }
    echo json_encode(['jobs'=>$jobs,'csrfToken'=>$_SESSION['apply_csrf']],JSON_THROW_ON_ERROR);
} catch(Throwable $e) { error_log($e->getMessage()); http_response_code(500); echo json_encode(['error'=>'Unable to load jobs.']); }

exit;
}
if ($action !== '') { http_response_code(404); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kawader - Jobs</title>
  <link rel="stylesheet" href="style.css">
<style>
#applyModal .modal { width:min(620px,94vw); max-width:620px; padding:24px; font-size:14px; }
#applyModal .apply-header h3 { font-size:21px; margin:0; }
#applyModal .apply-subtitle, #applyModal .modal > p { font-size:13px; line-height:1.5; }
#applyModal .upload-box { padding:16px; min-height:0; }
#applyModal .upload-box svg { width:26px; height:26px; }
#applyModal .upload-box strong { font-size:14px; overflow-wrap:anywhere; }
#applyModal .upload-box small { font-size:12px; }
#cvReview { margin-top:18px; }
#cvReview h3 { font-size:17px; font-weight:650; margin:0 0 5px; }
#cvReview p { font-size:12px; line-height:1.5; margin:0 0 12px; color:#626273; }
#cvReview details { border:1px solid #e5e2ed; border-radius:8px; margin:6px 0; overflow-wrap:anywhere; }
#cvReview summary { cursor:pointer; padding:10px 12px; font-size:13px; font-weight:600; color:#38304f; }
#cvReview details[open] summary { background:#f7f5fc; border-radius:8px 8px 0 0; }
#cvReview ul { margin:0; padding:9px 16px 10px 30px; font-size:13px; line-height:1.55; }
#cvReview li { margin:3px 0; white-space:pre-wrap; }
#applyModal .modal-actions { position:sticky; bottom:0; background:#fff; padding:12px 0 4px; margin-top:12px; border-top:1px solid #eee; z-index:2; }
#applyModal .modal-actions button { font-size:13px; }
</style>
</head>
<body id="jobsPage">

  <!-- Header (filled by layout.js) -->
  <div id="site-header" data-menu="candidate" data-active="jobs"></div>

  <section class="page-hero jobs-hero">
    <div class="page-hero-glow"></div>
    <div class="container jobs-hero-content">
      <span class="badge">AI-assisted recruitment</span>
      <h1>Find the job that <strong>fits</strong> your skills</h1>
      <p>Browse open positions and apply with your PDF CV.</p>
      <div class="jobs-search">
        <label for="searchInput" class="sr-only">Search jobs</label>
        <input id="searchInput" class="input" type="text" placeholder="Search by job title, keyword or skill">
        <button id="searchBtn" class="btn-primary">Search</button>
      </div>
    </div>
  </section>

  <main class="container jobs-section">
    <div id="filters" class="jobs-filters"></div>
    <div class="jobs-head"><h2>Available Jobs</h2><span id="resultsCount"></span></div>
    <div id="jobsGrid" class="jobs-grid"></div>
  </main>

  <div id="site-footer"></div>

  <div id="detailsModal" class="modal-overlay" hidden>
    <div id="detailsContent" class="modal details-modal" role="dialog" aria-modal="true"></div>
  </div>

  <div id="applyModal" class="modal-overlay" hidden>
    <div class="modal" role="dialog" aria-modal="true" style="max-height:85vh;overflow-y:auto">
      <div class="apply-header">
        <div>
          <h3>Upload your CV</h3>
          <p id="applySubtitle" class="apply-subtitle"></p>
        </div>
        <button id="closeApplyBtn" class="close-btn" aria-label="Close">&times;</button>
      </div>
      <div class="steps"><span id="step1"></span><span id="step2"></span><span id="step3"></span></div>
      <p>Upload a PDF, review the extracted information, then submit.</p>
      <label id="dropZone" for="cvInput" class="upload-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></svg>
        <strong id="dropTitle"></strong>
        <small id="dropHint"></small>
      </label>
      <input id="cvInput" type="file" accept="application/pdf" hidden>
      <div id="cvReview" hidden></div>
      <p id="formMessage" class="message"></p>
      <div class="modal-actions">
        <button id="cancelBtn" class="btn-secondary">Cancel</button>
        <button id="submitBtn" class="btn-primary" disabled>Submit Application</button>
      </div>
    </div>
  </div>

  <div id="successModal" class="modal-overlay" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="successTitle" style="text-align:center;max-width:420px;padding:32px">
      <svg aria-hidden="true" viewBox="0 0 48 48" width="64" height="64" fill="none" stroke="#21834d" stroke-width="3"><circle cx="24" cy="24" r="20"/><path d="m14 24 7 7 13-14" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <h3 id="successTitle" style="margin:16px 0 8px">Application Submitted Successfully!</h3>
      <p>Your application has been successfully submitted.</p>
      <button id="successDoneBtn" type="button" class="btn-primary" style="margin-top:20px;width:100%">Done</button>
    </div>
  </div>

  <script src="layout.js"></script>
  <script>
// Kawader - Jobs page

let categories = ["All"];   // filled automatically from the jobs

let csrfToken = "";
let allJobs = [];
let searchText = "";
let selectedCategory = "All";
let selectedJobId = null;
let selectedFile = null;
let profileCV = null;
let usingProfileCV = false;

// ---------- Jobs ----------
function findJob(jobId) {
  return allJobs.find(job => job.id === jobId);
}

function renderTags(skills) {
  return skills.map(skill => `<span class="tag">${esc(skill)}</span>`).join("");
}

function esc(value) { return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
async function loadJobs() {
  const response = await fetch('jobs.php?action=list', {cache:'no-store'});
  if (!response.ok) throw new Error('Unable to load jobs');
  const data = await response.json(); csrfToken = data.csrfToken; return data.jobs;
}

function searchJobs(jobs, text) {
  text = text.trim().toLowerCase();
  return jobs.filter(job =>
    [job.title, job.location, job.description, job.company, job.category, ...job.preferredSkills, ...job.requiredSkills].join(" ").toLowerCase().includes(text));
}

function filterJobs(jobs, category) {
  return category === "All" ? jobs : jobs.filter(job => job.category === category);
}

function updateJobs() {
  renderJobs(filterJobs(searchJobs(allJobs, searchText), selectedCategory));
}

function renderJobs(jobs) {
  byId("resultsCount").textContent = jobs.length + (jobs.length === 1 ? " job found" : " jobs found");
  byId("jobsGrid").innerHTML = jobs.length === 0
    ? '<p class="jobs-empty">No jobs match your search.</p>'
    : jobs.map(job => `
      <article class="card job-card">
        <div>
          <h3>${esc(job.title)}</h3>
          <div class="job-company">${esc(job.company)}</div>
        </div>
        <p class="job-desc">${esc(job.description)}</p>
        <div class="job-tags">${renderTags(job.requiredSkills)}</div>
        <div class="job-meta">
          <span class="job-city"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>${esc(job.location)}</span>
          <span>Posted ${formatDate(job.postedDate)}</span>
        </div>
        <div class="job-actions">
          <button class="btn-secondary details-btn" data-id="${esc(job.id)}">View Details</button>
          <button class="btn-primary apply-btn" data-id="${esc(job.id)}">Apply</button>
        </div>
      </article>`).join("");
}

function renderFilters() {
  byId("filters").innerHTML = categories.map(c =>
    `<button class="jobs-chip ${c === selectedCategory ? "active" : ""}" data-category="${esc(c)}">${esc(c)}</button>`).join("");
}

// ---------- Job details ----------
function formatDate(value) {
  const date = new Date(String(value).replace(" ", "T"));   // also works with MySQL dates
  return date.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" });
}

function renderList(items) {
  return items.length ? "<ul>" + items.map(item => `<li>${esc(item)}</li>`).join("") + "</ul>" : "<p>None required</p>";
}

function openJobDetails(jobId) {
  const job = findJob(jobId);
  byId("detailsContent").innerHTML = `
    <h3>${esc(job.title)}</h3>
    <div class="job-company">${esc(job.company)}</div>
    <div class="details-meta">
      <div><small>Location</small><span>${esc(job.location)}</span></div>
      <div><small>Work Mode</small><span>${esc(job.workMode)}</span></div>
      <div><small>Job Type</small><span>${esc(job.employmentType)}</span></div>
      <div><small>Salary</small><span>${esc(job.salary || "Not specified")}</span></div>
      <div><small>Minimum Experience</small><span>${esc(job.minimumExperience)}</span></div>
      <div><small>Education</small><span>${esc(job.educationRequirement)}</span></div>
      <div><small>Job Status</small><span>${job.status === "active" ? "Active" : "Closed"}</span></div>
      <div><small>Department</small><span>${esc(job.category)}</span></div>
      <div><small>Application Deadline</small><span>${formatDate(job.deadline)}</span></div>
      <div><small>Posted Date</small><span>${formatDate(job.postedDate)}</span></div>
    </div>
    <h4>Job Description</h4><p>${esc(job.description)}</p>
    <h4>Responsibilities</h4>${renderList(job.responsibilities)}
    <h4>Required Skills</h4><div class="job-tags">${job.requiredSkills.map(s => `<span class="tag">${esc(s)}</span>`).join("")}</div>
    <h4>Preferred Skills</h4><div class="job-tags">${renderTags(job.preferredSkills)}</div>
    <h4>Required Certifications</h4>${renderList(job.certifications)}
    <h4>Mandatory Requirements</h4>${renderList(job.mandatoryRequirements)}
    <div class="modal-actions">
      <button id="closeDetailsBtn" class="btn-secondary">Close</button>
      <button id="detailsApplyBtn" class="btn-primary" data-id="${esc(job.id)}">Apply Now</button>
    </div>`;
  byId("detailsModal").hidden = false;
}

function closeJobDetails() {
  byId("detailsModal").hidden = true;
}

// ---------- Apply ----------
function setMessage(text, type = "") {
  const message = byId("formMessage");
  message.textContent = text;
  message.className = "message " + type;
}

function setStep(step) {
  [1, 2, 3].forEach(n => byId("step" + n).classList.toggle("on", n <= step));
}

function showFile(title, hint) {
  byId("dropTitle").textContent = title;
  byId("dropHint").textContent = hint;
}

let applyOpenCount=0, previewToken=null, busy=false;
function openApplyModal(jobId) {
 if(busy) return;
 ++applyOpenCount; selectedJobId=jobId; previewToken=null;
 byId('applySubtitle').textContent='Applying for '+findJob(jobId).title;
 byId('cvInput').value=''; byId('cvInput').disabled=false; byId('submitBtn').disabled=true;
 byId('cvReview').hidden=true; byId('cvReview').replaceChildren();
 showFile('Upload your CV (PDF)','Up to 5 MB'); setStep(1); setMessage(''); byId('applyModal').hidden=false;
}
function closeApplyModal(){ if(busy)return; ++applyOpenCount; byId('applyModal').hidden=true; }
async function handleCVUpload(file){
 if(busy||!file)return; previewToken=null; byId('submitBtn').disabled=true; byId('cvReview').hidden=true; setStep(1);
 if(!file.name.toLowerCase().endsWith('.pdf')||file.size>5242880)return setMessage('Choose a PDF up to 5 MB.','error');
 busy=true; byId('cvInput').disabled=true; showFile(file.name,'Processing CV...'); setMessage('CV Parsing and Sensitive Information Removal in progress...');
 try {
  const body=new FormData(); body.append('cv',file); body.append('jobId',selectedJobId);
  const r=await fetch('jobs.php?action=parse',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrfToken},body}); const d=await r.json(); if(!r.ok)throw Error(d.error||'Processing failed.');
  previewToken=d.token; const review=byId('cvReview'); review.replaceChildren();
  const h=document.createElement('h3'); h.textContent='CV Review'; review.append(h);
  const note=document.createElement('p'); note.textContent='CV parsing and sensitive information removal completed. Expand each section to review.'; review.append(note);
  for(const key of ['experience','education','skills','certifications','languages']){
   const section=document.createElement('details'); section.open=(key==='experience');
   const title=document.createElement('summary'); title.textContent=key[0].toUpperCase()+key.slice(1)+' ('+d.sections[key].length+')'; section.append(title);
   const ul=document.createElement('ul'); for(const item of (d.sections[key].length?d.sections[key]:['No information extracted.'])){const li=document.createElement('li');li.textContent=item;ul.append(li);} section.append(ul); review.append(section);
  }
  review.hidden=false; setStep(2); showFile(file.name,'Processed successfully'); setMessage(''); byId('submitBtn').disabled=false;
 }catch(e){setMessage(e.message,'error');showFile('Upload your CV (PDF)','Try again');}finally{busy=false;byId('cvInput').disabled=false;byId('cvInput').value='';}
}
async function submitApplication(jobId){
 if(busy||!previewToken)return; busy=true; byId('submitBtn').disabled=true; byId('cvInput').disabled=true;
 try{const r=await fetch('jobs.php?action=apply',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrfToken},body:JSON.stringify({jobId,token:previewToken})});const d=await r.json();if(!r.ok)throw Error(d.error||'Application failed.');previewToken=null;setStep(3);byId('applyModal').hidden=true;byId('successModal').hidden=false;byId('successDoneBtn').focus();}
 catch(e){setMessage(e.message,'error');byId('submitBtn').disabled=false;}finally{busy=false;byId('cvInput').disabled=false;}
}

function setupEvents() {
  const searchInput = byId("searchInput");
  const search = () => { searchText = searchInput.value; updateJobs(); };
  searchInput.addEventListener("input", search);
  byId("searchBtn").addEventListener("click", search);

  byId("filters").addEventListener("click", event => {
    if (!event.target.dataset.category) return;
    selectedCategory = event.target.dataset.category;
    renderFilters();
    updateJobs();
  });

  byId("jobsGrid").addEventListener("click", event => {
    if (event.target.classList.contains("apply-btn")) openApplyModal(event.target.dataset.id);
    if (event.target.classList.contains("details-btn")) openJobDetails(event.target.dataset.id);
  });

  byId("detailsModal").addEventListener("click", event => {
    if (event.target.id === "detailsModal" || event.target.id === "closeDetailsBtn") closeJobDetails();
    if (event.target.id === "detailsApplyBtn") {
      closeJobDetails();
      openApplyModal(event.target.dataset.id);
    }
  });

  byId("cvInput").addEventListener("change", event => handleCVUpload(event.target.files[0]));
  byId("cancelBtn").addEventListener("click", closeApplyModal);
  byId("successDoneBtn").addEventListener("click", () => { byId("successModal").hidden = true; });
  byId("closeApplyBtn").addEventListener("click", closeApplyModal);


  const dropZone = byId("dropZone");
  dropZone.addEventListener("dragover", event => { event.preventDefault(); dropZone.classList.add("dragging"); });
  dropZone.addEventListener("dragleave", () => dropZone.classList.remove("dragging"));
  dropZone.addEventListener("drop", event => {
    event.preventDefault();
    dropZone.classList.remove("dragging");
    handleCVUpload(event.dataTransfer.files[0]);
  });
  byId("submitBtn").addEventListener("click", () => submitApplication(selectedJobId));
}

// ---------- Start ----------
async function startJobsPage() {
  setupEvents();
  try { allJobs = await loadJobs(); } catch(error) { byId("jobsGrid").textContent = "Unable to load jobs. Please refresh and try again."; return; }
  categories = ["All", ...new Set(allJobs.map(job => job.category))];
  renderFilters();
  updateJobs();
}

startJobsPage();
  </script>
</body>
</html>