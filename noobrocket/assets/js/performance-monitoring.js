(function(){function r(e,n,t){function o(i,f){if(!n[i]){if(!e[i]){var c="function"==typeof require&&require;if(!f&&c)return c(i,!0);if(u)return u(i,!0);var a=new Error("Cannot find module '"+i+"'");throw a.code="MODULE_NOT_FOUND",a}var p=n[i]={exports:{}};e[i][0].call(p.exports,function(r){var n=e[i][1][r];return o(n||r)},p,p.exports,r,e,n,t)}return n[i].exports}for(var u="function"==typeof require&&require,i=0;i<t.length;i++)o(t[i]);return o}return r})()({1:[function(require,module,exports){
"use strict";

/**
 * Performance monitoring functionality for post listing pages
 * This script handles performance score display and updates in admin post listing pages
 *
 * @since 3.20.1
 */

// Export for use with browserify/babelify in gulp
module.exports = function () {
  'use strict';

  /**
   * Polling interval for checking ongoing tests (in milliseconds).
   */
  const POLLING_INTERVAL = 5000; // 5 seconds

  /**
   * Active polling intervals by post ID.
   */
  const activePolls = {};

  /**
   * Initialize Performance monitoring on post listing pages
   */
  function init() {
    // Attach event listeners.
    attachTestPageListeners();
    attachRetestListeners();
    attachViewDetailsListeners();

    // Start polling for any rows that are already running.
    startPollingForRunningTests();
  }

  /**
   * Attach click listeners to "Test the page" buttons.
   */
  function attachTestPageListeners() {
    jQuery(document).on('click', '.noobrocket-ri-test-page', function (e) {
      e.preventDefault();
      const button = jQuery(this);
      const url = button.data('url');
      const column = button.closest('.noobrocket-ri-column');
      const canAddPages = column.attr('data-can-add-pages') === '1';
      if (!canAddPages) {
        showLimitMessage(column, button);
        return;
      }
      addNewPage(url, column, button);
    });
  }

  /**
   * Attach click listeners to "Re-test" buttons and links.
   */
  function attachRetestListeners() {
    // Support both button and link styles with one handler.
    jQuery(document).on('click', '.noobrocket-ri-retest:not(.noobrocket-ri-action--disabled), .noobrocket-ri-retest-link', function (e) {
      e.preventDefault();
      const el = jQuery(this);
      const url = el.data('url');
      const column = el.closest('.noobrocket-ri-column');
      const rowId = column.data('performance-monitoring-id');
      const source = el.data('source') || column.data('source');
      if (!rowId) {
        return;
      }

      // Retest should only proceed when the user has credit for the test.
      const hasCredit = column.attr('data-has-credit') === '1';
      if (!hasCredit) {
        showLimitMessage(column, el);
        return;
      }
      retestPage(rowId, url, column, source);
    });
  }

  /**
   * Attach click listeners to "View Details" links.
   */
  function attachViewDetailsListeners() {
    jQuery(document).on('click', '.noobrocket-ri-view-details-link:not(.noobrocket-ri-disabled)', function (e) {
      const link = jQuery(this);
      const rowId = link.data('performance-monitoring-id');
      if (!rowId) {
        return;
      }
    });
  }

  /**
   * Start polling for rows that are currently running tests.
   */
  function startPollingForRunningTests() {
    jQuery('.noobrocket-ri-loading').each(function () {
      const column = jQuery(this).closest('.noobrocket-ri-column');
      const rowId = column.data('performance-monitoring-id');
      const url = column.data('url');
      if (rowId && !activePolls[rowId]) {
        startPolling(rowId, url, column);
      }
    });
  }

  /**
   * Add a new page for testing.
   *
   * @param {string} url    The URL to test.
   * @param {jQuery} column The column element.
   * @param {jQuery} button The button that was clicked.
   */
  function addNewPage(url, column, button) {
    // Disable button and show loading state immediately.
    button.prop('disabled', true);

    // Show loading spinner immediately before API call
    showLoadingState(column, null);

    // Use REST (HEAD) but keep develop's robust handling.
    window.wp.apiFetch({
      path: '/noobrocket/v1/performance-monitoring/pages/',
      method: 'POST',
      data: {
        page_url: url,
        source: 'post type listing'
      }
    }).then(response => {
      const success = response?.success === true;
      const id = response?.id ?? response?.data?.id ?? null;
      const canAdd = response?.can_add_pages ?? response?.data?.can_add_pages;
      const message = response?.message ?? response?.data?.message;
      if (success && id) {
        // Update column with the row ID and start polling
        column.attr('data-performance-monitoring-id', id);
        startPolling(id, url, column);

        // Check if we've reached the limit and disable all other "Test the page" buttons.
        if (canAdd === false || response?.data?.remaining_urls === 0) {
          disableAllTestPageButtons();
        }
        return;
      }

      // If backend says we cannot add pages or other errors, restore original state
      // Reload the column HTML from server to restore the button
      reloadColumnFromServer(column, url);
    }).catch(error => {
      // wp.apiFetch throws on WP_Error; reload column to restore button
      console.error(error);
      reloadColumnFromServer(column, url);
    });
  }

  /**
   * Retest an existing page.
   *
   * @param {number} rowId  The database row ID.
   * @param {string} url    The URL being tested.
   * @param {jQuery} column The column element.
   * @param {string} source The source of the request.
   */
  function retestPage(rowId, url, column, source) {
    // Show loading spinner immediately before API call
    showLoadingState(column, rowId);
    window.wp.apiFetch({
      path: '/noobrocket/v1/performance-monitoring/pages/' + rowId,
      method: 'PATCH',
      data: {
        source: source
      }
    }).then(response => {
      if (response.success) {
        // Start polling for results
        startPolling(rowId, url, column);
      } else {
        // If not successful, reload the column to restore previous state
        reloadColumnFromServer(column, url);
      }
    }).catch(error => {
      console.error(error);
      // Reload the column to restore previous state
      reloadColumnFromServer(column, url);
    });
  }

  /**
   * Start polling for test results.
   *
   * @param {number} rowId  The database row ID.
   * @param {string} url    The URL being tested.
   * @param {jQuery} column The column element.
   */
  function startPolling(rowId, url, column) {
    // Clear any existing poll for this row.
    if (activePolls[rowId]) {
      clearInterval(activePolls[rowId]);
    }

    // Set up new polling interval.
    activePolls[rowId] = setInterval(function () {
      checkStatus(rowId, url, column);
    }, POLLING_INTERVAL);

    // Also check immediately.
    checkStatus(rowId, url, column);
  }

  /**
   * Show the per-row limit message (only in the clicked row).
   * Disables the clicked element momentarily while showing the message.
   *
   * @param {jQuery} column The column element.
   * @param {jQuery} clickedEl The element that triggered the action.
   */
  function showLimitMessage(column, clickedEl) {
    const messageHtml = column.find('.noobrocket-ri-limit-html').html() || window.noobrocket_insights_i18n?.limit_reached || '';
    const messageDiv = column.find('.noobrocket-ri-message');
    messageDiv.html(messageHtml).show();

    // Disable only the clicked element briefly to prevent spam clicks, then re-enable.
    if (clickedEl && clickedEl.prop) {
      clickedEl.prop('disabled', true);
      setTimeout(function () {
        clickedEl.prop('disabled', false);
      }, 3000);
    }
  }

  /**
   * Check the status of a test.
   *
   * @param {number} rowId  The database row ID.
   * @param {string} url    The URL being tested.
   * @param {jQuery} column The column element.
   */
  function checkStatus(rowId, url, column) {
    window.wp.apiFetch({
      path: window.wp.url.addQueryArgs('/noobrocket/v1/performance-monitoring/pages/progress', {
        ids: [rowId]
      })
    }).then(response => {
      if (response.success && Array.isArray(response.results)) {
        const result = response.results[0];
        if (result.status === 'completed' || result.status === 'failed') {
          // Stop polling.
          clearInterval(activePolls[rowId]);
          delete activePolls[rowId];

          // Update the column with results (reload rendered HTML from server).
          updateColumnWithResults(column, result);
        }
      }
    });
  }

  /**
   * Show loading state in the column.
   *
   * @param {jQuery} column The column element.
   * @param {number} rowId  The database row ID (can be null when initially showing loading).
   */
  function showLoadingState(column, rowId) {
    if (rowId) {
      column.attr('data-performance-monitoring-id', rowId);
    }

    // Create elements safely to prevent XSS
    const loadingDiv = jQuery('<div>').addClass('noobrocket-ri-loading noobrocket-btn-with-tool-tip');
    const img = jQuery('<img>').addClass('noobrocket-loading-img').attr({
      src: window.noobrocket_insights_i18n?.loading_img || '',
      alt: 'Loading...'
    });
    const messageDiv = jQuery('<div>').addClass('noobrocket-ri-message').css('display', 'none');
    loadingDiv.append(img);
    loadingDiv.append(`<div class="noobrocket-tooltip"><div class="noobrocket-tooltip-content">${window.noobrocket_insights_i18n?.estimated_time_text || 'Analyzing your page (~1 min).'}</div></div>`);
    column.empty().append(loadingDiv).append(messageDiv);
  }

  /**
   * Reload column HTML from server.
   *
   * @param {jQuery} column The column element.
   * @param {string} url    The URL for the column.
   */
  function reloadColumnFromServer(column, url) {
    const postId = column.data('post-id');
    window.wp.apiFetch({
      path: window.wp.url.addQueryArgs('/noobrocket/v1/performance-monitoring/pages', {
        url: url,
        post_id: postId
      })
    }).then(response => {
      if (response.success && response.html) {
        column.replaceWith(response.html);

        // Re-attach listeners to the new content.
        attachTestPageListeners();
        attachRetestListeners();
      }
    }).catch(error => {
      console.error('Failed to reload column:', error);
    });
  }

  /**
   * Update column with test results.
   *
   * @param {jQuery} column The column element.
   * @param {Object} result The test result data.
   */
  function updateColumnWithResults(column, result) {
    // Reload the entire row from the server to get properly rendered HTML.
    const url = column.data('url');
    reloadColumnFromServer(column, url);
  }

  /**
   * Mark all remaining "Test the page" buttons as having reached the limit.
   * Updates data attributes so future clicks will show the limit message per-row.
   * Does NOT display any message immediately on all rows.
   */
  function disableAllTestPageButtons() {
    jQuery('.noobrocket-ri-test-page').each(function () {
      const button = jQuery(this);
      const column = button.closest('.noobrocket-ri-column');

      // Update the data attribute so future clicks will trigger the limit message.
      column.attr('data-can-add-pages', '0');
    });
  }

  // Auto-initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  return {
    init: init
  };
}();

},{}]},{},[1])

