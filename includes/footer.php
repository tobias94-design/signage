<!-- includes/footer.php -->
<script>
// ── Theme ────────────────────────────────────────────────────
function toggleTheme() {
  const html = document.documentElement;
  const isDark = html.classList.contains('dark');
  html.classList.toggle('dark', !isDark);
  html.classList.toggle('light', isDark);
  localStorage.setItem('pb_theme', isDark ? 'light' : 'dark');
  updateThemeIcon();
}

function updateThemeIcon() {
  const isDark = document.documentElement.classList.contains('dark');
  const icon = document.getElementById('theme-icon');
  if (!icon) return;
  icon.innerHTML = isDark
    ? '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>'
    : '<path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>';
}
updateThemeIcon();

// ── Sidebar mobile ───────────────────────────────────────────
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  const o = document.getElementById('sidebar-overlay');
  if (!s) return;
  const open = s.classList.toggle('open');
  if (o) o.style.display = open ? 'block' : 'none';
}

// ── Segmented controls ───────────────────────────────────────
document.querySelectorAll('.seg').forEach(seg => {
  seg.querySelectorAll('.seg-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      seg.querySelectorAll('.seg-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
    });
  });
});
</script>
</body>
</html>
