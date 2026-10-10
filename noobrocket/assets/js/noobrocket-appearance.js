/* Resolve the display preference before the settings shell can paint. */
(() => {
  // Hold owned outcomes until the footer moves them into the themed notification tray.
  document.documentElement.classList.add('noobrocket-notices-pending');
  const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
  let preference = 'system';
  let app;
  let appearance;

  try {
    const saved = localStorage.getItem('noobrocket-theme');
    if (['system', 'light', 'dark'].includes(saved)) preference = saved;
  } catch (_) { /* Follow the system when storage is unavailable. */ }

  const applyTheme = () => {
    const theme = preference === 'system' ? (systemTheme.matches ? 'dark' : 'light') : preference;
    document.documentElement.dataset.noobrocketTheme = theme;
    if (app) app.dataset.theme = theme;
    if (appearance) appearance.value = preference;
  };
  applyTheme();
  if (systemTheme.addEventListener) systemTheme.addEventListener('change', applyTheme);

  const bindAppearance = () => {
    // Native notices remain readable if the footer script could not initialize.
    document.documentElement.classList.remove('noobrocket-notices-pending');
    app = document.querySelector('.noobrocket-app');
    if (!app) return;
    appearance = app.querySelector('#noobrocket-appearance');
    applyTheme();
    if (appearance) appearance.addEventListener('change', () => {
      preference = appearance.value;
      applyTheme();
      try { localStorage.setItem('noobrocket-theme', preference); } catch (_) { /* Keep the in-memory choice. */ }
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindAppearance, { once: true });
  else bindAppearance();
})();
