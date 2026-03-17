// (function () {
//   const html = document.documentElement;

//   function applyTheme(theme) {
//     html.setAttribute('data-theme', theme);
//     const btn = document.getElementById('darkToggle');
//     if (btn) btn.textContent = theme === 'dark' ? '☀️' : '🌙';
//   }

//   // Apply saved theme
//   const saved = localStorage.getItem('compulab_theme') || 'light';
//   applyTheme(saved);

//   document.addEventListener('DOMContentLoaded', function () {
//     const btn = document.getElementById('darkToggle');
//     if (!btn) return;
//     applyTheme(localStorage.getItem('compulab_theme') || 'light');
//     btn.addEventListener('click', function () {
//       const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
//       applyTheme(next);
//       localStorage.setItem('compulab_theme', next);
//     });
//   });
// })();


(function() {
  const html = document.documentElement;
  const STORAGE_KEY = 'compulab_theme';
 
  function getTheme() {
    return localStorage.getItem(STORAGE_KEY) || 'light';
  }
 
  function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem(STORAGE_KEY, theme);
    // Update semua tombol toggle di halaman (navbar atau sidebar)
    document.querySelectorAll('#darkToggle').forEach(btn => {
      btn.textContent = theme === 'dark' ? '☀️' : '🌙';
    });
  }
 
  // Apply tema saat halaman load
  setTheme(getTheme());
 
  // Tunggu DOM siap
  document.addEventListener('DOMContentLoaded', function() {
    setTheme(getTheme());
    document.querySelectorAll('#darkToggle').forEach(btn => {
      btn.addEventListener('click', function() {
        const current = getTheme();
        setTheme(current === 'dark' ? 'light' : 'dark');
      });
    });
  });
})();