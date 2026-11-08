(function () {
  const dateNode = document.getElementById('todayDate');
  if (dateNode) {
    dateNode.textContent = new Intl.DateTimeFormat(undefined, {
      weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
    }).format(new Date());
  }

  const sidebar = document.getElementById('hrSidebar');
  const toggle = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('mobileBackdrop');

  function closeSidebar() {
    if (!sidebar || !toggle || !backdrop) return;
    sidebar.classList.remove('is-open');
    backdrop.classList.remove('is-visible');
    toggle.setAttribute('aria-expanded', 'false');
  }

  if (sidebar && toggle && backdrop) {
    toggle.addEventListener('click', function () {
      const isOpen = sidebar.classList.toggle('is-open');
      backdrop.classList.toggle('is-visible', isOpen);
      toggle.setAttribute('aria-expanded', String(isOpen));
    });
    backdrop.addEventListener('click', closeSidebar);
    window.addEventListener('resize', function () {
      if (window.innerWidth > 760) closeSidebar();
    });
  }
})();
