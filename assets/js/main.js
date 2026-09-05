/* ============================================================
   KHSMIS — Main JavaScript
   ============================================================ */

'use strict';

// ── Toast notifications ──────────────────────────────────────
const Toast = (() => {
  let container = null;
  function getContainer() {
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    return container;
  }
  function show(message, type = 'info', duration = 4000) {
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${message}</span><button class="toast-close" aria-label="Dismiss">&times;</button>`;
    t.querySelector('.toast-close').onclick = () => t.remove();
    getContainer().appendChild(t);
    if (duration > 0) setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(120%)'; t.style.transition='all .4s'; setTimeout(()=>t.remove(),400); }, duration);
  }
  return { show, success:(m)=>show(m,'success'), error:(m)=>show(m,'error'), warning:(m)=>show(m,'warning'), info:(m)=>show(m,'info') };
})();

// ── Mobile nav toggle ─────────────────────────────────────────
(function () {
  const btn = document.getElementById('menuBtn');
  const nav = document.getElementById('mainNav');
  if (!btn || !nav) return;
  btn.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    btn.setAttribute('aria-expanded', String(open));
    const icon = btn.querySelector('.menu-icon');
    if (icon) icon.textContent = open ? '✕' : '☰';
  });
  document.addEventListener('click', (e) => {
    if (!btn.contains(e.target) && !nav.contains(e.target)) {
      nav.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
      const icon = btn.querySelector('.menu-icon');
      if (icon) icon.textContent = '☰';
    }
  });
})();

// ── Admin sidebar toggle ──────────────────────────────────────
(function () {
  const toggle  = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (!toggle || !sidebar) return;
  toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 980 && !sidebar.contains(e.target) && !toggle.contains(e.target))
      sidebar.classList.remove('open');
  });
})();

// ── Auto-dismiss alerts ───────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert:not(.alert-sticky)').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 6000);
  });
});

// ── Tab switcher ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.tab-bar').forEach(bar => {
    const btns   = bar.querySelectorAll('.tab-btn');
    const panels = bar.closest('.tab-container')?.querySelectorAll('.tab-panel') ?? [];
    btns.forEach((btn, i) => {
      btn.addEventListener('click', () => {
        btns.forEach(b => b.classList.remove('active'));
        panels.forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        if (panels[i]) panels[i].classList.add('active');
      });
    });
  });
});

// ── Multi-step Application Modal ──────────────────────────────
(function () {
  const GRADES = ['ABC/KG','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6',
                  'Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'];
  let step = 1, data = {};
  const TOTAL = 7; // steps

  function openModal() {
    const el = document.getElementById('applicationModal');
    if (!el) return;
    el.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    render(1);
  }
  function closeModal() {
    const el = document.getElementById('applicationModal');
    if (!el) return;
    el.classList.add('hidden');
    document.body.style.overflow = '';
    step = 1; data = {};
  }

  function render(s) {
    step = s;
    updateStepper();
    document.getElementById('modalBody').innerHTML = stepHTML(s);
    document.getElementById('modalActions').innerHTML = actionsHTML(s);
    const body = document.getElementById('modalBody');
    // Restore values
    body.querySelectorAll('input,select,textarea').forEach(el => {
      if (el.name && data[el.name] !== undefined) {
        if (el.type === 'checkbox') el.checked = data[el.name];
        else el.value = data[el.name];
      }
    });
    // Wire actions
    const next = document.getElementById('modalNext');
    const back = document.getElementById('modalBack');
    if (next) next.onclick = () => { if (save() && validate()) { if (s < TOTAL) render(s+1); else submit(); } };
    if (back) back.onclick = () => { save(); render(s-1); };
  }

  function updateStepper() {
    document.querySelectorAll('.stepper-step').forEach((el, i) => {
      el.classList.remove('active','done');
      if (i+1 === step) el.classList.add('active');
      else if (i+1 < step) el.classList.add('done');
    });
  }

  function save() {
    document.querySelectorAll('#modalBody input,#modalBody select,#modalBody textarea').forEach(el => {
      if (el.name) data[el.name] = el.type === 'checkbox' ? el.checked : el.value;
    });
    return true;
  }

  function validate() {
    let ok = true;
    document.querySelectorAll('#modalBody [required]').forEach(el => {
      el.style.borderColor = '';
      if (!el.value.trim()) { el.style.borderColor='var(--error)'; ok = false; }
    });
    if (!ok) showErr('Please fill in all required fields.');
    else clearErr();
    return ok;
  }

  function showErr(msg) {
    clearErr();
    const e = document.createElement('div');
    e.id = 'modalErr'; e.className = 'form-error'; e.textContent = msg;
    document.getElementById('modalBody').appendChild(e);
    e.scrollIntoView({behavior:'smooth',block:'nearest'});
  }
  function clearErr() { document.getElementById('modalErr')?.remove(); }

  function actionsHTML(s) {
    const back = s > 1 ? `<button class="button button-secondary" id="modalBack">← Back</button>` : '';
    const next = s < TOTAL
      ? `<button class="button button-primary" id="modalNext">Continue →</button>`
      : `<button class="button button-primary" id="modalNext">Submit Application</button>`;
    return back + next;
  }

  function stepHTML(s) {
    if (s === 1) return `<div class="form-grid">
      <label>First name <input name="firstName" required placeholder="First name"/></label>
      <label>Middle name <input name="middleName" placeholder="Optional"/></label>
      <label class="wide">Last name <input name="lastName" required placeholder="Last name"/></label>
      <label>Date of birth <input name="dateOfBirth" type="date" required/></label>
      <label>Gender <select name="gender" required><option value="">Select...</option><option>Female</option><option>Male</option></select></label>
      <label>Nationality <input name="nationality" value="Liberian" required/></label>
      <label>Phone <input name="phone" required placeholder="+231 ..."/></label>
      <label>Email (optional) <input name="email" type="email" placeholder="you@example.com"/></label>
      <label class="wide">Current address <input name="address" required placeholder="Community, County"/></label>
      <label>Community <input name="community" placeholder="Community"/></label>
      <label>County <input name="county" value="Nimba" required/></label>
      <label>District <input name="district" placeholder="District (optional)"/></label>
    </div>`;

    if (s === 2) return `<div class="form-grid">
      <label class="wide">Parent/Guardian full name <input name="guardianName" required placeholder="Full name"/></label>
      <label>Relationship <select name="guardianRelationship" required><option value="">Select...</option><option>Mother</option><option>Father</option><option>Aunt/Uncle</option><option>Guardian</option><option>Other</option></select></label>
      <label>Guardian phone <input name="guardianPhone" required placeholder="+231 ..."/></label>
      <label>Guardian email <input name="guardianEmail" type="email" placeholder="Optional"/></label>
      <label>Emergency contact name <input name="emergencyName" placeholder="Optional"/></label>
      <label>Emergency contact phone <input name="emergencyPhone" placeholder="+231 ..."/></label>
    </div>`;

    if (s === 3) return `<div class="form-grid">
      <label class="wide">Previous school <input name="previousSchool" placeholder="School name (leave blank if none)"/></label>
      <label>Last grade completed <input name="lastGrade" placeholder="e.g. Grade 7"/></label>
      <label>Year completed <input name="lastGradeYear" placeholder="e.g. 2025"/></label>
      <label class="wide">Any special educational needs? <input name="specialNeeds" placeholder="Describe if any"/></label>
    </div>`;

    if (s === 4) return `<div class="form-grid">
      <label class="wide">Grade applying for <select name="grade" required><option value="">Select grade...</option>${GRADES.map(g=>`<option>${g}</option>`).join('')}</select></label>
      <label class="wide">Academic year <select name="academicYear" required><option value="2026/2027">2026/2027</option></select></label>
    </div>`;

    if (s === 5) return `<div class="form-grid">
      <div class="upload-box">
        <span class="upload-icon">📄</span>
        <strong>Documents can be uploaded after submission</strong>
        <span>Previous report card, birth certificate, passport photo</span>
        <span style="font-size:12px;color:var(--ink-faint)">Accepted: PDF, JPG, PNG (max 5 MB each)</span>
      </div>
    </div>`;

    if (s === 6) {
      const rows = [
        ['Applicant',    `${data.firstName||''} ${data.middleName||''} ${data.lastName||''}`.trim()],
        ['Date of birth', data.dateOfBirth||''],
        ['Gender',        data.gender||''],
        ['Phone',         data.phone||''],
        ['Grade applying',data.grade||''],
        ['Academic year', data.academicYear||''],
        ['Guardian',      `${data.guardianName||''} (${data.guardianRelationship||''})`],
        ['Guardian phone',data.guardianPhone||''],
        ['Address',       data.address||''],
      ];
      return `<div class="review-box">
        <p>Please review your details before submitting.</p>
        ${rows.map(([l,v])=>`<div class="review-row"><span class="rl">${l}</span><span class="rv">${v||'Not provided'}</span></div>`).join('')}
      </div>`;
    }
    return '';
  }

  async function submit() {
    const btn = document.getElementById('modalNext');
    if (btn) { btn.disabled=true; btn.textContent='Submitting…'; }
    try {
      const res  = await fetch('/KARNHIGHSCHOOL/submit_application.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)
      });
      const json = await res.json();
      if (json.success) {
        showSuccess(json.application_number);
      } else {
        showErr(json.message || 'Submission failed. Please try again.');
        if (btn) { btn.disabled=false; btn.textContent='Submit Application'; }
      }
    } catch(e) {
      showErr('Network error. Please check your connection and try again.');
      if (btn) { btn.disabled=false; btn.textContent='Submit Application'; }
    }
  }

  function showSuccess(num) {
    document.querySelector('.stepper').style.display='none';
    document.getElementById('modalBody').innerHTML = `
      <div class="success-state">
        <div class="success-icon">✓</div>
        <h3>Application Submitted!</h3>
        <p>Your application has been received. Keep your application number safe — you will need it to track your status.</p>
        <div class="application-number">
          <small>APPLICATION NUMBER</small>
          <strong>${num}</strong>
        </div>
        <p style="font-size:13px;color:var(--ink-faint)">You can check your status at <a href="/KARNHIGHSCHOOL/application-status.php" style="color:var(--primary)">application status page</a></p>
      </div>`;
    document.getElementById('modalActions').innerHTML =
      `<button class="button button-primary" onclick="document.getElementById('applicationModal').querySelector('[data-close-modal]').click()">Done →</button>`;
  }

  // Init
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-apply]').forEach(el => el.addEventListener('click', openModal));
    const modal = document.getElementById('applicationModal');
    if (!modal) return;
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    modal.querySelector('[data-close-modal]')?.addEventListener('click', closeModal);
  });
})();

// ── Entrance Exam Timer & Navigation ─────────────────────────
const ExamPlayer = (() => {
  let timerEl, seconds, timerInterval, saveInterval, currentQ = 0, answers = {}, flagged = new Set();

  function init(totalSeconds) {
    timerEl = document.getElementById('examTimer');
    seconds = totalSeconds;
    // Restore from localStorage if available
    const saved = localStorage.getItem('khs_exam_answers');
    if (saved) try { answers = JSON.parse(saved); } catch(e){}
    const savedFlags = localStorage.getItem('khs_exam_flagged');
    if (savedFlags) try { flagged = new Set(JSON.parse(savedFlags)); } catch(e){}

    timerInterval = setInterval(tick, 1000);
    saveInterval  = setInterval(autoSave, 15000);
    updateTimer();
    renderQNav();
    showQuestion(0);
  }

  function tick() {
    if (--seconds <= 0) { clearInterval(timerInterval); submitExam(true); return; }
    if (seconds <= 300) timerEl?.classList.add('warning');
    updateTimer();
  }

  function updateTimer() {
    if (!timerEl) return;
    const m = Math.floor(seconds/60), s = seconds%60;
    timerEl.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
  }

  function autoSave() {
    localStorage.setItem('khs_exam_answers', JSON.stringify(answers));
    localStorage.setItem('khs_exam_flagged', JSON.stringify([...flagged]));
    // Push to server
    const attemptId = document.getElementById('examAttemptId')?.value;
    if (!attemptId) return;
    fetch('/KARNHIGHSCHOOL/exam/save_answers.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({attempt_id:attemptId, answers})
    }).catch(()=>{});
  }

  function renderQNav() {
    const grid = document.getElementById('examQGrid');
    if (!grid) return;
    const total = parseInt(document.getElementById('examTotal')?.value||'0');
    grid.innerHTML = '';
    for(let i=0;i<total;i++) {
      const b = document.createElement('button');
      b.className = 'exam-q-btn' + (answers[i]!==undefined?' answered':'') + (flagged.has(i)?' flagged':'') + (i===currentQ?' current':'');
      b.textContent = i+1;
      b.onclick = () => showQuestion(i);
      grid.appendChild(b);
    }
  }

  function showQuestion(i) {
    currentQ = i;
    const panels = document.querySelectorAll('.question-panel');
    panels.forEach((p,idx) => p.style.display = idx===i ? 'block':'none');
    // Mark current answer if any
    const opts = panels[i]?.querySelectorAll('.exam-option');
    opts?.forEach(o => {
      const inp = o.querySelector('input');
      if (inp && answers[i] !== undefined) inp.checked = (inp.value == answers[i]);
      o.classList.toggle('selected', inp?.checked);
    });
    renderQNav();
    // Prev/Next
    document.getElementById('examPrev')?.toggleAttribute('disabled', i===0);
    document.getElementById('examNext')?.toggleAttribute('disabled', i===panels.length-1);
  }

  function recordAnswer(qIndex, val) {
    answers[qIndex] = val;
    renderQNav();
  }

  function toggleFlag(qIndex) {
    if (flagged.has(qIndex)) flagged.delete(qIndex); else flagged.add(qIndex);
    renderQNav();
  }

  async function submitExam(auto=false) {
    clearInterval(timerInterval); clearInterval(saveInterval);
    autoSave();
    localStorage.removeItem('khs_exam_answers');
    localStorage.removeItem('khs_exam_flagged');
    const attemptId = document.getElementById('examAttemptId')?.value;
    if (!attemptId) return;
    const resp = await fetch('/KARNHIGHSCHOOL/exam/submit_exam.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({attempt_id:attemptId, answers, auto_submitted:auto})
    });
    const data = await resp.json();
    if (data.redirect) window.location.href = data.redirect;
  }

  return { init, recordAnswer, toggleFlag, showQuestion, submitExam,
    next:()=>showQuestion(currentQ+1), prev:()=>showQuestion(currentQ-1) };
})();

// ── Detail row toggle ─────────────────────────────────────────
function toggleDetail(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = el.style.display === 'none' ? 'block':'none';
}

// ── Confirm before delete ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });
});

// ── Number formatting ─────────────────────────────────────────
function fmtLRD(n) { return 'LRD ' + Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function fmtUSD(n) { return 'USD ' + Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }

// ── Inline search / filter enhancement ───────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-live-search]').forEach(input => {
    const target = document.querySelector(input.dataset.liveSearch);
    if (!target) return;
    const rows = [...target.querySelectorAll('tbody tr')];
    input.addEventListener('input', () => {
      const q = input.value.toLowerCase();
      rows.forEach(r => r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none');
    });
  });
});

// ── Print helpers ─────────────────────────────────────────────
function printSection(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const w = window.open('','','width=900,height=700');
  w.document.write('<html><head><title>Print</title>');
  w.document.write('<link rel="stylesheet" href="/KARNHIGHSCHOOL/assets/css/style.css"/>');
  w.document.write('</head><body>');
  w.document.write(el.innerHTML);
  w.document.write('</body></html>');
  w.document.close();
  w.focus();
  setTimeout(() => { w.print(); w.close(); }, 500);
}

// ── Expose globals ────────────────────────────────────────────
window.Toast      = Toast;
window.ExamPlayer = ExamPlayer;
window.toggleDetail = toggleDetail;
window.printSection = printSection;
