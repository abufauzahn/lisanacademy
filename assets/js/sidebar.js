/* Lisanun Mubeen Academy — sidebar toggle */
function toggleSidebar() {
  var sb = document.getElementById('sidebar');
  var ov = document.getElementById('sidebarOverlay');
  if (!sb) return;
  var open = sb.classList.toggle('open');
  if (ov) ov.classList.toggle('open', open);
}

document.addEventListener('click', function (e) {
  var ov = document.getElementById('sidebarOverlay');
  if (ov && ov.classList.contains('open') && e.target === ov) {
    toggleSidebar();
  }
});