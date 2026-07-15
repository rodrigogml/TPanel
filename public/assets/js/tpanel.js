(() => {
  const root = document.documentElement;
  const shell = document.querySelector('[data-shell]');
  const themeToggle = document.querySelector('[data-theme-toggle]');
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const storedTheme = window.localStorage.getItem('tpanel.theme');

  if (storedTheme === 'light' || storedTheme === 'dark') {
    root.dataset.theme = storedTheme;
  }

  themeToggle?.addEventListener('click', () => {
    const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = nextTheme;
    window.localStorage.setItem('tpanel.theme', nextTheme);
  });

  menuToggle?.addEventListener('click', () => {
    shell?.classList.toggle('is-menu-open');
  });
})();
