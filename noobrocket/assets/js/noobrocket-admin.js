/* Flight Deck shell: display preferences, navigation and native form feedback. */
(() => {
  const app = document.querySelector('.noobrocket-app');
  if (!app) return;
  const menu = app.querySelector('.noobrocket-menu-toggle');
  const navigation = app.querySelector('#noobrocket-navigation');
  const preferences = app.querySelector('.noobrocket-navigation-preferences');
  const compactNavigation = window.matchMedia('(max-width: 1100px)');
  const positionPreferences = () => {
    if (!preferences) return;
    const parent = compactNavigation.matches ? navigation : app.querySelector('.noobrocket-brand-actions');
    if (parent && preferences.parentElement !== parent) parent.prepend(preferences);
  };
  positionPreferences();
  compactNavigation.addEventListener?.('change', positionPreferences);
  const settings = app.querySelector('#noobrocket_options');
  const footer = app.querySelector('.noobrocket-settings-footer');
  const state = app.querySelector('[data-noobrocket-save-state]');
  const submit = app.querySelector('#noobrocket-options-submit');
  const cancel = app.querySelector('#noobrocket-options-cancel');
  const cleanupDialog = app.querySelector('#noobrocket-cleanup-dialog');
  const cleanupForm = app.querySelector('#noobrocket-database-cleanup');
  const cleanupCancel = cleanupDialog?.querySelector('[data-noobrocket-cleanup-cancel]');
  const cleanupConfirm = cleanupDialog?.querySelector('[data-noobrocket-cleanup-confirm]');
  const operationDialog = app.querySelector('#noobrocket-operation-dialog');
  const operationCancel = operationDialog?.querySelector('[data-noobrocket-operation-cancel]');
  const operationSave = operationDialog?.querySelector('[data-noobrocket-operation-save]');
  const operationContinue = operationDialog?.querySelector('[data-noobrocket-operation-continue]');
  const editor = app.querySelector('#noobrocket-rule-editor');
  const toasts = app.querySelector('.noobrocket-toasts');

  // Keep native POST/new-tab fallback; links also support middle-click and the browser's tab/window menu.
  app.querySelectorAll('button[data-noobrocket-preview-link]').forEach(button => {
    const form = button.form;
    const input = form?.querySelector('input[name="url"]');
    if (!form || !input) return;
    form.target = '_blank';
    form.rel = 'noopener';
    form.dataset.noobrocketPreviewLink = '';
    const link = document.createElement('a');
    link.className = button.className;
    link.textContent = button.textContent;
    link.target = '_blank';
    link.rel = 'noopener';
    link.dataset.noobrocketPreviewLink = '';
    const updateLink = () => {
      const url = new URL(form.getAttribute('action'), location.href);
      const fields = new FormData(form);
      for (const name of ['action', 'command', '_wpnonce', 'draft_revision', 'url']) {
        url.searchParams.set(name, fields.get(name) || '');
      }
      link.href = url.href;
    };
    input.addEventListener('input', updateLink);
    input.addEventListener('change', updateLink);
    form.addEventListener('reset', () => queueMicrotask(updateLink));
    const validatePreview = event => {
      if (event.type === 'auxclick' && event.button !== 1) return;
      if (!form.reportValidity()) event.preventDefault();
    };
    link.addEventListener('click', validatePreview);
    link.addEventListener('auxclick', validatePreview);
    updateLink();
    button.replaceWith(link);
  });

  let toastClearance = null;
  const clearToastClearance = () => {
    toastClearance?.remove();
    toastClearance = null;
  };
  const ensureFocusVisible = target => {
    if (!target?.closest('#noobrocket-main') || footer?.contains(target) || toasts?.contains(target) || !target.getClientRects().length) return;
    const control = ['checkbox', 'radio'].includes(target.type) ? target.labels?.[0] || target : target;
    // Scroll reserve can move the sticky Save footer; settle against its updated bounds.
    for (let pass = 0; pass < 2; pass++) {
      positionToasts();
      const rect = control.getBoundingClientRect();
      const top = (app.querySelector('.noobrocket-brand-bar')?.getBoundingClientRect().bottom || 0) + 8;
      const toastRect = toasts?.getBoundingClientRect();
      const toastOverlaps = toastRect?.height > 0 && rect.right > toastRect.left && rect.left < toastRect.right;
      const bottom = Math.min(footer && !footer.hidden ? footer.getBoundingClientRect().top : window.innerHeight, toastOverlaps ? toastRect.top : window.innerHeight) - 8;
      const offset = rect.top < top ? rect.top - top : rect.bottom > bottom ? rect.bottom - bottom : 0;
      if (!offset) break;
      const availableScroll = Math.max(0, document.documentElement.scrollHeight - window.innerHeight - window.scrollY);
      if (toastOverlaps && offset > availableScroll) {
        if (!toastClearance) {
          toastClearance = document.createElement('div');
          toastClearance.className = 'noobrocket-toast-clearance';
          toastClearance.setAttribute('aria-hidden', 'true');
          if (footer) footer.before(toastClearance);
          else app.querySelector('#noobrocket-main').append(toastClearance);
        }
        toastClearance.style.height = `${Math.ceil((parseFloat(toastClearance.style.height) || 0) + offset - availableScroll)}px`;
      }
      window.scrollBy({ top: offset, behavior: 'auto' });
    }
  };

  // WordPress moves unscoped notices under its first heading. Keep validation with its settings page.
  const errorPages = {
    sucuri_waf_api_key_invalid: 'sucuri',
    cloudflare_api_key_invalid: 'cloudflare',
    reject_uri_global_exclusion: 'advanced_cache'
  };
  const anchors = new Map();
  let firstSettingsError = null;
  for (const notice of document.querySelectorAll('.settings-error.notice-error[id^="setting-error-"]')) {
    const code = notice.id.slice('setting-error-'.length);
    const pageId = errorPages[code] || (code.startsWith('cdn_cname_invalid_') ? 'page_cdn' : null);
    const page = pageId && app.querySelector(`#${pageId}.noobrocket-Page`);
    const anchor = anchors.get(pageId) || page?.querySelector('.noobrocket-sectionHeader');
    if (!anchor) continue;
    notice.classList.add('inline');
    notice.tabIndex = -1;
    anchor.after(notice);
    anchors.set(pageId, notice);
    if (!firstSettingsError) firstSettingsError = { page, notice };
  }
  const revealSettingsError = () => {
    if (!firstSettingsError || app.dataset.noobrocketSettingsUpdated !== 'true') return;
    // Replace the post-save destination instead of adding a second history entry.
    const oldURL = location.href;
    history.replaceState(history.state, '', `#${firstSettingsError.page.id}`);
    window.dispatchEvent(new HashChangeEvent('hashchange', { oldURL, newURL: location.href }));
    requestAnimationFrame(() => firstSettingsError.notice.focus());
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', revealSettingsError);
  else revealSettingsError();

  // One tray owns standalone outcomes. Field validation keeps its original context.
  const renderToast = notice => {
    if (!toasts || !notice || notice.closest('.inline, [data-noobrocket-inline-feedback]')) return false;
    if (notice.classList.contains('noobrocket-toast')) return true;
    const severity = ['success', 'error', 'warning', 'info'].find(type => notice.classList.contains(`notice-${type}`))
      || (notice.classList.contains('updated') ? 'success' : notice.classList.contains('error') ? 'error' : null);
    if (!severity) return false;
    const focused = notice.contains(document.activeElement) ? document.activeElement : null;
    notice.querySelectorAll('.notice-dismiss').forEach(button => button.remove());
    const dismissible = notice.dataset.noobrocketDismissible !== 'false';
    const content = document.createElement('div');
    content.className = 'noobrocket-toast-content';
    content.append(...notice.childNodes);
    const label = document.createElement('span');
    label.className = 'noobrocket-toast-severity';
    label.textContent = toasts.dataset[`${severity}Label`] || '';
    if (label.textContent) content.prepend(label);
    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('class', 'noobrocket-toast-icon');
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('fill', 'none');
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = {
      success: '<circle cx="12" cy="12" r="9"/><path d="m7.5 12 3 3 6-6"/>',
      error: '<circle cx="12" cy="12" r="9"/><path d="m8 8 8 8m0-8-8 8"/>',
      warning: '<path d="m12 3 10 18H2L12 3Z"/><path d="M12 9v5m0 3v.5"/>',
      info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.5"/>'
    }[severity];
    notice.classList.remove('notice', 'updated', 'error', 'is-dismissible');
    notice.classList.add('noobrocket-toast', `notice-${severity}`);
    // The existing tray announces each labeled outcome once, without nested live regions.
    notice.removeAttribute('role');
    notice.removeAttribute('aria-live');
    notice.setAttribute('aria-atomic', 'true');
    notice.replaceChildren(icon, content);
    // Outcomes stay readable until dismissed or the user leaves this document.
    const dismiss = () => {
      if (notice.contains(document.activeElement)) {
        const heading = [...app.querySelectorAll('.noobrocket-Page h1')].find(node => node.getClientRects().length) || app.querySelector('#noobrocket-main');
        heading?.focus({ preventScroll: true });
      }
      notice.remove();
      positionToasts();
      ensureFocusVisible(document.activeElement);
    };
    if (dismissible) {
      const close = document.createElement('button');
      close.type = 'button';
      close.className = 'noobrocket-toast-dismiss';
      close.setAttribute('aria-label', toasts.dataset.dismiss);
      close.textContent = '×';
      close.addEventListener('click', dismiss);
      notice.append(close);
    }
    // Detached notices cannot leave hidden controls in the page's keyboard order.
    notice.remove();
    requestAnimationFrame(() => {
      toasts.append(notice);
      if (focused && document.activeElement === document.body) focused.focus({ preventScroll: true });
      positionToasts();
      ensureFocusVisible(document.activeElement);
    });
    return true;
  };
  const notices = new Set([
    ...app.querySelectorAll('[data-noobrocket-toast]'),
    ...document.querySelectorAll('[data-noobrocket-notice]'),
    ...document.querySelectorAll('.settings-error[id^="setting-error-noobrocket_"]'),
    document.querySelector('#setting-error-settings_updated')
  ]);
  notices.forEach(renderToast);
  const positionToasts = () => {
    if (!toasts) return;
    if (![...toasts.children].some(notice => notice.getClientRects().length)) clearToastClearance();
    const bottom = footer && !footer.hidden ? Math.max(0, window.innerHeight - footer.getBoundingClientRect().top) : 0;
    toasts.style.bottom = `${bottom + 16}px`;
  };
  window.addEventListener('scroll', positionToasts, { passive: true });
  const resetToastClearance = () => {
    clearToastClearance();
    positionToasts();
    ensureFocusVisible(document.activeElement);
  };
  window.addEventListener('resize', resetToastClearance);
  window.addEventListener('hashchange', resetToastClearance);
  document.addEventListener('noobrocketJsAfterPageNavigation', resetToastClearance);
  document.addEventListener('noobrocketNoticeDismissed', positionToasts);
  let editorInitial = '';
  const unfinished = () => !!editor && JSON.stringify([...new FormData(editor)]) !== editorInitial;
  let dirty = false;
  let leaving = false;
  let saving = false;
  let cleanupSubmitter = null;
  let pendingOperation = null;
  let operationConfirmed = false;
  app.addEventListener('click', event => {
    const button = event.target.closest('[data-noobrocket-secret-toggle]');
    if (!button) return;
    const input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;
    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    button.textContent = reveal ? button.dataset.hide : button.dataset.show;
    button.setAttribute('aria-label', reveal ? button.dataset.hideLabel : button.dataset.showLabel);
    button.setAttribute('aria-pressed', String(reveal));
  });

  const closeMenu = () => {
    navigation?.classList.remove('is-open');
    menu?.setAttribute('aria-expanded', 'false');
    if (navigation) { navigation.style.top = ''; navigation.style.maxHeight = ''; }
  };
  const positionMenu = () => {
    if (!navigation?.classList.contains('is-open')) return;
    const bottom = Math.max(0, app.querySelector('.noobrocket-brand-bar')?.getBoundingClientRect().bottom || 0);
    navigation.style.top = `${bottom}px`;
    navigation.style.maxHeight = `calc(100dvh - ${bottom}px)`;
  };
  menu?.addEventListener('click', () => {
    const open = navigation.classList.toggle('is-open');
    menu.setAttribute('aria-expanded', String(open));
    if (open) positionMenu();
    else closeMenu();
  });
  window.addEventListener('scroll', positionMenu, { passive: true });
  window.addEventListener('resize', positionMenu);
  app.addEventListener('keydown', event => {
    if (event.key === 'Escape' && navigation?.classList.contains('is-open')) {
      closeMenu();
      menu.focus();
    }
  });
  navigation?.addEventListener('click', event => {
    if (event.target.closest('.noobrocket-menuItem')) {
      closeMenu();
    }
  });
  app.addEventListener('focusin', event => {
    if (!event.target.closest('#noobrocket-main') && !toasts?.contains(event.target)) return;
    closeMenu();
    if (!event.target.matches('a, button, input, select, textarea, summary')) return;
    // Native focus scrolling does not account for the sticky header and Save bar.
    window.requestAnimationFrame(() => {
      if (document.activeElement === event.target) ensureFocusVisible(event.target);
    });
  });
  document.addEventListener('click', event => {
    if (!event.target.closest('#noobrocket-navigation, .noobrocket-menu-toggle')) closeMenu();
  });

  const syncNavigation = () => {
    const currentLink = app.querySelector('.noobrocket-menuItem.isActive');
    const id = currentLink?.hash.slice(1) || location.hash.slice(1).split('=')[0] || 'dashboard';
    app.dataset.page = id;
    app.querySelectorAll('.noobrocket-menuItem').forEach(link => {
      if (link.hash.slice(1) === id) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
    if (footer) {
      footer.hidden = !dirty && !saving;
      if (!footer.hidden && (dirty || saving)) submit.style.display = 'block';
    }
    positionToasts();
  };
  window.addEventListener('hashchange', syncNavigation);
  document.addEventListener('noobrocketJsAfterPageNavigation', syncNavigation);
  syncNavigation();

  if (editor) {
    const scope = editor.querySelector('#noobrocket-rule-scope');
    const expert = editor.querySelector('#noobrocket-rule-expert');
    const raw = editor.querySelector('#noobrocket-rule-value');
    const choosers = {
      post: editor.querySelector('#noobrocket-rule-page'),
      post_type: editor.querySelector('#noobrocket-rule-post-type'),
      template: editor.querySelector('#noobrocket-rule-template')
    };
    const syncRuleScope = () => {
      const kind = scope.value;
      editor.querySelectorAll('[data-asset-scope]').forEach(group => { group.hidden = group.dataset.assetScope !== kind; });
      Object.entries(choosers).forEach(([key, field]) => {
        if (!field) return;
        field.disabled = key !== kind || expert.checked;
        field.required = !field.disabled;
        field.hidden = expert.checked;
        field.labels?.forEach(label => { label.hidden = expert.checked; });
      });
      const rawMode = expert.checked && kind !== 'everywhere';
      editor.querySelector('[data-asset-raw]').hidden = !rawMode;
      raw.disabled = !rawMode;
      raw.required = rawMode;
      raw.inputMode = kind === 'post' ? 'numeric' : 'text';
      if (kind === 'post') raw.pattern = '[1-9][0-9]*';
      else raw.removeAttribute('pattern');
      raw.maxLength = 200;
      const rawHelp = editor.querySelector('#noobrocket-rule-value-help');
      const scopeHelp = editor.querySelector(`[data-asset-scope="${kind}"] .description`);
      if (rawHelp && scopeHelp) rawHelp.textContent = scopeHelp.textContent;
      if (scopeHelp) scopeHelp.hidden = rawMode;
      const rawLabel = raw.labels?.[0];
      const rawLabelText = { post: raw.dataset.labelPost, post_type: raw.dataset.labelPostType, template: raw.dataset.labelTemplate }[kind];
      if (rawLabel && rawLabelText) rawLabel.textContent = rawLabelText;
      const expertToggle = editor.querySelector('[data-asset-expert-toggle]');
      if (expertToggle) expertToggle.hidden = kind === 'everywhere';
      if (!rawMode) raw.value = kind === 'everywhere' ? '' : choosers[kind]?.value || '';
    };
    if (scope && expert && raw) {
      scope.addEventListener('change', syncRuleScope);
      expert.addEventListener('change', syncRuleScope);
      Object.values(choosers).forEach(field => field?.addEventListener('change', syncRuleScope));
      syncRuleScope();
      app.addEventListener('click', event => {
        const editorLink = event.target.closest('[data-asset-editor-link]');
        if (editorLink) {
          event.preventDefault();
          editor.scrollIntoView({ block: 'start' });
          editor.querySelector('[name="rule[handle]"]').focus({ preventScroll: true });
          return;
        }
        const button = event.target.closest('.noobrocket-asset-create');
        if (!button) return;
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (unfinished() && !window.confirm(state.dataset.replaceRule)) return;
        editor.querySelector('[name="rule[type]"]').value = button.dataset.assetType;
        editor.querySelector('[name="rule[handle]"]').value = button.dataset.assetHandle;
        scope.value = 'post';
        const pageId = button.dataset.assetPage || '';
        const page = choosers.post;
        if (page && [...page.options].some(option => option.value === pageId)) {
          expert.checked = false;
          page.value = pageId;
        } else {
          expert.checked = !!pageId;
          raw.value = pageId;
          if (page) page.value = '';
        }
        editor.querySelector('[name="rule[exceptions]"]').value = '';
        syncRuleScope();
        editor.scrollIntoView({ block: 'start' });
        editor.querySelector('[name="rule[handle]"]').focus({ preventScroll: true });
      });
    }
    editorInitial = JSON.stringify([...new FormData(editor)]);
  }

  if (settings && submit && state) {
    const values = () => {
      const entries = new Map();
      for (const [name, value] of new FormData(settings)) {
        // Display proxies are discarded by the native settings sanitizer.
        if (name === 'noobrocket_settings[cloudflare_api_key_mask]' || name === 'noobrocket_settings[cloudflare_zone_id_mask]') continue;
        if (!entries.has(name)) entries.set(name, []);
        entries.get(name).push(value);
      }
      return entries;
    };
    const displayField = field => field.type === 'hidden' && field.id
      ? document.getElementById(`${field.id}_mask`) || field : field;
    const initial = values();
    const savedControls = [...settings.elements].filter(field => field.name).map(field => ({
      field, value: field.value, checked: field.checked, disabled: field.disabled,
      selected: field.options ? [...field.options].map(option => option.selected) : null
    }));
    const cnameList = settings.querySelector('#noobrocket-cnames-list');
    const savedCnames = cnameList?.cloneNode(true);
    let pendingSections = new Map();
    const fieldLabel = field => {
      field = displayField(field);
      const legend = field.type === 'radio' ? field.closest('fieldset')?.querySelector('legend') : null;
      if (legend) return legend.textContent.trim();
      const label = field.id ? [...settings.querySelectorAll('label')].find(item => item.htmlFor === field.id) : null;
      const heading = field.closest('.noobrocket-fieldsContainer')?.querySelector('.noobrocket-title2, .noobrocket-title3');
      const name = label?.cloneNode(true);
      name?.querySelector('.noobrocket-field-count')?.remove();
      return (name?.textContent || field.getAttribute('aria-label') || heading?.textContent || field.name).trim();
    };
    const describeField = field => ({ page: displayField(field).closest('.noobrocket-Page'), label: fieldLabel(field) });
    const initialFields = new Map([...settings.elements].filter(field => field.name).map(field => [field.name, describeField(field)]));
    const changedFields = () => {
      const current = values();
      return [...new Set([...initial.keys(), ...current.keys()])]
        .filter(name => JSON.stringify(initial.get(name) || []) !== JSON.stringify(current.get(name) || []))
        .map(name => {
          const field = [...settings.elements].find(item => item.name === name);
          return field ? describeField(field) : initialFields.get(name);
        })
        .filter(Boolean);
    };
    const changedSections = fields => {
      const sections = new Map();
      fields.forEach(({ page, label }) => {
        if (!page) return;
        if (!sections.has(page.id)) sections.set(page.id, { title: page.querySelector('h1')?.textContent.trim() || page.id, fields: new Set() });
        sections.get(page.id).fields.add(label);
      });
      return sections;
    };
    const renderChanges = (list, detail = false) => {
      if (!list) return;
      list.replaceChildren();
      pendingSections.forEach((section, id) => {
        const item = document.createElement('li');
        if (detail) item.textContent = `${section.title}: ${[...section.fields].join(', ')}`;
        else {
          const link = document.createElement('a');
          link.href = `#${id}`;
          link.textContent = section.title;
          item.append(link);
        }
        list.append(item);
      });
      if (detail && !list.children.length) {
        const item = document.createElement('li');
        item.textContent = state.dataset.clean;
        list.append(item);
      }
    };
    const syncSaveState = () => {
      const changes = changedFields();
      dirty = changes.length > 0;
      pendingSections = changedSections(changes);
      state.textContent = saving ? state.dataset.saving : dirty ? state.dataset.dirty
        : state.dataset.savedEvidence === 'true' ? state.dataset.saved : state.dataset.clean;
      submit.value = saving ? state.dataset.saving : submit.defaultValue;
      submit.disabled = saving || !dirty;
      if (cancel) {
        cancel.hidden = !dirty && !saving;
        cancel.disabled = saving || leaving || !dirty;
      }
      renderChanges(app.querySelector('[data-noobrocket-changed-sections]'));
      const summary = app.querySelector('[data-noobrocket-pending-summary]');
      if (summary) summary.textContent = `${summary.dataset.label} (${pendingSections.size})`;
      app.querySelector('.noobrocket-save-summary').hidden = !dirty;
      syncNavigation();
    };
    settings.addEventListener('input', syncSaveState);
    settings.addEventListener('change', syncSaveState);
    document.addEventListener('noobrocketJsAfterPageNavigation', syncSaveState);
    syncSaveState();

    cancel?.addEventListener('click', () => {
      if (!dirty || saving || leaving) return;
      const message = unfinished()
        ? `${cancel.dataset.noobrocketConfirmCancel}\n\n${state.dataset.unfinished}`
        : cancel.dataset.noobrocketConfirmCancel;
      if (!window.confirm(message)) return;
      // Native reset restores controls; the owned dynamic list restores removed rows.
      settings.reset();
      if (cnameList && savedCnames) cnameList.replaceChildren(...savedCnames.cloneNode(true).childNodes);
      savedControls.forEach(({ field, value, checked, disabled, selected }) => {
        if (!field.isConnected) return;
        field.value = value;
        field.checked = checked;
        field.disabled = disabled;
        if (selected) [...field.options].forEach((option, index) => { option.selected = selected[index]; });
        field.setCustomValidity?.('');
        field.removeAttribute('aria-invalid');
      });
      if (unfinished()) {
        editor.reset();
        editor.querySelector('#noobrocket-rule-scope')?.dispatchEvent(new Event('change'));
      }
      document.dispatchEvent(new CustomEvent('noobrocketSettingsReset'));
      syncSaveState();
      const page = [...app.querySelectorAll('.noobrocket-Page')].find(node => node.getClientRects().length);
      page?.querySelector('h1')?.focus({ preventScroll: true });
    });

    const reviewOperation = (event, operation) => {
      if (operationConfirmed || (!dirty && (!unfinished() || operation.form === editor))) return false;
      event.preventDefault();
      if (!operationDialog || typeof operationDialog.showModal !== 'function') {
        window.alert(operationDialog?.querySelector('p')?.textContent || state.dataset.discard);
        return true;
      }
      pendingOperation = operation;
      const initiator = operation.link || operation.submitter;
      const owner = operation.form || operation.link;
      const name = owner?.dataset.noobrocketOperationName || initiator?.dataset.noobrocketOperationName
        || initiator?.textContent.trim() || initiator?.value || '';
      const restore = owner?.querySelector('[name="command"]')?.value === 'restore';
      const evictsTarget = restore && owner.dataset.snapshotSaveEvicts === '1';
      const title = operationDialog.querySelector('#noobrocket-operation-title');
      const description = operationDialog.querySelector('#noobrocket-operation-description');
      if (title) title.textContent = title.dataset.review.replace('%s', name);
      if (description) description.textContent = restore ? description.dataset.restore : description.dataset.saved;
      const retention = operationDialog.querySelector('[data-noobrocket-restore-retention]');
      if (retention) retention.hidden = !evictsTarget;
      operationSave.disabled = evictsTarget;
      if (operationContinue.dataset.saved) operationContinue.textContent = restore
        ? operationContinue.dataset.restore : operationContinue.dataset.saved.replace('%s', name);
      renderChanges(operationDialog.querySelector('[data-noobrocket-operation-changes]'), true);
      operationSave.hidden = !dirty;
      if (unfinished() && operation.form !== editor) {
        const item = document.createElement('li');
        item.textContent = state.dataset.unfinished;
        operationDialog.querySelector('[data-noobrocket-operation-changes]').append(item);
      }
      operationDialog.showModal();
      (title || operationCancel).focus({ preventScroll: true });
      operationDialog.scrollTop = 0;
      return true;
    };
    app.addEventListener('click', event => {
      if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      const link = event.target.closest('a[href]');
      if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;
      const url = new URL(link.href);
      if (url.origin !== location.origin || !url.pathname.endsWith('/admin-post.php')) return;
      if (reviewOperation(event, { link })) return;
      leaving = true;
    });

    app.addEventListener('submit', event => {
      if (event.defaultPrevented) return;
      if (event.target !== settings) {
        // A private preview opens elsewhere and must not discard or lock the editor left here.
        const previewForm = event.target.hasAttribute('data-noobrocket-preview-link') && event.target.target === '_blank';
        const previewButton = event.submitter?.matches('button[data-noobrocket-preview-link]') && event.submitter.formTarget === '_blank';
        if ((previewForm || previewButton) && event.target.querySelector('[name="command"]')?.value === 'preview') return;
        if (event.target.dataset.noobrocketPending) { event.preventDefault(); return; }
        if (event.target !== cleanupForm && reviewOperation(event, { form: event.target, submitter: event.submitter })) return;
        const discard = event.target.querySelector('[data-noobrocket-confirm-discard]');
        if (discard && !window.confirm(discard.dataset.noobrocketConfirmDiscard)) {
          event.preventDefault();
          return;
        }
        leaving = true;
        markPending(event.target, event.submitter, event);
        return;
      }
      if (saving) {
        event.preventDefault();
        return;
      }
      if (unfinished() && !window.confirm(state.dataset.unfinished)) {
        event.preventDefault();
        return;
      }
      const submitter = event.submitter;
      saving = true;
      leaving = true;
      settings.dataset.noobrocketSaving = 'true';
      settings.setAttribute('aria-busy', 'true');
      syncSaveState();
      markPending(settings, submitter, event);
    });

    app.querySelector('[data-noobrocket-cleanup-review]')?.addEventListener('click', event => {
      if (!cleanupForm) return;
      const selected = [...settings.querySelectorAll('#database input[type="checkbox"][id^="database_"]:checked')].filter(field => !field.disabled);
      const selection = cleanupDialog?.querySelector('[data-noobrocket-cleanup-selection]');
      const scope = cleanupForm.querySelector('[data-noobrocket-cleanup-scope]');
      selection?.replaceChildren();
      scope.replaceChildren();
      selected.forEach(field => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'noobrocket_cleanup[]';
        input.value = field.id;
        scope.append(input);
        const item = document.createElement('li');
        const count = field.closest('.noobrocket-field')?.querySelector('.noobrocket-field-count')?.textContent.trim();
        item.textContent = `${fieldLabel(field)}${count ? `: ${count}` : ''}`;
        selection?.append(item);
      });
      if (cleanupConfirm) cleanupConfirm.disabled = !selected.length;
      const empty = cleanupDialog?.querySelector('[data-noobrocket-cleanup-empty]');
      if (empty) empty.hidden = selected.length > 0;
      const pending = cleanupDialog?.querySelector('[data-noobrocket-cleanup-pending]');
      if (pending) { pending.hidden = !dirty && !unfinished(); pending.open = !pending.hidden; }
      const changes = cleanupDialog?.querySelector('[data-noobrocket-cleanup-changes]');
      renderChanges(changes, true);
      if (unfinished() && changes) {
        const item = document.createElement('li');
        item.textContent = state.dataset.unfinished;
        changes.append(item);
      }
      cleanupSubmitter = event.currentTarget;
      if (cleanupDialog && typeof cleanupDialog.showModal === 'function') {
        cleanupDialog.showModal();
        cleanupDialog.querySelector('#noobrocket-cleanup-title')?.focus({ preventScroll: true });
        cleanupDialog.scrollTop = 0;
      } else if (selected.length && window.confirm(`${cleanupDialog?.textContent || ''}`)) {
        app.querySelector('#noobrocket-cleanup-confirmed').checked = true;
        cleanupForm.requestSubmit();
      }
    });
    cleanupCancel?.addEventListener('click', () => cleanupDialog.close());
    cleanupDialog?.addEventListener('close', () => {
      cleanupSubmitter?.focus();
      cleanupSubmitter = null;
    });
    cleanupConfirm?.addEventListener('click', () => {
      if (!cleanupSubmitter || cleanupConfirm.disabled) return;
      cleanupDialog.close();
      app.querySelector('#noobrocket-cleanup-confirmed').checked = true;
      cleanupForm.requestSubmit();
    });

    operationCancel?.addEventListener('click', () => operationDialog.close());
    operationDialog?.addEventListener('close', () => {
      if (pendingOperation) {
        (pendingOperation.link || pendingOperation.submitter)?.focus();
        pendingOperation = null;
      }
    });
    operationSave?.addEventListener('click', () => {
      operationDialog.close();
      settings.requestSubmit(submit);
    });
    operationContinue?.addEventListener('click', () => {
      if (!pendingOperation) return;
      const operation = pendingOperation;
      pendingOperation = null;
      operationDialog.close();
      operationConfirmed = true;
      if (operation.form) operation.form.requestSubmit(operation.submitter);
      else { leaving = true; location.assign(operation.link.href); }
      operationConfirmed = false;
    });

    const markPending = (form, button, event) => {
      form.dataset.noobrocketPending = 'true';
      form.setAttribute('aria-busy', 'true');
      button?.setAttribute('aria-disabled', 'true');
      if (cancel) cancel.disabled = true;
      const status = document.createElement('p');
      status.className = 'noobrocket-operation-pending';
      status.setAttribute('role', 'status');
      status.textContent = state.dataset.pending;
      (button?.parentElement || form).append(status);
      const reset = () => {
        delete form.dataset.noobrocketPending;
        form.removeAttribute('aria-busy');
        button?.removeAttribute('aria-disabled');
        status.remove();
        window.removeEventListener('pageshow', resume);
        leaving = false;
        if (form === settings) {
          saving = false;
          delete settings.dataset.noobrocketSaving;
          submit.value = submit.defaultValue;
        }
        syncSaveState();
      };
      const resume = event => { if (event.persisted) reset(); };
      // Native event propagation can run microtasks before later submit listeners.
      setTimeout(() => { if (event.defaultPrevented) reset(); }, 0);
      window.addEventListener('pageshow', resume);
    };

    window.addEventListener('beforeunload', event => {
      if ((dirty || unfinished()) && !leaving) {
        event.preventDefault();
        event.returnValue = '';
      }
    });
  }

  // Lab checks update their own results without submitting or resetting pending settings.
  const pagespeed = app.querySelector('[data-noobrocket-pagespeed]');
  if (pagespeed) {
    const keyError = pagespeed.querySelector('#noobrocket-pagespeed-key-error[data-noobrocket-inline-feedback]');
    if (keyError && (!location.hash || location.hash === '#dashboard')) {
      const revealKeyError = () => requestAnimationFrame(() => {
        keyError.scrollIntoView({ block: 'center' });
        keyError.focus({ preventScroll: true });
      });
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', revealKeyError);
      else revealKeyError();
    }
    const buttons = [...pagespeed.querySelectorAll('[data-noobrocket-pagespeed-run]')];
    const status = pagespeed.querySelector('[data-noobrocket-pagespeed-status]');
    const showOutcome = (severity, message) => {
      const notice = document.createElement('div');
      notice.className = `notice notice-${severity}`;
      const text = document.createElement('p');
      text.textContent = message;
      notice.append(text);
      if (renderToast(notice)) {
        status.textContent = '';
        status.classList.remove('is-error');
      } else {
        status.textContent = message;
        status.classList.toggle('is-error', severity === 'error');
      }
    };
    const locale = document.documentElement.lang || undefined;
    const number = (value, digits) => new Intl.NumberFormat(locale, {
      minimumFractionDigits: digits, maximumFractionDigits: digits
    }).format(value);
    const renderResult = (panel, result) => {
      panel.querySelector('[data-noobrocket-pagespeed-empty]').hidden = true;
      panel.querySelector('[data-noobrocket-pagespeed-data]').hidden = false;
      panel.querySelector('[data-noobrocket-pagespeed-url]').textContent = result.url;
      const checked = new Date(result.checked_at * 1000);
      const time = panel.querySelector('[data-noobrocket-pagespeed-time]');
      time.dateTime = checked.toISOString();
      time.textContent = checked.toLocaleString(locale);
      for (const scoreNode of panel.querySelectorAll('[data-noobrocket-pagespeed-score]')) {
        const category = scoreNode.dataset.noobrocketPagespeedScore;
        const value = result.scores[category];
        const available = Number.isInteger(value) && value >= 0 && value <= 100;
        const grade = !available ? 'unavailable' : value >= 90 ? 'good' : value >= 50 ? 'improve' : 'poor';
        scoreNode.textContent = available ? String(value) : '—';
        const badge = panel.querySelector(`[data-noobrocket-pagespeed-grade="${category}"]`);
        badge.textContent = pagespeed.dataset[grade];
        badge.className = `noobrocket-badge ${
          { good: 'is-success', improve: 'is-warning', poor: 'is-error', unavailable: 'is-neutral' }[grade]
        }`;
      }
      for (const metricNode of panel.querySelectorAll('[data-noobrocket-pagespeed-metric]')) {
        const metric = metricNode.dataset.noobrocketPagespeedMetric;
        const value = result.metrics[metric];
        if (!Number.isFinite(value)) metricNode.textContent = pagespeed.dataset.unavailable;
        else if (metric === 'cls') metricNode.textContent = number(value, 3);
        else if (metric === 'tbt') metricNode.textContent = `${number(value, 0)} ms`;
        else metricNode.textContent = `${number(value / 1000, 2)} s`;
      }
    };
    let checking = false;
    for (const button of buttons) {
      button.addEventListener('click', async () => {
        if (checking || button.disabled) return;
        checking = true;
        const strategy = button.dataset.noobrocketPagespeedRun;
        const panel = pagespeed.querySelector(`[data-noobrocket-pagespeed-result="${strategy}"]`);
        const device = panel.querySelector('h3').textContent;
        const disabled = buttons.map(control => control.disabled);
        buttons.forEach(control => { control.disabled = true; });
        panel.setAttribute('aria-busy', 'true');
        status.classList.remove('is-error');
        status.textContent = `${device}: ${pagespeed.dataset.running}`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 110000);
        try {
          const response = await fetch(window.ajaxurl, {
            method: 'POST', credentials: 'same-origin', signal: controller.signal,
            body: new URLSearchParams({
              action: 'noobrocket_pagespeed_run', nonce: pagespeed.dataset.nonce, strategy
            })
          });
          const payload = await response.json();
          if (!response.ok || !payload.success) {
            showOutcome('error', payload.data?.message || pagespeed.dataset.error);
            return;
          }
          renderResult(panel, payload.data.result);
          showOutcome('success', `${device}: ${pagespeed.dataset.complete}`);
        } catch {
          showOutcome('error', pagespeed.dataset.error);
        } finally {
          clearTimeout(timeout);
          checking = false;
          panel.removeAttribute('aria-busy');
          buttons.forEach((control, index) => { control.disabled = disabled[index]; });
        }
      });
    }
  }
})();
