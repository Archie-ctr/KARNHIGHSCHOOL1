'use strict';

/* ── Mobile navigation ──────────────────────────────────────── */
(function () {
  const toggle = document.getElementById('navToggle');
  const nav    = document.getElementById('mainNav');
  if (!toggle || !nav) return;

  function openNav() {
    nav.classList.add('open');
    toggle.classList.add('open');
    toggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }
  function closeNav() {
    nav.classList.remove('open');
    toggle.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  toggle.addEventListener('click', () =>
    nav.classList.contains('open') ? closeNav() : openNav()
  );

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!toggle.contains(e.target) && !nav.contains(e.target)) closeNav();
  });

  // Close on Escape
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeNav(); });

  // Close on link click (mobile)
  nav.querySelectorAll('a').forEach(a => a.addEventListener('click', closeNav));
})();

/* ── Sticky header shadow on scroll ────────────────────────── */
(function () {
  const header = document.querySelector('.site-header');
  if (!header) return;
  const onScroll = () => {
    header.style.boxShadow = window.scrollY > 10
      ? '0 2px 20px rgba(0,0,0,.1)'
      : '0 1px 3px rgba(0,0,0,.06)';
  };
  window.addEventListener('scroll', onScroll, { passive: true });
})();

/* ── Smooth scroll for anchor links ────────────────────────── */
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (!target) return;
    e.preventDefault();
    const offset = 80;
    window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
  });
});

/* ── Intersection Observer: fade-in on scroll ───────────────── */
(function () {
  const style = document.createElement('style');
  style.textContent = `
    .fade-up { opacity:0; transform:translateY(28px); transition:opacity .55s ease, transform .55s ease; }
    .fade-up.visible { opacity:1; transform:translateY(0); }
  `;
  document.head.appendChild(style);

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } });
  }, { threshold: 0.12 });

  document.querySelectorAll('.card, .process-item, .news-card, .event-item, .stat-item').forEach((el, i) => {
    el.classList.add('fade-up');
    el.style.transitionDelay = (i % 4) * 80 + 'ms';
    observer.observe(el);
  });
})();

/* ── FAQ accordion ──────────────────────────────────────────── */
function toggleFaq(i) {
  const body = document.getElementById('faq-body-' + i);
  const icon = document.getElementById('faq-icon-' + i);
  if (!body) return;
  const open = body.style.display === 'block';
  body.style.display = open ? 'none' : 'block';
  if (icon) icon.textContent = open ? '+' : '−';
}
window.toggleFaq = toggleFaq;

/* ── Auto-dismiss alerts ───────────────────────────────────── */
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity .5s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  }, 6000);
});

/* ── Counter animation for stats ───────────────────────────── */
(function () {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const target = parseFloat(el.dataset.count || el.textContent);
      if (isNaN(target)) return;
      const suffix = el.textContent.replace(/[\d.]/g, '');
      let start = 0;
      const duration = 1400;
      const step = (timestamp) => {
        if (!start) start = timestamp;
        const progress = Math.min((timestamp - start) / duration, 1);
        const ease = 1 - Math.pow(1 - progress, 3);
        el.textContent = (Number.isInteger(target)
          ? Math.floor(ease * target)
          : (ease * target).toFixed(0)) + suffix;
        if (progress < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
      observer.unobserve(el);
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('.stat-item strong').forEach(el => observer.observe(el));
})();
