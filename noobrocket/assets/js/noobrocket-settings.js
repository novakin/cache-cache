(function(){function r(e,n,t){function o(i,f){if(!n[i]){if(!e[i]){var c="function"==typeof require&&require;if(!f&&c)return c(i,!0);if(u)return u(i,!0);var a=new Error("Cannot find module '"+i+"'");throw a.code="MODULE_NOT_FOUND",a}var p=n[i]={exports:{}};e[i][0].call(p.exports,function(r){var n=e[i][1][r];return o(n||r)},p,p.exports,r,e,n,t)}return n[i].exports}for(var u="function"==typeof require&&require,i=0;i<t.length;i++)o(t[i]);return o}return r})()({1:[function(require,module,exports){
"use strict";

document.querySelectorAll(".custom-select").forEach(customSelect => {
  const selectBtn = customSelect.querySelector(".select-button");
  const selectedValue = customSelect.querySelector(".selected-value");
  const handler = function (elm) {
    const customChangeEvent = new CustomEvent('custom-select-change', {
      detail: {
        selectedOption: elm
      }
    });
    selectedValue.textContent = elm.textContent;
    customSelect.classList.remove("active");
    customSelect.dispatchEvent(customChangeEvent);
  };
  selectBtn.addEventListener("click", () => {
    customSelect.classList.toggle("active");
    selectBtn.setAttribute("aria-expanded", selectBtn.getAttribute("aria-expanded") === "true" ? "false" : "true");
  });
  customSelect.addEventListener('click', function (e) {
    if (e.target.matches('label')) {
      const allItems = customSelect.querySelectorAll('li');
      allItems.forEach(item => item.classList.remove('active'));
      const clickedPlan = e.target.closest('li');
      if (clickedPlan) {
        clickedPlan.classList.add('active');
        handler(clickedPlan);
      }
    }
  });
  document.addEventListener("click", e => {
    if (!customSelect.contains(e.target)) {
      customSelect.classList.remove("active");
      selectBtn.setAttribute("aria-expanded", "false");
    }
  });
});

},{}],2:[function(require,module,exports){
"use strict";

var $ = jQuery;
$(document).ready(function () {
  /**
   * Refresh License data
   */
  var _isRefreshing = false;
  $('#noobrocket-action-refresh_account').on('click', function (e) {
    if (!_isRefreshing) {
      var button = $(this);
      var account = $('#noobrocket-account-data');
      var expire = $('#noobrocket-expiration-data');
      e.preventDefault();
      _isRefreshing = true;
      button.trigger('blur');

      // Start polling if not already running.addClass('noobrocket-isLoading');
      expire.removeClass('noobrocket-isValid noobrocket-isInvalid');
      $.post(ajaxurl, {
        action: 'noobrocket_refresh_customer_data',
        _ajax_nonce: noobrocket_ajax_data.nonce
      }, function (response) {
        button.removeClass('noobrocket-isLoading');
        button.addClass('noobrocket-isHidden');
        if (true === response.success) {
          account.html(response.data.license_type);
          expire.addClass(response.data.license_class).html(response.data.license_expiration);
          if (response.data.license_expiration_label) {
            document.getElementById('noobrocket-expiration-label').textContent = response.data.license_expiration_label;
          }
          setTimeout(function () {
            button.removeClass('noobrocket-icon-refresh noobrocket-isHidden');
            button.addClass('noobrocket-icon-check');
          }, 250);
        } else {
          setTimeout(function () {
            button.removeClass('noobrocket-icon-refresh noobrocket-isHidden');
            button.addClass('noobrocket-icon-close');
          }, 250);
        }
        setTimeout(function () {
          var vTL = new TimelineLite({
            onComplete: function () {
              _isRefreshing = false;
            }
          }).set(button, {
            css: {
              className: '+=noobrocket-isHidden'
            }
          }).set(button, {
            css: {
              className: '-=noobrocket-icon-check'
            }
          }, 0.25).set(button, {
            css: {
              className: '-=noobrocket-icon-close'
            }
          }).set(button, {
            css: {
              className: '+=noobrocket-icon-refresh'
            }
          }, 0.25).set(button, {
            css: {
              className: '-=noobrocket-isHidden'
            }
          });
        }, 2000);
      });
    }
    return false;
  });

  /**
      * Save enable CPCSS for mobiles option.
      */
  $('#noobrocket-action-noobrocket_enable_mobile_cpcss').on('click', function (e) {
    e.preventDefault();
    $('#noobrocket-action-noobrocket_enable_mobile_cpcss').addClass('noobrocket-isLoading');
    $.post(ajaxurl, {
      action: 'noobrocket_enable_mobile_cpcss',
      _ajax_nonce: noobrocket_ajax_data.nonce
    }, function (response) {
      if (response.success) {
        // Hide Mobile CPCSS btn on success.
        $('#noobrocket-action-noobrocket_enable_mobile_cpcss').hide();
        $('.noobrocket-hide-on-click').hide();
        $('.noobrocket-show-on-click').show();
        $('#noobrocket-action-noobrocket_enable_mobile_cpcss').removeClass('noobrocket-isLoading');
      }
    });
  });

  /**
   * Enable a Tools option only after the action confirms persisted settings.
   */
  function enableToolsOption(selector, action, onSuccess) {
    $(selector).on('click', function (e) {
      e.preventDefault();
      var button = $(this);
      if (button.prop('disabled')) return;
      var hadFocus = button[0] === document.activeElement;
      button.addClass('noobrocket-isLoading').prop('disabled', true).attr('aria-busy', 'true');
      var showError = function () {
        var notice = document.createElement('div');
        notice.className = 'notice notice-error';
        notice.setAttribute('role', 'alert');
        var text = document.createElement('p');
        text.textContent = button.attr('data-error');
        notice.appendChild(text);
        var feedback = document.querySelector('.noobrocket-global-feedback');
        if (feedback) feedback.appendChild(notice);
        else button[0].parentNode.appendChild(notice);
        // The admin shell uses its existing tray; native feedback remains if it is unavailable.
        document.dispatchEvent(new CustomEvent('noobrocketNoticeAdded', { detail: notice }));
      };
      $.post(ajaxurl, {
        action: action,
        _ajax_nonce: noobrocket_ajax_data.nonce
      }).done(function (response) {
        if (response && response.success === true) onSuccess(button);
        else showError();
      }).fail(showError).always(function () {
        button.removeClass('noobrocket-isLoading').prop('disabled', false).removeAttr('aria-busy');
        if (hadFocus && document.activeElement === document.body && button[0].getClientRects().length) button[0].focus();
      });
    });
  }

  /**
   * Save enable Google Fonts Optimization option.
   */
  enableToolsOption('#noobrocket-action-noobrocket_enable_google_fonts', 'noobrocket_enable_google_fonts', function (button) {
    button.hide();
    $('#noobrocket-google-fonts-enabler .noobrocket-hide-on-click').hide();
    $('#noobrocket-google-fonts-enabler .noobrocket-show-on-click').show();
    $('#minify_google_fonts').val(1);
  });
  $('#noobrocket-dismiss-promotion').on('click', function (e) {
    e.preventDefault();
    $.post(ajaxurl, {
      action: 'noobrocket_dismiss_promo',
      nonce: noobrocket_ajax_data.nonce
    }, function (response) {
      if (response.success) {
        $('#noobrocket-promo-banner').hide('slow');
      }
    });
  });
  $('#noobrocket-dismiss-renewal').on('click', function (e) {
    e.preventDefault();
    $.post(ajaxurl, {
      action: 'noobrocket_dismiss_renewal',
      nonce: noobrocket_ajax_data.nonce
    }, function (response) {
      if (response.success) {
        $('#noobrocket-renewal-banner').hide('slow');
      }
    });
  });
  $('#noobrocket-update-exclusion-list').on('click', function (e) {
    e.preventDefault();
    $('#noobrocket-update-exclusion-msg').html('');
    $.ajax({
      url: noobrocket_ajax_data.rest_url,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', noobrocket_ajax_data.rest_nonce);
        xhr.setRequestHeader('Accept', 'application/json, */*;q=0.1');
        xhr.setRequestHeader('Content-Type', 'application/json');
      },
      method: "PUT",
      success: function (responses) {
        let exclusion_msg_container = $('#noobrocket-update-exclusion-msg');
        exclusion_msg_container.html('');
        if (undefined !== responses['success']) {
          exclusion_msg_container.append('<div class="notice notice-error">' + responses['message'] + '</div>');
          return;
        }
        Object.keys(responses).forEach(response_key => {
          exclusion_msg_container.append('<strong>' + response_key + ': </strong>');
          exclusion_msg_container.append(responses[response_key]['message']);
          exclusion_msg_container.append('<br>');
        });
      }
    });
  });

  /**
   * Enable mobile cache option.
   */
  enableToolsOption('#nbr_enable_mobile_cache', 'noobrocket_enable_mobile_cache', function (button) {
    button.hide();
    $('#nbr_mobile_cache_default').hide();
    $('#nbr_mobile_cache_response').show();
    $('#cache_mobile').val(1);
    $('#do_caching_mobile_files').val(1);
  });
});
document.addEventListener('DOMContentLoaded', function () {
  const analyticsCheckbox = document.getElementById('analytics_enabled');
  if (analyticsCheckbox) {
    analyticsCheckbox.addEventListener('change', function () {
      const isChecked = this.checked;

      // Update the global mixpanel data optin state immediately
      if (typeof noobrocket_mixpanel_data !== 'undefined') {
        noobrocket_mixpanel_data.optin_enabled = isChecked ? '1' : '0';
      }
      fetch(ajaxurl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          action: 'noobrocket_toggle_optin',
          value: isChecked ? 1 : 0,
          _ajax_nonce: noobrocket_ajax_data.nonce
        })
      });
    });
  }
});
document.addEventListener('DOMContentLoaded', function () {
  /**
   * Performance Monitoring with Progressive Polling.
   */

  // ==== Configuration ====
  const POLL_BASE_INTERVAL = 2000; // Start polling at 2 seconds
  const POLL_MAX_INTERVAL = 5000; // Max polling interval (5 seconds)

  // ==== State ====
  let noobrocketInsightsIds = Array.isArray(window.noobrocket_ajax_data?.noobrocket_insights_ids) ? window.noobrocket_ajax_data.noobrocket_insights_ids.slice() : [];
  let pollInterval = POLL_BASE_INTERVAL;
  let pollTimer = null;
  let hasCredit = true; // Track credit status
  let globalScoreData = {
    data: {
      status: '',
      score: 0,
      pages_num: 0
    },
    html: '',
    row_html: '',
    disabled_btn_html: {
      global_score_widget: '',
      noobrocket_insights: ''
    }
  };

  // Initialize globalScoreData from localized script data if available
  if (window.noobrocket_ajax_data?.global_score_data) {
    globalScoreData = window.noobrocket_ajax_data.global_score_data;
  }

  // ==== DOM Selectors ====
  const $pageUrlInput = $('#noobrocket-speed-radar-url-input');
  const $tableBody = $('.noobrocket-ri-urls-table tbody');
  const $table = $('.noobrocket-ri-urls-table');

  // ==== Utility Functions ====
  function isValidUrl(input) {
    try {
      const url = new URL(input);
      return url.hostname.includes('.') && url.hostname.split('.').pop().length > 0;
    } catch {
      return false;
    }
  }
  function addIds(newId) {
    if (!noobrocketInsightsIds.includes(newId)) {
      noobrocketInsightsIds.push(newId);
    }
  }
  function hasId(id) {
    return noobrocketInsightsIds.includes(id);
  }
  function removeId(id) {
    // Ensure that the id to be removed is an integer for accurate comparison.
    const idToRemove = parseInt(id, 10);
    noobrocketInsightsIds = noobrocketInsightsIds.filter(x => parseInt(x, 10) !== idToRemove);
  }
  function updateQuotaBanner(canAddPages) {
    const $summaryInfo = $('.noobrocket-ri-summary-info');
    const isFree = window.noobrocket_ajax_data?.is_free === '1';
    const $quotaBanner = isFree ? $('#noobrocket-ri-quota-banner') : $('#noobrocket_insights_survey');

    // Show banner if URL limit reached OR no credits left (matching PHP logic in Subscriber.php line 398).
    const shouldShowBanner = canAddPages === false || !hasCredit;
    if (shouldShowBanner) {
      $summaryInfo.hide();
      $quotaBanner.removeClass('hidden');
    } else {
      $summaryInfo.show();
      $quotaBanner.addClass('hidden');
    }
  }
  function updateCreditState(responseHasCredit) {
    if (responseHasCredit !== undefined && hasCredit !== responseHasCredit) {
      hasCredit = responseHasCredit;

      // Update all retest buttons when credit status changes
      updateAllRetestButtons();
    }
  }
  function updateAllRetestButtons() {
    const retestButtons = document.querySelectorAll('.noobrocket-action-speed_radar_refresh');
    retestButtons.forEach(button => {
      const row = button.closest('.noobrocket-ri-item');
      if (!row) return;

      // Get the row ID and check if it's currently being processed
      const rowId = parseInt(row.dataset.noobrocketInsightsId, 10);
      const isRunning = noobrocketInsightsIds.includes(rowId);
      if (!hasCredit || isRunning) {
        // Disable button
        button.classList.add('noobrocket-ri-action--disabled');
        button.setAttribute('disabled', 'true');
        if (!hasCredit) {
          // Add tooltip for no credit
          button.classList.add('noobrocket-btn-with-tool-tip');
          button.setAttribute('title', window.noobrocket_ajax_data?.noobrocket_insights_no_credit_tooltip || 'Upgrade your plan to get access to re-test performance or run new tests');
        }
      } else {
        // Enable button
        button.classList.remove('noobrocket-ri-action--disabled', 'noobrocket-btn-with-tool-tip');
        button.removeAttribute('disabled');
        button.removeAttribute('title');
      }
    });
  }
  function resetPolling() {
    if (pollTimer) {
      clearTimeout(pollTimer);
      pollTimer = null;
    }
    pollInterval = POLL_BASE_INTERVAL;
  }
  function schedulePolling() {
    if (noobrocketInsightsIds.length > 0) {
      pollTimer = setTimeout(() => {
        getResults();
      }, pollInterval);
    }
  }
  function incrementPolling() {
    pollInterval = Math.min(pollInterval * 1.3, POLL_MAX_INTERVAL); // Exponential backoff
  }
  function isOnDashboard() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('page') === 'noobrocket' && window.location.hash === '#dashboard';
  }
  function isOnPerformanceMonitoring() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('page') === 'noobrocket' && window.location.hash === '#noobrocket_insights';
  }
  function updateGlobalScoreRow(globalScoreData) {
    const $tableGlobalScore = $('.noobrocket-ri-urls-table .noobrocket-global-score');
    if ($tableGlobalScore.length > 0) {
      $tableGlobalScore.replaceWith(globalScoreData.row_html);
    } else if ($tableBody.length > 0) {
      $tableBody.prepend(globalScoreData.row_html);
    }
    decideGlobalScoreToUpdate();
  }

  /**
   * Updates the global score UI widget or table row based on the selected menu.
   * When the dashboard or rocket insights menu is clicked, this function updates
   * the corresponding global score display after a short delay.
   */
  function decideGlobalScoreToUpdate() {
    if ('' === globalScoreData.html) {
      return;
    }
    let globalScoreWidgets = $('.noobrocket-global-score-widget');
    if (globalScoreWidgets.length === 0) {
      return;
    }

    // Update all global score widget instances.
    globalScoreWidgets.html(globalScoreData.html);

    // Disable "Add Pages" button on global score widget.
    if (!('disabled_btn_html' in globalScoreData)) {
      return;
    }
    $('.noobrocket-global-score-widget .noobrocket-global-score-widget-btn-wrapper').html(globalScoreData.disabled_btn_html.global_score_widget);
  }

  // ==== AJAX Handlers ====
  function getResults() {
    if (noobrocketInsightsIds.length === 0) {
      resetPolling();
      return;
    }
    window.wp.apiFetch({
      path: window.wp.url.addQueryArgs('/noobrocket/v1/performance-monitoring/pages/progress', {
        ids: noobrocketInsightsIds
      })
    }).then(response => {
      if (response.success && Array.isArray(response.results)) {
        // Update credit status
        updateCreditState(response.has_credit);

        // Update quota banner visibility
        updateQuotaBanner(response.can_add_pages);

        // Update global score data and widget when status || page count changes.
        if (globalScoreData.data.status !== response.global_score_data.data.status || globalScoreData.data.pages_num !== response.global_score_data.data.pages_num) {
          // Update global score data.
          globalScoreData = response.global_score_data;

          // Update all global score widget instances.
          $('.noobrocket-global-score-widget').html(response.global_score_data.html);
          // Update global score row in table if on Performance monitoring page.
          updateGlobalScoreRow(globalScoreData);

          // Fire custom event for other widgets (like recommendations)
          document.dispatchEvent(new CustomEvent('noobrocketGlobalScoreUpdated', {
            detail: globalScoreData.data
          }));
        }
        response.results.forEach(result => {
          const $row = $(`.noobrocket-ri-item[data-performance-monitoring-id="${result.id}"]`);
          $row.replaceWith(result.html);
          $(document).trigger('performance-monitoring-page-test-polling', [result.id]);

          // Trigger custom event only when test is completed and not failed, so we don't target an element that might be removed from the DOM after test completion.
          if (result.status === 'completed') {
            $(document).trigger('performance-monitoring-page-test-completed', [result.id]);
          }
          if (result.status === 'completed' || result.status === 'failed') {
            removeId(result.id);
          }
        });
        incrementPolling();
        schedulePolling();
      } else {
        // On error, clear IDs and stop polling
        noobrocketInsightsIds = [];
        resetPolling();
        console.error('Polling error:', response.results || response);
      }
    });
  }
  function handleAddPage(e) {
    e.preventDefault();

    // check if has attr disabled
    if ($(this).attr('disabled')) {
      return;
    }
    const pageUrl = $pageUrlInput.val().trim();
    if (!isValidUrl(pageUrl)) {
      alert('Please enter a valid URL');
      return;
    }
    const source = $(this).data('source');
    window.wp.apiFetch({
      path: '/noobrocket/v1/performance-monitoring/pages/',
      method: 'POST',
      data: {
        page_url: pageUrl,
        source: source
      }
    }).then(response => {
      if (response.success) {
        if (!hasId(response.id)) {
          $pageUrlInput.val('');
          $tableBody.append(response.html);

          // Custom event when new page is added.
          $(document).trigger('performance-monitoring-page-added');
          $table.removeClass('hidden');
          addIds(response.id);
          let pages_num_container = $('#noobrocket_noobrocket_insights_pages_num');
          pages_num_container.text(parseInt(pages_num_container.text()) + 1);

          // Update credit status
          updateCreditState(response.has_credit);

          // Update global score data.
          globalScoreData = response.global_score_data;

          // Update global score row in table if on Performance monitoring page.
          updateGlobalScoreRow(globalScoreData);
          if ('disabled_btn_html' in globalScoreData) {
            $('#nbr_noobrocket_insights_add_page_btn_wrapper').html(globalScoreData.disabled_btn_html.noobrocket_insights);
          }

          // Show/hide quota banner based on can_add_pages
          updateQuotaBanner(response.can_add_pages);
          if (pollTimer) {
            resetPolling();
          }
          schedulePolling();
        }
      } else {
        // Clear the input field on error
        $pageUrlInput.val('');

        // Handle URL limit reached error
        if (response?.message && response.message.includes('Maximum number of URLs reached')) {
          // Update UI state to reflect URL limit has been reached
          disableAddUrlElements();
          // Show quota banner (can_add_pages = false)
          updateQuotaBanner(response.can_add_pages !== undefined ? response.can_add_pages : false);
        }
        console.error(response?.message || response);
      }
    });
  }
  function handleResetPage(e) {
    e.preventDefault();
    const $button = $(this);
    let id = $button.parents('.noobrocket-ri-item').data('performance-monitoring-id');
    if (!id) {
      return;
    }
    const source = $button.data('source');
    window.wp.apiFetch({
      path: '/noobrocket/v1/performance-monitoring/pages/' + id,
      method: 'PATCH',
      data: {
        source: source
      }
    }).then(response => {
      if (response.success) {
        addIds(response.id);
        $(`#ri_details_${response.id} .details-section-td`).remove();
        const $row = $(`[data-performance-monitoring-id="${response.id}"]`);
        $row.replaceWith(response.html);

        // Custom event when page is retested.
        $(document).trigger('performance-monitoring-page-retest', [response.id]);

        // Update credit status
        updateCreditState(response.has_credit);

        // Update quota banner visibility
        updateQuotaBanner(response.can_add_pages);

        // Update global score data.
        globalScoreData = response.global_score_data;

        // Update global score row in table if on Performance monitoring page.
        updateGlobalScoreRow(globalScoreData);
        if (pollTimer) {
          resetPolling();
        }
        schedulePolling();
      } else {
        console.error(response?.message || response);
      }
    });
  }

  // ==== Initialization ====
  // Bind event
  $(document).on('click', '#noobrocket-action-add_page_speed_radar', handleAddPage);
  $(document).on('click', '.noobrocket-action-speed_radar_refresh', handleResetPage);
  // Handle Enter key press on page url input.
  $(document).on('keypress', '#noobrocket-speed-radar-url-input', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      $('#noobrocket-action-add_page_speed_radar').click();
    }
  });

  // Only poll if on a noobrocket section that requires polling(dashboard|noobrocket_insights) (more robust check)
  function isValidPageForPolling() {
    const urlParams = new URLSearchParams(window.location.search);
    return 'noobrocket' === urlParams.get('page');
  }

  // Resume polling if needed
  if (isValidPageForPolling() && noobrocketInsightsIds.length > 0) {
    if (pollTimer) {
      resetPolling();
    }
    schedulePolling();
  }

  // Handle UI update on the rocket insights tab when "Add Pages" button on the global score widget is clicked.
  $(document).on('click', '.noobrocket-percentage-score-widget .noobrocket-ri-add-url-button', function () {
    if (!this.textContent.includes('Add Pages')) {
      return;
    }

    // Delay UI update a bit till element is visible.
    setTimeout(() => {
      updateGlobalScoreRow(globalScoreData);
    }, 30);
  });

  // Handle collapseed styling for first load or dynamic row addition.
  function addCollapsedStylingToLastRow(onLoad = false) {
    $('.noobrocket-ri-item').last().find('td').addClass('border-bottom');
    if (onLoad) {
      // On load, remove noobrocket-last-expanded from elements that are not the last after being added from backend.
      $('.noobrocket-ri-item-toggle').not(':last').removeClass('noobrocket-last-expanded');
      $('.noobrocket-ri-item-actions').not(':last').removeClass('noobrocket-last-expanded');
      $('.details-section-td').not(':last').removeClass('noobrocket-last-expanded');

      // Bail early if last item is already expanded on load so as not to have conflicting styles.
      if ($('.details-section-td').last().hasClass('noobrocket-last-expanded')) {
        return;
      }
    }
    $('.noobrocket-ri-item-toggle').last().addClass('noobrocket-last-collapsed');
    $('.noobrocket-ri-item-actions').last().addClass('noobrocket-last-collapsed');
    $('.details-section-td').last().addClass('noobrocket-last-collapsed');
  }

  // Toggles the visibility of a single test details row, switches the caret icon, and updates styling for the last item.
  function toggleSingleRowVisibility(insightsId, source) {
    let $element = $(`#ri_details_${insightsId}`);
    let isVisible = $element.hasClass('noobrocket-ri-details--expanded');
    let isLast = $(`[data-performance-monitoring-id="${insightsId}"] .noobrocket-ri-item-toggle-single`).is($('.noobrocket-ri-item-toggle-single').last());
    if (isVisible) {
      $element.removeClass('noobrocket-ri-details--expanded');
      $(`[data-performance-monitoring-id="${insightsId}"]`).removeClass('noobrocket-ri-item--expanded');
      // Manipulate styling for last elements when details cell is not visible.
      if (isLast) {
        updateRowStylingForLastItem(false);
      }
      return;
    }
    $element.addClass('noobrocket-ri-details--expanded');
    $(`[data-performance-monitoring-id="${insightsId}"]`).addClass('noobrocket-ri-item--expanded');

    // Track expand only expand metric action.
    handleMetricActionTracking('expand', insightsId, source);
    if (isLast) {
      updateRowStylingForLastItem();
    }
  }

  // Tracks user interactions with metric actions in Performance monitoring via AJAX.
  function handleMetricActionTracking(event, rowId, source) {
    $.post(ajaxurl, {
      action: 'noobrocket_insight_track_metric_actions',
      _ajax_nonce: noobrocket_ajax_data.nonce,
      event: event,
      row_id: rowId,
      source: source
    }, function (response) {
      if (!response.success) {
        console.error('Metric action tracking failed:', response?.data || response);
      }
    });
  }

  /**
   * Updates the border styling for the last row item in the Performance monitoring table.
   *
   * Manages border radius and bottom border styling based on whether the details
   * cell is expanded or collapsed to maintain proper visual appearance.
   */
  function updateRowStylingForLastItem(isExpanded = true) {
    const addState = isExpanded ? 'noobrocket-last-expanded' : 'noobrocket-last-collapsed';
    const removeState = isExpanded ? 'noobrocket-last-collapsed' : 'noobrocket-last-expanded';
    var $selectors = {
      lastToggle: $('.noobrocket-ri-item-toggle').last(),
      lastActions: $('.noobrocket-ri-item-actions').last()
    };
    $selectors.lastToggle.removeClass(removeState).addClass(addState);
    $selectors.lastActions.removeClass(removeState).addClass(addState);

    // Check if last detail row is not the last row in the table so as not to apply improper styling with border radius between rows.
    var $lastDetailsCell = $('.details-section-td').last();
    if ($lastDetailsCell.closest('tr').next('tr').length !== 0) {
      return;
    }
    $lastDetailsCell.removeClass(removeState).addClass(addState);
  }

  // Toggle single item.
  $(document).on('click', '.noobrocket-ri-item-toggle-single', function () {
    var insightsId = $(this).closest('.noobrocket-ri-item').data('performance-monitoring-id');
    toggleSingleRowVisibility(insightsId, 'url_expand');
  });

  // Toggle all items.
  $(document).on('click', '.noobrocket-ri-item-toggle-all', function () {
    if ($('.noobrocket-ri-details--expanded').length > 0) {
      $('.noobrocket-ri-details').removeClass('noobrocket-ri-details--expanded');
      $('.noobrocket-ri-item').removeClass('noobrocket-ri-item--expanded');
      $(this).removeClass('noobrocket-ri-item-toggle-all--expanded');
      updateRowStylingForLastItem(false);
      return;
    }
    $('.noobrocket-ri-details').addClass('noobrocket-ri-details--expanded');
    $('.noobrocket-ri-item').addClass('noobrocket-ri-item--expanded');
    $(this).addClass('noobrocket-ri-item-toggle-all--expanded');
    updateRowStylingForLastItem();

    // Track single expand event for "Expand All" action with test_id as 'all'.
    handleMetricActionTracking('expand', 'all', 'global_expand');
  });

  // Track "See GTmetrix Report" clicks in Performance monitoring.
  $(document).on('click', '.noobrocket-ri-report', function (e) {
    // Only track if link is not disabled and mixpanel is available.
    if ($(this).hasClass('noobrocket-ri-action--disabled')) {
      return;
    }
    var insightsId = $(this).data('performance-monitoring-row-id');
    handleMetricActionTracking('see_report', insightsId, 'see_report_button');
  });

  // Update table styling after new page is added.
  $(document).on('performance-monitoring-page-test-completed', function (e, insightsId) {
    // Bail out if there is more than 1 result.
    if ($('.noobrocket-ri-item-result').length > 1) {
      return;
    }

    // Remove dynamic class when only one item exist in table.
    $('.noobrocket-last-collapsed').removeClass('noobrocket-last-collapsed');
  });

  // Update table styling after new page is added.
  $(document).on('performance-monitoring-page-added', function (e) {
    // Remove dynamic class for last item if exists when new page is added.
    $('.noobrocket-last-collapsed').removeClass('noobrocket-last-collapsed');
    $('.noobrocket-last-expanded').removeClass('noobrocket-last-expanded');
    $('.border-bottom').removeClass('border-bottom');
  });

  // Update table styling after retest or polling update for last row.
  $(document).on('performance-monitoring-page-retest performance-monitoring-page-test-polling', function (e, insightsId) {
    // Check if item is the last.
    var isLast = $(`[data-performance-monitoring-id="${insightsId}"]`).is($('.noobrocket-ri-item-result').last());
    if (isLast) {
      addCollapsedStylingToLastRow();
    }
  });
  $(document).on('performance-monitoring-page-retest', function (e, insightsId) {
    $(`#ri_details_${insightsId}`).removeClass('noobrocket-ri-details--expanded');
    $(`[data-performance-monitoring-id="${insightsId}"]`).removeClass('noobrocket-ri-item--expanded');
  });
  $(window).load(() => {
    if (!isOnPerformanceMonitoring()) {
      return;
    }

    // Add collapsed styling to the last row on initial load.
    addCollapsedStylingToLastRow(true);

    // Set initial expand/collapse state.
    const urlParams = new URLSearchParams(window.location.search);
    const testId = urlParams.get('ri_id');

    // Send mixpanel event for auto expanded row.
    let firstRowId = $('.noobrocket-ri-item--expanded')?.first()?.data('performance-monitoring-id');

    // Check if ri_id was passed in query string to open specific test.
    if (!testId || testId === '') {
      if (firstRowId) {
        handleMetricActionTracking('expand', firstRowId, 'auto_expand_url');
      }
      return;
    }
    handleMetricActionTracking('expand', testId, 'post type listing');
    $('html, body').animate({
      scrollTop: $(`[data-performance-monitoring-id="${testId}"]`).offset().top - 100
    }, 500);

    // Remove ri_id from URL without page reload
    urlParams.delete('ri_id');
    const newUrl = window.location.pathname + '?' + urlParams.toString() + window.location.hash;
    window.history.replaceState({}, '', newUrl);
  });
});

},{}],3:[function(require,module,exports){
"use strict";

require("../lib/greensock/TweenLite.min.js");
require("../lib/greensock/TimelineLite.min.js");
require("../lib/greensock/easing/EasePack.min.js");
require("../lib/greensock/plugins/CSSPlugin.min.js");
require("../lib/greensock/plugins/ScrollToPlugin.min.js");
require("../global/pageManager.js");
require("../global/main.js");
require("../global/fields.js");
require("../global/beacon.js");
require("../global/ajax.js");
require("../global/recommendations-widget.js");
require("../global/countdown.js");
require("../global/mixpanel.js");

},{"../global/ajax.js":2,"../global/beacon.js":4,"../global/cdn-driver.js":5,"../global/countdown.js":6,"../global/fields.js":7,"../global/main.js":8,"../global/mixpanel.js":9,"../global/pageManager.js":10,"../global/recommendations-widget.js":11,"../global/noobrocketcdn-subscription-polling.js":12,"../global/noobrocketcdn.js":13,"../lib/greensock/TimelineLite.min.js":14,"../lib/greensock/TweenLite.min.js":15,"../lib/greensock/easing/EasePack.min.js":16,"../lib/greensock/plugins/CSSPlugin.min.js":17,"../lib/greensock/plugins/ScrollToPlugin.min.js":18}],4:[function(require,module,exports){
"use strict";

var $ = jQuery;
$(document).ready(function () {
  $('.noobrocket-infoAction--help').on('click', function (event) {
    event.preventDefault();
    window.open(noobrocket_ajax_data.documentation_url, '_blank', 'noopener,noreferrer');
  });
  $('.noobrocket-ri-report').on('click', function () {
    noobrocketTrackHelpButton('rocket insights see gtmetrix report', 'performance summary');
  });

  // MixPanel tracking function
  function noobrocketTrackHelpButton(button, context) {
    if (typeof mixpanel !== 'undefined' && mixpanel.track) {
      // Check if user has opted in using localized data
      if (typeof noobrocket_mixpanel_data === 'undefined' || !noobrocket_mixpanel_data.optin_enabled || noobrocket_mixpanel_data.optin_enabled === '0') {
        return;
      }

      // Identify user with hashed license email if available
      if (noobrocket_mixpanel_data.user_id && typeof mixpanel.identify === 'function') {
        mixpanel.identify(noobrocket_mixpanel_data.user_id);
      }
      mixpanel.track('Button Clicked', {
        'button': button,
        'button_context': context,
        'plugin': noobrocket_mixpanel_data.plugin,
        'brand': noobrocket_mixpanel_data.brand,
        'application': noobrocket_mixpanel_data.app,
        'context': noobrocket_mixpanel_data.context,
        'path': noobrocket_mixpanel_data.path,
        'interaction_channel': 'UI'
      });
    }
  }

  // Make function globally available
  window.noobrocketTrackHelpButton = noobrocketTrackHelpButton;
});

},{}],5:[function(require,module,exports){
"use strict";

(document => {
  'use strict';

  function notifyCdnStateChange() {
    document.dispatchEvent(new CustomEvent('noobrocket-cdn-state-change'));
  }

  /**
   * Keeps the hidden cdn_type and cdn_state form inputs in sync after a REST-driven
   * mode change so a subsequent form save doesn't send stale values and trigger
   * CdnStateBridge::reconcile() to recompute the wrong state.
   *
   * Mirrors the mapping in Rest::apply_cdn_mode():
   *   byocdn → cdn_type=byocdn; everything else → cdn_type=noobrocketcdn.
   *
   * @param {string} mode New cdn_state value ('noobrocketcdn_free', 'byocdn', 'nothing', etc.).
   */
  function syncCdnHiddenInputs(mode) {
    const stateInput = document.getElementById('cdn_state');
    if (stateInput) {
      stateInput.value = mode;
    }
    const typeInput = document.getElementById('cdn_type');
    if (typeInput) {
      typeInput.value = 'byocdn' === mode ? 'byocdn' : 'noobrocketcdn';
    }
    const cdnInput = document.querySelector('input[type="hidden"][name="noobrocket_settings[cdn]"]');
    if (cdnInput) {
      cdnInput.value = 'nothing' === mode ? '0' : '1';
    }
  }
  document.addEventListener('DOMContentLoaded', () => {
    initCdnDriverTabs();
    initCdnModeToggle();
    initAddHomepage();
    initAddPage();
    initDeletePage();
    updateSubmitButtonStateOnSubscriptionLoading();
  });
  const addHomeButton = document.querySelector('#nbr_add_page_component .noobrocket-cdn-add-page__homepage');

  /**
   * Updates the status indicator component with new HTML content.
   *
   * The paid tier's indicator carries the same `.noobrocketcdn` class its section
   * header uses, since it has no separate content wrapper of its own - see
   * add_noobrocketcdn_paid_section() in Controller.php. That's what lets
   * toggleDriverSections() hide it while "Other CDN" is the active tab, by
   * toggling `noobrocket-isHidden` directly on it. The server-rendered replacement
   * markup has no notion of that client-only tab state, so a plain outerHTML
   * swap would drop the class and make it pop up outside its tab - carry it
   * over explicitly when present.
   *
   * @param {string} html - The HTML string to replace the status indicator with.
   * @returns {void}
   */
  function updateStatusIndicatorComponent(html) {
    // #nbr_cdn_status_indicator is shared by the free, paid AND "Your CDN" (BYOCDN)
    // templates - the free tier additionally wraps it in .noobrocket-cdn-built-in, the paid
    // tier does not, and both tab panels stay in the DOM at once (one merely hidden),
    // so a plain getElementById() can silently grab the BYOCDN copy instead and inject
    // HostedCDN's message into the "Other CDN" tab. Explicitly skip any match under
    // .your-own-cdn - see updateByocdnStatusIndicator(), which excludes HostedCDN's
    // copy the same way in reverse.
    const statusIndicator = Array.from(document.querySelectorAll('#nbr_cdn_status_indicator')).find(el => !el.closest('.your-own-cdn'));
    if (!statusIndicator) {
      if (html) {
        // Re-insert when the element was removed (e.g. count dropped back to 0 after
        // all pages were deleted, and the indicator was removed when count first hit 1).
        const addPageSection = document.querySelector('.noobrocket-cdn-built-in .noobrocket-cdn-add-page');
        if (addPageSection) {
          addPageSection.insertAdjacentHTML('beforebegin', html);
        }
      }
      return;
    }
    if (!html) {
      statusIndicator.remove();
      return;
    }
    const wasHidden = statusIndicator.classList.contains('noobrocket-isHidden');
    statusIndicator.outerHTML = html;
    if (wasHidden) {
      // Same duplicate-id caveat as above - re-run the same exclusion instead of
      // getElementById(), which would return the "Your CDN" copy if it precedes
      // this one in document order.
      const refreshed = Array.from(document.querySelectorAll('#nbr_cdn_status_indicator')).find(el => !el.closest('.your-own-cdn'));
      if (refreshed) {
        refreshed.classList.add('noobrocket-isHidden');
      }
    }
  }

  /**
   * Updates the "Other CDN" (BYOCDN) status indicator with new HTML content.
   *
   * Scoped to the "Your CDN" section container rather than the shared
   * #nbr_cdn_status_indicator id, since that id can also be present (hidden)
   * in the HostedCDN section markup at the same time - a global lookup would
   * risk updating the wrong one. Unlike HostedCDN's indicator, an empty
   * `html` here means "no status message at all", so the element is removed
   * rather than left untouched - along with its adjacent separator, which
   * the initial PHP render (your-own-cdn.php) only draws while the message
   * is showing.
   *
   * @param {string} html - The status indicator HTML to show, or an empty string to show none.
   * @returns {void}
   */
  function updateByocdnStatusIndicator(html) {
    const container = document.querySelector('.noobrocket-fieldsContainer-fieldset.your-own-cdn');
    if (!container) {
      return;
    }
    const existing = container.querySelector('#nbr_cdn_status_indicator');
    const separator = container.querySelector('.noobrocket-cdn-built-in__separator');
    if (existing) {
      if (html) {
        existing.outerHTML = html;
      } else {
        existing.remove();
      }
    } else if (html) {
      container.insertAdjacentHTML('afterbegin', html);
    }
    if (!html) {
      if (separator) {
        separator.remove();
      }
      return;
    }
    if (!separator) {
      const indicator = container.querySelector('#nbr_cdn_status_indicator');
      if (indicator) {
        indicator.insertAdjacentHTML('afterend', '<div class="noobrocket-cdn-built-in__separator"></div>');
      }
    }
  }

  /**
   * Toggles the disabled state of CDN-related UI elements based on the active driver.
   *
   * For the 'noobrocketcdn' driver, targets both shared CDN and HostedCDN sections.
   * For all other drivers, only targets the shared CDN section and always enables it.
   *
   * @param {string}  driver   The CDN driver identifier (e.g. 'noobrocketcdn').
   * @param {boolean} disabled Whether to disable the CDN UI elements.
   */
  function updateHostedCDNElementsState(driver, disabled) {
    if ('noobrocketcdn' === driver) {
      if (!disabled) {
        document.querySelectorAll('.cdn-shared-section, .noobrocketcdn-shared-section').forEach(el => {
          el.classList.remove('noobrocket-cdn-disabled');
        });
        return;
      }
      document.querySelectorAll('.cdn-shared-section, .noobrocketcdn-shared-section').forEach(el => {
        el.classList.add('noobrocket-cdn-disabled');
      });
      return;
    }
    document.querySelectorAll('.cdn-shared-section').forEach(el => {
      el.classList.remove('noobrocket-cdn-disabled');
    });
  }

  /**
   * Shows or hides the limit-reached tooltip on the ADD PAGE button.
   *
   * @param {boolean} limitReached Whether the free-tier page limit has been reached.
   * @returns {void}
   */
  function updateTooltipState(limitReached) {
    const tooltip = document.querySelector('.noobrocket-cdn-add-page__button-wrapper .noobrocket-tooltip');
    if (tooltip) {
      tooltip.classList.toggle('noobrocket-isHidden', !limitReached);
    }
  }

  /** Pending banner auto-expand timer ID, or null when no timer is active. */
  let autoExpandTimer = null;

  /**
   * Updates the HostedCDN CTA visibility and expansion state.
   *
   * When the page count reaches the limit, expansion is deferred by 15 seconds so the
   * user has time to react before the upsell banner opens. Any in-flight timer is
   * cancelled whenever this function is called (e.g. on page deletion), ensuring stale
   * expands never fire after the state has changed.
   *
   * @param {number} count Current number of free-tier pages.
   * @param {number} limit Free-tier page limit.
   * @returns {void}
   */
  function updateMonitoringCtaState(count, limit) {
    const cta = document.getElementById('noobrocket-noobrocketcdn-cta');
    const resellerBanner = document.getElementById('noobrocket-noobrocketcdn-reseller-limit-cta');
    if (!cta && !resellerBanner) {
      return;
    }

    // Cancel any pending expand — state has changed.
    if (autoExpandTimer !== null) {
      clearTimeout(autoExpandTimer);
      autoExpandTimer = null;
    }
    const atLimit = count >= limit;
    if (cta) {
      cta.classList.toggle('noobrocket-isHidden', count === 0);
    }
    if (resellerBanner) {
      resellerBanner.classList.toggle('noobrocket-isHidden', !atLimit);
    }
    if (cta) {
      if (atLimit) {
        // Always show "Nice work!" text immediately.
        cta.classList.add('noobrocket-noobrocketcdn-cta---max-limit');
        if (!cta.classList.contains('noobrocket-noobrocketcdn-cta--expanded')) {
          // Banner is collapsed — keep it collapsed for 15s then expand.
          cta.classList.add('noobrocket-noobrocketcdn-cta--collapsed');
          cta.classList.remove('noobrocket-noobrocketcdn-cta--expanded');
          autoExpandTimer = setTimeout(() => {
            autoExpandTimer = null;
            cta.classList.remove('noobrocket-noobrocketcdn-cta--collapsed');
            cta.classList.add('noobrocket-noobrocketcdn-cta--expanded');
            document.dispatchEvent(new CustomEvent('noobrocketCDNBannerAutoExpanded'));
          }, 15000);
        }
      } else {
        cta.classList.toggle('noobrocket-noobrocketcdn-cta--collapsed', count > 0);
        cta.classList.remove('noobrocket-noobrocketcdn-cta--expanded', 'noobrocket-noobrocketcdn-cta---max-limit');
      }
    }
  }

  // Cancel any pending banner expand when the user navigates away from the CDN tab.
  document.addEventListener('noobrocketJsAfterPageNavigation', e => {
    if (e.detail.pageId !== 'page_cdn' && autoExpandTimer !== null) {
      clearTimeout(autoExpandTimer);
      autoExpandTimer = null;
    }
  });

  /**
   * Listens for custom 'noobrocketJsAfterPageNavigation' event to update the state of the submit button
   * based on the presence of a CDN subscription loading indicator on the CDN settings page.
   *
   * Disables the submit button when navigating to the CDN page if a subscription loading indicator is present,
   * and re-enables it when navigating away from the CDN page.
   */
  function updateSubmitButtonStateOnSubscriptionLoading() {
    document.addEventListener('noobrocketJsAfterPageNavigation', e => {
      // Bail out if submit button is not visible for the current page.
      if (getComputedStyle(e.detail.submitButton).display === 'none') {
        return;
      }
      const classes = ['.noobrocket-icon-orange-loader', '.noobrocket-cdn-built-in--disabled'];
      const allPresent = classes.every(cls => document.querySelector(cls) !== null);

      // Re-enable submit button when page is not cdn and bail out.
      if (e.detail.pageId !== 'page_cdn') {
        if (e.detail.submitButton.classList.contains('noobrocket-cdn-disabled')) {
          e.detail.submitButton.classList.remove('noobrocket-cdn-disabled');
        }
        return;
      }

      // Bail out if no cdn subscription loader is present.
      if (!allPresent) {
        return;
      }

      // Disable submit button when on cdn page and subscription loader is present.
      e.detail.submitButton.classList.add('noobrocket-cdn-disabled');
    });
  }
  function setSubscriptionLoadingState() {
    const builtIn = document.querySelector('.noobrocket-cdn-built-in');
    if (builtIn) {
      builtIn.classList.add('noobrocket-cdn-built-in--disabled');
    }

    // Disable purge CDN cache section.
    const purgeSection = document.querySelector('.noobrocket-cdn-purge.noobrocketcdn');
    if (purgeSection) {
      purgeSection.classList.add('noobrocket-cdn-disabled');
    }

    // Disable exclusion fields and section header.
    document.querySelectorAll('.noobrocket-cdn-exclusions').forEach(el => {
      el.classList.add('noobrocket-cdn-disabled');
      const textarea = el.querySelector('textarea');
      if (textarea) {
        textarea.disabled = true;
      }
    });
    const submitButton = document.querySelector('#noobrocket-options-submit');
    if (submitButton) {
      submitButton.classList.add('noobrocket-cdn-disabled');
    }

    // Disable mode toggles to prevent state changes during subscription creation.
    document.querySelectorAll('.noobrocket-cdn-mode-toggle__input').forEach(modeToggle => {
      modeToggle.disabled = true;
    });

    // Create polling mechanism to send a request every 10 seconds to get the subscription status and once the subscription is active, we will refresh the page for now.
    document.dispatchEvent(new CustomEvent('noobrocketCDNSubscriptionLoading', {}));
  }

  /**
   * Updates the `noobrocket-cdn-active-indicator` class to reflect which CDN driver header
   * and tab are active.
   *
   * @param {Element|null} activeToggle Toggle whose parent header and matching tab should
   *                                    receive the class, or null to clear all.
   */
  function updateCdnActiveIndicator(activeToggle) {
    document.querySelectorAll('.noobrocket-cdn-active-indicator').forEach(el => {
      el.classList.remove('noobrocket-cdn-active-indicator');
    });
    if (activeToggle) {
      const header = activeToggle.closest('.noobrocket-optionHeader');
      if (header) {
        header.classList.add('noobrocket-cdn-active-indicator');
      }
      const mode = activeToggle.getAttribute('data-cdn-mode');
      const tabDriver = 'byocdn' === mode ? 'your-own-cdn' : 'noobrocketcdn';
      const tab = document.querySelector(`.noobrocket-cdn-tabs__tab[data-cdn-driver="${tabDriver}"]`);
      if (tab) {
        tab.classList.add('noobrocket-cdn-active-indicator');
      }
    }
  }

  /**
   * Updates the toggle checkboxes and active indicators to reflect HostedCDN Free
   * having just been activated server-side (auto-activation from the "nothing active"
   * state, or after a confirmed activation prompt), without a full page reload.
   */
  function activateFreeModeUI() {
    const freeToggle = document.querySelector('.noobrocket-cdn-mode-toggle__input[data-cdn-mode="noobrocketcdn_free"]');
    if (!freeToggle) {
      return;
    }
    document.querySelectorAll('.noobrocket-cdn-mode-toggle__input').forEach(other => {
      other.checked = other === freeToggle;
    });
    updateCdnActiveIndicator(freeToggle);
    toggleDriverSections('noobrocketcdn');
    setActiveTab('noobrocketcdn');
    notifyCdnStateChange();
    syncCdnHiddenInputs('noobrocketcdn_free');
  }

  /**
   * Initializes the CDN mode toggle checkboxes.
   *
   * Checking activates that mode; unchecking leaves all modes inactive ('nothing').
   * Only one mode can be active at a time — checking one unchecks the others.
   * The request fires immediately on toggle change.
   */
  function initCdnModeToggle() {
    document.addEventListener('change', event => {
      const toggle = event.target.closest('.noobrocket-cdn-mode-toggle__input');
      if (!toggle) {
        return;
      }
      const mode = toggle.getAttribute('data-cdn-mode');
      if (!mode) {
        return;
      }

      // The checkbox has already flipped by the time `change` fires - capture the
      // requested state, then hold the toggle at its previous state and disabled
      // until the request resolves, so nothing about it changes prematurely.
      const requestedChecked = toggle.checked;
      const requestedMode = requestedChecked ? mode : 'nothing';
      const sectionDriver = 'byocdn' === mode ? 'your-own-cdn' : 'noobrocketcdn';
      const toggleWrapper = toggle.closest('.noobrocket-cdn-mode-toggle');
      toggle.checked = !requestedChecked;
      toggle.disabled = true;
      if (toggleWrapper) {
        toggleWrapper.classList.add('noobrocket-cdn-mode-toggle--loading');
      }
      window.wp.apiFetch({
        path: '/noobrocket/v1/noobrocketcdn/mode',
        method: 'POST',
        data: {
          mode: requestedMode
        }
      }).then(response => {
        toggle.checked = requestedChecked;
        if (toggleWrapper) {
          toggleWrapper.classList.remove('noobrocket-cdn-mode-toggle--loading');
        }
        if (requestedChecked) {
          // Uncheck all other mode toggles (mutually exclusive).
          document.querySelectorAll('.noobrocket-cdn-mode-toggle__input').forEach(other => {
            if (other !== toggle) {
              other.checked = false;
            }
          });
          toggleDriverSections(sectionDriver);
          setActiveTab(sectionDriver);
        }
        updateCdnActiveIndicator(requestedChecked ? toggle : null);
        notifyCdnStateChange();
        updateHostedCDNElementsState('byocdn' === mode ? 'byocdn' : 'noobrocketcdn', response.disable_noobrocket_cdn_elements);
        syncCdnHiddenInputs(requestedMode);

        // Enabling either driver changes the other's paused/active status too - e.g.
        // switching to Other CDN pauses HostedCDN, and vice versa - so refresh both
        // status indicators regardless of which toggle was just flipped, instead of
        // only the one matching `mode`. Safe to call unconditionally: both update
        // functions now scope themselves to their own tab (each explicitly excludes
        // the other's copy of #nbr_cdn_status_indicator), and every save_cdn_mode()
        // response already carries both HTML fragments, freshly rendered from the
        // just-persisted state.
        updateByocdnStatusIndicator(response.byocdn_status_indicator_html);
        refreshUIElements(response);
        if ('byocdn' === mode) {
          toggle.disabled = false;
          return;
        }

        // refreshUIElements() calls setSubscriptionLoadingState() above when the async
        // subscription creation is still in progress, which disables every mode toggle -
        // leave this one disabled too until the poller confirms it's live.
        if (!response.is_subscription_creation_loading) {
          toggle.disabled = false;
        }
      }).catch(() => {
        // Request failed - the toggle still reflects its pre-click state, just re-enable it.
        toggle.disabled = false;
        if (toggleWrapper) {
          toggleWrapper.classList.remove('noobrocket-cdn-mode-toggle--loading');
        }
      });
    });
  }

  /**
   * Toggles visibility of CDN driver sections using the hidden utility class.
   *
   * @param {string} activeDriver Active CDN driver slug ('noobrocketcdn' or 'your-own-cdn').
   */
  function toggleDriverSections(activeDriver) {
    document.querySelectorAll('.noobrocketcdn, .your-own-cdn').forEach(section => {
      section.classList.toggle('noobrocket-isHidden', !section.classList.contains(activeDriver));
    });
  }

  /**
   * Updates all .noobrocketcdn-driver-js spans to reflect the active driver label.
   *
   * @param {string} driver Active CDN driver slug ('noobrocketcdn' or 'your-own-cdn').
   */
  function updateDriverLabel(driver) {
    const tab = document.querySelector(`.noobrocket-cdn-tabs__tab[data-cdn-driver="${driver}"]`);
    if (!tab) {
      return;
    }
    const label = tab.getAttribute('data-title');
    if (!label) {
      return;
    }
    document.querySelectorAll('.noobrocketcdn-driver-js').forEach(span => {
      span.textContent = label;
    });
  }

  /**
   * Updates the "Need Help?" link href for the CDN Exclusions section
   * to point to the correct docs article for the active driver.
   *
   * @param {string} driver Active CDN driver slug ('noobrocketcdn' or 'your-own-cdn').
   */
  function updateExcludeCdnHelpUrl(driver) {
    const link = document.querySelector('.exclude-cdn-help-js');
    if (!link) {
      return;
    }
    const isHostedCdn = 'noobrocketcdn' === driver;
    const url = isHostedCdn ? link.dataset.noobrocketcdnUrl : link.dataset.otherCdnUrl;
    const id = isHostedCdn ? link.dataset.noobrocketcdnId : link.dataset.otherCdnId;
    if (url) {
      link.href = url;
    }
    if (id) {
      link.dataset.beaconId = id;
    }
  }

  /**
   * Sets the active driver tab and syncs all tab-dependent UI (label spans, help URL).
   *
   * @param {string} driver Active CDN driver slug ('noobrocketcdn' or 'your-own-cdn').
   */
  function setActiveTab(driver) {
    document.querySelectorAll('.noobrocket-cdn-tabs__tab').forEach(t => {
      t.classList.toggle('noobrocket-cdn-tabs__tab--active', t.getAttribute('data-cdn-driver') === driver);
    });
    updateDriverLabel(driver);
    updateExcludeCdnHelpUrl(driver);
  }

  /**
   * Initializes CDN driver tab switching behavior.
   *
   * Tabs are navigation only — no backend call on click.
   * Initial driver is derived from the PHP-rendered active-state header
   * (noobrocket-cdn-active-indicator), which reflects cdn_state even when the mode
   * toggle itself is hidden (e.g. noobrocket_display_cdn_mode_toggle returning
   * false for a hosting compatibility layer). Falls back to whichever
   * toggle is checked when no header is marked active (e.g. cdn_state is
   * 'nothing').
   */
  function initCdnDriverTabs() {
    const tabs = document.querySelectorAll('.noobrocket-cdn-tabs__tab');
    if (!tabs.length) {
      return;
    }
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const driver = tab.getAttribute('data-cdn-driver');
        if (!driver) {
          return;
        }
        setActiveTab(driver);
        toggleDriverSections(driver);
        notifyCdnStateChange();
      });
    });
    const activeHeader = document.querySelector('.noobrocket-optionHeader.noobrocket-cdn-active-indicator');
    let initialDriver;
    if (activeHeader) {
      initialDriver = activeHeader.classList.contains('your-own-cdn') ? 'your-own-cdn' : 'noobrocketcdn';
    } else {
      const checkedToggle = document.querySelector('.noobrocket-cdn-mode-toggle__input:checked');
      initialDriver = checkedToggle && 'byocdn' === checkedToggle.getAttribute('data-cdn-mode') ? 'your-own-cdn' : 'noobrocketcdn';
    }
    setActiveTab(initialDriver);
    toggleDriverSections(initialDriver);
    notifyCdnStateChange();
  }

  /**
   * Adds a page (or the homepage) to HostedCDN free-tier delivery, transparently
   * handling the "HostedCDN Free is inactive" activation prompt: on a 409
   * confirm-required error, shows a native confirmation dialog and retries with
   * `confirm_activation` if the user accepts. If the server auto-activated Free
   * (no mode was active at all), updates the toggle UI to reflect it.
   *
   * @param {string} path REST path to call ('/noobrocket/v1/noobrocketcdn/pages' or '.../pages/homepage').
   * @param {Object} data Request body data (e.g. { url }).
   * @returns {Promise} Resolves with the REST response; rejects on final failure or cancellation.
   */
  function requestAddPage(path, data) {
    return window.wp.apiFetch({
      path,
      method: 'POST',
      data
    }).then(response => {
      if (response.free_activated) {
        activateFreeModeUI();
      }
      return response;
    }).catch(error => {
      if ('noobrocketcdn_free_inactive_confirm_required' === error.code && window.confirm(error.message)) {
        return requestAddPage(path, Object.assign({}, data, {
          confirm_activation: true
        }));
      }
      throw error;
    });
  }

  /**
   * Initializes the "ADD HOMEPAGE" button.
   *
   * Sends a POST request to the HostedCDN REST endpoint to add
   * the site homepage as a free-tier CDN page.
   */
  function initAddHomepage() {
    document.addEventListener('click', event => {
      const button = event.target.closest('#nbr_add_page_component .noobrocket-cdn-add-page__homepage');
      if (!button) {
        return;
      }
      button.disabled = true;
      const builtIn = document.querySelector('.noobrocket-cdn-built-in');
      if (builtIn) {
        builtIn.classList.add('noobrocket-cdn-built-in--disabled');
      }
      requestAddPage('/noobrocket/v1/noobrocketcdn/pages/homepage', {}).then(response => {
        button.classList.add('noobrocket-isHidden');
        updateMonitoringCtaState(response.count, response.limit);
        if (builtIn) {
          builtIn.classList.remove('noobrocket-cdn-built-in--disabled');
        }
        if (response.items_html) {
          const existing = document.querySelector('.noobrocket-cdn-built-in .noobrocket-table-list');
          if (existing) {
            existing.remove();
          }
          const addPageSection = document.querySelector('.noobrocket-cdn-add-page');
          if (addPageSection) {
            addPageSection.insertAdjacentHTML('beforebegin', response.items_html);
          }
        }

        // Track banner view when first page is added and banner becomes visible.
        if (1 === response.count) {
          document.querySelector('.noobrocket-cdn-built-in .noobrocket-cdn-built-in__separator')?.remove();
          document.dispatchEvent(new CustomEvent('noobrocketCDNBannerFirstVisible'));
        }
        refreshUIElements(response);
      }).catch(() => {
        button.disabled = false;
        if (builtIn) {
          builtIn.classList.remove('noobrocket-cdn-built-in--disabled');
        }
      });
    });
  }
  /**
   * Initializes the "ADD PAGE" input and button.
   *
   * Sends a POST request to the HostedCDN REST endpoint to add
   * a page URL to the free-tier CDN page list.
   */
  function initAddPage() {
    const input = document.getElementById('nbr_cdn_add_page_input');
    const button = document.querySelector('.noobrocket-cdn-add-page__button');
    if (!input || !button) {
      return;
    }
    function isValidUrl(input) {
      try {
        const url = new URL(input);
        return url.hostname.includes('.') && url.hostname.split('.').pop().length > 0;
      } catch {
        return false;
      }
    }
    function submitPage() {
      const url = input.value.trim();
      if (!isValidUrl(url)) {
        alert('Please enter a valid URL');
        return;
      }

      // Prevent duplicate request while request is in flight.
      input.disabled = true;
      button.disabled = true;
      const builtIn = document.querySelector('.noobrocket-cdn-built-in');
      if (builtIn) {
        builtIn.classList.add('noobrocket-cdn-built-in--disabled');
      }
      requestAddPage('/noobrocket/v1/noobrocketcdn/pages', {
        url
      }).then(response => {
        input.value = '';
        input.disabled = false;
        button.disabled = false;
        addHomeButton.classList.add('noobrocket-isHidden');
        updateMonitoringCtaState(response.count, response.limit);
        if (builtIn) {
          builtIn.classList.remove('noobrocket-cdn-built-in--disabled');
        }

        // Update page list with response.
        if (response.items_html) {
          const existing = document.querySelector('.noobrocket-cdn-built-in .noobrocket-table-list');
          if (existing) {
            existing.remove();
          }
          const addPageSection = document.querySelector('.noobrocket-cdn-add-page');
          if (addPageSection) {
            addPageSection.insertAdjacentHTML('beforebegin', response.items_html);
          }
        }

        // Track banner view when first page is added and banner becomes visible.
        if (1 === response.count) {
          document.querySelector('.noobrocket-cdn-built-in .noobrocket-cdn-built-in__separator')?.remove();
          document.dispatchEvent(new CustomEvent('noobrocketCDNBannerFirstVisible'));
        }
        if (response.limit === response.count) {
          // Disable input and button when page limit is reached.
          document.querySelector('.noobrocket-cdn-built-in').classList.add('noobrocket-cdn-built-in--disabled');
          const addPageWrapper = document.querySelector('.noobrocket-cdn-add-page__button-wrapper');
          if (addPageWrapper) {
            addPageWrapper.classList.add('noobrocket-btn-with-tool-tip');
          }
          const addPageBtn = document.querySelector('.noobrocket-cdn-add-page__button');
          if (addPageBtn) {
            addPageBtn.disabled = true;
          }
          updateTooltipState(true);
          document.dispatchEvent(new CustomEvent('noobrocketCDNBannerAutoExpanded'));
        }
        refreshUIElements(response);
      }).catch(() => {
        input.disabled = false;
        button.disabled = false;
        if (builtIn) {
          builtIn.classList.remove('noobrocket-cdn-built-in--disabled');
        }
      });
    }
    button.addEventListener('click', submitPage);
    input.addEventListener('keydown', e => {
      if ('Enter' === e.key) {
        e.preventDefault();
        submitPage();
      }
    });
  }
  function refreshUIElements(response) {
    // Set subscription loading state when first page is added.
    if (response.is_subscription_creation_loading) {
      setSubscriptionLoadingState();
    }

    // Only the mode-toggle response carries this - keeps the add-page controls
    // (homepage/add buttons, URL input) in sync with the server's authoritative
    // disabled state (limit reached / subscription loading / forced off) right
    // after enabling or disabling HostedCDN, instead of only on a page reload.
    if ('free_add_page_disabled' in response) {
      const builtIn = document.querySelector('.noobrocket-cdn-built-in');
      if (builtIn) {
        builtIn.classList.toggle('noobrocket-cdn-built-in--disabled', response.free_add_page_disabled);
      }
    }

    // Update status indicator component.
    updateStatusIndicatorComponent(response.status_indicator_html);
  }

  /**
   * Initializes delete buttons for CDN page rows.
   *
   * Uses event delegation on the built-in CDN container to handle
   * clicks on dynamically added delete buttons.
   */
  function initDeletePage() {
    const container = document.querySelector('#nbr_add_page_component');
    if (!container) {
      return;
    }
    container.parentElement.addEventListener('click', e => {
      const button = e.target.closest('.noobrocket-table-list__delete');
      if (!button) {
        return;
      }
      const id = button.dataset.id;
      if (!id) {
        return;
      }
      button.disabled = true;
      window.wp.apiFetch({
        path: `/noobrocket/v1/noobrocketcdn/pages/${id}`,
        method: 'DELETE'
      }).then(response => {
        updateMonitoringCtaState(response.count, response.limit);

        // Update status indicator before the zero-count block so the separator
        // logic below can find the freshly-inserted element when count hits 0.
        updateStatusIndicatorComponent(response.status_indicator_html);
        if (response.items_html) {
          const existing = container.parentElement.querySelector('.noobrocket-cdn-built-in .noobrocket-table-list');
          if (existing) {
            existing.remove();
          }
          const addPageSection = container.parentElement.querySelector('.noobrocket-cdn-add-page');
          if (addPageSection) {
            addPageSection.insertAdjacentHTML('beforebegin', response.items_html);
          }
        }

        // Show re-add HOMEPAGE button and no-page notice when all pages are deleted.
        if (0 === response.count) {
          // Remove table list component.
          document.querySelector('.noobrocket-cdn-built-in .noobrocket-table-list').remove();
          const homepageBtn = container.querySelector('.noobrocket-cdn-add-page__homepage');
          if (homepageBtn) {
            homepageBtn.classList.remove('noobrocket-isHidden');
            homepageBtn.disabled = false;
          }

          // Restore separator between the status indicator and the add-page section.
          const indicator = document.querySelector('.noobrocket-cdn-built-in #nbr_cdn_status_indicator');
          if (indicator) {
            indicator.insertAdjacentHTML('afterend', '<div class="noobrocket-cdn-built-in__separator"></div>');
          }
        }
        if (response.limit > response.count) {
          // Re-enable input and button when page limit is not reached.
          document.querySelector('.noobrocket-cdn-built-in').classList.remove('noobrocket-cdn-built-in--disabled');
          const addPageWrapper = document.querySelector('.noobrocket-cdn-add-page__button-wrapper');
          if (addPageWrapper) {
            addPageWrapper.classList.remove('noobrocket-btn-with-tool-tip');
          }
          const addPageBtn = document.querySelector('.noobrocket-cdn-add-page__button');
          if (addPageBtn) {
            addPageBtn.disabled = false;
          }
          updateTooltipState(false);

          // Track auto-collapse when deletion drops count just below the limit.
          if (response.count === response.limit - 1) {
            document.dispatchEvent(new CustomEvent('noobrocketCDNBannerAutoCollapsed'));
          }
        }
      }).catch(() => {
        button.disabled = false;
      });
    });
  }
})(document);

},{}],6:[function(require,module,exports){
"use strict";

function getTimeRemaining(endtime) {
  const start = Date.now();
  const total = endtime * 1000 - start;
  const seconds = Math.floor(total / 1000 % 60);
  const minutes = Math.floor(total / 1000 / 60 % 60);
  const hours = Math.floor(total / (1000 * 60 * 60) % 24);
  const days = Math.floor(total / (1000 * 60 * 60 * 24));
  return {
    total,
    days,
    hours,
    minutes,
    seconds
  };
}
function initializeClock(id, endtime) {
  const clock = document.getElementById(id);
  if (clock === null) {
    return;
  }
  const daysSpan = clock.querySelector('.noobrocket-countdown-days');
  const hoursSpan = clock.querySelector('.noobrocket-countdown-hours');
  const minutesSpan = clock.querySelector('.noobrocket-countdown-minutes');
  const secondsSpan = clock.querySelector('.noobrocket-countdown-seconds');
  function updateClock() {
    const t = getTimeRemaining(endtime);
    if (t.total < 0) {
      clearInterval(timeinterval);
      return;
    }
    daysSpan.innerHTML = t.days;
    hoursSpan.innerHTML = ('0' + t.hours).slice(-2);
    minutesSpan.innerHTML = ('0' + t.minutes).slice(-2);
    secondsSpan.innerHTML = ('0' + t.seconds).slice(-2);
  }
  updateClock();
  const timeinterval = setInterval(updateClock, 1000);
}
function rucssTimer(id, endtime) {
  const timer = document.getElementById(id);
  const notice = document.getElementById('noobrocket-notice-saas-processing');
  const success = document.getElementById('noobrocket-notice-saas-success');
  if (timer === null) {
    return;
  }
  function updateTimer() {
    const start = Date.now();
    const remaining = Math.floor((endtime * 1000 - start) / 1000);
    if (remaining <= 0) {
      clearInterval(timerInterval);
      if (notice !== null) {
        notice.classList.add('hidden');
      }
      if (success !== null) {
        success.classList.remove('hidden');
      }
      if (noobrocket_ajax_data.cron_disabled) {
        return;
      }
      const data = new FormData();
      data.append('action', 'noobrocket_spawn_cron');
      data.append('nonce', noobrocket_ajax_data.nonce);
      fetch(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
      });
      return;
    }
    timer.innerHTML = remaining;
  }
  updateTimer();
  const timerInterval = setInterval(updateTimer, 1000);
}
if (!Date.now) {
  Date.now = function now() {
    return new Date().getTime();
  };
}
if (typeof noobrocket_ajax_data.promo_end !== 'undefined') {
  initializeClock('noobrocket-promo-countdown', noobrocket_ajax_data.promo_end);
}
if (typeof noobrocket_ajax_data.license_expiration !== 'undefined') {
  initializeClock('noobrocket-renew-countdown', noobrocket_ajax_data.license_expiration);
}
if (typeof noobrocket_ajax_data.notice_end_time !== 'undefined') {
  rucssTimer('noobrocket-rucss-timer', noobrocket_ajax_data.notice_end_time);
}

},{}],7:[function(require,module,exports){
"use strict";

require("../custom/custom-select.js");
var $ = jQuery;
$(document).ready(function () {
  /***
  * Check parent / show children
  ***/

  function noobrocketShowChildren(aElem) {
    var parentId, $children;
    aElem = $(aElem);
    parentId = aElem.attr('id');
    $children = $('[data-parent="' + parentId + '"]');

    // Test check for switch
    if (aElem.is(':checked')) {
      $children.addClass('noobrocket-isOpen');
      $children.each(function () {
        if ($(this).find('input[type=checkbox]').is(':checked')) {
          var id = $(this).find('input[type=checkbox]').attr('id');
          $('[data-parent="' + id + '"]').addClass('noobrocket-isOpen');
        }
      });
    } else {
      $children.removeClass('noobrocket-isOpen');
      $children.each(function () {
        var id = $(this).find('input[type=checkbox]').attr('id');
        $('[data-parent="' + id + '"]').removeClass('noobrocket-isOpen');
      });
    }
  }

  /**
   * Tell if the given child field has an active parent field.
   *
   * @param  object $field A jQuery object of a ".noobrocket-field" field.
   * @return bool|null
   */
  function noobrocketIsParentActive($field) {
    var $parent;
    if (!$field.length) {
      // ¯\_(ツ)_/¯
      return null;
    }
    $parent = $field.data('parent');
    if (typeof $parent !== 'string') {
      // This field has no parent field: then we can display it.
      return true;
    }
    $parent = $parent.replace(/^\s+|\s+$/g, '');
    if ('' === $parent) {
      // This field has no parent field: then we can display it.
      return true;
    }
    $parent = $('#' + $parent);
    if (!$parent.length) {
      // This field's parent is missing: let's consider it's not active then.
      return false;
    }
    if (!$parent.is(':checked') && $parent.is('input')) {
      // This field's parent is checkbox and not checked: don't display the field then.
      return false;
    }
    if (!$parent.hasClass('radio-active') && $parent.is('button')) {
      // This field's parent button and is not active: don't display the field then.
      return false;
    }
    // Go recursive to the last parent.
    return noobrocketIsParentActive($parent.closest('.noobrocket-field'));
  }

  /**
   * Masks sensitive information in an input field by replacing all but the last 4 characters with asterisks.
   *
   * @param {string} id_selector - The ID of the input field to be masked.
   * @returns {void} - Modifies the input field value in-place.
   *
   * @example
   * // HTML: <input type="text" id="creditCardInput" value="1234567890123456">
   * maskField('creditCardInput');
   * // Result: Updates the input field value to '************3456'.
   */
  function maskField(proxy_selector, concrete_selector) {
    if (!proxy_selector.length || !concrete_selector.length) return;
    // Password fields keep the real value concealed until the explicit Show action.
    if (proxy_selector.attr('type') === 'password') {
      proxy_selector.val(concrete_selector.val());
      proxy_selector.on('input', function () {
        concrete_selector.val(proxy_selector.val());
      });
      return;
    }
    var concrete = {
      'val': concrete_selector.val(),
      'length': concrete_selector.val().length
    };
    if (concrete.length > 4) {
      var hiddenPart = '\u2022'.repeat(Math.max(0, concrete.length - 4));
      var visiblePart = concrete.val.slice(-4);

      // Combine the hidden and visible parts
      var maskedValue = hiddenPart + visiblePart;
      proxy_selector.val(maskedValue);
    }
    // Ensure events are not added more than once
    if (!proxy_selector.data('eventsAttached')) {
      proxy_selector.on('input', handleInput);
      proxy_selector.on('focus', handleFocus);
      proxy_selector.data('eventsAttached', true);
    }

    /**
     * Handle the input event
     */
    function handleInput() {
      var proxyValue = proxy_selector.val();
      if (proxyValue.indexOf('\u2022') === -1) {
        concrete_selector.val(proxyValue);
      }
    }

    /**
     * Handle the focus event
     */
    function handleFocus() {
      var concrete_value = concrete_selector.val();
      proxy_selector.val(concrete_value);
    }
  }

  // Update the concrete field when the proxy is updated.

  maskField($('#cloudflare_api_key_mask'), $('#cloudflare_api_key'));
  maskField($('#cloudflare_zone_id_mask'), $('#cloudflare_zone_id'));

  // Display/Hide children fields on checkbox change.
  $('.noobrocket-isParent input[type=checkbox]').on('change', function () {
    noobrocketShowChildren($(this));
  });

  // On page load, display the active fields.
  $('.noobrocket-field--children').each(function () {
    var $field = $(this);
    if (noobrocketIsParentActive($field)) {
      $field.addClass('noobrocket-isOpen');
    }
  });

  /***
  * Warning fields
  ***/

  $('.noobrocket-field--parent').each(function () {
    var $parent = $(this);
    var $checkbox = $parent.find('input[type=checkbox]').first();
    var $warning = $parent.next('.noobrocket-fieldWarning');
    var $nextFields = $parent.parent().next('.noobrocket-warningContainer').find('.noobrocket-field');
    var accepting = false;
    noobrocketShowChildren($checkbox);

    $checkbox.on('change', function () {
      if (this.checked && !accepting && $warning.length) {
        this.checked = false;
        $warning.addClass('noobrocket-isOpen');
        noobrocketShowChildren($checkbox);
        return;
      }
      $warning.removeClass('noobrocket-isOpen');
      noobrocketShowChildren($checkbox);
      $nextFields.toggleClass('noobrocket-isDisabled', !this.checked);
      $nextFields.find('input').prop('disabled', !this.checked);
      if (!this.checked) {
        $nextFields.find('input[type=checkbox]:checked').each(function () {
          this.checked = false;
          this.dispatchEvent(new Event('change', { bubbles: true }));
        });
      }
    });
    $warning.find('.noobrocket-button').on('click', function (event) {
      event.preventDefault();
      if ($checkbox.prop('disabled')) return;
      accepting = true;
      $checkbox.prop('checked', true);
      $checkbox[0].dispatchEvent(new Event('change', { bubbles: true }));
      accepting = false;
      $checkbox[0].focus();
    });
  });

  // Availability follows the current form, including unsaved prerequisite changes.
  function syncCombineJavaScript() {
    var minify = document.getElementById('minify_js');
    var combine = document.getElementById('minify_concatenate_js');
    var delay = document.getElementById('delay_js');
    if (!minify || !combine) return;
    combine.disabled = !minify.checked || minify.disabled || !!(delay && delay.checked);
    $(combine).closest('.noobrocket-field').toggleClass('noobrocket-isDisabled', combine.disabled);
    if (combine.disabled) {
      $(combine).closest('.noobrocket-warningContainer').find('.noobrocket-fieldWarning').removeClass('noobrocket-isOpen');
      if (combine.checked) {
        combine.checked = false;
        combine.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }
  }
  $('#minify_js, #delay_js, #minify_concatenate_js').on('change', syncCombineJavaScript);
  syncCombineJavaScript();

  document.addEventListener('noobrocketSettingsReset', function () {
    $('.noobrocket-isParent input[type=checkbox]').each(function () {
      noobrocketShowChildren($(this));
    });
    $('.noobrocket-fieldWarning').removeClass('noobrocket-isOpen');
    $('.noobrocket-field--parent').each(function () {
      var checked = $(this).find('input[type=checkbox]').first().prop('checked');
      $(this).parent().next('.noobrocket-warningContainer').find('.noobrocket-field').toggleClass('noobrocket-isDisabled', !checked);
    });
    syncCombineJavaScript();
    document.querySelectorAll('#noobrocket-cnames-list [data-noobrocket-cname]').forEach(function (input) {
      validateCname(input, false);
    });
  });

  /**
   * CNAMES add/remove lines
   */
  var cnameSequence = 0;
  function validateCname(input, showError) {
    var value = input.value.trim();
    var valid = !value;
    if (value) {
      var parts = value.match(/^(?:https?:\/\/|\/\/)?([^\/?#:\s]+)(?::\d+)?(?:[\/?#]\S*)?$/i);
      var host = parts ? parts[1] : '';
      valid = host.length > 0 && host.length <= 253 && host.split('.').every(function (label, index, labels) {
        if (!label && index === labels.length - 1) return true;
        return /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i.test(label);
      });
    }
    var error = input.parentElement.querySelector('[data-noobrocket-cname-error]');
    input.setCustomValidity(valid ? '' : error.textContent.trim());
    if (showError || valid) {
      error.hidden = valid;
      if (valid) input.removeAttribute('aria-invalid');
      else input.setAttribute('aria-invalid', 'true');
    }
    return valid;
  }
  document.querySelectorAll('#noobrocket-cnames-list [data-noobrocket-cname]').forEach(function (input) {
    validateCname(input, false);
  });
  $(document).on('input', '[data-noobrocket-cname]', function () {
    validateCname(this, this.hasAttribute('aria-invalid'));
  }).on('focusout', '[data-noobrocket-cname]', function () {
    validateCname(this, true);
  });
  var cnameFocusPending = false;
  var cnameForm = document.getElementById('noobrocket_options');
  if (cnameForm) cnameForm.addEventListener('invalid', function (event) {
    if (!event.target.matches('[data-noobrocket-cname]')) return;
    event.preventDefault();
    validateCname(event.target, true);
    if (cnameFocusPending) return;
    cnameFocusPending = true;
    var input = event.target;
    window.location.hash = 'page_cdn';
    window.requestAnimationFrame(function () {
      input.closest('.noobrocket-multiple').scrollIntoView({ block: 'center' });
      input.focus({ preventScroll: true });
      cnameFocusPending = false;
    });
  }, true);
  $(document).on('click', '.noobrocket-multiple-close', function (e) {
    e.preventDefault();
    var $row = $(this).parent(),
      $list = $row.parent(),
      $next = $row.next('.noobrocket-multiple');
    if (!$next.length) {
      $next = $row.prev('.noobrocket-multiple');
    }
    $row.remove();
    $list[0].dispatchEvent(new Event('change', { bubbles: true }));
    var focusTarget = $next.find('input').first()[0] || $('.noobrocket-button--addMulti')[0];
    if (focusTarget) {
      focusTarget.focus();
    }
  });
  $('.noobrocket-button--addMulti').on('click', function (e) {
    e.preventDefault();
    var template = document.getElementById('noobrocket-cname-model');
    var row = template.content.firstElementChild.cloneNode(true);
    var id = 'noobrocket-cname-new-' + (++cnameSequence);
    var input = row.querySelector('input');
    var scope = row.querySelector('select');
    var labels = row.querySelectorAll('label');
    var error = row.querySelector('[data-noobrocket-cname-error]');
    input.id = id;
    scope.id = id + '-scope';
    labels[0].htmlFor = input.id;
    labels[1].htmlFor = scope.id;
    error.id = id + '-error';
    input.setAttribute('aria-describedby', 'cdn_cnames-description ' + error.id);
    document.getElementById('noobrocket-cnames-list').appendChild(row);
    document.getElementById('noobrocket-cnames-list').dispatchEvent(new Event('change', { bubbles: true }));
    input.focus();
  });

  /***
   * NoobRocket Radio button
   ***/
  var disable_radio_warning = false;
  $(document).on('click', '.noobrocket-radio-buttons-container button', function (e) {
    e.preventDefault();
    if ($(this).hasClass('radio-active')) {
      return false;
    }
    var $parent = $(this).parents('.noobrocket-radio-buttons');
    $parent.find('.noobrocket-radio-buttons-container button').removeClass('radio-active');
    $parent.find('.noobrocket-extra-fields-container').removeClass('noobrocket-isOpen');
    $parent.find('.noobrocket-fieldWarning').removeClass('noobrocket-isOpen');
    $(this).addClass('radio-active');
    noobrocketShowRadioWarning($(this));
  });
  function noobrocketShowRadioWarning($elm) {
    disable_radio_warning = false;
    $elm.trigger("before_show_radio_warning", [$elm]);
    if (!$elm.hasClass('has-warning') || disable_radio_warning) {
      noobrocketShowRadioButtonChildren($elm);
      $elm.trigger("radio_button_selected", [$elm]);
      return false;
    }
    var $warningField = $('[data-parent="' + $elm.attr('id') + '"].noobrocket-fieldWarning');
    $warningField.addClass('noobrocket-isOpen');
    var $warningButton = $warningField.find('.noobrocket-button');

    // Validate the warning
    $warningButton.on('click', function () {
      $warningField.removeClass('noobrocket-isOpen');
      noobrocketShowRadioButtonChildren($elm);
      $elm.trigger("radio_button_selected", [$elm]);
      return false;
    });
  }
  function noobrocketShowRadioButtonChildren($elm) {
    var $parent = $elm.parents('.noobrocket-radio-buttons');
    var $children = $('.noobrocket-extra-fields-container[data-parent="' + $elm.attr('id') + '"]');
    $children.addClass('noobrocket-isOpen');
  }

  /***
   * NoobRocket Optimize Css Delivery Field
   ***/
  var rucssActive = parseInt($('#remove_unused_css').val());
  $("#optimize_css_delivery_method .noobrocket-radio-buttons-container button").on("radio_button_selected", function (event, $elm) {
    toggleActiveOptimizeCssDeliveryMethod($elm);
  });
  $("#optimize_css_delivery").on("change", function () {
    if ($(this).is(":not(:checked)")) {
      disableOptimizeCssDelivery();
    } else {
      var default_radio_button_id = '#' + $('#optimize_css_delivery_method').data('default');
      $(default_radio_button_id).trigger('click');
    }
  });
  function toggleActiveOptimizeCssDeliveryMethod($elm) {
    var optimize_method = $elm.data('value');
    if ('remove_unused_css' === optimize_method) {
      $('#remove_unused_css').val(1);
      $('#async_css').val(0);
    } else {
      $('#remove_unused_css').val(0);
      $('#async_css').val(1);
    }
  }
  function disableOptimizeCssDelivery() {
    $('#remove_unused_css').val(0);
    $('#async_css').val(0);
  }
  $("#optimize_css_delivery_method .noobrocket-radio-buttons-container button").on("before_show_radio_warning", function (event, $elm) {
    disable_radio_warning = 'remove_unused_css' === $elm.data('value') && 1 === rucssActive;
  });
  $('.noobrocket-field--categorizedmultiselect .noobrocket-list').each(function () {
    var $list = $(this);
    var $header = $list.find('.noobrocket-list-header');
    var $body = $list.find('.noobrocket-list-body');
    var $items = $body.find('input[type="checkbox"][name]');
    var main = $list.find('.noobrocket-main-checkbox')[0];
    function updateSelection() {
      var count = $items.filter(':checked').length;
      if (main) {
        main.checked = $items.length > 0 && count === $items.length;
        main.indeterminate = count > 0 && count < $items.length;
      }
      $list.find('.noobrocket-badge-counter').toggle(count > 0).find('[data-noobrocket-selection-count]').text(count);
    }
    $header.on('click', function () {
      var open = !$list.hasClass('open');
      $list.toggleClass('open', open);
      this.setAttribute('aria-expanded', String(open));
      $body.prop('hidden', !open);
    });
    $body.on('change', 'input[type="checkbox"]', function () {
      if (this === main) {
        var checked = main.checked;
        $items.each(function () {
          if (this.checked !== checked) {
            this.checked = checked;
            this.dispatchEvent(new Event('change', { bubbles: true }));
          }
        });
      }
      updateSelection();
    });
    updateSelection();
    document.addEventListener('noobrocketSettingsReset', updateSelection);
  });

  /**
   * Delay JS Execution Safe Mode Field
   */
  var $dje_safe_mode_checkbox = $('#delay_js_execution_safe_mode');
  $('#delay_js').on('change', function () {
    if ($(this).is(':not(:checked)') && $dje_safe_mode_checkbox.is(':checked')) {
      $dje_safe_mode_checkbox.trigger('click');
    }
  });
  let stacked_select = document.getElementById('noobrocket_stacked_select');
  if (stacked_select) {
    stacked_select.addEventListener('custom-select-change', function (event) {
      let selected_option = $(event.detail.selectedOption);
      let name = selected_option.data('name');
      let saving = selected_option.data('saving');
      let regular_price = selected_option.data('regular-price');
      let price = selected_option.data('price');
      let url = selected_option.data('url');
      let parent_item = $(this).parents('.noobrocket-upgrade-item');
      if (saving) {
        parent_item.find('.noobrocket-upgrade-saving span').html(saving);
      }
      if (name) {
        parent_item.find('.noobrocket-upgrade-title').html(name);
      }
      if (regular_price) {
        parent_item.find('.noobrocket-upgrade-price-regular span').html(regular_price);
      }
      if (price) {
        parent_item.find('.noobrocket-upgrade-price-value').html(price);
      }
      if (url) {
        parent_item.find('.noobrocket-upgrade-link').attr('href', url);
      }
    });
  }
  $(document).on('click', '.noobrocket-confirm-delete', function (e) {
    return confirm($(this).data('nbr_confirm_msg'));
  });
});

},{"../custom/custom-select.js":1}],8:[function(require,module,exports){
"use strict";

var $ = jQuery;
$(document).ready(function () {
  /***
  * Dashboard notice
  ***/

  var $notice = $('.noobrocket-notice');
  var $noticeClose = $('#noobrocket-congratulations-notice');
  $noticeClose.on('click', function () {
    noobrocketCloseDashboardNotice();
    return false;
  });
  function noobrocketCloseDashboardNotice() {
    var vTL = new TimelineLite().to($notice, 1, {
      autoAlpha: 0,
      x: 40,
      ease: Power4.easeOut
    }).to($notice, 0.6, {
      height: 0,
      marginTop: 0,
      ease: Power4.easeOut
    }, '=-.4').set($notice, {
      'display': 'none'
    });
  }

  /**
   * Rocket Analytics notice info collect
   */
  $('.noobrocket-analytics-data-container').hide();
  $('.noobrocket-preview-analytics-data').on('click', function (e) {
    e.preventDefault();
    $(this).parent().next('.noobrocket-analytics-data-container').toggle();
  });

  // Provider destinations stay discoverable; their pages explain inactive integrations.
  $('.noobrocket-toggle-button').each(function () {
    $(this).css('display', 'inline-flex');
  });

  /***
  * Help Button Tracking
  ***/

  // Track clicks on various help elements with data attributes
  $(document).on('click', '[data-nbr_track_help]', function (e) {
    if (typeof window.noobrocketTrackHelpButton === 'function') {
      var $el = $(this);
      var button = $el.data('nbr_track_help');
      var context = $el.data('nbr_track_context') || '';
      window.noobrocketTrackHelpButton(button, context);
    }
  });

  // Track specific help resource clicks with explicit selectors
  $(document).on('click', '.wistia_embed', function () {
    if (typeof window.noobrocketTrackHelpButton === 'function') {
      var title = $(this).text() || 'Getting Started Video';
      window.noobrocketTrackHelpButton(title, 'Getting Started');
    }
  });

  // Track FAQ links
  $(document).on('click', 'a[data-beacon-article]', function () {
    if (typeof window.noobrocketTrackHelpButton === 'function') {
      var href = $(this).attr('href');
      var text = $(this).text();

      // Check if it's in FAQ section or sidebar documentation
      if ($(this).closest('.noobrocket-fieldsContainer-fieldset').prev('.noobrocket-optionHeader').find('.noobrocket-title2').text().includes('Frequently Asked Questions')) {
        window.noobrocketTrackHelpButton('FAQ - ' + text, 'Dashboard');
      } else if ($(this).closest('.noobrocket-documentation').length > 0) {
        window.noobrocketTrackHelpButton('Documentation', 'Sidebar');
      } else {
        window.noobrocketTrackHelpButton('Documentation Link', 'General');
      }
    }
  });

  // Track "How to measure loading time" link
  $(document).on('click', 'a[href*="how-to-test-wordpress-site-performance"]', function () {
    if (typeof window.noobrocketTrackHelpButton === 'function') {
      window.noobrocketTrackHelpButton('Loading Time Guide', 'Sidebar');
    }
  });

  // Track "Need help?" links (existing help buttons)
  $(document).on('click', '.noobrocket-infoAction--help:not([data-beacon-id])', function () {
    if (typeof window.noobrocketTrackHelpButton === 'function') {
      window.noobrocketTrackHelpButton('Need Help', 'General');
    }
  });

  /***
  * Show popin analytics
  ***/

  var $noobrocketAnalyticsPopin = $('.noobrocket-Popin-Analytics'),
    $noobrocketPopinOverlay = $('.noobrocket-Popin-overlay'),
    $noobrocketAnalyticsClosePopin = $('.noobrocket-Popin-Analytics-close'),
    $noobrocketAnalyticsPopinButton = $('.noobrocket-Popin-Analytics .noobrocket-button'),
    $noobrocketAnalyticsOpenPopin = $('.noobrocket-js-popin');
  $noobrocketAnalyticsOpenPopin.on('click', function (e) {
    e.preventDefault();
    noobrocketOpenAnalytics();
    return false;
  });
  $noobrocketAnalyticsClosePopin.on('click', function (e) {
    e.preventDefault();
    noobrocketCloseAnalytics();
    return false;
  });
  $noobrocketAnalyticsPopinButton.on('click', function (e) {
    e.preventDefault();
    noobrocketActivateAnalytics();
    return false;
  });
  function noobrocketOpenAnalytics() {
    var vTL = new TimelineLite().set($noobrocketAnalyticsPopin, {
      'display': 'block'
    }).set($noobrocketPopinOverlay, {
      'display': 'block'
    }).fromTo($noobrocketPopinOverlay, 0.6, {
      autoAlpha: 0
    }, {
      autoAlpha: 1,
      ease: Power4.easeOut
    }).fromTo($noobrocketAnalyticsPopin, 0.6, {
      autoAlpha: 0,
      marginTop: -24
    }, {
      autoAlpha: 1,
      marginTop: 0,
      ease: Power4.easeOut
    }, '=-.5');
  }
  function noobrocketCloseAnalytics() {
    var vTL = new TimelineLite().fromTo($noobrocketAnalyticsPopin, 0.6, {
      autoAlpha: 1,
      marginTop: 0
    }, {
      autoAlpha: 0,
      marginTop: -24,
      ease: Power4.easeOut
    }).fromTo($noobrocketPopinOverlay, 0.6, {
      autoAlpha: 1
    }, {
      autoAlpha: 0,
      ease: Power4.easeOut
    }, '=-.5').set($noobrocketAnalyticsPopin, {
      'display': 'none'
    }).set($noobrocketPopinOverlay, {
      'display': 'none'
    });
  }
  function noobrocketActivateAnalytics() {
    noobrocketCloseAnalytics();
    $('#analytics_enabled').prop('checked', true);
    var analyticsEnabled = document.getElementById('analytics_enabled');
    if (analyticsEnabled) {
      var changeEvent = new Event('change', {
        bubbles: true
      });
      analyticsEnabled.dispatchEvent(changeEvent);
    }
  }

  // Display CTA within the popin `What info will we collect?`
  $('#analytics_enabled').on('change', function () {
    $('.noobrocket-noobrocket-analytics-cta').toggleClass('noobrocket-isHidden');
  });

  /***
  * Show popin upgrade
  ***/

  var $noobrocketUpgradePopin = $('.noobrocket-Popin-Upgrade'),
    $noobrocketUpgradeClosePopin = $('.noobrocket-Popin-Upgrade-close'),
    $noobrocketUpgradeOpenPopin = $('.noobrocket-popin-upgrade-toggle');
  $noobrocketUpgradeOpenPopin.on('click', function (e) {
    e.preventDefault();
    noobrocketOpenUpgradePopin();
    return false;
  });
  $noobrocketUpgradeClosePopin.on('click', function () {
    noobrocketCloseUpgradePopin();
    return false;
  });
  function noobrocketOpenUpgradePopin() {
    var vTL = new TimelineLite();
    vTL.set($noobrocketUpgradePopin, {
      'display': 'block'
    }).set($noobrocketPopinOverlay, {
      'display': 'block'
    }).fromTo($noobrocketPopinOverlay, 0.6, {
      autoAlpha: 0
    }, {
      autoAlpha: 1,
      ease: Power4.easeOut
    }).fromTo($noobrocketUpgradePopin, 0.6, {
      autoAlpha: 0,
      marginTop: -24
    }, {
      autoAlpha: 1,
      marginTop: 0,
      ease: Power4.easeOut
    }, '=-.5');
  }
  function noobrocketCloseUpgradePopin() {
    var vTL = new TimelineLite();
    vTL.fromTo($noobrocketUpgradePopin, 0.6, {
      autoAlpha: 1,
      marginTop: 0
    }, {
      autoAlpha: 0,
      marginTop: -24,
      ease: Power4.easeOut
    }).fromTo($noobrocketPopinOverlay, 0.6, {
      autoAlpha: 1
    }, {
      autoAlpha: 0,
      ease: Power4.easeOut
    }, '=-.5').set($noobrocketUpgradePopin, {
      'display': 'none'
    }).set($noobrocketPopinOverlay, {
      'display': 'none'
    });
  }

  /***
  * Detect Adblock
  ***/

  if (document.getElementById('LKgOcCRpwmAj')) {
    $('.noobrocket-adblock').css('display', 'none');
  } else {
    $('.noobrocket-adblock').css('display', 'block');
  }
  var $adblock = $('.noobrocket-adblock');
  var $adblockClose = $('.noobrocket-adblock-close');
  $adblockClose.on('click', function () {
    noobrocketCloseAdblockNotice();
    return false;
  });
  function noobrocketCloseAdblockNotice() {
    var vTL = new TimelineLite().to($adblock, 1, {
      autoAlpha: 0,
      x: 40,
      ease: Power4.easeOut
    }).to($adblock, 0.4, {
      height: 0,
      marginTop: 0,
      ease: Power4.easeOut
    }, '=-.4').set($adblock, {
      'display': 'none'
    });
  }

  // Handle expand/collapse of recommendations list.
  $(document).on('click', '#noobrocket-recommendations-load-more', function () {
    let $list = $('.noobrocket-recommendations__list');
    let $hiddenItems = $list.find('.noobrocket-recommendation-item:gt(2)');

    // Track Load More button click
    if (typeof window.noobrocketTrackHelpButton === 'function') {
      window.noobrocketTrackHelpButton('rocket insights recommendations load more', 'load_more');
    }
    if ($list.hasClass('is-expanded')) {
      $hiddenItems.slideUp(300, function () {
        $list.removeClass('is-expanded');
      });
      $(this).removeClass('is-expanded');
      return;
    }
    $list.addClass('is-expanded');
    $hiddenItems.slideDown(300);
    $(this).addClass('is-expanded');
  });

  // Track Performance monitoring Recommendation Activate button clicks
  $(document).on('click', '.noobrocket-recommendation-item__activate', function () {
    var recommendation = $(this).data('recommendation') || 'unknown';

    // If the element is visible, scroll to the element with id matching the recommendation value.
    // Delay scroll by 100ms to allow navigation to the section to complete and ensure the target element is in view.
    setTimeout(function () {
      var $target = $(`#${recommendation}`);
      if (!$target.length && !$target.is(':visible')) {
        return;
      }
      $target[0].scrollIntoView({
        behavior: 'smooth',
        block: 'center'
      });
    }, 100);

    // Track directly with Mixpanel
    if (typeof mixpanel !== 'undefined' && mixpanel.track) {
      // Check if user has opted in
      if (typeof noobrocket_mixpanel_data !== 'undefined' && noobrocket_mixpanel_data.optin_enabled && noobrocket_mixpanel_data.optin_enabled !== '0') {
        // Identify user if available
        if (noobrocket_mixpanel_data.user_id && typeof mixpanel.identify === 'function') {
          mixpanel.identify(noobrocket_mixpanel_data.user_id);
        }

        // Track the Button Clicked event
        mixpanel.track('Button Clicked', {
          'button': 'rocket insights recommendation',
          'recommendation': recommendation,
          'plugin': noobrocket_mixpanel_data.plugin,
          'brand': noobrocket_mixpanel_data.brand,
          'application': noobrocket_mixpanel_data.app,
          'context': noobrocket_mixpanel_data.context,
          'path': noobrocket_mixpanel_data.path,
          'interaction_channel': 'UI'
        });
      }
    }
  });

  // Track CTA clicks for Performance monitoring in the settings saved notice.
  $(document).on('click', '#noobrocket_ri_new_test_save_settings_link', function () {
    // Track directly with Mixpanel
    if (typeof mixpanel === 'undefined' || !mixpanel.track) {
      return;
    }

    // Check if user has opted in
    if (typeof noobrocket_mixpanel_data === 'undefined' || !noobrocket_mixpanel_data.optin_enabled || noobrocket_mixpanel_data.optin_enabled === '0') {
      return;
    }

    // Identify user if available
    if (noobrocket_mixpanel_data.user_id && typeof mixpanel.identify === 'function') {
      mixpanel.identify(noobrocket_mixpanel_data.user_id);
    }

    // Track the Button Clicked event
    mixpanel.track('Performance monitoring CTA click from settings notice', {
      'button': 'CTA on save settings notice',
      'source': 'Settings Saved Notice',
      'plugin': noobrocket_mixpanel_data.plugin,
      'brand': noobrocket_mixpanel_data.brand,
      'application': noobrocket_mixpanel_data.app,
      'context': noobrocket_mixpanel_data.context,
      'path': noobrocket_mixpanel_data.path,
      'interaction_channel': 'UI'
    });
  });
});

},{}],9:[function(require,module,exports){
"use strict";

function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == typeof i ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != typeof t || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != typeof i) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
class NoobRocketMixpanel {
  constructor(config) {
    _defineProperty(this, "trackedTabs", ['dashboard', 'noobrocket_insights', 'page_cdn', 'file_optimization', 'media', 'preload', 'advanced_cache', 'database', 'heartbeat', 'addons', 'imagify', 'tutorials', 'plugins', 'tools']);
    this.config = config;
  }

  /**
   * Initializes the handler.
   */
  init() {
    if (typeof mixpanel === 'undefined' || !mixpanel.track) {
      return;
    }
    if (!this.config.optin_enabled || this.config.optin_enabled === '0') {
      return;
    }
    mixpanel.identify(this.config.user_id);
    this._initListeners(this);
  }

  /**
   * Initializes the event listeners.
   *
   * @param self instance of this object, used for binding "this" to the listeners.
   */
  _initListeners(self) {
    window.addEventListener('hashchange', self._onHashChange.bind(self));
    window.addEventListener('load', self._onPageLoad.bind(self));
  }

  /**
   * Event listener when the hash changed in a page.
   *
   * @param Event event Event instance.
   */
  _onHashChange(event) {
    const oldHash = this._cleanHash(new URL(event.oldURL).hash);
    const newHash = this._cleanHash(new URL(event.newURL).hash);
    if (!this._canTrackTab(newHash)) {
      return;
    }
    this._sendPageViewedEvent(this._getSource(oldHash), newHash);
  }
  _onPageLoad() {
    const newHash = this._cleanHash(window.location.hash);
    if (!this._canTrackTab(newHash)) {
      return;
    }
    this._sendPageViewedEvent(this._getSource(), newHash);
  }
  _cleanHash(tabHash) {
    if (!tabHash || !tabHash.startsWith('#')) {
      return tabHash;
    }
    return tabHash.substring(1);
  }
  _canTrackTab(tabHash) {
    return this.trackedTabs.includes(tabHash);
  }
  _getSource(oldHash = '') {
    if (oldHash) {
      return `settings_${oldHash}`;
    }
    let source = this._getSourceFromQueryStringAndRemoveIt();
    if (source) {
      return source;
    }
    return this._getSourceFromReferrer();
  }
  _getSourceFromQueryStringAndRemoveIt() {
    const url = new URL(window.location.href);
    const urlParams = url.searchParams;

    // 1. Check for explicit source param
    if (!urlParams.has('noobrocket_source')) {
      return '';
    }

    // 2. Get the value
    const sourceValue = urlParams.get('noobrocket_source');

    // 3. Delete the parameter from the URLSearchParams object
    urlParams.delete('noobrocket_source');

    // 4. Update the browser's URL using the History API
    // This removes the parameter from the URL bar without reloading the page.
    window.history.replaceState(null, '', url.search ? url.href : url.pathname);

    // 5. Return the retrieved value
    return sourceValue;
  }
  _getSourceFromReferrer() {
    const referrer = document.referrer;
    if (!referrer) {
      return 'noreferrer';
    }
    if (!referrer.includes(window.location.hostname)) {
      return 'external';
    }
    return 'internal';
  }
  _sendPageViewedEvent(source, newHash) {
    mixpanel.track('Page Viewed', {
      path: `/wp-admin/options-general.php?page=noobrocket#${newHash}`,
      page_name: newHash.replace('_', ' '),
      source: source,
      plugin: noobrocket_mixpanel_data.plugin,
      brand: noobrocket_mixpanel_data.brand,
      application: noobrocket_mixpanel_data.app,
      context: noobrocket_mixpanel_data.context,
      interaction_channel: 'UI'
    });
  }

  /**
   * Named static constructor to encapsulate how to create the object.
   */
  static run() {
    // Bail out if the configuration not passed from the server.
    if (typeof noobrocket_mixpanel_data === 'undefined') {
      return;
    }
    const instance = new NoobRocketMixpanel(noobrocket_mixpanel_data);
    instance.init();
  }
}
NoobRocketMixpanel.run();

},{}],10:[function(require,module,exports){
"use strict";

document.addEventListener('DOMContentLoaded', function () {
  var $pageManager = document.querySelector(".noobrocket-Content");
  if ($pageManager) {
    new PageManager($pageManager);
  }
});

/*-----------------------------------------------*\
		CLASS PAGEMANAGER
\*-----------------------------------------------*/
/**
 * Manages the display of pages / section for NoobRocket plugin
 *
 * Public method :
     detectID - Detect ID with hash
     getBodyTop - Get body top position
	 change - Displays the corresponding page
 *
 */

function PageManager(aElem) {
  var refThis = this;
  this.$body = document.querySelector('.noobrocket-body');
  this.$menuItems = document.querySelectorAll('.noobrocket-menuItem');
  this.$submitButton = document.querySelector('.noobrocket-Content #noobrocket-options-submit');
  this.$pages = document.querySelectorAll('.noobrocket-Page');
  this.$content = document.querySelector('.noobrocket-Content');
  this.$links = document.querySelectorAll('.noobrocket-body a');
  this.$menuItem = null;
  this.$page = null;
  this.pageId = null;
  this.bodyTop = 0;
  this.restoringHistory = false;
  this.historyScroll = null;
  this.changingPage = false;
  this.buttonText = this.$submitButton.value;
  refThis.getBodyTop();

  // Each history entry retains its own reading position, including repeated visits.
  window.history.scrollRestoration = 'manual';
  window.addEventListener('popstate', function (event) {
    refThis.restoringHistory = true;
    refThis.historyScroll = event.state && event.state.noobrocketScroll;
  });
  var scrollFrame = null;
  window.addEventListener('scroll', function () {
    if (scrollFrame !== null) return;
    scrollFrame = window.requestAnimationFrame(function () {
      scrollFrame = null;
      if (refThis.changingPage || refThis.pageId !== window.location.hash.slice(1).split('=')[0]) return;
      window.history.replaceState(Object.assign({}, window.history.state, { noobrocketScroll: window.scrollY }), '', window.location.href);
    });
  }, { passive: true });

  // If url page change
  window.onhashchange = function () {
    refThis.detectID();
  };

  if (!window.location.hash) {
    var session = '';
    try {
      session = localStorage.getItem('noobrocket-hash');
    } catch (_) { /* Navigation works when storage is unavailable. */ }
    window.history.replaceState(null, '', '#' + (session || 'dashboard'));
  }
  this.detectID(window.location.hash.includes('='));
  ['do_cloudflare', 'sucury_waf_cache_sync'].forEach(function (id) {
    var checkbox = document.getElementById(id);
    if (checkbox) checkbox.addEventListener('change', function () { refThis.syncProviders(); });
  });
  this.syncProviders();
  document.addEventListener('noobrocketSettingsReset', function () { refThis.syncProviders(); });

  var skipLink = document.querySelector('.noobrocket-skip-link');
  if (skipLink) {
    skipLink.addEventListener('click', function (event) {
      event.preventDefault();
      refThis.focusHeading();
    });
  }

  // Click link same hash
  for (var i = 0; i < this.$links.length; i++) {
    this.$links[i].onclick = function () {
      refThis.getBodyTop();
      var hrefSplit = this.href.split('#')[1];
      if (hrefSplit == refThis.pageId && hrefSplit != undefined) {
        refThis.detectID();
        return false;
      }
    };
  }

  // Click links not NoobRocket to reset hash
  var $otherlinks = document.querySelectorAll('#adminmenumain a, #wpadminbar a');
  for (var i = 0; i < $otherlinks.length; i++) {
    $otherlinks[i].onclick = function () {
      try {
        localStorage.setItem('noobrocket-hash', '');
      } catch (_) { /* No saved navigation state to clear. */ }
    };
  }
  document.addEventListener('noobrocket-cdn-state-change', function () {
    refThis.updateSubmitDisabledState();
  });
  this.$content.addEventListener('invalid', function (event) {
    for (var ancestor = event.target.parentElement; ancestor; ancestor = ancestor.parentElement) {
      if (ancestor.tagName === 'DETAILS') {
        ancestor.open = true;
      }
    }
    var page = event.target.closest('.noobrocket-Page');
    if (page && page !== refThis.$page) {
      window.history.pushState(null, '', '#' + page.id);
      refThis.detectID(false);
    }
  }, true);
}

/*
* Page detect ID
*/
PageManager.prototype.detectID = function (focusHeading = true) {
  this.pageId = window.location.hash.slice(1);
  this.pageId = this.pageId.includes('=') ? this.pageId.split('=')[0] : this.pageId;
  this.$page = document.getElementById(this.pageId);
  this.$menuItem = document.getElementById('noobrocket-nav-' + this.pageId);
  if (!this.$page || !this.$page.classList.contains('noobrocket-Page') || !this.$menuItem) {
    this.pageId = 'dashboard';
    this.$page = document.getElementById(this.pageId);
    this.$menuItem = document.getElementById('noobrocket-nav-' + this.pageId);
    window.history.replaceState(null, '', '#dashboard');
  }
  try {
    localStorage.setItem('noobrocket-hash', this.pageId);
  } catch (_) { /* Keep the current page without a saved preference. */ }
  this.change(focusHeading);
};

PageManager.prototype.syncProviders = function () {
  var providers = { cloudflare: 'do_cloudflare', sucuri: 'sucury_waf_cache_sync' };
  Object.keys(providers).forEach(function (id) {
    var page = document.getElementById(id);
    var checkbox = document.getElementById(providers[id]);
    if (!page) return;
    var available = !!(checkbox && checkbox.checked);
    var prerequisite = page.querySelector('[data-noobrocket-provider-prerequisite]');
    var configuration = page.querySelector('[data-noobrocket-provider-settings]');
    if (prerequisite) prerequisite.hidden = available;
    if (configuration) configuration.hidden = !available;
    // An inactive integration must not block saving with an unreachable email field.
    var email = configuration && configuration.querySelector('#cloudflare_email');
    if (email) email.type = available ? 'email' : 'text';
  });
};

PageManager.prototype.focusHeading = function () {
  var fieldId = window.location.hash.split('=')[1];
  var field = fieldId && document.getElementById(fieldId);
  if (field && this.$page.contains(field)) {
    var target = field;
    var providerSettings = field.closest('[data-noobrocket-provider-settings]');
    if (providerSettings && providerSettings.hidden) {
      target = this.$page.querySelector('[data-noobrocket-provider-prerequisite] a') || this.$page.querySelector('h1');
    }
    // Follow inactive prerequisites without changing their values.
    for (var ancestor = field; ancestor && this.$page.contains(ancestor); ancestor = ancestor.parentElement) {
      var parentId = ancestor.getAttribute('data-parent');
      var parent = parentId && document.getElementById(parentId);
      if (parent && ((parent.matches('input[type="checkbox"]') && !parent.checked) || (parent.matches('button') && !parent.classList.contains('radio-active')))) {
        target = parent;
        ancestor = parent;
      }
    }
    if (target.id === 'minify_concatenate_js' && target.disabled) {
      var minify = document.getElementById('minify_js');
      var delay = document.getElementById('delay_js');
      target = minify && !minify.checked ? minify : delay || minify || target;
    }
    for (var ancestor = target.parentElement; ancestor; ancestor = ancestor.parentElement) {
      if (ancestor.tagName === 'DETAILS') ancestor.open = true;
      if (ancestor.classList.contains('noobrocket-list')) {
        ancestor.classList.add('open');
        ancestor.querySelector('.noobrocket-list-header').setAttribute('aria-expanded', 'true');
        ancestor.querySelector('.noobrocket-list-body').hidden = false;
      }
    }
    if (!target.matches('input, select, textarea, button, summary, a[href], [tabindex]')) {
      target = target.querySelector('input, select, textarea, button, summary, a[href]') || target;
    }
    if (!target.matches('input, select, textarea, button, summary, a[href], [tabindex]')) target.tabIndex = -1;
    target.focus({ preventScroll: true });
    target.scrollIntoView({ block: 'center' });
    return;
  }
  var heading = this.$page.querySelector('h1');
  if (heading) {
    heading.focus({ preventScroll: true });
  }
};

/*
* Get body top position
*/
PageManager.prototype.getBodyTop = function () {
  var bodyPos = this.$body.getBoundingClientRect();
  var toolbar = document.getElementById('wpadminbar');
  var header = document.querySelector('.noobrocket-brand-bar');
  this.bodyTop = Math.max(0, bodyPos.top + window.pageYOffset - (toolbar ? toolbar.offsetHeight : 0) - (header ? header.offsetHeight : 0));
};

/*
* Page change
*/
PageManager.prototype.change = function (focusHeading) {
  var refThis = this;
  this.changingPage = true;

  // Hide other pages
  for (var i = 0; i < this.$pages.length; i++) {
    this.$pages[i].style.display = 'none';
  }
  for (var i = 0; i < this.$menuItems.length; i++) {
    this.$menuItems[i].classList.remove('isActive');
  }

  // Show current default page
  this.$page.style.display = 'block';
  this.$submitButton.style.display = 'block';
  this.$menuItem.classList.add('isActive');
  this.$submitButton.value = this.buttonText;
  this.$content.classList.add('isNotFull');
  const pagesWithoutSubmit = ['dashboard', 'assets', 'history', 'database', 'tools'];
  if (pagesWithoutSubmit.includes(this.pageId)) {
    this.$submitButton.style.display = 'none';
  }
  this.updateSubmitDisabledState();
  this.syncProviders();
  var targetScroll = this.restoringHistory && Number.isFinite(this.historyScroll) ? this.historyScroll : this.bodyTop;
  window.scrollTo({ top: targetScroll, behavior: 'auto' });
  this.restoringHistory = false;
  this.historyScroll = null;
  this.changingPage = false;
  window.history.replaceState(Object.assign({}, window.history.state, { noobrocketScroll: window.scrollY }), '', window.location.href);

  // Dispatch custom event after page navigation for other scripts to hook into.
  document.dispatchEvent(new CustomEvent('noobrocketJsAfterPageNavigation', {
    detail: {
      pageId: this.pageId,
      submitButton: this.$submitButton
    }
  }));
  if (focusHeading) {
    this.focusHeading();
  }
};

/*
* Update submit button disabled state
*/
PageManager.prototype.updateSubmitDisabledState = function () {
  if (!this.$submitButton || 'none' === this.$submitButton.style.display) {
    return;
  }
  var isCdnPage = 'page_cdn' === this.pageId;
  var pausedHostedCdnBlock = document.querySelector('.noobrocket-Page#page_cdn .noobrocket-notice.noobrocket-ri-notice.noobrocket-cdn-expired__notice');
  this.$submitButton.disabled = isCdnPage && !!pausedHostedCdnBlock;
};

},{}],11:[function(require,module,exports){
"use strict";

/**
 * Recommendations Widget Handler
 *
 * Listens for Global Score updates and fetches/updates recommendations dynamically.
 */
var $ = jQuery;
$(document).ready(function () {
  /**
   * Updates the recommendations widget UI based on the fetched data.
   *
   * @param {Object} data - The recommendations data from the API.
   * @param {Array} data.recommendations - Array of recommendations details.
   * @param {string} data.recommendations.html - Recommendations HTML.
   */
  function updateRecommendationsWidget(data) {
    const widget = $('.noobrocket-recommendations');
    if (!widget || !data?.recommendations?.html) {
      return;
    }

    // Update the widget content with the new recommendations HTML
    widget.replaceWith(data?.recommendations?.html);
  }

  /**
   * Fetches the current recommendations status from the REST API.
   */
  function fetchRecommendationsStatus() {
    // Use WordPress REST API client if available
    if (window.wp && window.wp.apiFetch) {
      window.wp.apiFetch({
        path: '/noobrocket/v1/recommendations'
      }).then(function (data) {
        updateRecommendationsWidget(data);
      }).catch(function (error) {
        console.error('Failed to fetch recommendations status:', error);
      });
    } else {
      // Fallback to fetch API
      fetch(window.wpApiSettings?.root + 'noobrocket/v1/recommendations', {
        headers: {
          'X-WP-Nonce': window.wpApiSettings?.nonce || ''
        }
      }).then(function (response) {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      }).then(function (data) {
        updateRecommendationsWidget(data);
      }).catch(function (error) {
        console.error('Failed to fetch recommendations status:', error);
      });
    }
  }

  /**
   * Listen for Global Score update event.
   * This is fired by ajax.js when the Global Score polling detects a change.
   */
  $(document).on('noobrocketGlobalScoreUpdated performance-monitoring-page-added performance-monitoring-page-retest', () => {
    fetchRecommendationsStatus();
  });
});

},{}],12:[function(require,module,exports){
"use strict";

(document => {
  'use strict';

  /**
   * Polls the HostedCDN subscription endpoint until is_loading becomes false.
   */
  class HostedCDNSubscriptionPoller {
    constructor() {
      this.path = '/noobrocket/v1/noobrocketcdn/subscription';
      this.pollInterval = 10000; // 10 seconds.
      this.maxRetries = 60; // 10 minutes total.
      this.timerId = null;
      this.retryCount = 0;
      document.addEventListener('noobrocketCDNSubscriptionLoading', () => this.start());

      // Re-trigger polling after page refresh.
      const classes = ['.noobrocket-icon-orange-loader', '.noobrocket-cdn-built-in--disabled'];
      const allPresent = classes.every(cls => document.querySelector(cls) !== null);
      if (allPresent) {
        this.start();
      }
    }

    /**
     * Starts polling.
     */
    start() {
      if (this.timerId) {
        return;
      }
      this.retryCount = 0;
      this.poll();
    }

    /**
     * Stops polling.
     */
    stop() {
      if (this.timerId) {
        clearTimeout(this.timerId);
        this.timerId = null;
      }
    }

    /**
     * Performs a single poll and schedules the next one if still loading.
     */
    poll() {
      if (this.retryCount >= this.maxRetries) {
        this.stop();
        return;
      }
      this.retryCount++;
      window.wp.apiFetch({
        path: this.path,
        method: 'GET'
      }).then(response => {
        if (!response || !response.is_loading) {
          this.stop();
          window.location.reload();
          return;
        }
        this.timerId = setTimeout(() => this.poll(), this.pollInterval);
      }).catch(() => {
        this.timerId = setTimeout(() => this.poll(), this.pollInterval);
      });
    }
  }
  new HostedCDNSubscriptionPoller();
})(document);

},{}],13:[function(require,module,exports){
"use strict";

/*eslint-env es6, browser*/
/* global MicroModal, mixpanel, noobrocket_mixpanel_data, noobrocket_cdn_mixpanel_data, noobrocket_ajax_data, ajaxurl */
((document, window) => {
  'use strict';

  const BANNER_STATE = {
    OPENED: false,
    // Big CTA - opened state
    COLLAPSED: true // Small CTA - collapsed state
  };
  document.addEventListener('noobrocketCDNBannerAutoExpanded', () => trackHostedCDNUpsellBannerExpanded('auto_limit_reached'));
  document.addEventListener('noobrocketCDNBannerAutoCollapsed', () => trackHostedCDNUpsellBannerCollapsed('auto_limit_released'));
  document.addEventListener('noobrocketCDNBannerFirstVisible', () => trackHostedCDNUpsellBannerViewed(BANNER_STATE.COLLAPSED));
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.noobrocket-noobrocketcdn-open').forEach(el => {
      el.addEventListener('click', e => {
        e.preventDefault();
        const isCTA = el.classList.contains('noobrocket-noobrocketcdn-pricing--cta');
        checkButtonUrlAndOpen(isCTA);
      });
    });

    // Always initialize MicroModal to set up close handlers
    MicroModal.init({
      disableScroll: true
    });
    maybeOpenModalFromURL();

    // Only auto-open modal if there's no direct button URL
    if (!window.noobrocketcdnButtonUrl || window.noobrocketcdnButtonUrl === '') {
      maybeOpenModal();
      const iframe = document.getElementById('noobrocketcdn-iframe');
      const loader = document.getElementById('noobrocket-noobrocketcdn-modal-loader');
      if (iframe && loader) {
        iframe.addEventListener('load', function () {
          loader.style.display = 'none';
        });
      }
    }
  });

  /**
   * Checks if the user is currently on the CDN tab.
   *
   * @return {boolean} True if on CDN tab, false otherwise.
   */
  function isOnCDNTab() {
    return window.location.hash === '#page_cdn';
  }
  function maybeTrackBannerView() {
    const smallCTA = document.querySelector('#noobrocket-noobrocketcdn-cta-small');
    const bigCTA = document.querySelector('#noobrocket-noobrocketcdn-cta');

    // Only track if one of the banners is visible.
    if (bigCTA && !bigCTA.classList.contains('noobrocket-isHidden')) {
      trackHostedCDNUpsellBannerViewed(BANNER_STATE.OPENED);
    } else if (smallCTA && !smallCTA.classList.contains('noobrocket-isHidden')) {
      trackHostedCDNUpsellBannerViewed(BANNER_STATE.COLLAPSED);
    }
  }
  window.addEventListener('load', () => {
    let openCTA = document.querySelector('#noobrocket-noobrocketcdn-open-cta'),
      closeCTA = document.querySelector('#noobrocket-noobrocketcdn-close-cta'),
      smallCTA = document.querySelector('#noobrocket-noobrocketcdn-cta-small'),
      bigCTA = document.querySelector('#noobrocket-noobrocketcdn-cta'),
      inputToggle = document.querySelector('.noobrocket-noobrocketcdn-toggle--input');
    const ctaToggle = document.querySelectorAll('.noobrocket-noobrocketcdn-cta-toggle');

    /**
     * Toggles HostedCDN CTA internal collapsed/expanded state.
     *
     * @return {void}
     */
    function toggleBigCTAState() {
      if (!bigCTA || !ctaToggle.length) {
        return;
      }
      const isCollapsed = bigCTA.classList.toggle('noobrocket-noobrocketcdn-cta--collapsed');
      bigCTA.classList.toggle('noobrocket-noobrocketcdn-cta--expanded', !isCollapsed);
      ctaToggle.forEach(el => {
        el.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
      });
      if (!isCollapsed) {
        trackHostedCDNUpsellBannerExpanded('manual');
      } else {
        trackHostedCDNUpsellBannerCollapsed();
      }
    }
    if (ctaToggle.length && bigCTA) {
      ctaToggle.forEach(el => {
        el.addEventListener('click', toggleBigCTAState);
        el.addEventListener('keydown', event => {
          if ('Enter' === event.key || ' ' === event.key) {
            event.preventDefault();
            toggleBigCTAState();
          }
        });
      });
    }

    // Track banner view on page load if banner is visible and user is on CDN tab.
    maybeTrackBannerView();

    // Track banner view when user navigates to CDN tab.
    window.addEventListener('hashchange', () => {
      maybeTrackBannerView();
    });

    // Prices selectors for toggling visibility based on the billing cycle toggle state.
    const prices = {
      monthly: {
        regular: document.querySelectorAll('.noobrocket-noobrocketcdn-pricing-regular-price--monthly'),
        current: document.querySelectorAll('.noobrocket-noobrocketcdn-pricing--monthly'),
        period: document.querySelectorAll('.noobrocket-noobrocketcdn-pricing--billing-period--monthly')
      },
      yearly: {
        regular: document.querySelectorAll('.noobrocket-noobrocketcdn-pricing-regular-price--yearly'),
        current: document.querySelectorAll('.noobrocket-noobrocketcdn-pricing--annual'),
        period: document.querySelectorAll('.noobrocket-noobrocketcdn-pricing--billing-period--yearly')
      }
    };

    // Display the correct prices on page based on billing cycle toggle state.
    if (inputToggle) {
      inputToggle.addEventListener('change', function () {
        const isYearly = this.checked;
        if (isYearly) {
          Object.values(prices.monthly).forEach(list => list.forEach(el => el.classList.add('noobrocket-isHidden')));
          Object.values(prices.yearly).forEach(list => list.forEach(el => el.classList.remove('noobrocket-isHidden')));
        } else {
          Object.values(prices.monthly).forEach(list => list.forEach(el => el.classList.remove('noobrocket-isHidden')));
          Object.values(prices.yearly).forEach(list => list.forEach(el => el.classList.add('noobrocket-isHidden')));
        }

        // Update the button URL with the correct is_monthly parameter.
        updateButtonUrlBillingCycle(isYearly);
      });
    }

    // Track HostedCDN activation failed CTA click
    const activationCTA = document.querySelector('#noobrocket-noobrocketcdn-activation-cta');
    if (activationCTA) {
      activationCTA.addEventListener('click', () => {
        trackHostedCDNActivationCTA();
      });
    }
  });
  window.onmessage = e => {
    const iframeURL = noobrocket_ajax_data.origin_url;
    if (e.origin !== iframeURL) {
      return;
    }
    setCDNFrameHeight(e.data);
    closeModal(e.data);
    tokenHandler(e.data, iframeURL);
    processStatus(e.data);
    enableCDN(e.data, iframeURL);
    disableCDN(e.data, iframeURL);
    validateTokenAndCNAME(e.data);
  };
  function openHostedCDNModal() {
    const noobrocketcdnIframe = document.getElementById('noobrocketcdn-iframe');
    if (!noobrocketcdnIframe || !noobrocketcdnIframe.dataset || !noobrocketcdnIframe.dataset.src || noobrocketcdnIframe.dataset.src === noobrocketcdnIframe.src) {
      return;
    }
    noobrocketcdnIframe.src = noobrocketcdnIframe.dataset.src;
    MicroModal.show('noobrocket-noobrocketcdn-modal');
  }
  function checkButtonUrlAndOpen(isCTA) {
    let iframeVisit = !window.noobrocketcdnButtonUrl;
    // Track CTA click if this is the pricing CTA button.
    if (isCTA) {
      trackHostedCDNUpsellCTAClicked(iframeVisit);
    }

    // Which button was actually clicked - the dashboard-tab CTA or the upsell banner CTA -
    // can only be resolved here. Reused below whether the click leads to a redirect or the
    // iframe fallback, since both paths converge on the same shared button/modal.
    const source = isCTA ? 'banner_cta' : 'dashboard_upgrade';

    // Check if button URL was injected by PHP
    if (!iframeVisit) {
      window.noobrocketcdnButtonUrl = setSourceParam(window.noobrocketcdnButtonUrl, source);

      // Small delay to ensure Mixpanel event is sent before navigation
      setTimeout(function () {
        window.location.href = window.noobrocketcdnButtonUrl;
      }, 100);
    } else {
      // Remembered so enableCDN() can report which CTA led to this iframe checkout.
      window.noobrocketcdnIframeSource = source;

      // Show iframe modal as usual
      openHostedCDNModal();
    }
  }

  /**
   * Updates the button URL with the correct is_monthly parameter based on billing cycle toggle.
   *
   * @param {boolean} isYearly - True if yearly billing is selected, false for monthly.
   */
  function updateButtonUrlBillingCycle(isYearly) {
    if (!window.noobrocketcdnButtonUrl || window.noobrocketcdnButtonUrl === '') {
      return;
    }
    window.noobrocketcdnButtonUrl = setIsMonthlyParam(window.noobrocketcdnButtonUrl, isYearly);
  }
  function maybeOpenModal() {
    let postData = '';
    postData += 'action=noobrocketcdn_process_status';
    postData += '&nonce=' + noobrocket_ajax_data.nonce;
    const request = sendHTTPRequest(postData);
    request.onreadystatechange = () => {
      if (request.readyState === XMLHttpRequest.DONE && 200 === request.status) {
        let responseTxt = JSON.parse(request.responseText);
        if (true === responseTxt.success) {
          openHostedCDNModal();
        }
      }
    };
  }
  function maybeOpenModalFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('noobrocketcdn_open_iframe') && '1' === urlParams.get('noobrocketcdn_open_iframe')) {
      // Set hash to page_cdn to show CDN tab behind modal
      window.location.hash = '#page_cdn';
      openHostedCDNModal();

      // Clean up the URL to prevent re-triggering on refresh
      urlParams.delete('noobrocketcdn_open_iframe');
      const search = urlParams.toString();
      const newURL = window.location.pathname + (search ? '?' + search : '') + window.location.hash;
      window.history.replaceState({}, '', newURL);
    }
  }
  function closeModal(data) {
    if (!data.hasOwnProperty('cdnFrameClose')) {
      return;
    }
    MicroModal.close('noobrocket-noobrocketcdn-modal');
    // Ensure scroll is restored
    document.body.style.overflow = '';
    let pages = ['iframe-payment-success', 'iframe-unsubscribe-success'];
    if (!data.hasOwnProperty('cdn_page_message')) {
      return;
    }
    if (pages.indexOf(data.cdn_page_message) === -1) {
      return;
    }
    window.location.hash = '#page_cdn';
    document.location.reload();
  }
  function processStatus(data) {
    if (!data.hasOwnProperty('noobrocketcdn_process')) {
      return;
    }
    let postData = '';
    postData += 'action=noobrocketcdn_process_set';
    postData += '&status=' + data.noobrocketcdn_process;
    postData += '&nonce=' + noobrocket_ajax_data.nonce;
    sendHTTPRequest(postData);
  }
  function enableCDN(data, iframeURL) {
    let iframe = document.querySelector('#noobrocketcdn-iframe').contentWindow;
    if (!data.hasOwnProperty('noobrocketcdn_url')) {
      return;
    }
    let postData = '';
    postData += 'action=noobrocketcdn_enable';
    postData += '&cdn_url=' + data.noobrocketcdn_url;
    postData += '&nonce=' + noobrocket_ajax_data.nonce;
    // Only set when the iframe was opened via checkButtonUrlAndOpen() - absent when the modal
    // was auto-reopened (maybeOpenModal(), maybeOpenModalFromURL()) with no click to attribute.
    // Cleared immediately after reading so a later call with no fresh click doesn't reuse it.
    if (window.noobrocketcdnIframeSource) {
      postData += '&source=' + window.noobrocketcdnIframeSource;
      delete window.noobrocketcdnIframeSource;
    }
    const request = sendHTTPRequest(postData);
    request.onreadystatechange = () => {
      if (request.readyState === XMLHttpRequest.DONE && 200 === request.status) {
        let responseTxt = JSON.parse(request.responseText);
        iframe.postMessage({
          'success': responseTxt.success,
          'data': responseTxt.data,
          'noobrocketcdn': true
        }, iframeURL);
      }
    };
  }
  function setSourceParam(url, source) {
    // The redirect-back destination is nested, URL-encoded, inside the outer URL's
    // dashboard_url param (alongside noobrocketcdn_checkout) - so that's where noobrocketcdn_source
    // has to be added too, for handle_noobrocketcdn_checkout_parameter() to read it back.
    try {
      const outerUrl = new URL(url);
      const dashboardUrl = outerUrl.searchParams.get('dashboard_url');
      if (!dashboardUrl) {
        return url;
      }
      const innerUrl = new URL(dashboardUrl);
      innerUrl.searchParams.set('noobrocketcdn_source', source);
      outerUrl.searchParams.set('dashboard_url', innerUrl.toString());
      return outerUrl.toString();
    } catch (e) {
      return url;
    }
  }
  function setIsMonthlyParam(url, isYearly) {
    // Remove any existing is_monthly param
    let newUrl = url.replace(/([?&])is_monthly=[^&]*/g, '');
    // Add the new param
    const sep = newUrl.includes('?') ? '&' : '?';
    newUrl += sep + 'is_monthly=' + (isYearly ? '0' : '1');
    return newUrl;
  }
  function disableCDN(data, iframeURL) {
    let iframe = document.querySelector('#noobrocketcdn-iframe').contentWindow;
    if (!data.hasOwnProperty('noobrocketcdn_disable')) {
      return;
    }
    let postData = '';
    postData += 'action=noobrocketcdn_disable';
    postData += '&nonce=' + noobrocket_ajax_data.nonce;
    const request = sendHTTPRequest(postData);
    request.onreadystatechange = () => {
      if (request.readyState === XMLHttpRequest.DONE && 200 === request.status) {
        let responseTxt = JSON.parse(request.responseText);
        iframe.postMessage({
          'success': responseTxt.success,
          'data': responseTxt.data,
          'noobrocketcdn': true
        }, iframeURL);
      }
    };
  }
  function sendHTTPRequest(postData) {
    const httpRequest = new XMLHttpRequest();
    httpRequest.open('POST', ajaxurl);
    httpRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    httpRequest.send(postData);
    return httpRequest;
  }
  function setCDNFrameHeight(data) {
    if (!data.hasOwnProperty('cdnFrameHeight')) {
      return;
    }
    document.getElementById('noobrocketcdn-iframe').style.height = `${data.cdnFrameHeight}px`;
  }
  function tokenHandler(data, iframeURL) {
    let iframe = document.querySelector('#noobrocketcdn-iframe').contentWindow;
    if (!data.hasOwnProperty('noobrocketcdn_token')) {
      let data = {
        process: "subscribe",
        message: "token_not_received"
      };
      iframe.postMessage({
        'success': false,
        'data': data,
        'noobrocketcdn': true
      }, iframeURL);
      return;
    }
    let postData = '';
    postData += 'action=save_noobrocketcdn_token';
    postData += '&value=' + data.noobrocketcdn_token;
    postData += '&nonce=' + noobrocket_ajax_data.nonce;
    const request = sendHTTPRequest(postData);
    request.onreadystatechange = () => {
      if (request.readyState === XMLHttpRequest.DONE && 200 === request.status) {
        let responseTxt = JSON.parse(request.responseText);
        iframe.postMessage({
          'success': responseTxt.success,
          'data': responseTxt.data,
          'noobrocketcdn': true
        }, iframeURL);
      }
    };
  }
  function validateTokenAndCNAME(data) {
    if (!data.hasOwnProperty('noobrocketcdn_validate_token') || !data.hasOwnProperty('noobrocketcdn_validate_cname')) {
      return;
    }
    let postData = '';
    postData += 'action=noobrocketcdn_validate_token_cname';
    postData += '&cdn_url=' + data.noobrocketcdn_validate_cname;
    postData += '&cdn_token=' + data.noobrocketcdn_validate_token;
    postData += '&nonce=' + noobrocket_ajax_data.nonce;
    const request = sendHTTPRequest(postData);
  }

  /**
   * Tracks HostedCDN activation failed CTA click with Mixpanel.
   */
  function trackHostedCDNActivationCTA() {
    trackHostedCDNUpsellMixpanelEvent('HostedCDN Activation Failed CTA Clicked');
  }

  /**
   * Tracks a HostedCDN upsell event with Mixpanel.
   *
   * @param {string} eventName   The Mixpanel event name.
   * @param {Object} [extraProps] Optional additional properties to merge.
   */
  function trackHostedCDNUpsellMixpanelEvent(eventName, extraProps) {
    if (typeof mixpanel === 'undefined' || !mixpanel.track) {
      return;
    }

    // Check if user has opted in.
    if (typeof noobrocket_mixpanel_data === 'undefined' || !noobrocket_mixpanel_data.optin_enabled || noobrocket_mixpanel_data.optin_enabled === '0') {
      return;
    }

    // Identify user if available - best-effort only, absence shouldn't block tracking.
    if (noobrocket_mixpanel_data.user_id && typeof mixpanel.identify === 'function') {
      mixpanel.identify(noobrocket_mixpanel_data.user_id);
    }
    var cdnData = typeof noobrocket_cdn_mixpanel_data !== 'undefined' ? noobrocket_cdn_mixpanel_data : {};
    var props = {
      context: noobrocket_mixpanel_data.context,
      plugin: noobrocket_mixpanel_data.plugin,
      brand: noobrocket_mixpanel_data.brand,
      application: noobrocket_mixpanel_data.app,
      path: noobrocket_mixpanel_data.path,
      cdn_mode: cdnData.cdn_mode,
      cdn_status: cdnData.cdn_status,
      interaction_channel: 'UI'
    };

    // Merge extra properties if provided and valid.
    if (extraProps && typeof extraProps === 'object') {
      for (var key in extraProps) {
        if (Object.prototype.hasOwnProperty.call(extraProps, key)) {
          props[key] = extraProps[key];
        }
      }
    }
    mixpanel.track(eventName, props);
  }

  /**
   * Tracks HostedCDN upsell banner view with Mixpanel.
   *
   * @param {boolean} [is_collapsed=false] Whether the small banner variant is displayed, Sends `collapsed` when true, otherwise `opened`.
   */
  function trackHostedCDNUpsellBannerViewed(is_collapsed = false) {
    if (!isOnCDNTab()) {
      return;
    }
    const hash = window.location.hash;
    const basePath = typeof noobrocket_mixpanel_data !== 'undefined' && noobrocket_mixpanel_data.path ? noobrocket_mixpanel_data.path : '';
    trackHostedCDNUpsellMixpanelEvent('HostedCDN Upsell Banner Viewed', {
      state: is_collapsed ? 'collapsed' : 'opened',
      page_name: hash,
      path: basePath + hash
    });
  }

  /**
   * Tracks HostedCDN upsell banner expanded with Mixpanel.
   *
   * @param {string} trigger 'manual' by default.
   */
  function trackHostedCDNUpsellBannerExpanded(trigger) {
    trackHostedCDNUpsellMixpanelEvent('HostedCDN Upsell Banner Expanded', {
      location: window.location.hash,
      trigger: trigger
    });
  }

  /**
   * Tracks HostedCDN upsell banner collapsed with Mixpanel.
   *
   * @param {string} [trigger='manual'] 'manual' when user clicks toggle, 'auto_limit_released' when a page deletion drops count below limit.
   */
  function trackHostedCDNUpsellBannerCollapsed(trigger = 'manual') {
    trackHostedCDNUpsellMixpanelEvent('HostedCDN Upsell Banner Collapsed', {
      location: window.location.hash,
      trigger: trigger
    });
  }

  /**
   * Tracks HostedCDN upsell CTA click with Mixpanel.
   */
  function trackHostedCDNUpsellCTAClicked(iframeVisit = false) {
    const tableList = document.querySelector('.noobrocket-cdn-built-in .noobrocket-table-list');
    const pagesCount = tableList ? tableList.querySelectorAll('[data-id]').length : 0;
    trackHostedCDNUpsellMixpanelEvent('HostedCDN Upsell CTA Clicked', {
      destination: iframeVisit ? 'iframe' : 'express-checkout',
      pages_count: pagesCount
    });
  }
})(document, window);

},{}],14:[function(require,module,exports){
"use strict";

/*!
 * VERSION: 1.12.1
 * DATE: 2014-06-26
 * UPDATES AND DOCS AT: http://www.greensock.com
 *
 * @license Copyright (c) 2008-2014, GreenSock. All rights reserved.
 * This work is subject to the terms at http://www.greensock.com/terms_of_use.html or for
 * Club GreenSock members, the software agreement that was issued with your membership.
 * 
 * @author: Jack Doyle, jack@greensock.com
 */
(window._gsQueue || (window._gsQueue = [])).push(function () {
  "use strict";

  window._gsDefine("TimelineLite", ["core.Animation", "core.SimpleTimeline", "TweenLite"], function (t, e, i) {
    var s = function (t) {
        e.call(this, t), this._labels = {}, this.autoRemoveChildren = this.vars.autoRemoveChildren === !0, this.smoothChildTiming = this.vars.smoothChildTiming === !0, this._sortChildren = !0, this._onUpdate = this.vars.onUpdate;
        var i,
          s,
          r = this.vars;
        for (s in r) i = r[s], a(i) && -1 !== i.join("").indexOf("{self}") && (r[s] = this._swapSelfInParams(i));
        a(r.tweens) && this.add(r.tweens, 0, r.align, r.stagger);
      },
      r = 1e-10,
      n = i._internals.isSelector,
      a = i._internals.isArray,
      o = [],
      h = window._gsDefine.globals,
      l = function (t) {
        var e,
          i = {};
        for (e in t) i[e] = t[e];
        return i;
      },
      _ = function (t, e, i, s) {
        t._timeline.pause(t._startTime), e && e.apply(s || t._timeline, i || o);
      },
      u = o.slice,
      f = s.prototype = new e();
    return s.version = "1.12.1", f.constructor = s, f.kill()._gc = !1, f.to = function (t, e, s, r) {
      var n = s.repeat && h.TweenMax || i;
      return e ? this.add(new n(t, e, s), r) : this.set(t, s, r);
    }, f.from = function (t, e, s, r) {
      return this.add((s.repeat && h.TweenMax || i).from(t, e, s), r);
    }, f.fromTo = function (t, e, s, r, n) {
      var a = r.repeat && h.TweenMax || i;
      return e ? this.add(a.fromTo(t, e, s, r), n) : this.set(t, r, n);
    }, f.staggerTo = function (t, e, r, a, o, h, _, f) {
      var p,
        c = new s({
          onComplete: h,
          onCompleteParams: _,
          onCompleteScope: f,
          smoothChildTiming: this.smoothChildTiming
        });
      for ("string" == typeof t && (t = i.selector(t) || t), n(t) && (t = u.call(t, 0)), a = a || 0, p = 0; t.length > p; p++) r.startAt && (r.startAt = l(r.startAt)), c.to(t[p], e, l(r), p * a);
      return this.add(c, o);
    }, f.staggerFrom = function (t, e, i, s, r, n, a, o) {
      return i.immediateRender = 0 != i.immediateRender, i.runBackwards = !0, this.staggerTo(t, e, i, s, r, n, a, o);
    }, f.staggerFromTo = function (t, e, i, s, r, n, a, o, h) {
      return s.startAt = i, s.immediateRender = 0 != s.immediateRender && 0 != i.immediateRender, this.staggerTo(t, e, s, r, n, a, o, h);
    }, f.call = function (t, e, s, r) {
      return this.add(i.delayedCall(0, t, e, s), r);
    }, f.set = function (t, e, s) {
      return s = this._parseTimeOrLabel(s, 0, !0), null == e.immediateRender && (e.immediateRender = s === this._time && !this._paused), this.add(new i(t, 0, e), s);
    }, s.exportRoot = function (t, e) {
      t = t || {}, null == t.smoothChildTiming && (t.smoothChildTiming = !0);
      var r,
        n,
        a = new s(t),
        o = a._timeline;
      for (null == e && (e = !0), o._remove(a, !0), a._startTime = 0, a._rawPrevTime = a._time = a._totalTime = o._time, r = o._first; r;) n = r._next, e && r instanceof i && r.target === r.vars.onComplete || a.add(r, r._startTime - r._delay), r = n;
      return o.add(a, 0), a;
    }, f.add = function (r, n, o, h) {
      var l, _, u, f, p, c;
      if ("number" != typeof n && (n = this._parseTimeOrLabel(n, 0, !0, r)), !(r instanceof t)) {
        if (r instanceof Array || r && r.push && a(r)) {
          for (o = o || "normal", h = h || 0, l = n, _ = r.length, u = 0; _ > u; u++) a(f = r[u]) && (f = new s({
            tweens: f
          })), this.add(f, l), "string" != typeof f && "function" != typeof f && ("sequence" === o ? l = f._startTime + f.totalDuration() / f._timeScale : "start" === o && (f._startTime -= f.delay())), l += h;
          return this._uncache(!0);
        }
        if ("string" == typeof r) return this.addLabel(r, n);
        if ("function" != typeof r) throw "Cannot add " + r + " into the timeline; it is not a tween, timeline, function, or string.";
        r = i.delayedCall(0, r);
      }
      if (e.prototype.add.call(this, r, n), (this._gc || this._time === this._duration) && !this._paused && this._duration < this.duration()) for (p = this, c = p.rawTime() > r._startTime; p._timeline;) c && p._timeline.smoothChildTiming ? p.totalTime(p._totalTime, !0) : p._gc && p._enabled(!0, !1), p = p._timeline;
      return this;
    }, f.remove = function (e) {
      if (e instanceof t) return this._remove(e, !1);
      if (e instanceof Array || e && e.push && a(e)) {
        for (var i = e.length; --i > -1;) this.remove(e[i]);
        return this;
      }
      return "string" == typeof e ? this.removeLabel(e) : this.kill(null, e);
    }, f._remove = function (t, i) {
      e.prototype._remove.call(this, t, i);
      var s = this._last;
      return s ? this._time > s._startTime + s._totalDuration / s._timeScale && (this._time = this.duration(), this._totalTime = this._totalDuration) : this._time = this._totalTime = this._duration = this._totalDuration = 0, this;
    }, f.append = function (t, e) {
      return this.add(t, this._parseTimeOrLabel(null, e, !0, t));
    }, f.insert = f.insertMultiple = function (t, e, i, s) {
      return this.add(t, e || 0, i, s);
    }, f.appendMultiple = function (t, e, i, s) {
      return this.add(t, this._parseTimeOrLabel(null, e, !0, t), i, s);
    }, f.addLabel = function (t, e) {
      return this._labels[t] = this._parseTimeOrLabel(e), this;
    }, f.addPause = function (t, e, i, s) {
      return this.call(_, ["{self}", e, i, s], this, t);
    }, f.removeLabel = function (t) {
      return delete this._labels[t], this;
    }, f.getLabelTime = function (t) {
      return null != this._labels[t] ? this._labels[t] : -1;
    }, f._parseTimeOrLabel = function (e, i, s, r) {
      var n;
      if (r instanceof t && r.timeline === this) this.remove(r);else if (r && (r instanceof Array || r.push && a(r))) for (n = r.length; --n > -1;) r[n] instanceof t && r[n].timeline === this && this.remove(r[n]);
      if ("string" == typeof i) return this._parseTimeOrLabel(i, s && "number" == typeof e && null == this._labels[i] ? e - this.duration() : 0, s);
      if (i = i || 0, "string" != typeof e || !isNaN(e) && null == this._labels[e]) null == e && (e = this.duration());else {
        if (n = e.indexOf("="), -1 === n) return null == this._labels[e] ? s ? this._labels[e] = this.duration() + i : i : this._labels[e] + i;
        i = parseInt(e.charAt(n - 1) + "1", 10) * Number(e.substr(n + 1)), e = n > 1 ? this._parseTimeOrLabel(e.substr(0, n - 1), 0, s) : this.duration();
      }
      return Number(e) + i;
    }, f.seek = function (t, e) {
      return this.totalTime("number" == typeof t ? t : this._parseTimeOrLabel(t), e !== !1);
    }, f.stop = function () {
      return this.paused(!0);
    }, f.gotoAndPlay = function (t, e) {
      return this.play(t, e);
    }, f.gotoAndStop = function (t, e) {
      return this.pause(t, e);
    }, f.render = function (t, e, i) {
      this._gc && this._enabled(!0, !1);
      var s,
        n,
        a,
        h,
        l,
        _ = this._dirty ? this.totalDuration() : this._totalDuration,
        u = this._time,
        f = this._startTime,
        p = this._timeScale,
        c = this._paused;
      if (t >= _ ? (this._totalTime = this._time = _, this._reversed || this._hasPausedChild() || (n = !0, h = "onComplete", 0 === this._duration && (0 === t || 0 > this._rawPrevTime || this._rawPrevTime === r) && this._rawPrevTime !== t && this._first && (l = !0, this._rawPrevTime > r && (h = "onReverseComplete"))), this._rawPrevTime = this._duration || !e || t || this._rawPrevTime === t ? t : r, t = _ + 1e-4) : 1e-7 > t ? (this._totalTime = this._time = 0, (0 !== u || 0 === this._duration && this._rawPrevTime !== r && (this._rawPrevTime > 0 || 0 > t && this._rawPrevTime >= 0)) && (h = "onReverseComplete", n = this._reversed), 0 > t ? (this._active = !1, 0 === this._duration && this._rawPrevTime >= 0 && this._first && (l = !0), this._rawPrevTime = t) : (this._rawPrevTime = this._duration || !e || t || this._rawPrevTime === t ? t : r, t = 0, this._initted || (l = !0))) : this._totalTime = this._time = this._rawPrevTime = t, this._time !== u && this._first || i || l) {
        if (this._initted || (this._initted = !0), this._active || !this._paused && this._time !== u && t > 0 && (this._active = !0), 0 === u && this.vars.onStart && 0 !== this._time && (e || this.vars.onStart.apply(this.vars.onStartScope || this, this.vars.onStartParams || o)), this._time >= u) for (s = this._first; s && (a = s._next, !this._paused || c);) (s._active || s._startTime <= this._time && !s._paused && !s._gc) && (s._reversed ? s.render((s._dirty ? s.totalDuration() : s._totalDuration) - (t - s._startTime) * s._timeScale, e, i) : s.render((t - s._startTime) * s._timeScale, e, i)), s = a;else for (s = this._last; s && (a = s._prev, !this._paused || c);) (s._active || u >= s._startTime && !s._paused && !s._gc) && (s._reversed ? s.render((s._dirty ? s.totalDuration() : s._totalDuration) - (t - s._startTime) * s._timeScale, e, i) : s.render((t - s._startTime) * s._timeScale, e, i)), s = a;
        this._onUpdate && (e || this._onUpdate.apply(this.vars.onUpdateScope || this, this.vars.onUpdateParams || o)), h && (this._gc || (f === this._startTime || p !== this._timeScale) && (0 === this._time || _ >= this.totalDuration()) && (n && (this._timeline.autoRemoveChildren && this._enabled(!1, !1), this._active = !1), !e && this.vars[h] && this.vars[h].apply(this.vars[h + "Scope"] || this, this.vars[h + "Params"] || o)));
      }
    }, f._hasPausedChild = function () {
      for (var t = this._first; t;) {
        if (t._paused || t instanceof s && t._hasPausedChild()) return !0;
        t = t._next;
      }
      return !1;
    }, f.getChildren = function (t, e, s, r) {
      r = r || -9999999999;
      for (var n = [], a = this._first, o = 0; a;) r > a._startTime || (a instanceof i ? e !== !1 && (n[o++] = a) : (s !== !1 && (n[o++] = a), t !== !1 && (n = n.concat(a.getChildren(!0, e, s)), o = n.length))), a = a._next;
      return n;
    }, f.getTweensOf = function (t, e) {
      var s,
        r,
        n = this._gc,
        a = [],
        o = 0;
      for (n && this._enabled(!0, !0), s = i.getTweensOf(t), r = s.length; --r > -1;) (s[r].timeline === this || e && this._contains(s[r])) && (a[o++] = s[r]);
      return n && this._enabled(!1, !0), a;
    }, f._contains = function (t) {
      for (var e = t.timeline; e;) {
        if (e === this) return !0;
        e = e.timeline;
      }
      return !1;
    }, f.shiftChildren = function (t, e, i) {
      i = i || 0;
      for (var s, r = this._first, n = this._labels; r;) r._startTime >= i && (r._startTime += t), r = r._next;
      if (e) for (s in n) n[s] >= i && (n[s] += t);
      return this._uncache(!0);
    }, f._kill = function (t, e) {
      if (!t && !e) return this._enabled(!1, !1);
      for (var i = e ? this.getTweensOf(e) : this.getChildren(!0, !0, !1), s = i.length, r = !1; --s > -1;) i[s]._kill(t, e) && (r = !0);
      return r;
    }, f.clear = function (t) {
      var e = this.getChildren(!1, !0, !0),
        i = e.length;
      for (this._time = this._totalTime = 0; --i > -1;) e[i]._enabled(!1, !1);
      return t !== !1 && (this._labels = {}), this._uncache(!0);
    }, f.invalidate = function () {
      for (var t = this._first; t;) t.invalidate(), t = t._next;
      return this;
    }, f._enabled = function (t, i) {
      if (t === this._gc) for (var s = this._first; s;) s._enabled(t, !0), s = s._next;
      return e.prototype._enabled.call(this, t, i);
    }, f.duration = function (t) {
      return arguments.length ? (0 !== this.duration() && 0 !== t && this.timeScale(this._duration / t), this) : (this._dirty && this.totalDuration(), this._duration);
    }, f.totalDuration = function (t) {
      if (!arguments.length) {
        if (this._dirty) {
          for (var e, i, s = 0, r = this._last, n = 999999999999; r;) e = r._prev, r._dirty && r.totalDuration(), r._startTime > n && this._sortChildren && !r._paused ? this.add(r, r._startTime - r._delay) : n = r._startTime, 0 > r._startTime && !r._paused && (s -= r._startTime, this._timeline.smoothChildTiming && (this._startTime += r._startTime / this._timeScale), this.shiftChildren(-r._startTime, !1, -9999999999), n = 0), i = r._startTime + r._totalDuration / r._timeScale, i > s && (s = i), r = e;
          this._duration = this._totalDuration = s, this._dirty = !1;
        }
        return this._totalDuration;
      }
      return 0 !== this.totalDuration() && 0 !== t && this.timeScale(this._totalDuration / t), this;
    }, f.usesFrames = function () {
      for (var e = this._timeline; e._timeline;) e = e._timeline;
      return e === t._rootFramesTimeline;
    }, f.rawTime = function () {
      return this._paused ? this._totalTime : (this._timeline.rawTime() - this._startTime) * this._timeScale;
    }, s;
  }, !0);
}), window._gsDefine && window._gsQueue.pop()();

},{}],15:[function(require,module,exports){
"use strict";

/*!
 * VERSION: 1.12.1
 * DATE: 2014-06-26
 * UPDATES AND DOCS AT: http://www.greensock.com
 *
 * @license Copyright (c) 2008-2014, GreenSock. All rights reserved.
 * This work is subject to the terms at http://www.greensock.com/terms_of_use.html or for
 * Club GreenSock members, the software agreement that was issued with your membership.
 * 
 * @author: Jack Doyle, jack@greensock.com
 */
(function (t) {
  "use strict";

  var e = t.GreenSockGlobals || t;
  if (!e.TweenLite) {
    var i,
      s,
      n,
      r,
      a,
      o = function (t) {
        var i,
          s = t.split("."),
          n = e;
        for (i = 0; s.length > i; i++) n[s[i]] = n = n[s[i]] || {};
        return n;
      },
      l = o("com.greensock"),
      h = 1e-10,
      _ = [].slice,
      u = function () {},
      m = function () {
        var t = Object.prototype.toString,
          e = t.call([]);
        return function (i) {
          return null != i && (i instanceof Array || "object" == typeof i && !!i.push && t.call(i) === e);
        };
      }(),
      f = {},
      p = function (i, s, n, r) {
        this.sc = f[i] ? f[i].sc : [], f[i] = this, this.gsClass = null, this.func = n;
        var a = [];
        this.check = function (l) {
          for (var h, _, u, m, c = s.length, d = c; --c > -1;) (h = f[s[c]] || new p(s[c], [])).gsClass ? (a[c] = h.gsClass, d--) : l && h.sc.push(this);
          if (0 === d && n) for (_ = ("com.greensock." + i).split("."), u = _.pop(), m = o(_.join("."))[u] = this.gsClass = n.apply(n, a), r && (e[u] = m, "function" == typeof define && define.amd ? define((t.GreenSockAMDPath ? t.GreenSockAMDPath + "/" : "") + i.split(".").join("/"), [], function () {
            return m;
          }) : "undefined" != typeof module && module.exports && (module.exports = m)), c = 0; this.sc.length > c; c++) this.sc[c].check();
        }, this.check(!0);
      },
      c = t._gsDefine = function (t, e, i, s) {
        return new p(t, e, i, s);
      },
      d = l._class = function (t, e, i) {
        return e = e || function () {}, c(t, [], function () {
          return e;
        }, i), e;
      };
    c.globals = e;
    var v = [0, 0, 1, 1],
      g = [],
      T = d("easing.Ease", function (t, e, i, s) {
        this._func = t, this._type = i || 0, this._power = s || 0, this._params = e ? v.concat(e) : v;
      }, !0),
      y = T.map = {},
      w = T.register = function (t, e, i, s) {
        for (var n, r, a, o, h = e.split(","), _ = h.length, u = (i || "easeIn,easeOut,easeInOut").split(","); --_ > -1;) for (r = h[_], n = s ? d("easing." + r, null, !0) : l.easing[r] || {}, a = u.length; --a > -1;) o = u[a], y[r + "." + o] = y[o + r] = n[o] = t.getRatio ? t : t[o] || new t();
      };
    for (n = T.prototype, n._calcEnd = !1, n.getRatio = function (t) {
      if (this._func) return this._params[0] = t, this._func.apply(null, this._params);
      var e = this._type,
        i = this._power,
        s = 1 === e ? 1 - t : 2 === e ? t : .5 > t ? 2 * t : 2 * (1 - t);
      return 1 === i ? s *= s : 2 === i ? s *= s * s : 3 === i ? s *= s * s * s : 4 === i && (s *= s * s * s * s), 1 === e ? 1 - s : 2 === e ? s : .5 > t ? s / 2 : 1 - s / 2;
    }, i = ["Linear", "Quad", "Cubic", "Quart", "Quint,Strong"], s = i.length; --s > -1;) n = i[s] + ",Power" + s, w(new T(null, null, 1, s), n, "easeOut", !0), w(new T(null, null, 2, s), n, "easeIn" + (0 === s ? ",easeNone" : "")), w(new T(null, null, 3, s), n, "easeInOut");
    y.linear = l.easing.Linear.easeIn, y.swing = l.easing.Quad.easeInOut;
    var P = d("events.EventDispatcher", function (t) {
      this._listeners = {}, this._eventTarget = t || this;
    });
    n = P.prototype, n.addEventListener = function (t, e, i, s, n) {
      n = n || 0;
      var o,
        l,
        h = this._listeners[t],
        _ = 0;
      for (null == h && (this._listeners[t] = h = []), l = h.length; --l > -1;) o = h[l], o.c === e && o.s === i ? h.splice(l, 1) : 0 === _ && n > o.pr && (_ = l + 1);
      h.splice(_, 0, {
        c: e,
        s: i,
        up: s,
        pr: n
      }), this !== r || a || r.wake();
    }, n.removeEventListener = function (t, e) {
      var i,
        s = this._listeners[t];
      if (s) for (i = s.length; --i > -1;) if (s[i].c === e) return s.splice(i, 1), void 0;
    }, n.dispatchEvent = function (t) {
      var e,
        i,
        s,
        n = this._listeners[t];
      if (n) for (e = n.length, i = this._eventTarget; --e > -1;) s = n[e], s.up ? s.c.call(s.s || i, {
        type: t,
        target: i
      }) : s.c.call(s.s || i);
    };
    var k = t.requestAnimationFrame,
      b = t.cancelAnimationFrame,
      A = Date.now || function () {
        return new Date().getTime();
      },
      S = A();
    for (i = ["ms", "moz", "webkit", "o"], s = i.length; --s > -1 && !k;) k = t[i[s] + "RequestAnimationFrame"], b = t[i[s] + "CancelAnimationFrame"] || t[i[s] + "CancelRequestAnimationFrame"];
    d("Ticker", function (t, e) {
      var i,
        s,
        n,
        o,
        l,
        _ = this,
        m = A(),
        f = e !== !1 && k,
        p = 500,
        c = 33,
        d = function (t) {
          var e,
            r,
            a = A() - S;
          a > p && (m += a - c), S += a, _.time = (S - m) / 1e3, e = _.time - l, (!i || e > 0 || t === !0) && (_.frame++, l += e + (e >= o ? .004 : o - e), r = !0), t !== !0 && (n = s(d)), r && _.dispatchEvent("tick");
        };
      P.call(_), _.time = _.frame = 0, _.tick = function () {
        d(!0);
      }, _.lagSmoothing = function (t, e) {
        p = t || 1 / h, c = Math.min(e, p, 0);
      }, _.sleep = function () {
        null != n && (f && b ? b(n) : clearTimeout(n), s = u, n = null, _ === r && (a = !1));
      }, _.wake = function () {
        null !== n ? _.sleep() : _.frame > 10 && (S = A() - p + 5), s = 0 === i ? u : f && k ? k : function (t) {
          return setTimeout(t, 0 | 1e3 * (l - _.time) + 1);
        }, _ === r && (a = !0), d(2);
      }, _.fps = function (t) {
        return arguments.length ? (i = t, o = 1 / (i || 60), l = this.time + o, _.wake(), void 0) : i;
      }, _.useRAF = function (t) {
        return arguments.length ? (_.sleep(), f = t, _.fps(i), void 0) : f;
      }, _.fps(t), setTimeout(function () {
        f && (!n || 5 > _.frame) && _.useRAF(!1);
      }, 1500);
    }), n = l.Ticker.prototype = new l.events.EventDispatcher(), n.constructor = l.Ticker;
    var x = d("core.Animation", function (t, e) {
      if (this.vars = e = e || {}, this._duration = this._totalDuration = t || 0, this._delay = Number(e.delay) || 0, this._timeScale = 1, this._active = e.immediateRender === !0, this.data = e.data, this._reversed = e.reversed === !0, B) {
        a || r.wake();
        var i = this.vars.useFrames ? Q : B;
        i.add(this, i._time), this.vars.paused && this.paused(!0);
      }
    });
    r = x.ticker = new l.Ticker(), n = x.prototype, n._dirty = n._gc = n._initted = n._paused = !1, n._totalTime = n._time = 0, n._rawPrevTime = -1, n._next = n._last = n._onUpdate = n._timeline = n.timeline = null, n._paused = !1;
    var C = function () {
      a && A() - S > 2e3 && r.wake(), setTimeout(C, 2e3);
    };
    C(), n.play = function (t, e) {
      return null != t && this.seek(t, e), this.reversed(!1).paused(!1);
    }, n.pause = function (t, e) {
      return null != t && this.seek(t, e), this.paused(!0);
    }, n.resume = function (t, e) {
      return null != t && this.seek(t, e), this.paused(!1);
    }, n.seek = function (t, e) {
      return this.totalTime(Number(t), e !== !1);
    }, n.restart = function (t, e) {
      return this.reversed(!1).paused(!1).totalTime(t ? -this._delay : 0, e !== !1, !0);
    }, n.reverse = function (t, e) {
      return null != t && this.seek(t || this.totalDuration(), e), this.reversed(!0).paused(!1);
    }, n.render = function () {}, n.invalidate = function () {
      return this;
    }, n.isActive = function () {
      var t,
        e = this._timeline,
        i = this._startTime;
      return !e || !this._gc && !this._paused && e.isActive() && (t = e.rawTime()) >= i && i + this.totalDuration() / this._timeScale > t;
    }, n._enabled = function (t, e) {
      return a || r.wake(), this._gc = !t, this._active = this.isActive(), e !== !0 && (t && !this.timeline ? this._timeline.add(this, this._startTime - this._delay) : !t && this.timeline && this._timeline._remove(this, !0)), !1;
    }, n._kill = function () {
      return this._enabled(!1, !1);
    }, n.kill = function (t, e) {
      return this._kill(t, e), this;
    }, n._uncache = function (t) {
      for (var e = t ? this : this.timeline; e;) e._dirty = !0, e = e.timeline;
      return this;
    }, n._swapSelfInParams = function (t) {
      for (var e = t.length, i = t.concat(); --e > -1;) "{self}" === t[e] && (i[e] = this);
      return i;
    }, n.eventCallback = function (t, e, i, s) {
      if ("on" === (t || "").substr(0, 2)) {
        var n = this.vars;
        if (1 === arguments.length) return n[t];
        null == e ? delete n[t] : (n[t] = e, n[t + "Params"] = m(i) && -1 !== i.join("").indexOf("{self}") ? this._swapSelfInParams(i) : i, n[t + "Scope"] = s), "onUpdate" === t && (this._onUpdate = e);
      }
      return this;
    }, n.delay = function (t) {
      return arguments.length ? (this._timeline.smoothChildTiming && this.startTime(this._startTime + t - this._delay), this._delay = t, this) : this._delay;
    }, n.duration = function (t) {
      return arguments.length ? (this._duration = this._totalDuration = t, this._uncache(!0), this._timeline.smoothChildTiming && this._time > 0 && this._time < this._duration && 0 !== t && this.totalTime(this._totalTime * (t / this._duration), !0), this) : (this._dirty = !1, this._duration);
    }, n.totalDuration = function (t) {
      return this._dirty = !1, arguments.length ? this.duration(t) : this._totalDuration;
    }, n.time = function (t, e) {
      return arguments.length ? (this._dirty && this.totalDuration(), this.totalTime(t > this._duration ? this._duration : t, e)) : this._time;
    }, n.totalTime = function (t, e, i) {
      if (a || r.wake(), !arguments.length) return this._totalTime;
      if (this._timeline) {
        if (0 > t && !i && (t += this.totalDuration()), this._timeline.smoothChildTiming) {
          this._dirty && this.totalDuration();
          var s = this._totalDuration,
            n = this._timeline;
          if (t > s && !i && (t = s), this._startTime = (this._paused ? this._pauseTime : n._time) - (this._reversed ? s - t : t) / this._timeScale, n._dirty || this._uncache(!1), n._timeline) for (; n._timeline;) n._timeline._time !== (n._startTime + n._totalTime) / n._timeScale && n.totalTime(n._totalTime, !0), n = n._timeline;
        }
        this._gc && this._enabled(!0, !1), (this._totalTime !== t || 0 === this._duration) && (this.render(t, e, !1), z.length && q());
      }
      return this;
    }, n.progress = n.totalProgress = function (t, e) {
      return arguments.length ? this.totalTime(this.duration() * t, e) : this._time / this.duration();
    }, n.startTime = function (t) {
      return arguments.length ? (t !== this._startTime && (this._startTime = t, this.timeline && this.timeline._sortChildren && this.timeline.add(this, t - this._delay)), this) : this._startTime;
    }, n.timeScale = function (t) {
      if (!arguments.length) return this._timeScale;
      if (t = t || h, this._timeline && this._timeline.smoothChildTiming) {
        var e = this._pauseTime,
          i = e || 0 === e ? e : this._timeline.totalTime();
        this._startTime = i - (i - this._startTime) * this._timeScale / t;
      }
      return this._timeScale = t, this._uncache(!1);
    }, n.reversed = function (t) {
      return arguments.length ? (t != this._reversed && (this._reversed = t, this.totalTime(this._timeline && !this._timeline.smoothChildTiming ? this.totalDuration() - this._totalTime : this._totalTime, !0)), this) : this._reversed;
    }, n.paused = function (t) {
      if (!arguments.length) return this._paused;
      if (t != this._paused && this._timeline) {
        a || t || r.wake();
        var e = this._timeline,
          i = e.rawTime(),
          s = i - this._pauseTime;
        !t && e.smoothChildTiming && (this._startTime += s, this._uncache(!1)), this._pauseTime = t ? i : null, this._paused = t, this._active = this.isActive(), !t && 0 !== s && this._initted && this.duration() && this.render(e.smoothChildTiming ? this._totalTime : (i - this._startTime) / this._timeScale, !0, !0);
      }
      return this._gc && !t && this._enabled(!0, !1), this;
    };
    var R = d("core.SimpleTimeline", function (t) {
      x.call(this, 0, t), this.autoRemoveChildren = this.smoothChildTiming = !0;
    });
    n = R.prototype = new x(), n.constructor = R, n.kill()._gc = !1, n._first = n._last = null, n._sortChildren = !1, n.add = n.insert = function (t, e) {
      var i, s;
      if (t._startTime = Number(e || 0) + t._delay, t._paused && this !== t._timeline && (t._pauseTime = t._startTime + (this.rawTime() - t._startTime) / t._timeScale), t.timeline && t.timeline._remove(t, !0), t.timeline = t._timeline = this, t._gc && t._enabled(!0, !0), i = this._last, this._sortChildren) for (s = t._startTime; i && i._startTime > s;) i = i._prev;
      return i ? (t._next = i._next, i._next = t) : (t._next = this._first, this._first = t), t._next ? t._next._prev = t : this._last = t, t._prev = i, this._timeline && this._uncache(!0), this;
    }, n._remove = function (t, e) {
      return t.timeline === this && (e || t._enabled(!1, !0), t.timeline = null, t._prev ? t._prev._next = t._next : this._first === t && (this._first = t._next), t._next ? t._next._prev = t._prev : this._last === t && (this._last = t._prev), this._timeline && this._uncache(!0)), this;
    }, n.render = function (t, e, i) {
      var s,
        n = this._first;
      for (this._totalTime = this._time = this._rawPrevTime = t; n;) s = n._next, (n._active || t >= n._startTime && !n._paused) && (n._reversed ? n.render((n._dirty ? n.totalDuration() : n._totalDuration) - (t - n._startTime) * n._timeScale, e, i) : n.render((t - n._startTime) * n._timeScale, e, i)), n = s;
    }, n.rawTime = function () {
      return a || r.wake(), this._totalTime;
    };
    var D = d("TweenLite", function (e, i, s) {
        if (x.call(this, i, s), this.render = D.prototype.render, null == e) throw "Cannot tween a null target.";
        this.target = e = "string" != typeof e ? e : D.selector(e) || e;
        var n,
          r,
          a,
          o = e.jquery || e.length && e !== t && e[0] && (e[0] === t || e[0].nodeType && e[0].style && !e.nodeType),
          l = this.vars.overwrite;
        if (this._overwrite = l = null == l ? G[D.defaultOverwrite] : "number" == typeof l ? l >> 0 : G[l], (o || e instanceof Array || e.push && m(e)) && "number" != typeof e[0]) for (this._targets = a = _.call(e, 0), this._propLookup = [], this._siblings = [], n = 0; a.length > n; n++) r = a[n], r ? "string" != typeof r ? r.length && r !== t && r[0] && (r[0] === t || r[0].nodeType && r[0].style && !r.nodeType) ? (a.splice(n--, 1), this._targets = a = a.concat(_.call(r, 0))) : (this._siblings[n] = M(r, this, !1), 1 === l && this._siblings[n].length > 1 && $(r, this, null, 1, this._siblings[n])) : (r = a[n--] = D.selector(r), "string" == typeof r && a.splice(n + 1, 1)) : a.splice(n--, 1);else this._propLookup = {}, this._siblings = M(e, this, !1), 1 === l && this._siblings.length > 1 && $(e, this, null, 1, this._siblings);
        (this.vars.immediateRender || 0 === i && 0 === this._delay && this.vars.immediateRender !== !1) && (this._time = -h, this.render(-this._delay));
      }, !0),
      I = function (e) {
        return e.length && e !== t && e[0] && (e[0] === t || e[0].nodeType && e[0].style && !e.nodeType);
      },
      E = function (t, e) {
        var i,
          s = {};
        for (i in t) j[i] || i in e && "transform" !== i && "x" !== i && "y" !== i && "width" !== i && "height" !== i && "className" !== i && "border" !== i || !(!L[i] || L[i] && L[i]._autoCSS) || (s[i] = t[i], delete t[i]);
        t.css = s;
      };
    n = D.prototype = new x(), n.constructor = D, n.kill()._gc = !1, n.ratio = 0, n._firstPT = n._targets = n._overwrittenProps = n._startAt = null, n._notifyPluginsOfEnabled = n._lazy = !1, D.version = "1.12.1", D.defaultEase = n._ease = new T(null, null, 1, 1), D.defaultOverwrite = "auto", D.ticker = r, D.autoSleep = !0, D.lagSmoothing = function (t, e) {
      r.lagSmoothing(t, e);
    }, D.selector = t.$ || t.jQuery || function (e) {
      return t.$ ? (D.selector = t.$, t.$(e)) : t.document ? t.document.getElementById("#" === e.charAt(0) ? e.substr(1) : e) : e;
    };
    var z = [],
      O = {},
      N = D._internals = {
        isArray: m,
        isSelector: I,
        lazyTweens: z
      },
      L = D._plugins = {},
      U = N.tweenLookup = {},
      F = 0,
      j = N.reservedProps = {
        ease: 1,
        delay: 1,
        overwrite: 1,
        onComplete: 1,
        onCompleteParams: 1,
        onCompleteScope: 1,
        useFrames: 1,
        runBackwards: 1,
        startAt: 1,
        onUpdate: 1,
        onUpdateParams: 1,
        onUpdateScope: 1,
        onStart: 1,
        onStartParams: 1,
        onStartScope: 1,
        onReverseComplete: 1,
        onReverseCompleteParams: 1,
        onReverseCompleteScope: 1,
        onRepeat: 1,
        onRepeatParams: 1,
        onRepeatScope: 1,
        easeParams: 1,
        yoyo: 1,
        immediateRender: 1,
        repeat: 1,
        repeatDelay: 1,
        data: 1,
        paused: 1,
        reversed: 1,
        autoCSS: 1,
        lazy: 1
      },
      G = {
        none: 0,
        all: 1,
        auto: 2,
        concurrent: 3,
        allOnStart: 4,
        preexisting: 5,
        "true": 1,
        "false": 0
      },
      Q = x._rootFramesTimeline = new R(),
      B = x._rootTimeline = new R(),
      q = function () {
        var t = z.length;
        for (O = {}; --t > -1;) i = z[t], i && i._lazy !== !1 && (i.render(i._lazy, !1, !0), i._lazy = !1);
        z.length = 0;
      };
    B._startTime = r.time, Q._startTime = r.frame, B._active = Q._active = !0, setTimeout(q, 1), x._updateRoot = D.render = function () {
      var t, e, i;
      if (z.length && q(), B.render((r.time - B._startTime) * B._timeScale, !1, !1), Q.render((r.frame - Q._startTime) * Q._timeScale, !1, !1), z.length && q(), !(r.frame % 120)) {
        for (i in U) {
          for (e = U[i].tweens, t = e.length; --t > -1;) e[t]._gc && e.splice(t, 1);
          0 === e.length && delete U[i];
        }
        if (i = B._first, (!i || i._paused) && D.autoSleep && !Q._first && 1 === r._listeners.tick.length) {
          for (; i && i._paused;) i = i._next;
          i || r.sleep();
        }
      }
    }, r.addEventListener("tick", x._updateRoot);
    var M = function (t, e, i) {
        var s,
          n,
          r = t._gsTweenID;
        if (U[r || (t._gsTweenID = r = "t" + F++)] || (U[r] = {
          target: t,
          tweens: []
        }), e && (s = U[r].tweens, s[n = s.length] = e, i)) for (; --n > -1;) s[n] === e && s.splice(n, 1);
        return U[r].tweens;
      },
      $ = function (t, e, i, s, n) {
        var r, a, o, l;
        if (1 === s || s >= 4) {
          for (l = n.length, r = 0; l > r; r++) if ((o = n[r]) !== e) o._gc || o._enabled(!1, !1) && (a = !0);else if (5 === s) break;
          return a;
        }
        var _,
          u = e._startTime + h,
          m = [],
          f = 0,
          p = 0 === e._duration;
        for (r = n.length; --r > -1;) (o = n[r]) === e || o._gc || o._paused || (o._timeline !== e._timeline ? (_ = _ || K(e, 0, p), 0 === K(o, _, p) && (m[f++] = o)) : u >= o._startTime && o._startTime + o.totalDuration() / o._timeScale > u && ((p || !o._initted) && 2e-10 >= u - o._startTime || (m[f++] = o)));
        for (r = f; --r > -1;) o = m[r], 2 === s && o._kill(i, t) && (a = !0), (2 !== s || !o._firstPT && o._initted) && o._enabled(!1, !1) && (a = !0);
        return a;
      },
      K = function (t, e, i) {
        for (var s = t._timeline, n = s._timeScale, r = t._startTime; s._timeline;) {
          if (r += s._startTime, n *= s._timeScale, s._paused) return -100;
          s = s._timeline;
        }
        return r /= n, r > e ? r - e : i && r === e || !t._initted && 2 * h > r - e ? h : (r += t.totalDuration() / t._timeScale / n) > e + h ? 0 : r - e - h;
      };
    n._init = function () {
      var t,
        e,
        i,
        s,
        n,
        r = this.vars,
        a = this._overwrittenProps,
        o = this._duration,
        l = !!r.immediateRender,
        h = r.ease;
      if (r.startAt) {
        this._startAt && (this._startAt.render(-1, !0), this._startAt.kill()), n = {};
        for (s in r.startAt) n[s] = r.startAt[s];
        if (n.overwrite = !1, n.immediateRender = !0, n.lazy = l && r.lazy !== !1, n.startAt = n.delay = null, this._startAt = D.to(this.target, 0, n), l) if (this._time > 0) this._startAt = null;else if (0 !== o) return;
      } else if (r.runBackwards && 0 !== o) if (this._startAt) this._startAt.render(-1, !0), this._startAt.kill(), this._startAt = null;else {
        i = {};
        for (s in r) j[s] && "autoCSS" !== s || (i[s] = r[s]);
        if (i.overwrite = 0, i.data = "isFromStart", i.lazy = l && r.lazy !== !1, i.immediateRender = l, this._startAt = D.to(this.target, 0, i), l) {
          if (0 === this._time) return;
        } else this._startAt._init(), this._startAt._enabled(!1);
      }
      if (this._ease = h ? h instanceof T ? r.easeParams instanceof Array ? h.config.apply(h, r.easeParams) : h : "function" == typeof h ? new T(h, r.easeParams) : y[h] || D.defaultEase : D.defaultEase, this._easeType = this._ease._type, this._easePower = this._ease._power, this._firstPT = null, this._targets) for (t = this._targets.length; --t > -1;) this._initProps(this._targets[t], this._propLookup[t] = {}, this._siblings[t], a ? a[t] : null) && (e = !0);else e = this._initProps(this.target, this._propLookup, this._siblings, a);
      if (e && D._onPluginEvent("_onInitAllProps", this), a && (this._firstPT || "function" != typeof this.target && this._enabled(!1, !1)), r.runBackwards) for (i = this._firstPT; i;) i.s += i.c, i.c = -i.c, i = i._next;
      this._onUpdate = r.onUpdate, this._initted = !0;
    }, n._initProps = function (e, i, s, n) {
      var r, a, o, l, h, _;
      if (null == e) return !1;
      O[e._gsTweenID] && q(), this.vars.css || e.style && e !== t && e.nodeType && L.css && this.vars.autoCSS !== !1 && E(this.vars, e);
      for (r in this.vars) {
        if (_ = this.vars[r], j[r]) _ && (_ instanceof Array || _.push && m(_)) && -1 !== _.join("").indexOf("{self}") && (this.vars[r] = _ = this._swapSelfInParams(_, this));else if (L[r] && (l = new L[r]())._onInitTween(e, this.vars[r], this)) {
          for (this._firstPT = h = {
            _next: this._firstPT,
            t: l,
            p: "setRatio",
            s: 0,
            c: 1,
            f: !0,
            n: r,
            pg: !0,
            pr: l._priority
          }, a = l._overwriteProps.length; --a > -1;) i[l._overwriteProps[a]] = this._firstPT;
          (l._priority || l._onInitAllProps) && (o = !0), (l._onDisable || l._onEnable) && (this._notifyPluginsOfEnabled = !0);
        } else this._firstPT = i[r] = h = {
          _next: this._firstPT,
          t: e,
          p: r,
          f: "function" == typeof e[r],
          n: r,
          pg: !1,
          pr: 0
        }, h.s = h.f ? e[r.indexOf("set") || "function" != typeof e["get" + r.substr(3)] ? r : "get" + r.substr(3)]() : parseFloat(e[r]), h.c = "string" == typeof _ && "=" === _.charAt(1) ? parseInt(_.charAt(0) + "1", 10) * Number(_.substr(2)) : Number(_) - h.s || 0;
        h && h._next && (h._next._prev = h);
      }
      return n && this._kill(n, e) ? this._initProps(e, i, s, n) : this._overwrite > 1 && this._firstPT && s.length > 1 && $(e, this, i, this._overwrite, s) ? (this._kill(i, e), this._initProps(e, i, s, n)) : (this._firstPT && (this.vars.lazy !== !1 && this._duration || this.vars.lazy && !this._duration) && (O[e._gsTweenID] = !0), o);
    }, n.render = function (t, e, i) {
      var s,
        n,
        r,
        a,
        o = this._time,
        l = this._duration,
        _ = this._rawPrevTime;
      if (t >= l) this._totalTime = this._time = l, this.ratio = this._ease._calcEnd ? this._ease.getRatio(1) : 1, this._reversed || (s = !0, n = "onComplete"), 0 === l && (this._initted || !this.vars.lazy || i) && (this._startTime === this._timeline._duration && (t = 0), (0 === t || 0 > _ || _ === h) && _ !== t && (i = !0, _ > h && (n = "onReverseComplete")), this._rawPrevTime = a = !e || t || _ === t ? t : h);else if (1e-7 > t) this._totalTime = this._time = 0, this.ratio = this._ease._calcEnd ? this._ease.getRatio(0) : 0, (0 !== o || 0 === l && _ > 0 && _ !== h) && (n = "onReverseComplete", s = this._reversed), 0 > t ? (this._active = !1, 0 === l && (this._initted || !this.vars.lazy || i) && (_ >= 0 && (i = !0), this._rawPrevTime = a = !e || t || _ === t ? t : h)) : this._initted || (i = !0);else if (this._totalTime = this._time = t, this._easeType) {
        var u = t / l,
          m = this._easeType,
          f = this._easePower;
        (1 === m || 3 === m && u >= .5) && (u = 1 - u), 3 === m && (u *= 2), 1 === f ? u *= u : 2 === f ? u *= u * u : 3 === f ? u *= u * u * u : 4 === f && (u *= u * u * u * u), this.ratio = 1 === m ? 1 - u : 2 === m ? u : .5 > t / l ? u / 2 : 1 - u / 2;
      } else this.ratio = this._ease.getRatio(t / l);
      if (this._time !== o || i) {
        if (!this._initted) {
          if (this._init(), !this._initted || this._gc) return;
          if (!i && this._firstPT && (this.vars.lazy !== !1 && this._duration || this.vars.lazy && !this._duration)) return this._time = this._totalTime = o, this._rawPrevTime = _, z.push(this), this._lazy = t, void 0;
          this._time && !s ? this.ratio = this._ease.getRatio(this._time / l) : s && this._ease._calcEnd && (this.ratio = this._ease.getRatio(0 === this._time ? 0 : 1));
        }
        for (this._lazy !== !1 && (this._lazy = !1), this._active || !this._paused && this._time !== o && t >= 0 && (this._active = !0), 0 === o && (this._startAt && (t >= 0 ? this._startAt.render(t, e, i) : n || (n = "_dummyGS")), this.vars.onStart && (0 !== this._time || 0 === l) && (e || this.vars.onStart.apply(this.vars.onStartScope || this, this.vars.onStartParams || g))), r = this._firstPT; r;) r.f ? r.t[r.p](r.c * this.ratio + r.s) : r.t[r.p] = r.c * this.ratio + r.s, r = r._next;
        this._onUpdate && (0 > t && this._startAt && this._startTime && this._startAt.render(t, e, i), e || (this._time !== o || s) && this._onUpdate.apply(this.vars.onUpdateScope || this, this.vars.onUpdateParams || g)), n && (this._gc || (0 > t && this._startAt && !this._onUpdate && this._startTime && this._startAt.render(t, e, i), s && (this._timeline.autoRemoveChildren && this._enabled(!1, !1), this._active = !1), !e && this.vars[n] && this.vars[n].apply(this.vars[n + "Scope"] || this, this.vars[n + "Params"] || g), 0 === l && this._rawPrevTime === h && a !== h && (this._rawPrevTime = 0)));
      }
    }, n._kill = function (t, e) {
      if ("all" === t && (t = null), null == t && (null == e || e === this.target)) return this._lazy = !1, this._enabled(!1, !1);
      e = "string" != typeof e ? e || this._targets || this.target : D.selector(e) || e;
      var i, s, n, r, a, o, l, h;
      if ((m(e) || I(e)) && "number" != typeof e[0]) for (i = e.length; --i > -1;) this._kill(t, e[i]) && (o = !0);else {
        if (this._targets) {
          for (i = this._targets.length; --i > -1;) if (e === this._targets[i]) {
            a = this._propLookup[i] || {}, this._overwrittenProps = this._overwrittenProps || [], s = this._overwrittenProps[i] = t ? this._overwrittenProps[i] || {} : "all";
            break;
          }
        } else {
          if (e !== this.target) return !1;
          a = this._propLookup, s = this._overwrittenProps = t ? this._overwrittenProps || {} : "all";
        }
        if (a) {
          l = t || a, h = t !== s && "all" !== s && t !== a && ("object" != typeof t || !t._tempKill);
          for (n in l) (r = a[n]) && (r.pg && r.t._kill(l) && (o = !0), r.pg && 0 !== r.t._overwriteProps.length || (r._prev ? r._prev._next = r._next : r === this._firstPT && (this._firstPT = r._next), r._next && (r._next._prev = r._prev), r._next = r._prev = null), delete a[n]), h && (s[n] = 1);
          !this._firstPT && this._initted && this._enabled(!1, !1);
        }
      }
      return o;
    }, n.invalidate = function () {
      return this._notifyPluginsOfEnabled && D._onPluginEvent("_onDisable", this), this._firstPT = null, this._overwrittenProps = null, this._onUpdate = null, this._startAt = null, this._initted = this._active = this._notifyPluginsOfEnabled = this._lazy = !1, this._propLookup = this._targets ? {} : [], this;
    }, n._enabled = function (t, e) {
      if (a || r.wake(), t && this._gc) {
        var i,
          s = this._targets;
        if (s) for (i = s.length; --i > -1;) this._siblings[i] = M(s[i], this, !0);else this._siblings = M(this.target, this, !0);
      }
      return x.prototype._enabled.call(this, t, e), this._notifyPluginsOfEnabled && this._firstPT ? D._onPluginEvent(t ? "_onEnable" : "_onDisable", this) : !1;
    }, D.to = function (t, e, i) {
      return new D(t, e, i);
    }, D.from = function (t, e, i) {
      return i.runBackwards = !0, i.immediateRender = 0 != i.immediateRender, new D(t, e, i);
    }, D.fromTo = function (t, e, i, s) {
      return s.startAt = i, s.immediateRender = 0 != s.immediateRender && 0 != i.immediateRender, new D(t, e, s);
    }, D.delayedCall = function (t, e, i, s, n) {
      return new D(e, 0, {
        delay: t,
        onComplete: e,
        onCompleteParams: i,
        onCompleteScope: s,
        onReverseComplete: e,
        onReverseCompleteParams: i,
        onReverseCompleteScope: s,
        immediateRender: !1,
        useFrames: n,
        overwrite: 0
      });
    }, D.set = function (t, e) {
      return new D(t, 0, e);
    }, D.getTweensOf = function (t, e) {
      if (null == t) return [];
      t = "string" != typeof t ? t : D.selector(t) || t;
      var i, s, n, r;
      if ((m(t) || I(t)) && "number" != typeof t[0]) {
        for (i = t.length, s = []; --i > -1;) s = s.concat(D.getTweensOf(t[i], e));
        for (i = s.length; --i > -1;) for (r = s[i], n = i; --n > -1;) r === s[n] && s.splice(i, 1);
      } else for (s = M(t).concat(), i = s.length; --i > -1;) (s[i]._gc || e && !s[i].isActive()) && s.splice(i, 1);
      return s;
    }, D.killTweensOf = D.killDelayedCallsTo = function (t, e, i) {
      "object" == typeof e && (i = e, e = !1);
      for (var s = D.getTweensOf(t, e), n = s.length; --n > -1;) s[n]._kill(i, t);
    };
    var H = d("plugins.TweenPlugin", function (t, e) {
      this._overwriteProps = (t || "").split(","), this._propName = this._overwriteProps[0], this._priority = e || 0, this._super = H.prototype;
    }, !0);
    if (n = H.prototype, H.version = "1.10.1", H.API = 2, n._firstPT = null, n._addTween = function (t, e, i, s, n, r) {
      var a, o;
      return null != s && (a = "number" == typeof s || "=" !== s.charAt(1) ? Number(s) - i : parseInt(s.charAt(0) + "1", 10) * Number(s.substr(2))) ? (this._firstPT = o = {
        _next: this._firstPT,
        t: t,
        p: e,
        s: i,
        c: a,
        f: "function" == typeof t[e],
        n: n || e,
        r: r
      }, o._next && (o._next._prev = o), o) : void 0;
    }, n.setRatio = function (t) {
      for (var e, i = this._firstPT, s = 1e-6; i;) e = i.c * t + i.s, i.r ? e = Math.round(e) : s > e && e > -s && (e = 0), i.f ? i.t[i.p](e) : i.t[i.p] = e, i = i._next;
    }, n._kill = function (t) {
      var e,
        i = this._overwriteProps,
        s = this._firstPT;
      if (null != t[this._propName]) this._overwriteProps = [];else for (e = i.length; --e > -1;) null != t[i[e]] && i.splice(e, 1);
      for (; s;) null != t[s.n] && (s._next && (s._next._prev = s._prev), s._prev ? (s._prev._next = s._next, s._prev = null) : this._firstPT === s && (this._firstPT = s._next)), s = s._next;
      return !1;
    }, n._roundProps = function (t, e) {
      for (var i = this._firstPT; i;) (t[this._propName] || null != i.n && t[i.n.split(this._propName + "_").join("")]) && (i.r = e), i = i._next;
    }, D._onPluginEvent = function (t, e) {
      var i,
        s,
        n,
        r,
        a,
        o = e._firstPT;
      if ("_onInitAllProps" === t) {
        for (; o;) {
          for (a = o._next, s = n; s && s.pr > o.pr;) s = s._next;
          (o._prev = s ? s._prev : r) ? o._prev._next = o : n = o, (o._next = s) ? s._prev = o : r = o, o = a;
        }
        o = e._firstPT = n;
      }
      for (; o;) o.pg && "function" == typeof o.t[t] && o.t[t]() && (i = !0), o = o._next;
      return i;
    }, H.activate = function (t) {
      for (var e = t.length; --e > -1;) t[e].API === H.API && (L[new t[e]()._propName] = t[e]);
      return !0;
    }, c.plugin = function (t) {
      if (!(t && t.propName && t.init && t.API)) throw "illegal plugin definition.";
      var e,
        i = t.propName,
        s = t.priority || 0,
        n = t.overwriteProps,
        r = {
          init: "_onInitTween",
          set: "setRatio",
          kill: "_kill",
          round: "_roundProps",
          initAll: "_onInitAllProps"
        },
        a = d("plugins." + i.charAt(0).toUpperCase() + i.substr(1) + "Plugin", function () {
          H.call(this, i, s), this._overwriteProps = n || [];
        }, t.global === !0),
        o = a.prototype = new H(i);
      o.constructor = a, a.API = t.API;
      for (e in r) "function" == typeof t[e] && (o[r[e]] = t[e]);
      return a.version = t.version, H.activate([a]), a;
    }, i = t._gsQueue) {
      for (s = 0; i.length > s; s++) i[s]();
      for (n in f) f[n].func || t.console.log("GSAP encountered missing dependency: com.greensock." + n);
    }
    a = !1;
  }
})(window);

},{}],16:[function(require,module,exports){
"use strict";

/*!
 * VERSION: beta 1.9.3
 * DATE: 2013-04-02
 * UPDATES AND DOCS AT: http://www.greensock.com
 *
 * @license Copyright (c) 2008-2014, GreenSock. All rights reserved.
 * This work is subject to the terms at http://www.greensock.com/terms_of_use.html or for
 * Club GreenSock members, the software agreement that was issued with your membership.
 * 
 * @author: Jack Doyle, jack@greensock.com
 **/
(window._gsQueue || (window._gsQueue = [])).push(function () {
  "use strict";

  window._gsDefine("easing.Back", ["easing.Ease"], function (t) {
    var e,
      i,
      s,
      r = window.GreenSockGlobals || window,
      n = r.com.greensock,
      a = 2 * Math.PI,
      o = Math.PI / 2,
      h = n._class,
      l = function (e, i) {
        var s = h("easing." + e, function () {}, !0),
          r = s.prototype = new t();
        return r.constructor = s, r.getRatio = i, s;
      },
      _ = t.register || function () {},
      u = function (t, e, i, s) {
        var r = h("easing." + t, {
          easeOut: new e(),
          easeIn: new i(),
          easeInOut: new s()
        }, !0);
        return _(r, t), r;
      },
      c = function (t, e, i) {
        this.t = t, this.v = e, i && (this.next = i, i.prev = this, this.c = i.v - e, this.gap = i.t - t);
      },
      f = function (e, i) {
        var s = h("easing." + e, function (t) {
            this._p1 = t || 0 === t ? t : 1.70158, this._p2 = 1.525 * this._p1;
          }, !0),
          r = s.prototype = new t();
        return r.constructor = s, r.getRatio = i, r.config = function (t) {
          return new s(t);
        }, s;
      },
      p = u("Back", f("BackOut", function (t) {
        return (t -= 1) * t * ((this._p1 + 1) * t + this._p1) + 1;
      }), f("BackIn", function (t) {
        return t * t * ((this._p1 + 1) * t - this._p1);
      }), f("BackInOut", function (t) {
        return 1 > (t *= 2) ? .5 * t * t * ((this._p2 + 1) * t - this._p2) : .5 * ((t -= 2) * t * ((this._p2 + 1) * t + this._p2) + 2);
      })),
      m = h("easing.SlowMo", function (t, e, i) {
        e = e || 0 === e ? e : .7, null == t ? t = .7 : t > 1 && (t = 1), this._p = 1 !== t ? e : 0, this._p1 = (1 - t) / 2, this._p2 = t, this._p3 = this._p1 + this._p2, this._calcEnd = i === !0;
      }, !0),
      d = m.prototype = new t();
    return d.constructor = m, d.getRatio = function (t) {
      var e = t + (.5 - t) * this._p;
      return this._p1 > t ? this._calcEnd ? 1 - (t = 1 - t / this._p1) * t : e - (t = 1 - t / this._p1) * t * t * t * e : t > this._p3 ? this._calcEnd ? 1 - (t = (t - this._p3) / this._p1) * t : e + (t - e) * (t = (t - this._p3) / this._p1) * t * t * t : this._calcEnd ? 1 : e;
    }, m.ease = new m(.7, .7), d.config = m.config = function (t, e, i) {
      return new m(t, e, i);
    }, e = h("easing.SteppedEase", function (t) {
      t = t || 1, this._p1 = 1 / t, this._p2 = t + 1;
    }, !0), d = e.prototype = new t(), d.constructor = e, d.getRatio = function (t) {
      return 0 > t ? t = 0 : t >= 1 && (t = .999999999), (this._p2 * t >> 0) * this._p1;
    }, d.config = e.config = function (t) {
      return new e(t);
    }, i = h("easing.RoughEase", function (e) {
      e = e || {};
      for (var i, s, r, n, a, o, h = e.taper || "none", l = [], _ = 0, u = 0 | (e.points || 20), f = u, p = e.randomize !== !1, m = e.clamp === !0, d = e.template instanceof t ? e.template : null, g = "number" == typeof e.strength ? .4 * e.strength : .4; --f > -1;) i = p ? Math.random() : 1 / u * f, s = d ? d.getRatio(i) : i, "none" === h ? r = g : "out" === h ? (n = 1 - i, r = n * n * g) : "in" === h ? r = i * i * g : .5 > i ? (n = 2 * i, r = .5 * n * n * g) : (n = 2 * (1 - i), r = .5 * n * n * g), p ? s += Math.random() * r - .5 * r : f % 2 ? s += .5 * r : s -= .5 * r, m && (s > 1 ? s = 1 : 0 > s && (s = 0)), l[_++] = {
        x: i,
        y: s
      };
      for (l.sort(function (t, e) {
        return t.x - e.x;
      }), o = new c(1, 1, null), f = u; --f > -1;) a = l[f], o = new c(a.x, a.y, o);
      this._prev = new c(0, 0, 0 !== o.t ? o : o.next);
    }, !0), d = i.prototype = new t(), d.constructor = i, d.getRatio = function (t) {
      var e = this._prev;
      if (t > e.t) {
        for (; e.next && t >= e.t;) e = e.next;
        e = e.prev;
      } else for (; e.prev && e.t >= t;) e = e.prev;
      return this._prev = e, e.v + (t - e.t) / e.gap * e.c;
    }, d.config = function (t) {
      return new i(t);
    }, i.ease = new i(), u("Bounce", l("BounceOut", function (t) {
      return 1 / 2.75 > t ? 7.5625 * t * t : 2 / 2.75 > t ? 7.5625 * (t -= 1.5 / 2.75) * t + .75 : 2.5 / 2.75 > t ? 7.5625 * (t -= 2.25 / 2.75) * t + .9375 : 7.5625 * (t -= 2.625 / 2.75) * t + .984375;
    }), l("BounceIn", function (t) {
      return 1 / 2.75 > (t = 1 - t) ? 1 - 7.5625 * t * t : 2 / 2.75 > t ? 1 - (7.5625 * (t -= 1.5 / 2.75) * t + .75) : 2.5 / 2.75 > t ? 1 - (7.5625 * (t -= 2.25 / 2.75) * t + .9375) : 1 - (7.5625 * (t -= 2.625 / 2.75) * t + .984375);
    }), l("BounceInOut", function (t) {
      var e = .5 > t;
      return t = e ? 1 - 2 * t : 2 * t - 1, t = 1 / 2.75 > t ? 7.5625 * t * t : 2 / 2.75 > t ? 7.5625 * (t -= 1.5 / 2.75) * t + .75 : 2.5 / 2.75 > t ? 7.5625 * (t -= 2.25 / 2.75) * t + .9375 : 7.5625 * (t -= 2.625 / 2.75) * t + .984375, e ? .5 * (1 - t) : .5 * t + .5;
    })), u("Circ", l("CircOut", function (t) {
      return Math.sqrt(1 - (t -= 1) * t);
    }), l("CircIn", function (t) {
      return -(Math.sqrt(1 - t * t) - 1);
    }), l("CircInOut", function (t) {
      return 1 > (t *= 2) ? -.5 * (Math.sqrt(1 - t * t) - 1) : .5 * (Math.sqrt(1 - (t -= 2) * t) + 1);
    })), s = function (e, i, s) {
      var r = h("easing." + e, function (t, e) {
          this._p1 = t || 1, this._p2 = e || s, this._p3 = this._p2 / a * (Math.asin(1 / this._p1) || 0);
        }, !0),
        n = r.prototype = new t();
      return n.constructor = r, n.getRatio = i, n.config = function (t, e) {
        return new r(t, e);
      }, r;
    }, u("Elastic", s("ElasticOut", function (t) {
      return this._p1 * Math.pow(2, -10 * t) * Math.sin((t - this._p3) * a / this._p2) + 1;
    }, .3), s("ElasticIn", function (t) {
      return -(this._p1 * Math.pow(2, 10 * (t -= 1)) * Math.sin((t - this._p3) * a / this._p2));
    }, .3), s("ElasticInOut", function (t) {
      return 1 > (t *= 2) ? -.5 * this._p1 * Math.pow(2, 10 * (t -= 1)) * Math.sin((t - this._p3) * a / this._p2) : .5 * this._p1 * Math.pow(2, -10 * (t -= 1)) * Math.sin((t - this._p3) * a / this._p2) + 1;
    }, .45)), u("Expo", l("ExpoOut", function (t) {
      return 1 - Math.pow(2, -10 * t);
    }), l("ExpoIn", function (t) {
      return Math.pow(2, 10 * (t - 1)) - .001;
    }), l("ExpoInOut", function (t) {
      return 1 > (t *= 2) ? .5 * Math.pow(2, 10 * (t - 1)) : .5 * (2 - Math.pow(2, -10 * (t - 1)));
    })), u("Sine", l("SineOut", function (t) {
      return Math.sin(t * o);
    }), l("SineIn", function (t) {
      return -Math.cos(t * o) + 1;
    }), l("SineInOut", function (t) {
      return -.5 * (Math.cos(Math.PI * t) - 1);
    })), h("easing.EaseLookup", {
      find: function (e) {
        return t.map[e];
      }
    }, !0), _(r.SlowMo, "SlowMo", "ease,"), _(i, "RoughEase", "ease,"), _(e, "SteppedEase", "ease,"), p;
  }, !0);
}), window._gsDefine && window._gsQueue.pop()();

},{}],17:[function(require,module,exports){
"use strict";

/*!
 * VERSION: 1.12.1
 * DATE: 2014-06-26
 * UPDATES AND DOCS AT: http://www.greensock.com
 *
 * @license Copyright (c) 2008-2014, GreenSock. All rights reserved.
 * This work is subject to the terms at http://www.greensock.com/terms_of_use.html or for
 * Club GreenSock members, the software agreement that was issued with your membership.
 * 
 * @author: Jack Doyle, jack@greensock.com
 */
(window._gsQueue || (window._gsQueue = [])).push(function () {
  "use strict";

  window._gsDefine("plugins.CSSPlugin", ["plugins.TweenPlugin", "TweenLite"], function (t, e) {
    var i,
      r,
      s,
      n,
      a = function () {
        t.call(this, "css"), this._overwriteProps.length = 0, this.setRatio = a.prototype.setRatio;
      },
      o = {},
      l = a.prototype = new t("css");
    l.constructor = a, a.version = "1.12.1", a.API = 2, a.defaultTransformPerspective = 0, a.defaultSkewType = "compensated", l = "px", a.suffixMap = {
      top: l,
      right: l,
      bottom: l,
      left: l,
      width: l,
      height: l,
      fontSize: l,
      padding: l,
      margin: l,
      perspective: l,
      lineHeight: ""
    };
    var h,
      u,
      f,
      _,
      p,
      c,
      d = /(?:\d|\-\d|\.\d|\-\.\d)+/g,
      m = /(?:\d|\-\d|\.\d|\-\.\d|\+=\d|\-=\d|\+=.\d|\-=\.\d)+/g,
      g = /(?:\+=|\-=|\-|\b)[\d\-\.]+[a-zA-Z0-9]*(?:%|\b)/gi,
      v = /[^\d\-\.]/g,
      y = /(?:\d|\-|\+|=|#|\.)*/g,
      T = /opacity *= *([^)]*)/i,
      w = /opacity:([^;]*)/i,
      x = /alpha\(opacity *=.+?\)/i,
      b = /^(rgb|hsl)/,
      P = /([A-Z])/g,
      S = /-([a-z])/gi,
      C = /(^(?:url\(\"|url\())|(?:(\"\))$|\)$)/gi,
      R = function (t, e) {
        return e.toUpperCase();
      },
      k = /(?:Left|Right|Width)/i,
      A = /(M11|M12|M21|M22)=[\d\-\.e]+/gi,
      O = /progid\:DXImageTransform\.Microsoft\.Matrix\(.+?\)/i,
      D = /,(?=[^\)]*(?:\(|$))/gi,
      M = Math.PI / 180,
      L = 180 / Math.PI,
      N = {},
      X = document,
      z = X.createElement("div"),
      I = X.createElement("img"),
      E = a._internals = {
        _specialProps: o
      },
      F = navigator.userAgent,
      Y = function () {
        var t,
          e = F.indexOf("Android"),
          i = X.createElement("div");
        return f = -1 !== F.indexOf("Safari") && -1 === F.indexOf("Chrome") && (-1 === e || Number(F.substr(e + 8, 1)) > 3), p = f && 6 > Number(F.substr(F.indexOf("Version/") + 8, 1)), _ = -1 !== F.indexOf("Firefox"), /MSIE ([0-9]{1,}[\.0-9]{0,})/.exec(F) && (c = parseFloat(RegExp.$1)), i.innerHTML = "<a style='top:1px;opacity:.55;'>a</a>", t = i.getElementsByTagName("a")[0], t ? /^0.55/.test(t.style.opacity) : !1;
      }(),
      B = function (t) {
        return T.test("string" == typeof t ? t : (t.currentStyle ? t.currentStyle.filter : t.style.filter) || "") ? parseFloat(RegExp.$1) / 100 : 1;
      },
      U = function (t) {
        window.console && console.log(t);
      },
      W = "",
      j = "",
      V = function (t, e) {
        e = e || z;
        var i,
          r,
          s = e.style;
        if (void 0 !== s[t]) return t;
        for (t = t.charAt(0).toUpperCase() + t.substr(1), i = ["O", "Moz", "ms", "Ms", "Webkit"], r = 5; --r > -1 && void 0 === s[i[r] + t];);
        return r >= 0 ? (j = 3 === r ? "ms" : i[r], W = "-" + j.toLowerCase() + "-", j + t) : null;
      },
      H = X.defaultView ? X.defaultView.getComputedStyle : function () {},
      q = a.getStyle = function (t, e, i, r, s) {
        var n;
        return Y || "opacity" !== e ? (!r && t.style[e] ? n = t.style[e] : (i = i || H(t)) ? n = i[e] || i.getPropertyValue(e) || i.getPropertyValue(e.replace(P, "-$1").toLowerCase()) : t.currentStyle && (n = t.currentStyle[e]), null == s || n && "none" !== n && "auto" !== n && "auto auto" !== n ? n : s) : B(t);
      },
      Q = E.convertToPixels = function (t, i, r, s, n) {
        if ("px" === s || !s) return r;
        if ("auto" === s || !r) return 0;
        var o,
          l,
          h,
          u = k.test(i),
          f = t,
          _ = z.style,
          p = 0 > r;
        if (p && (r = -r), "%" === s && -1 !== i.indexOf("border")) o = r / 100 * (u ? t.clientWidth : t.clientHeight);else {
          if (_.cssText = "border:0 solid red;position:" + q(t, "position") + ";line-height:0;", "%" !== s && f.appendChild) _[u ? "borderLeftWidth" : "borderTopWidth"] = r + s;else {
            if (f = t.parentNode || X.body, l = f._gsCache, h = e.ticker.frame, l && u && l.time === h) return l.width * r / 100;
            _[u ? "width" : "height"] = r + s;
          }
          f.appendChild(z), o = parseFloat(z[u ? "offsetWidth" : "offsetHeight"]), f.removeChild(z), u && "%" === s && a.cacheWidths !== !1 && (l = f._gsCache = f._gsCache || {}, l.time = h, l.width = 100 * (o / r)), 0 !== o || n || (o = Q(t, i, r, s, !0));
        }
        return p ? -o : o;
      },
      Z = E.calculateOffset = function (t, e, i) {
        if ("absolute" !== q(t, "position", i)) return 0;
        var r = "left" === e ? "Left" : "Top",
          s = q(t, "margin" + r, i);
        return t["offset" + r] - (Q(t, e, parseFloat(s), s.replace(y, "")) || 0);
      },
      $ = function (t, e) {
        var i,
          r,
          s = {};
        if (e = e || H(t, null)) {
          if (i = e.length) for (; --i > -1;) s[e[i].replace(S, R)] = e.getPropertyValue(e[i]);else for (i in e) s[i] = e[i];
        } else if (e = t.currentStyle || t.style) for (i in e) "string" == typeof i && void 0 === s[i] && (s[i.replace(S, R)] = e[i]);
        return Y || (s.opacity = B(t)), r = Pe(t, e, !1), s.rotation = r.rotation, s.skewX = r.skewX, s.scaleX = r.scaleX, s.scaleY = r.scaleY, s.x = r.x, s.y = r.y, xe && (s.z = r.z, s.rotationX = r.rotationX, s.rotationY = r.rotationY, s.scaleZ = r.scaleZ), s.filters && delete s.filters, s;
      },
      G = function (t, e, i, r, s) {
        var n,
          a,
          o,
          l = {},
          h = t.style;
        for (a in i) "cssText" !== a && "length" !== a && isNaN(a) && (e[a] !== (n = i[a]) || s && s[a]) && -1 === a.indexOf("Origin") && ("number" == typeof n || "string" == typeof n) && (l[a] = "auto" !== n || "left" !== a && "top" !== a ? "" !== n && "auto" !== n && "none" !== n || "string" != typeof e[a] || "" === e[a].replace(v, "") ? n : 0 : Z(t, a), void 0 !== h[a] && (o = new fe(h, a, h[a], o)));
        if (r) for (a in r) "className" !== a && (l[a] = r[a]);
        return {
          difs: l,
          firstMPT: o
        };
      },
      K = {
        width: ["Left", "Right"],
        height: ["Top", "Bottom"]
      },
      J = ["marginLeft", "marginRight", "marginTop", "marginBottom"],
      te = function (t, e, i) {
        var r = parseFloat("width" === e ? t.offsetWidth : t.offsetHeight),
          s = K[e],
          n = s.length;
        for (i = i || H(t, null); --n > -1;) r -= parseFloat(q(t, "padding" + s[n], i, !0)) || 0, r -= parseFloat(q(t, "border" + s[n] + "Width", i, !0)) || 0;
        return r;
      },
      ee = function (t, e) {
        (null == t || "" === t || "auto" === t || "auto auto" === t) && (t = "0 0");
        var i = t.split(" "),
          r = -1 !== t.indexOf("left") ? "0%" : -1 !== t.indexOf("right") ? "100%" : i[0],
          s = -1 !== t.indexOf("top") ? "0%" : -1 !== t.indexOf("bottom") ? "100%" : i[1];
        return null == s ? s = "0" : "center" === s && (s = "50%"), ("center" === r || isNaN(parseFloat(r)) && -1 === (r + "").indexOf("=")) && (r = "50%"), e && (e.oxp = -1 !== r.indexOf("%"), e.oyp = -1 !== s.indexOf("%"), e.oxr = "=" === r.charAt(1), e.oyr = "=" === s.charAt(1), e.ox = parseFloat(r.replace(v, "")), e.oy = parseFloat(s.replace(v, ""))), r + " " + s + (i.length > 2 ? " " + i[2] : "");
      },
      ie = function (t, e) {
        return "string" == typeof t && "=" === t.charAt(1) ? parseInt(t.charAt(0) + "1", 10) * parseFloat(t.substr(2)) : parseFloat(t) - parseFloat(e);
      },
      re = function (t, e) {
        return null == t ? e : "string" == typeof t && "=" === t.charAt(1) ? parseInt(t.charAt(0) + "1", 10) * Number(t.substr(2)) + e : parseFloat(t);
      },
      se = function (t, e, i, r) {
        var s,
          n,
          a,
          o,
          l = 1e-6;
        return null == t ? o = e : "number" == typeof t ? o = t : (s = 360, n = t.split("_"), a = Number(n[0].replace(v, "")) * (-1 === t.indexOf("rad") ? 1 : L) - ("=" === t.charAt(1) ? 0 : e), n.length && (r && (r[i] = e + a), -1 !== t.indexOf("short") && (a %= s, a !== a % (s / 2) && (a = 0 > a ? a + s : a - s)), -1 !== t.indexOf("_cw") && 0 > a ? a = (a + 9999999999 * s) % s - (0 | a / s) * s : -1 !== t.indexOf("ccw") && a > 0 && (a = (a - 9999999999 * s) % s - (0 | a / s) * s)), o = e + a), l > o && o > -l && (o = 0), o;
      },
      ne = {
        aqua: [0, 255, 255],
        lime: [0, 255, 0],
        silver: [192, 192, 192],
        black: [0, 0, 0],
        maroon: [128, 0, 0],
        teal: [0, 128, 128],
        blue: [0, 0, 255],
        navy: [0, 0, 128],
        white: [255, 255, 255],
        fuchsia: [255, 0, 255],
        olive: [128, 128, 0],
        yellow: [255, 255, 0],
        orange: [255, 165, 0],
        gray: [128, 128, 128],
        purple: [128, 0, 128],
        green: [0, 128, 0],
        red: [255, 0, 0],
        pink: [255, 192, 203],
        cyan: [0, 255, 255],
        transparent: [255, 255, 255, 0]
      },
      ae = function (t, e, i) {
        return t = 0 > t ? t + 1 : t > 1 ? t - 1 : t, 0 | 255 * (1 > 6 * t ? e + 6 * (i - e) * t : .5 > t ? i : 2 > 3 * t ? e + 6 * (i - e) * (2 / 3 - t) : e) + .5;
      },
      oe = function (t) {
        var e, i, r, s, n, a;
        return t && "" !== t ? "number" == typeof t ? [t >> 16, 255 & t >> 8, 255 & t] : ("," === t.charAt(t.length - 1) && (t = t.substr(0, t.length - 1)), ne[t] ? ne[t] : "#" === t.charAt(0) ? (4 === t.length && (e = t.charAt(1), i = t.charAt(2), r = t.charAt(3), t = "#" + e + e + i + i + r + r), t = parseInt(t.substr(1), 16), [t >> 16, 255 & t >> 8, 255 & t]) : "hsl" === t.substr(0, 3) ? (t = t.match(d), s = Number(t[0]) % 360 / 360, n = Number(t[1]) / 100, a = Number(t[2]) / 100, i = .5 >= a ? a * (n + 1) : a + n - a * n, e = 2 * a - i, t.length > 3 && (t[3] = Number(t[3])), t[0] = ae(s + 1 / 3, e, i), t[1] = ae(s, e, i), t[2] = ae(s - 1 / 3, e, i), t) : (t = t.match(d) || ne.transparent, t[0] = Number(t[0]), t[1] = Number(t[1]), t[2] = Number(t[2]), t.length > 3 && (t[3] = Number(t[3])), t)) : ne.black;
      },
      le = "(?:\\b(?:(?:rgb|rgba|hsl|hsla)\\(.+?\\))|\\B#.+?\\b";
    for (l in ne) le += "|" + l + "\\b";
    le = RegExp(le + ")", "gi");
    var he = function (t, e, i, r) {
        if (null == t) return function (t) {
          return t;
        };
        var s,
          n = e ? (t.match(le) || [""])[0] : "",
          a = t.split(n).join("").match(g) || [],
          o = t.substr(0, t.indexOf(a[0])),
          l = ")" === t.charAt(t.length - 1) ? ")" : "",
          h = -1 !== t.indexOf(" ") ? " " : ",",
          u = a.length,
          f = u > 0 ? a[0].replace(d, "") : "";
        return u ? s = e ? function (t) {
          var e, _, p, c;
          if ("number" == typeof t) t += f;else if (r && D.test(t)) {
            for (c = t.replace(D, "|").split("|"), p = 0; c.length > p; p++) c[p] = s(c[p]);
            return c.join(",");
          }
          if (e = (t.match(le) || [n])[0], _ = t.split(e).join("").match(g) || [], p = _.length, u > p--) for (; u > ++p;) _[p] = i ? _[0 | (p - 1) / 2] : a[p];
          return o + _.join(h) + h + e + l + (-1 !== t.indexOf("inset") ? " inset" : "");
        } : function (t) {
          var e, n, _;
          if ("number" == typeof t) t += f;else if (r && D.test(t)) {
            for (n = t.replace(D, "|").split("|"), _ = 0; n.length > _; _++) n[_] = s(n[_]);
            return n.join(",");
          }
          if (e = t.match(g) || [], _ = e.length, u > _--) for (; u > ++_;) e[_] = i ? e[0 | (_ - 1) / 2] : a[_];
          return o + e.join(h) + l;
        } : function (t) {
          return t;
        };
      },
      ue = function (t) {
        return t = t.split(","), function (e, i, r, s, n, a, o) {
          var l,
            h = (i + "").split(" ");
          for (o = {}, l = 0; 4 > l; l++) o[t[l]] = h[l] = h[l] || h[(l - 1) / 2 >> 0];
          return s.parse(e, o, n, a);
        };
      },
      fe = (E._setPluginRatio = function (t) {
        this.plugin.setRatio(t);
        for (var e, i, r, s, n = this.data, a = n.proxy, o = n.firstMPT, l = 1e-6; o;) e = a[o.v], o.r ? e = Math.round(e) : l > e && e > -l && (e = 0), o.t[o.p] = e, o = o._next;
        if (n.autoRotate && (n.autoRotate.rotation = a.rotation), 1 === t) for (o = n.firstMPT; o;) {
          if (i = o.t, i.type) {
            if (1 === i.type) {
              for (s = i.xs0 + i.s + i.xs1, r = 1; i.l > r; r++) s += i["xn" + r] + i["xs" + (r + 1)];
              i.e = s;
            }
          } else i.e = i.s + i.xs0;
          o = o._next;
        }
      }, function (t, e, i, r, s) {
        this.t = t, this.p = e, this.v = i, this.r = s, r && (r._prev = this, this._next = r);
      }),
      _e = (E._parseToProxy = function (t, e, i, r, s, n) {
        var a,
          o,
          l,
          h,
          u,
          f = r,
          _ = {},
          p = {},
          c = i._transform,
          d = N;
        for (i._transform = null, N = e, r = u = i.parse(t, e, r, s), N = d, n && (i._transform = c, f && (f._prev = null, f._prev && (f._prev._next = null))); r && r !== f;) {
          if (1 >= r.type && (o = r.p, p[o] = r.s + r.c, _[o] = r.s, n || (h = new fe(r, "s", o, h, r.r), r.c = 0), 1 === r.type)) for (a = r.l; --a > 0;) l = "xn" + a, o = r.p + "_" + l, p[o] = r.data[l], _[o] = r[l], n || (h = new fe(r, l, o, h, r.rxp[l]));
          r = r._next;
        }
        return {
          proxy: _,
          end: p,
          firstMPT: h,
          pt: u
        };
      }, E.CSSPropTween = function (t, e, r, s, a, o, l, h, u, f, _) {
        this.t = t, this.p = e, this.s = r, this.c = s, this.n = l || e, t instanceof _e || n.push(this.n), this.r = h, this.type = o || 0, u && (this.pr = u, i = !0), this.b = void 0 === f ? r : f, this.e = void 0 === _ ? r + s : _, a && (this._next = a, a._prev = this);
      }),
      pe = a.parseComplex = function (t, e, i, r, s, n, a, o, l, u) {
        i = i || n || "", a = new _e(t, e, 0, 0, a, u ? 2 : 1, null, !1, o, i, r), r += "";
        var f,
          _,
          p,
          c,
          g,
          v,
          y,
          T,
          w,
          x,
          P,
          S,
          C = i.split(", ").join(",").split(" "),
          R = r.split(", ").join(",").split(" "),
          k = C.length,
          A = h !== !1;
        for ((-1 !== r.indexOf(",") || -1 !== i.indexOf(",")) && (C = C.join(" ").replace(D, ", ").split(" "), R = R.join(" ").replace(D, ", ").split(" "), k = C.length), k !== R.length && (C = (n || "").split(" "), k = C.length), a.plugin = l, a.setRatio = u, f = 0; k > f; f++) if (c = C[f], g = R[f], T = parseFloat(c), T || 0 === T) a.appendXtra("", T, ie(g, T), g.replace(m, ""), A && -1 !== g.indexOf("px"), !0);else if (s && ("#" === c.charAt(0) || ne[c] || b.test(c))) S = "," === g.charAt(g.length - 1) ? ")," : ")", c = oe(c), g = oe(g), w = c.length + g.length > 6, w && !Y && 0 === g[3] ? (a["xs" + a.l] += a.l ? " transparent" : "transparent", a.e = a.e.split(R[f]).join("transparent")) : (Y || (w = !1), a.appendXtra(w ? "rgba(" : "rgb(", c[0], g[0] - c[0], ",", !0, !0).appendXtra("", c[1], g[1] - c[1], ",", !0).appendXtra("", c[2], g[2] - c[2], w ? "," : S, !0), w && (c = 4 > c.length ? 1 : c[3], a.appendXtra("", c, (4 > g.length ? 1 : g[3]) - c, S, !1)));else if (v = c.match(d)) {
          if (y = g.match(m), !y || y.length !== v.length) return a;
          for (p = 0, _ = 0; v.length > _; _++) P = v[_], x = c.indexOf(P, p), a.appendXtra(c.substr(p, x - p), Number(P), ie(y[_], P), "", A && "px" === c.substr(x + P.length, 2), 0 === _), p = x + P.length;
          a["xs" + a.l] += c.substr(p);
        } else a["xs" + a.l] += a.l ? " " + c : c;
        if (-1 !== r.indexOf("=") && a.data) {
          for (S = a.xs0 + a.data.s, f = 1; a.l > f; f++) S += a["xs" + f] + a.data["xn" + f];
          a.e = S + a["xs" + f];
        }
        return a.l || (a.type = -1, a.xs0 = a.e), a.xfirst || a;
      },
      ce = 9;
    for (l = _e.prototype, l.l = l.pr = 0; --ce > 0;) l["xn" + ce] = 0, l["xs" + ce] = "";
    l.xs0 = "", l._next = l._prev = l.xfirst = l.data = l.plugin = l.setRatio = l.rxp = null, l.appendXtra = function (t, e, i, r, s, n) {
      var a = this,
        o = a.l;
      return a["xs" + o] += n && o ? " " + t : t || "", i || 0 === o || a.plugin ? (a.l++, a.type = a.setRatio ? 2 : 1, a["xs" + a.l] = r || "", o > 0 ? (a.data["xn" + o] = e + i, a.rxp["xn" + o] = s, a["xn" + o] = e, a.plugin || (a.xfirst = new _e(a, "xn" + o, e, i, a.xfirst || a, 0, a.n, s, a.pr), a.xfirst.xs0 = 0), a) : (a.data = {
        s: e + i
      }, a.rxp = {}, a.s = e, a.c = i, a.r = s, a)) : (a["xs" + o] += e + (r || ""), a);
    };
    var de = function (t, e) {
        e = e || {}, this.p = e.prefix ? V(t) || t : t, o[t] = o[this.p] = this, this.format = e.formatter || he(e.defaultValue, e.color, e.collapsible, e.multi), e.parser && (this.parse = e.parser), this.clrs = e.color, this.multi = e.multi, this.keyword = e.keyword, this.dflt = e.defaultValue, this.pr = e.priority || 0;
      },
      me = E._registerComplexSpecialProp = function (t, e, i) {
        "object" != typeof e && (e = {
          parser: i
        });
        var r,
          s,
          n = t.split(","),
          a = e.defaultValue;
        for (i = i || [a], r = 0; n.length > r; r++) e.prefix = 0 === r && e.prefix, e.defaultValue = i[r] || a, s = new de(n[r], e);
      },
      ge = function (t) {
        if (!o[t]) {
          var e = t.charAt(0).toUpperCase() + t.substr(1) + "Plugin";
          me(t, {
            parser: function (t, i, r, s, n, a, l) {
              var h = (window.GreenSockGlobals || window).com.greensock.plugins[e];
              return h ? (h._cssRegister(), o[r].parse(t, i, r, s, n, a, l)) : (U("Error: " + e + " js file not loaded."), n);
            }
          });
        }
      };
    l = de.prototype, l.parseComplex = function (t, e, i, r, s, n) {
      var a,
        o,
        l,
        h,
        u,
        f,
        _ = this.keyword;
      if (this.multi && (D.test(i) || D.test(e) ? (o = e.replace(D, "|").split("|"), l = i.replace(D, "|").split("|")) : _ && (o = [e], l = [i])), l) {
        for (h = l.length > o.length ? l.length : o.length, a = 0; h > a; a++) e = o[a] = o[a] || this.dflt, i = l[a] = l[a] || this.dflt, _ && (u = e.indexOf(_), f = i.indexOf(_), u !== f && (i = -1 === f ? l : o, i[a] += " " + _));
        e = o.join(", "), i = l.join(", ");
      }
      return pe(t, this.p, e, i, this.clrs, this.dflt, r, this.pr, s, n);
    }, l.parse = function (t, e, i, r, n, a) {
      return this.parseComplex(t.style, this.format(q(t, this.p, s, !1, this.dflt)), this.format(e), n, a);
    }, a.registerSpecialProp = function (t, e, i) {
      me(t, {
        parser: function (t, r, s, n, a, o) {
          var l = new _e(t, s, 0, 0, a, 2, s, !1, i);
          return l.plugin = o, l.setRatio = e(t, r, n._tween, s), l;
        },
        priority: i
      });
    };
    var ve = "scaleX,scaleY,scaleZ,x,y,z,skewX,skewY,rotation,rotationX,rotationY,perspective".split(","),
      ye = V("transform"),
      Te = W + "transform",
      we = V("transformOrigin"),
      xe = null !== V("perspective"),
      be = E.Transform = function () {
        this.skewY = 0;
      },
      Pe = E.getTransform = function (t, e, i, r) {
        if (t._gsTransform && i && !r) return t._gsTransform;
        var s,
          n,
          o,
          l,
          h,
          u,
          f,
          _,
          p,
          c,
          d,
          m,
          g,
          v = i ? t._gsTransform || new be() : new be(),
          y = 0 > v.scaleX,
          T = 2e-5,
          w = 1e5,
          x = 179.99,
          b = x * M,
          P = xe ? parseFloat(q(t, we, e, !1, "0 0 0").split(" ")[2]) || v.zOrigin || 0 : 0;
        for (ye ? s = q(t, Te, e, !0) : t.currentStyle && (s = t.currentStyle.filter.match(A), s = s && 4 === s.length ? [s[0].substr(4), Number(s[2].substr(4)), Number(s[1].substr(4)), s[3].substr(4), v.x || 0, v.y || 0].join(",") : ""), n = (s || "").match(/(?:\-|\b)[\d\-\.e]+\b/gi) || [], o = n.length; --o > -1;) l = Number(n[o]), n[o] = (h = l - (l |= 0)) ? (0 | h * w + (0 > h ? -.5 : .5)) / w + l : l;
        if (16 === n.length) {
          var S = n[8],
            C = n[9],
            R = n[10],
            k = n[12],
            O = n[13],
            D = n[14];
          if (v.zOrigin && (D = -v.zOrigin, k = S * D - n[12], O = C * D - n[13], D = R * D + v.zOrigin - n[14]), !i || r || null == v.rotationX) {
            var N,
              X,
              z,
              I,
              E,
              F,
              Y,
              B = n[0],
              U = n[1],
              W = n[2],
              j = n[3],
              V = n[4],
              H = n[5],
              Q = n[6],
              Z = n[7],
              $ = n[11],
              G = Math.atan2(Q, R),
              K = -b > G || G > b;
            v.rotationX = G * L, G && (I = Math.cos(-G), E = Math.sin(-G), N = V * I + S * E, X = H * I + C * E, z = Q * I + R * E, S = V * -E + S * I, C = H * -E + C * I, R = Q * -E + R * I, $ = Z * -E + $ * I, V = N, H = X, Q = z), G = Math.atan2(S, B), v.rotationY = G * L, G && (F = -b > G || G > b, I = Math.cos(-G), E = Math.sin(-G), N = B * I - S * E, X = U * I - C * E, z = W * I - R * E, C = U * E + C * I, R = W * E + R * I, $ = j * E + $ * I, B = N, U = X, W = z), G = Math.atan2(U, H), v.rotation = G * L, G && (Y = -b > G || G > b, I = Math.cos(-G), E = Math.sin(-G), B = B * I + V * E, X = U * I + H * E, H = U * -E + H * I, Q = W * -E + Q * I, U = X), Y && K ? v.rotation = v.rotationX = 0 : Y && F ? v.rotation = v.rotationY = 0 : F && K && (v.rotationY = v.rotationX = 0), v.scaleX = (0 | Math.sqrt(B * B + U * U) * w + .5) / w, v.scaleY = (0 | Math.sqrt(H * H + C * C) * w + .5) / w, v.scaleZ = (0 | Math.sqrt(Q * Q + R * R) * w + .5) / w, v.skewX = 0, v.perspective = $ ? 1 / (0 > $ ? -$ : $) : 0, v.x = k, v.y = O, v.z = D;
          }
        } else if (!(xe && !r && n.length && v.x === n[4] && v.y === n[5] && (v.rotationX || v.rotationY) || void 0 !== v.x && "none" === q(t, "display", e))) {
          var J = n.length >= 6,
            te = J ? n[0] : 1,
            ee = n[1] || 0,
            ie = n[2] || 0,
            re = J ? n[3] : 1;
          v.x = n[4] || 0, v.y = n[5] || 0, u = Math.sqrt(te * te + ee * ee), f = Math.sqrt(re * re + ie * ie), _ = te || ee ? Math.atan2(ee, te) * L : v.rotation || 0, p = ie || re ? Math.atan2(ie, re) * L + _ : v.skewX || 0, c = u - Math.abs(v.scaleX || 0), d = f - Math.abs(v.scaleY || 0), Math.abs(p) > 90 && 270 > Math.abs(p) && (y ? (u *= -1, p += 0 >= _ ? 180 : -180, _ += 0 >= _ ? 180 : -180) : (f *= -1, p += 0 >= p ? 180 : -180)), m = (_ - v.rotation) % 180, g = (p - v.skewX) % 180, (void 0 === v.skewX || c > T || -T > c || d > T || -T > d || m > -x && x > m && false | m * w || g > -x && x > g && false | g * w) && (v.scaleX = u, v.scaleY = f, v.rotation = _, v.skewX = p), xe && (v.rotationX = v.rotationY = v.z = 0, v.perspective = parseFloat(a.defaultTransformPerspective) || 0, v.scaleZ = 1);
        }
        v.zOrigin = P;
        for (o in v) T > v[o] && v[o] > -T && (v[o] = 0);
        return i && (t._gsTransform = v), v;
      },
      Se = function (t) {
        var e,
          i,
          r = this.data,
          s = -r.rotation * M,
          n = s + r.skewX * M,
          a = 1e5,
          o = (0 | Math.cos(s) * r.scaleX * a) / a,
          l = (0 | Math.sin(s) * r.scaleX * a) / a,
          h = (0 | Math.sin(n) * -r.scaleY * a) / a,
          u = (0 | Math.cos(n) * r.scaleY * a) / a,
          f = this.t.style,
          _ = this.t.currentStyle;
        if (_) {
          i = l, l = -h, h = -i, e = _.filter, f.filter = "";
          var p,
            d,
            m = this.t.offsetWidth,
            g = this.t.offsetHeight,
            v = "absolute" !== _.position,
            w = "progid:DXImageTransform.Microsoft.Matrix(M11=" + o + ", M12=" + l + ", M21=" + h + ", M22=" + u,
            x = r.x,
            b = r.y;
          if (null != r.ox && (p = (r.oxp ? .01 * m * r.ox : r.ox) - m / 2, d = (r.oyp ? .01 * g * r.oy : r.oy) - g / 2, x += p - (p * o + d * l), b += d - (p * h + d * u)), v ? (p = m / 2, d = g / 2, w += ", Dx=" + (p - (p * o + d * l) + x) + ", Dy=" + (d - (p * h + d * u) + b) + ")") : w += ", sizingMethod='auto expand')", f.filter = -1 !== e.indexOf("DXImageTransform.Microsoft.Matrix(") ? e.replace(O, w) : w + " " + e, (0 === t || 1 === t) && 1 === o && 0 === l && 0 === h && 1 === u && (v && -1 === w.indexOf("Dx=0, Dy=0") || T.test(e) && 100 !== parseFloat(RegExp.$1) || -1 === e.indexOf("gradient(" && e.indexOf("Alpha")) && f.removeAttribute("filter")), !v) {
            var P,
              S,
              C,
              R = 8 > c ? 1 : -1;
            for (p = r.ieOffsetX || 0, d = r.ieOffsetY || 0, r.ieOffsetX = Math.round((m - ((0 > o ? -o : o) * m + (0 > l ? -l : l) * g)) / 2 + x), r.ieOffsetY = Math.round((g - ((0 > u ? -u : u) * g + (0 > h ? -h : h) * m)) / 2 + b), ce = 0; 4 > ce; ce++) S = J[ce], P = _[S], i = -1 !== P.indexOf("px") ? parseFloat(P) : Q(this.t, S, parseFloat(P), P.replace(y, "")) || 0, C = i !== r[S] ? 2 > ce ? -r.ieOffsetX : -r.ieOffsetY : 2 > ce ? p - r.ieOffsetX : d - r.ieOffsetY, f[S] = (r[S] = Math.round(i - C * (0 === ce || 2 === ce ? 1 : R))) + "px";
          }
        }
      },
      Ce = E.set3DTransformRatio = function (t) {
        var e,
          i,
          r,
          s,
          n,
          a,
          o,
          l,
          h,
          u,
          f,
          p,
          c,
          d,
          m,
          g,
          v,
          y,
          T,
          w,
          x,
          b,
          P,
          S = this.data,
          C = this.t.style,
          R = S.rotation * M,
          k = S.scaleX,
          A = S.scaleY,
          O = S.scaleZ,
          D = S.perspective;
        if (!(1 !== t && 0 !== t || "auto" !== S.force3D || S.rotationY || S.rotationX || 1 !== O || D || S.z)) return Re.call(this, t), void 0;
        if (_) {
          var L = 1e-4;
          L > k && k > -L && (k = O = 2e-5), L > A && A > -L && (A = O = 2e-5), !D || S.z || S.rotationX || S.rotationY || (D = 0);
        }
        if (R || S.skewX) y = Math.cos(R), T = Math.sin(R), e = y, n = T, S.skewX && (R -= S.skewX * M, y = Math.cos(R), T = Math.sin(R), "simple" === S.skewType && (w = Math.tan(S.skewX * M), w = Math.sqrt(1 + w * w), y *= w, T *= w)), i = -T, a = y;else {
          if (!(S.rotationY || S.rotationX || 1 !== O || D)) return C[ye] = "translate3d(" + S.x + "px," + S.y + "px," + S.z + "px)" + (1 !== k || 1 !== A ? " scale(" + k + "," + A + ")" : ""), void 0;
          e = a = 1, i = n = 0;
        }
        f = 1, r = s = o = l = h = u = p = c = d = 0, m = D ? -1 / D : 0, g = S.zOrigin, v = 1e5, R = S.rotationY * M, R && (y = Math.cos(R), T = Math.sin(R), h = f * -T, c = m * -T, r = e * T, o = n * T, f *= y, m *= y, e *= y, n *= y), R = S.rotationX * M, R && (y = Math.cos(R), T = Math.sin(R), w = i * y + r * T, x = a * y + o * T, b = u * y + f * T, P = d * y + m * T, r = i * -T + r * y, o = a * -T + o * y, f = u * -T + f * y, m = d * -T + m * y, i = w, a = x, u = b, d = P), 1 !== O && (r *= O, o *= O, f *= O, m *= O), 1 !== A && (i *= A, a *= A, u *= A, d *= A), 1 !== k && (e *= k, n *= k, h *= k, c *= k), g && (p -= g, s = r * p, l = o * p, p = f * p + g), s = (w = (s += S.x) - (s |= 0)) ? (0 | w * v + (0 > w ? -.5 : .5)) / v + s : s, l = (w = (l += S.y) - (l |= 0)) ? (0 | w * v + (0 > w ? -.5 : .5)) / v + l : l, p = (w = (p += S.z) - (p |= 0)) ? (0 | w * v + (0 > w ? -.5 : .5)) / v + p : p, C[ye] = "matrix3d(" + [(0 | e * v) / v, (0 | n * v) / v, (0 | h * v) / v, (0 | c * v) / v, (0 | i * v) / v, (0 | a * v) / v, (0 | u * v) / v, (0 | d * v) / v, (0 | r * v) / v, (0 | o * v) / v, (0 | f * v) / v, (0 | m * v) / v, s, l, p, D ? 1 + -p / D : 1].join(",") + ")";
      },
      Re = E.set2DTransformRatio = function (t) {
        var e,
          i,
          r,
          s,
          n,
          a = this.data,
          o = this.t,
          l = o.style;
        return a.rotationX || a.rotationY || a.z || a.force3D === !0 || "auto" === a.force3D && 1 !== t && 0 !== t ? (this.setRatio = Ce, Ce.call(this, t), void 0) : (a.rotation || a.skewX ? (e = a.rotation * M, i = e - a.skewX * M, r = 1e5, s = a.scaleX * r, n = a.scaleY * r, l[ye] = "matrix(" + (0 | Math.cos(e) * s) / r + "," + (0 | Math.sin(e) * s) / r + "," + (0 | Math.sin(i) * -n) / r + "," + (0 | Math.cos(i) * n) / r + "," + a.x + "," + a.y + ")") : l[ye] = "matrix(" + a.scaleX + ",0,0," + a.scaleY + "," + a.x + "," + a.y + ")", void 0);
      };
    me("transform,scale,scaleX,scaleY,scaleZ,x,y,z,rotation,rotationX,rotationY,rotationZ,skewX,skewY,shortRotation,shortRotationX,shortRotationY,shortRotationZ,transformOrigin,transformPerspective,directionalRotation,parseTransform,force3D,skewType", {
      parser: function (t, e, i, r, n, o, l) {
        if (r._transform) return n;
        var h,
          u,
          f,
          _,
          p,
          c,
          d,
          m = r._transform = Pe(t, s, !0, l.parseTransform),
          g = t.style,
          v = 1e-6,
          y = ve.length,
          T = l,
          w = {};
        if ("string" == typeof T.transform && ye) f = z.style, f[ye] = T.transform, f.display = "block", f.position = "absolute", X.body.appendChild(z), h = Pe(z, null, !1), X.body.removeChild(z);else if ("object" == typeof T) {
          if (h = {
            scaleX: re(null != T.scaleX ? T.scaleX : T.scale, m.scaleX),
            scaleY: re(null != T.scaleY ? T.scaleY : T.scale, m.scaleY),
            scaleZ: re(T.scaleZ, m.scaleZ),
            x: re(T.x, m.x),
            y: re(T.y, m.y),
            z: re(T.z, m.z),
            perspective: re(T.transformPerspective, m.perspective)
          }, d = T.directionalRotation, null != d) if ("object" == typeof d) for (f in d) T[f] = d[f];else T.rotation = d;
          h.rotation = se("rotation" in T ? T.rotation : "shortRotation" in T ? T.shortRotation + "_short" : "rotationZ" in T ? T.rotationZ : m.rotation, m.rotation, "rotation", w), xe && (h.rotationX = se("rotationX" in T ? T.rotationX : "shortRotationX" in T ? T.shortRotationX + "_short" : m.rotationX || 0, m.rotationX, "rotationX", w), h.rotationY = se("rotationY" in T ? T.rotationY : "shortRotationY" in T ? T.shortRotationY + "_short" : m.rotationY || 0, m.rotationY, "rotationY", w)), h.skewX = null == T.skewX ? m.skewX : se(T.skewX, m.skewX), h.skewY = null == T.skewY ? m.skewY : se(T.skewY, m.skewY), (u = h.skewY - m.skewY) && (h.skewX += u, h.rotation += u);
        }
        for (xe && null != T.force3D && (m.force3D = T.force3D, c = !0), m.skewType = T.skewType || m.skewType || a.defaultSkewType, p = m.force3D || m.z || m.rotationX || m.rotationY || h.z || h.rotationX || h.rotationY || h.perspective, p || null == T.scale || (h.scaleZ = 1); --y > -1;) i = ve[y], _ = h[i] - m[i], (_ > v || -v > _ || null != N[i]) && (c = !0, n = new _e(m, i, m[i], _, n), i in w && (n.e = w[i]), n.xs0 = 0, n.plugin = o, r._overwriteProps.push(n.n));
        return _ = T.transformOrigin, (_ || xe && p && m.zOrigin) && (ye ? (c = !0, i = we, _ = (_ || q(t, i, s, !1, "50% 50%")) + "", n = new _e(g, i, 0, 0, n, -1, "transformOrigin"), n.b = g[i], n.plugin = o, xe ? (f = m.zOrigin, _ = _.split(" "), m.zOrigin = (_.length > 2 && (0 === f || "0px" !== _[2]) ? parseFloat(_[2]) : f) || 0, n.xs0 = n.e = _[0] + " " + (_[1] || "50%") + " 0px", n = new _e(m, "zOrigin", 0, 0, n, -1, n.n), n.b = f, n.xs0 = n.e = m.zOrigin) : n.xs0 = n.e = _) : ee(_ + "", m)), c && (r._transformType = p || 3 === this._transformType ? 3 : 2), n;
      },
      prefix: !0
    }), me("boxShadow", {
      defaultValue: "0px 0px 0px 0px #999",
      prefix: !0,
      color: !0,
      multi: !0,
      keyword: "inset"
    }), me("borderRadius", {
      defaultValue: "0px",
      parser: function (t, e, i, n, a) {
        e = this.format(e);
        var o,
          l,
          h,
          u,
          f,
          _,
          p,
          c,
          d,
          m,
          g,
          v,
          y,
          T,
          w,
          x,
          b = ["borderTopLeftRadius", "borderTopRightRadius", "borderBottomRightRadius", "borderBottomLeftRadius"],
          P = t.style;
        for (d = parseFloat(t.offsetWidth), m = parseFloat(t.offsetHeight), o = e.split(" "), l = 0; b.length > l; l++) this.p.indexOf("border") && (b[l] = V(b[l])), f = u = q(t, b[l], s, !1, "0px"), -1 !== f.indexOf(" ") && (u = f.split(" "), f = u[0], u = u[1]), _ = h = o[l], p = parseFloat(f), v = f.substr((p + "").length), y = "=" === _.charAt(1), y ? (c = parseInt(_.charAt(0) + "1", 10), _ = _.substr(2), c *= parseFloat(_), g = _.substr((c + "").length - (0 > c ? 1 : 0)) || "") : (c = parseFloat(_), g = _.substr((c + "").length)), "" === g && (g = r[i] || v), g !== v && (T = Q(t, "borderLeft", p, v), w = Q(t, "borderTop", p, v), "%" === g ? (f = 100 * (T / d) + "%", u = 100 * (w / m) + "%") : "em" === g ? (x = Q(t, "borderLeft", 1, "em"), f = T / x + "em", u = w / x + "em") : (f = T + "px", u = w + "px"), y && (_ = parseFloat(f) + c + g, h = parseFloat(u) + c + g)), a = pe(P, b[l], f + " " + u, _ + " " + h, !1, "0px", a);
        return a;
      },
      prefix: !0,
      formatter: he("0px 0px 0px 0px", !1, !0)
    }), me("backgroundPosition", {
      defaultValue: "0 0",
      parser: function (t, e, i, r, n, a) {
        var o,
          l,
          h,
          u,
          f,
          _,
          p = "background-position",
          d = s || H(t, null),
          m = this.format((d ? c ? d.getPropertyValue(p + "-x") + " " + d.getPropertyValue(p + "-y") : d.getPropertyValue(p) : t.currentStyle.backgroundPositionX + " " + t.currentStyle.backgroundPositionY) || "0 0"),
          g = this.format(e);
        if (-1 !== m.indexOf("%") != (-1 !== g.indexOf("%")) && (_ = q(t, "backgroundImage").replace(C, ""), _ && "none" !== _)) {
          for (o = m.split(" "), l = g.split(" "), I.setAttribute("src", _), h = 2; --h > -1;) m = o[h], u = -1 !== m.indexOf("%"), u !== (-1 !== l[h].indexOf("%")) && (f = 0 === h ? t.offsetWidth - I.width : t.offsetHeight - I.height, o[h] = u ? parseFloat(m) / 100 * f + "px" : 100 * (parseFloat(m) / f) + "%");
          m = o.join(" ");
        }
        return this.parseComplex(t.style, m, g, n, a);
      },
      formatter: ee
    }), me("backgroundSize", {
      defaultValue: "0 0",
      formatter: ee
    }), me("perspective", {
      defaultValue: "0px",
      prefix: !0
    }), me("perspectiveOrigin", {
      defaultValue: "50% 50%",
      prefix: !0
    }), me("transformStyle", {
      prefix: !0
    }), me("backfaceVisibility", {
      prefix: !0
    }), me("userSelect", {
      prefix: !0
    }), me("margin", {
      parser: ue("marginTop,marginRight,marginBottom,marginLeft")
    }), me("padding", {
      parser: ue("paddingTop,paddingRight,paddingBottom,paddingLeft")
    }), me("clip", {
      defaultValue: "rect(0px,0px,0px,0px)",
      parser: function (t, e, i, r, n, a) {
        var o, l, h;
        return 9 > c ? (l = t.currentStyle, h = 8 > c ? " " : ",", o = "rect(" + l.clipTop + h + l.clipRight + h + l.clipBottom + h + l.clipLeft + ")", e = this.format(e).split(",").join(h)) : (o = this.format(q(t, this.p, s, !1, this.dflt)), e = this.format(e)), this.parseComplex(t.style, o, e, n, a);
      }
    }), me("textShadow", {
      defaultValue: "0px 0px 0px #999",
      color: !0,
      multi: !0
    }), me("autoRound,strictUnits", {
      parser: function (t, e, i, r, s) {
        return s;
      }
    }), me("border", {
      defaultValue: "0px solid #000",
      parser: function (t, e, i, r, n, a) {
        return this.parseComplex(t.style, this.format(q(t, "borderTopWidth", s, !1, "0px") + " " + q(t, "borderTopStyle", s, !1, "solid") + " " + q(t, "borderTopColor", s, !1, "#000")), this.format(e), n, a);
      },
      color: !0,
      formatter: function (t) {
        var e = t.split(" ");
        return e[0] + " " + (e[1] || "solid") + " " + (t.match(le) || ["#000"])[0];
      }
    }), me("borderWidth", {
      parser: ue("borderTopWidth,borderRightWidth,borderBottomWidth,borderLeftWidth")
    }), me("float,cssFloat,styleFloat", {
      parser: function (t, e, i, r, s) {
        var n = t.style,
          a = "cssFloat" in n ? "cssFloat" : "styleFloat";
        return new _e(n, a, 0, 0, s, -1, i, !1, 0, n[a], e);
      }
    });
    var ke = function (t) {
      var e,
        i = this.t,
        r = i.filter || q(this.data, "filter"),
        s = 0 | this.s + this.c * t;
      100 === s && (-1 === r.indexOf("atrix(") && -1 === r.indexOf("radient(") && -1 === r.indexOf("oader(") ? (i.removeAttribute("filter"), e = !q(this.data, "filter")) : (i.filter = r.replace(x, ""), e = !0)), e || (this.xn1 && (i.filter = r = r || "alpha(opacity=" + s + ")"), -1 === r.indexOf("pacity") ? 0 === s && this.xn1 || (i.filter = r + " alpha(opacity=" + s + ")") : i.filter = r.replace(T, "opacity=" + s));
    };
    me("opacity,alpha,autoAlpha", {
      defaultValue: "1",
      parser: function (t, e, i, r, n, a) {
        var o = parseFloat(q(t, "opacity", s, !1, "1")),
          l = t.style,
          h = "autoAlpha" === i;
        return "string" == typeof e && "=" === e.charAt(1) && (e = ("-" === e.charAt(0) ? -1 : 1) * parseFloat(e.substr(2)) + o), h && 1 === o && "hidden" === q(t, "visibility", s) && 0 !== e && (o = 0), Y ? n = new _e(l, "opacity", o, e - o, n) : (n = new _e(l, "opacity", 100 * o, 100 * (e - o), n), n.xn1 = h ? 1 : 0, l.zoom = 1, n.type = 2, n.b = "alpha(opacity=" + n.s + ")", n.e = "alpha(opacity=" + (n.s + n.c) + ")", n.data = t, n.plugin = a, n.setRatio = ke), h && (n = new _e(l, "visibility", 0, 0, n, -1, null, !1, 0, 0 !== o ? "inherit" : "hidden", 0 === e ? "hidden" : "inherit"), n.xs0 = "inherit", r._overwriteProps.push(n.n), r._overwriteProps.push(i)), n;
      }
    });
    var Ae = function (t, e) {
        e && (t.removeProperty ? ("ms" === e.substr(0, 2) && (e = "M" + e.substr(1)), t.removeProperty(e.replace(P, "-$1").toLowerCase())) : t.removeAttribute(e));
      },
      Oe = function (t) {
        if (this.t._gsClassPT = this, 1 === t || 0 === t) {
          this.t.setAttribute("class", 0 === t ? this.b : this.e);
          for (var e = this.data, i = this.t.style; e;) e.v ? i[e.p] = e.v : Ae(i, e.p), e = e._next;
          1 === t && this.t._gsClassPT === this && (this.t._gsClassPT = null);
        } else this.t.getAttribute("class") !== this.e && this.t.setAttribute("class", this.e);
      };
    me("className", {
      parser: function (t, e, r, n, a, o, l) {
        var h,
          u,
          f,
          _,
          p,
          c = t.getAttribute("class") || "",
          d = t.style.cssText;
        if (a = n._classNamePT = new _e(t, r, 0, 0, a, 2), a.setRatio = Oe, a.pr = -11, i = !0, a.b = c, u = $(t, s), f = t._gsClassPT) {
          for (_ = {}, p = f.data; p;) _[p.p] = 1, p = p._next;
          f.setRatio(1);
        }
        return t._gsClassPT = a, a.e = "=" !== e.charAt(1) ? e : c.replace(RegExp("\\s*\\b" + e.substr(2) + "\\b"), "") + ("+" === e.charAt(0) ? " " + e.substr(2) : ""), n._tween._duration && (t.setAttribute("class", a.e), h = G(t, u, $(t), l, _), t.setAttribute("class", c), a.data = h.firstMPT, t.style.cssText = d, a = a.xfirst = n.parse(t, h.difs, a, o)), a;
      }
    });
    var De = function (t) {
      if ((1 === t || 0 === t) && this.data._totalTime === this.data._totalDuration && "isFromStart" !== this.data.data) {
        var e,
          i,
          r,
          s,
          n = this.t.style,
          a = o.transform.parse;
        if ("all" === this.e) n.cssText = "", s = !0;else for (e = this.e.split(","), r = e.length; --r > -1;) i = e[r], o[i] && (o[i].parse === a ? s = !0 : i = "transformOrigin" === i ? we : o[i].p), Ae(n, i);
        s && (Ae(n, ye), this.t._gsTransform && delete this.t._gsTransform);
      }
    };
    for (me("clearProps", {
      parser: function (t, e, r, s, n) {
        return n = new _e(t, r, 0, 0, n, 2), n.setRatio = De, n.e = e, n.pr = -10, n.data = s._tween, i = !0, n;
      }
    }), l = "bezier,throwProps,physicsProps,physics2D".split(","), ce = l.length; ce--;) ge(l[ce]);
    l = a.prototype, l._firstPT = null, l._onInitTween = function (t, e, o) {
      if (!t.nodeType) return !1;
      this._target = t, this._tween = o, this._vars = e, h = e.autoRound, i = !1, r = e.suffixMap || a.suffixMap, s = H(t, ""), n = this._overwriteProps;
      var l,
        _,
        c,
        d,
        m,
        g,
        v,
        y,
        T,
        x = t.style;
      if (u && "" === x.zIndex && (l = q(t, "zIndex", s), ("auto" === l || "" === l) && this._addLazySet(x, "zIndex", 0)), "string" == typeof e && (d = x.cssText, l = $(t, s), x.cssText = d + ";" + e, l = G(t, l, $(t)).difs, !Y && w.test(e) && (l.opacity = parseFloat(RegExp.$1)), e = l, x.cssText = d), this._firstPT = _ = this.parse(t, e, null), this._transformType) {
        for (T = 3 === this._transformType, ye ? f && (u = !0, "" === x.zIndex && (v = q(t, "zIndex", s), ("auto" === v || "" === v) && this._addLazySet(x, "zIndex", 0)), p && this._addLazySet(x, "WebkitBackfaceVisibility", this._vars.WebkitBackfaceVisibility || (T ? "visible" : "hidden"))) : x.zoom = 1, c = _; c && c._next;) c = c._next;
        y = new _e(t, "transform", 0, 0, null, 2), this._linkCSSP(y, null, c), y.setRatio = T && xe ? Ce : ye ? Re : Se, y.data = this._transform || Pe(t, s, !0), n.pop();
      }
      if (i) {
        for (; _;) {
          for (g = _._next, c = d; c && c.pr > _.pr;) c = c._next;
          (_._prev = c ? c._prev : m) ? _._prev._next = _ : d = _, (_._next = c) ? c._prev = _ : m = _, _ = g;
        }
        this._firstPT = d;
      }
      return !0;
    }, l.parse = function (t, e, i, n) {
      var a,
        l,
        u,
        f,
        _,
        p,
        c,
        d,
        m,
        g,
        v = t.style;
      for (a in e) p = e[a], l = o[a], l ? i = l.parse(t, p, a, this, i, n, e) : (_ = q(t, a, s) + "", m = "string" == typeof p, "color" === a || "fill" === a || "stroke" === a || -1 !== a.indexOf("Color") || m && b.test(p) ? (m || (p = oe(p), p = (p.length > 3 ? "rgba(" : "rgb(") + p.join(",") + ")"), i = pe(v, a, _, p, !0, "transparent", i, 0, n)) : !m || -1 === p.indexOf(" ") && -1 === p.indexOf(",") ? (u = parseFloat(_), c = u || 0 === u ? _.substr((u + "").length) : "", ("" === _ || "auto" === _) && ("width" === a || "height" === a ? (u = te(t, a, s), c = "px") : "left" === a || "top" === a ? (u = Z(t, a, s), c = "px") : (u = "opacity" !== a ? 0 : 1, c = "")), g = m && "=" === p.charAt(1), g ? (f = parseInt(p.charAt(0) + "1", 10), p = p.substr(2), f *= parseFloat(p), d = p.replace(y, "")) : (f = parseFloat(p), d = m ? p.substr((f + "").length) || "" : ""), "" === d && (d = a in r ? r[a] : c), p = f || 0 === f ? (g ? f + u : f) + d : e[a], c !== d && "" !== d && (f || 0 === f) && u && (u = Q(t, a, u, c), "%" === d ? (u /= Q(t, a, 100, "%") / 100, e.strictUnits !== !0 && (_ = u + "%")) : "em" === d ? u /= Q(t, a, 1, "em") : "px" !== d && (f = Q(t, a, f, d), d = "px"), g && (f || 0 === f) && (p = f + u + d)), g && (f += u), !u && 0 !== u || !f && 0 !== f ? void 0 !== v[a] && (p || "NaN" != p + "" && null != p) ? (i = new _e(v, a, f || u || 0, 0, i, -1, a, !1, 0, _, p), i.xs0 = "none" !== p || "display" !== a && -1 === a.indexOf("Style") ? p : _) : U("invalid " + a + " tween value: " + e[a]) : (i = new _e(v, a, u, f - u, i, 0, a, h !== !1 && ("px" === d || "zIndex" === a), 0, _, p), i.xs0 = d)) : i = pe(v, a, _, p, !0, null, i, 0, n)), n && i && !i.plugin && (i.plugin = n);
      return i;
    }, l.setRatio = function (t) {
      var e,
        i,
        r,
        s = this._firstPT,
        n = 1e-6;
      if (1 !== t || this._tween._time !== this._tween._duration && 0 !== this._tween._time) {
        if (t || this._tween._time !== this._tween._duration && 0 !== this._tween._time || this._tween._rawPrevTime === -1e-6) for (; s;) {
          if (e = s.c * t + s.s, s.r ? e = Math.round(e) : n > e && e > -n && (e = 0), s.type) {
            if (1 === s.type) {
              if (r = s.l, 2 === r) s.t[s.p] = s.xs0 + e + s.xs1 + s.xn1 + s.xs2;else if (3 === r) s.t[s.p] = s.xs0 + e + s.xs1 + s.xn1 + s.xs2 + s.xn2 + s.xs3;else if (4 === r) s.t[s.p] = s.xs0 + e + s.xs1 + s.xn1 + s.xs2 + s.xn2 + s.xs3 + s.xn3 + s.xs4;else if (5 === r) s.t[s.p] = s.xs0 + e + s.xs1 + s.xn1 + s.xs2 + s.xn2 + s.xs3 + s.xn3 + s.xs4 + s.xn4 + s.xs5;else {
                for (i = s.xs0 + e + s.xs1, r = 1; s.l > r; r++) i += s["xn" + r] + s["xs" + (r + 1)];
                s.t[s.p] = i;
              }
            } else -1 === s.type ? s.t[s.p] = s.xs0 : s.setRatio && s.setRatio(t);
          } else s.t[s.p] = e + s.xs0;
          s = s._next;
        } else for (; s;) 2 !== s.type ? s.t[s.p] = s.b : s.setRatio(t), s = s._next;
      } else for (; s;) 2 !== s.type ? s.t[s.p] = s.e : s.setRatio(t), s = s._next;
    }, l._enableTransforms = function (t) {
      this._transformType = t || 3 === this._transformType ? 3 : 2, this._transform = this._transform || Pe(this._target, s, !0);
    };
    var Me = function () {
      this.t[this.p] = this.e, this.data._linkCSSP(this, this._next, null, !0);
    };
    l._addLazySet = function (t, e, i) {
      var r = this._firstPT = new _e(t, e, 0, 0, this._firstPT, 2);
      r.e = i, r.setRatio = Me, r.data = this;
    }, l._linkCSSP = function (t, e, i, r) {
      return t && (e && (e._prev = t), t._next && (t._next._prev = t._prev), t._prev ? t._prev._next = t._next : this._firstPT === t && (this._firstPT = t._next, r = !0), i ? i._next = t : r || null !== this._firstPT || (this._firstPT = t), t._next = e, t._prev = i), t;
    }, l._kill = function (e) {
      var i,
        r,
        s,
        n = e;
      if (e.autoAlpha || e.alpha) {
        n = {};
        for (r in e) n[r] = e[r];
        n.opacity = 1, n.autoAlpha && (n.visibility = 1);
      }
      return e.className && (i = this._classNamePT) && (s = i.xfirst, s && s._prev ? this._linkCSSP(s._prev, i._next, s._prev._prev) : s === this._firstPT && (this._firstPT = i._next), i._next && this._linkCSSP(i._next, i._next._next, s._prev), this._classNamePT = null), t.prototype._kill.call(this, n);
    };
    var Le = function (t, e, i) {
      var r, s, n, a;
      if (t.slice) for (s = t.length; --s > -1;) Le(t[s], e, i);else for (r = t.childNodes, s = r.length; --s > -1;) n = r[s], a = n.type, n.style && (e.push($(n)), i && i.push(n)), 1 !== a && 9 !== a && 11 !== a || !n.childNodes.length || Le(n, e, i);
    };
    return a.cascadeTo = function (t, i, r) {
      var s,
        n,
        a,
        o = e.to(t, i, r),
        l = [o],
        h = [],
        u = [],
        f = [],
        _ = e._internals.reservedProps;
      for (t = o._targets || o.target, Le(t, h, f), o.render(i, !0), Le(t, u), o.render(0, !0), o._enabled(!0), s = f.length; --s > -1;) if (n = G(f[s], h[s], u[s]), n.firstMPT) {
        n = n.difs;
        for (a in r) _[a] && (n[a] = r[a]);
        l.push(e.to(f[s], i, n));
      }
      return l;
    }, t.activate([a]), a;
  }, !0);
}), window._gsDefine && window._gsQueue.pop()();

},{}],18:[function(require,module,exports){
"use strict";

/*!
 * VERSION: 1.7.3
 * DATE: 2014-01-14
 * UPDATES AND DOCS AT: http://www.greensock.com
 *
 * @license Copyright (c) 2008-2014, GreenSock. All rights reserved.
 * This work is subject to the terms at http://www.greensock.com/terms_of_use.html or for
 * Club GreenSock members, the software agreement that was issued with your membership.
 * 
 * @author: Jack Doyle, jack@greensock.com
 **/
(window._gsQueue || (window._gsQueue = [])).push(function () {
  "use strict";

  var t = document.documentElement,
    e = window,
    i = function (i, s) {
      var r = "x" === s ? "Width" : "Height",
        n = "scroll" + r,
        a = "client" + r,
        o = document.body;
      return i === e || i === t || i === o ? Math.max(t[n], o[n]) - (e["inner" + r] || Math.max(t[a], o[a])) : i[n] - i["offset" + r];
    },
    s = window._gsDefine.plugin({
      propName: "scrollTo",
      API: 2,
      version: "1.7.3",
      init: function (t, s, r) {
        return this._wdw = t === e, this._target = t, this._tween = r, "object" != typeof s && (s = {
          y: s
        }), this._autoKill = s.autoKill !== !1, this.x = this.xPrev = this.getX(), this.y = this.yPrev = this.getY(), null != s.x ? (this._addTween(this, "x", this.x, "max" === s.x ? i(t, "x") : s.x, "scrollTo_x", !0), this._overwriteProps.push("scrollTo_x")) : this.skipX = !0, null != s.y ? (this._addTween(this, "y", this.y, "max" === s.y ? i(t, "y") : s.y, "scrollTo_y", !0), this._overwriteProps.push("scrollTo_y")) : this.skipY = !0, !0;
      },
      set: function (t) {
        this._super.setRatio.call(this, t);
        var s = this._wdw || !this.skipX ? this.getX() : this.xPrev,
          r = this._wdw || !this.skipY ? this.getY() : this.yPrev,
          n = r - this.yPrev,
          a = s - this.xPrev;
        this._autoKill && (!this.skipX && (a > 7 || -7 > a) && i(this._target, "x") > s && (this.skipX = !0), !this.skipY && (n > 7 || -7 > n) && i(this._target, "y") > r && (this.skipY = !0), this.skipX && this.skipY && this._tween.kill()), this._wdw ? e.scrollTo(this.skipX ? s : this.x, this.skipY ? r : this.y) : (this.skipY || (this._target.scrollTop = this.y), this.skipX || (this._target.scrollLeft = this.x)), this.xPrev = this.x, this.yPrev = this.y;
      }
    }),
    r = s.prototype;
  s.max = i, r.getX = function () {
    return this._wdw ? null != e.pageXOffset ? e.pageXOffset : null != t.scrollLeft ? t.scrollLeft : document.body.scrollLeft : this._target.scrollLeft;
  }, r.getY = function () {
    return this._wdw ? null != e.pageYOffset ? e.pageYOffset : null != t.scrollTop ? t.scrollTop : document.body.scrollTop : this._target.scrollTop;
  }, r._kill = function (t) {
    return t.scrollTo_x && (this.skipX = !0), t.scrollTo_y && (this.skipY = !0), this._super._kill.call(this, t);
  };
}), window._gsDefine && window._gsQueue.pop()();

},{}]},{},[3])
