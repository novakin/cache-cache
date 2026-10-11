/* This script depends on core.js through WordPress's native enqueue graph. */
(function () {
  'use strict';
  function start() {
    if (!window.richFixtureCore || !window.richFixtureCore.dependencyReady) throw new Error('Rich classic dependency order failed');
    document.querySelector('#rich-classic-state').textContent = 'RICH_CLASSIC_READY';
    const menu = document.querySelector('#rich-menu');
    document.querySelector('#rich-menu-toggle').addEventListener('click', function () { menu.hidden = !menu.hidden; this.setAttribute('aria-expanded', String(!menu.hidden)); });
    const dialog = document.querySelector('#rich-dialog');
    if (dialog) {
      document.querySelector('#rich-dialog-open').addEventListener('click', () => dialog.showModal());
      document.querySelector('#rich-dialog-close').addEventListener('click', () => dialog.close());
      for (const name of ['one', 'two']) document.querySelector('#rich-tab-' + name).addEventListener('click', () => {
        for (const other of ['one', 'two']) { document.querySelector('#rich-tab-' + other).setAttribute('aria-selected', String(other === name)); document.querySelector('#rich-panel-' + other).hidden = other !== name; }
      });
      document.querySelector('#rich-carousel-next').addEventListener('click', () => { window.richFixtureCore.slide = window.richFixtureCore.slide % 3 + 1; document.querySelector('#rich-slide-label').textContent = 'Slide ' + window.richFixtureCore.slide + ' of 3'; });
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true }); else start();
}());
