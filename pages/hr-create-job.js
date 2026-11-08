(() => {
  const $ = (id) => document.getElementById(id);
  const toast = (message) => {
    const el = $('jobToast'); if (!el) return;
    el.textContent = message; el.classList.add('is-visible');
    clearTimeout(toast.timer); toast.timer = setTimeout(() => el.classList.remove('is-visible'), 3600);
  };

  function setupSkillInput(inputId, listId) {
    const input = $(inputId), list = $(listId); if (!input || !list) return;
    const add = (value) => {
      const clean = value.trim().replace(/\s+/g, ' ');
      if (!clean) return;
      const exists = [...list.querySelectorAll('.job-chip')].some(chip => chip.firstChild.textContent.trim().toLowerCase() === clean.toLowerCase());
      if (exists) { input.value = ''; return; }
      const chip = document.createElement('span'); chip.className = 'job-chip';
      const label = document.createTextNode(clean); const remove = document.createElement('button');
      remove.type = 'button'; remove.setAttribute('aria-label', `Remove ${clean}`); remove.textContent = '×';
      chip.append(label, remove); list.append(chip); input.value = ''; updatePreview();
    };
    input.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ',') { event.preventDefault(); add(input.value); }
    });
    list.addEventListener('click', event => {
      const button = event.target.closest('button'); if (button) { button.closest('.job-chip').remove(); updatePreview(); }
    });
  }

  function skillsFrom(listId) {
    const list = $(listId); return list ? [...list.querySelectorAll('.job-chip')].map(chip => chip.firstChild.textContent.trim()) : [];
  }

  function updatePreview() {
    const title = $('jobTitle')?.value.trim();
    $('previewTitle').textContent = title || 'Your job title';
    const department = $('department')?.value.trim() || 'Department';
    const location = $('location')?.value.trim() || 'Location';
    const type = $('employmentType')?.value;
    $('previewMeta').textContent = [department, location, type].filter(Boolean).join(' · ');
    const description = $('jobDescription')?.value.trim();
    $('previewDescription').textContent = description || 'Your job description will appear here as you write it.';
    const skills = skillsFrom('requiredSkills'); const preview = $('previewSkills'); preview.innerHTML = '';
    (skills.length ? skills : ['Required skills']).forEach(skill => { const tag = document.createElement('span'); tag.textContent = skill; preview.append(tag); });
    $('descriptionCount').textContent = String(($('jobDescription').value || '').length);
  }


  ['jobTitle','department','location','employmentType','workArrangement','jobDescription'].forEach(id => {
    $(id)?.addEventListener('input', updatePreview);
    $(id)?.addEventListener('change', updatePreview);
  });
  setupSkillInput('requiredSkillInput', 'requiredSkills');
  setupSkillInput('preferredSkillInput', 'preferredSkills');

  $('previewButton')?.addEventListener('click', () => {
    const missing = [];
    if (!$('jobTitle').value.trim()) missing.push('job title');
    if (!$('jobDescription').value.trim()) missing.push('job description');
    if (!$('employmentType').value) missing.push('employment type');
    if (missing.length) { toast(`Complete the required fields: ${missing.join(', ')}.`); return; }
    toast('The live preview on the right reflects your job details.');
    document.querySelector('.job-preview-card')?.scrollIntoView({behavior:'smooth',block:'center'});
  });

  $('jobForm')?.addEventListener('submit', event => {
    event.preventDefault();
    const missing = [];
    if (!$('jobTitle').value.trim()) missing.push('job title');
    if (!$('jobDescription').value.trim()) missing.push('job description');
    if (!$('employmentType').value) missing.push('employment type');
    if (missing.length) { toast(`Please complete: ${missing.join(', ')}.`); (missing[0] === 'job title' ? $('jobTitle') : missing[0] === 'job description' ? $('jobDescription') : $('employmentType')).focus(); return; }
    toast('The form is ready. Database saving will be connected in the next implementation step; no job has been saved yet.');
  });

  updatePreview();
})();
