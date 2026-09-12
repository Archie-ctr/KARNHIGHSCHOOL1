/**
 * KHS — Notification Bell (all portals)
 * Polls /api/notifications.php?action=count every 60s
 * Updates any element with class .notif-count
 */
(function() {
  'use strict';

  const BASE_URL = window.__BASE_URL || '';
  const API      = BASE_URL + '/api/notifications.php';
  const INTERVAL = 60000; // 60 seconds

  function updateBadge(count) {
    document.querySelectorAll('.notif-count').forEach(el => {
      el.textContent = count > 99 ? '99+' : count;
      el.style.display = count > 0 ? 'flex' : 'none';
    });
    document.querySelectorAll('.ts-badge').forEach(el => {
      if (el.closest('a[href*="notifications"]')) {
        el.textContent = count > 99 ? '99+' : count;
        el.style.display = count > 0 ? 'inline-flex' : 'none';
      }
    });
    document.querySelectorAll('.notif-bell-badge').forEach(el => {
      el.textContent = count > 99 ? '99+' : count;
      el.style.display = count > 0 ? 'flex' : 'none';
    });
    // Update page title prefix
    if (count > 0 && !document.title.startsWith('(')) {
      document.title = '(' + count + ') ' + document.title;
    } else if (count === 0 && document.title.match(/^\(\d+\)/)) {
      document.title = document.title.replace(/^\(\d+\) /, '');
    }
  }

  function poll() {
    fetch(API + '?action=count', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => updateBadge(d.count || 0))
      .catch(() => {});
  }

  // Mark a single notification read via API
  window.markNotifRead = function(id) {
    const fd = new FormData();
    fd.append('action', 'mark_read');
    fd.append('id', id);
    fetch(API, { method:'POST', body:fd, credentials:'same-origin' })
      .then(r => r.json())
      .then(d => updateBadge(d.remaining_unread || 0))
      .catch(() => {});
  };

  // Initial poll + interval
  poll();
  setInterval(poll, INTERVAL);

  // Also refresh on page focus
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) poll();
  });
})();
