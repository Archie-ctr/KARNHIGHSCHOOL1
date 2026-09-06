'use strict';

/* ── Nav toggle ─────────────────────────────────────────────── */
(function(){
  const btn  = document.getElementById('navBtn');
  const menu = document.getElementById('navMenu');
  const hdr  = document.getElementById('site-header');
  if(!btn || !menu) return;

  function open(){
    menu.classList.add('open');
    btn.classList.add('open');
    btn.setAttribute('aria-expanded','true');
    btn.setAttribute('aria-label','Close menu');
    document.body.style.overflow = 'hidden';
  }
  function close(){
    menu.classList.remove('open');
    btn.classList.remove('open');
    btn.setAttribute('aria-expanded','false');
    btn.setAttribute('aria-label','Open menu');
    document.body.style.overflow = '';
  }
  btn.addEventListener('click',()=> menu.classList.contains('open') ? close() : open());
  document.addEventListener('click',e=>{ if(!btn.contains(e.target)&&!menu.contains(e.target)) close(); });
  document.addEventListener('keydown',e=>{ if(e.key==='Escape') close(); });
  menu.querySelectorAll('a').forEach(a=>a.addEventListener('click',close));

  /* Scroll shadow */
  window.addEventListener('scroll',()=>{
    hdr && hdr.classList.toggle('is-scrolled', window.scrollY > 12);
  },{passive:true});
})();

/* ── Fade-up on scroll ──────────────────────────────────────── */
(function(){
  const obs = new IntersectionObserver(entries=>{
    entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('vis'); obs.unobserve(e.target); } });
  },{threshold:0.1});
  document.querySelectorAll('.card,.step,.news-card,.ev-row,.fact,.pillar').forEach((el,i)=>{
    el.classList.add('fade-up');
    el.style.transitionDelay = (i%4)*70+'ms';
    obs.observe(el);
  });
})();

/* ── Stats counter ──────────────────────────────────────────── */
(function(){
  const obs = new IntersectionObserver(entries=>{
    entries.forEach(e=>{
      if(!e.isIntersecting) return;
      const el = e.target;
      const raw = el.textContent.replace(/,/g,'');
      const n = parseFloat(raw);
      if(isNaN(n)){ obs.unobserve(el); return; }
      const suffix = el.textContent.replace(/[\d.,]/g,'');
      const dur = 1300, start = performance.now();
      function step(now){
        const p = Math.min((now-start)/dur,1);
        const ease = 1-Math.pow(1-p,3);
        el.textContent = (Number.isInteger(n)?Math.floor(ease*n):(ease*n).toFixed(0))+suffix;
        if(p<1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
      obs.unobserve(el);
    });
  },{threshold:0.6});
  document.querySelectorAll('.stat-n').forEach(el=>obs.observe(el));
})();

/* ── FAQ accordion ──────────────────────────────────────────── */
function toggleFaq(i){
  const a = document.getElementById('fa-'+i);
  const ico = document.getElementById('fi-'+i);
  if(!a) return;
  const open = a.style.display==='block';
  a.style.display = open ? 'none' : 'block';
  if(ico){ ico.textContent = open?'+':'×'; ico.classList.toggle('open',!open); }
}
window.toggleFaq = toggleFaq;

/* ── Smooth anchor ──────────────────────────────────────────── */
document.querySelectorAll('a[href^="#"]').forEach(a=>{
  a.addEventListener('click',e=>{
    const t = document.querySelector(a.getAttribute('href'));
    if(!t) return;
    e.preventDefault();
    t.scrollIntoView({behavior:'smooth',block:'start'});
    t.setAttribute('tabindex','-1');
    t.focus({preventScroll:true});
  });
});

/* ── Auto-dismiss alerts ────────────────────────────────────── */
document.querySelectorAll('.alert').forEach(el=>{
  setTimeout(()=>{ el.style.transition='opacity .5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); },7000);
});
