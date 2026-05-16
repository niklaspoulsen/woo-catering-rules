jQuery(function ($) {
  function formatDisplayDate(nativeDate) {
    if (!nativeDate) return '';
    const parts = nativeDate.split('-');
    if (parts.length !== 3) return '';
    return parts[2] + '/' + parts[1] + '/' + parts[0];
  }

  function parseDateString(str) {
    if (!str) return null;
    const p = str.split('-');
    if (p.length !== 3) return null;
    return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
  }

  function roundUpQuarter(t) {
    const p = t.split(':');
    let total = parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
    total = Math.ceil(total / 15) * 15;
    if (total > 23 * 60 + 45) total = 23 * 60 + 45;
    return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
  }

  function roundDownQuarter(t) {
    const p = t.split(':');
    let total = parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
    total = Math.floor(total / 15) * 15;
    if (total < 0) total = 0;
    return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
  }

  function quarterValuesBetween(min, max) {
    const vals = [];
    let current = roundUpQuarter(min);

    while (current <= max) {
      vals.push(current);
      const p = current.split(':');
      const total = parseInt(p[0], 10) * 60 + parseInt(p[1], 10) + 15;
      if (total > 23 * 60 + 45) break;
      current = String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
    }

    return vals;
  }

  function getMinDate() {
    if (typeof wcrRules === 'undefined') return '';
    return wcrRules.minDate || wcrRules.today || '';
  }

  function getProductRules() {
    if (typeof wcrRules === 'undefined' || !wcrRules.productRules) {
      return {
        allowedWeekdays: [],
        allowedDates: [],
        blockedDates: []
      };
    }

    return {
      allowedWeekdays: Array.isArray(wcrRules.productRules.allowedWeekdays) ? wcrRules.productRules.allowedWeekdays : [],
      allowedDates: Array.isArray(wcrRules.productRules.allowedDates) ? wcrRules.productRules.allowedDates : [],
      blockedDates: Array.isArray(wcrRules.productRules.blockedDates) ? wcrRules.productRules.blockedDates : []
    };
  }

  function isClosedDate(nativeDate) {
    if (!nativeDate || typeof wcrRules === 'undefined') return false;

    if (wcrRules.closedToday === 'yes' && nativeDate === wcrRules.today) {
      return true;
    }

    return Array.isArray(wcrRules.closedDates) && wcrRules.closedDates.indexOf(nativeDate) !== -1;
  }

  function getWeekdayRow(nativeDate) {
    const dt = parseDateString(nativeDate);
    if (!dt || typeof wcrRules === 'undefined') return null;
    const weekday = dt.getDay();
    return wcrRules.storeHours && wcrRules.storeHours[weekday] ? wcrRules.storeHours[weekday] : null;
  }

  function isAllowedByProductRules(nativeDate) {
    if (!nativeDate) return true;

    const rules = getProductRules();
    const dt = parseDateString(nativeDate);
    if (!dt) return true;

    const weekday = String(dt.getDay());

    if (rules.allowedDates.length && rules.allowedDates.indexOf(nativeDate) === -1) {
      return false;
    }

    if (rules.blockedDates.length && rules.blockedDates.indexOf(nativeDate) !== -1) {
      return false;
    }

    if (rules.allowedWeekdays.length && rules.allowedWeekdays.indexOf(weekday) === -1) {
      return false;
    }

    return true;
  }

  function buildTimeOptions(nativeDate, selectedValue) {
    let html = '<option value="">Vælg tidspunkt</option>';
    if (!nativeDate || typeof wcrRules === 'undefined') return html;
    if (isClosedDate(nativeDate)) return html;
    if (!isAllowedByProductRules(nativeDate)) return html;

    const row = getWeekdayRow(nativeDate) || { open: '00:00', close: '23:45', closed: 'no' };
    if (row.closed === 'yes') return html;

    const min = roundUpQuarter(row.open || '00:00');
    const max = roundDownQuarter(row.close || '23:45');

    quarterValuesBetween(min, max).forEach(function (v) {
      const selected = selectedValue === v ? ' selected' : '';
      html += '<option value="' + v + '"' + selected + '>' + v + '</option>';
    });

    return html;
  }

  function applyMinDates() {
    if (typeof wcrRules === 'undefined') return;
    const minDate = getMinDate();
    $('.wcr-delivery-date-native').attr('min', minDate);

    const rules = getProductRules();
    if (rules.allowedDates.length) {
      const sorted = rules.allowedDates.slice().sort();
      const minAllowed = sorted[0];
      const maxAllowed = sorted[sorted.length - 1];
      if (minAllowed) {
        $('.wcr-delivery-date-native').attr('min', minAllowed > minDate ? minAllowed : minDate);
      }
      if (maxAllowed) {
        $('.wcr-delivery-date-native').attr('max', maxAllowed);
      }
    }
  }

  function getActiveNativeDate() {
    let value = '';
    $('.wcr-delivery-date-native').each(function () {
      const current = ($(this).val() || '').trim();
      if (current) {
        value = current;
        return false;
      }
    });
    return value;
  }

  function getActiveTimeValue() {
    let value = '';
    $('[name="wcr_delivery_time"]').each(function () {
      const current = ($(this).val() || '').trim();
      if (current) {
        value = current;
        return false;
      }
    });
    return value;
  }

  function ensureProductFormFields() {
    $('form.cart').each(function () {
      const $form = $(this);

      if (!$form.find('input[name="wcr_delivery_date"][type="hidden"]').length) {
        $form.append('<input type="hidden" name="wcr_delivery_date" class="wcr-hidden-delivery-date" value="">');
      }

      if (!$form.find('input[name="wcr_delivery_time"][type="hidden"]').length) {
        $form.append('<input type="hidden" name="wcr_delivery_time" class="wcr-hidden-delivery-time" value="">');
      }

      if (!$form.find('input[name="wcr_nonce"][type="hidden"]').length && typeof wcrRules !== 'undefined' && wcrRules.nonce) {
        $form.append('<input type="hidden" name="wcr_nonce" class="wcr-hidden-nonce" value="' + String(wcrRules.nonce).replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '">');
      }
    });
  }

  function syncProductFormFields(nativeDate, timeValue) {
    ensureProductFormFields();

    $('form.cart').each(function () {
      const $form = $(this);
      $form.find('input.wcr-hidden-delivery-date').val(nativeDate || '');
      $form.find('input.wcr-hidden-delivery-time').val(timeValue || '');
    });
  }

  function syncAll(nativeDate, timeValue) {
    $('.wcr-delivery-date-native').val(nativeDate);

    $('[name="wcr_delivery_time"]').each(function () {
      $(this).html(buildTimeOptions(nativeDate, timeValue));
      if (timeValue) {
        $(this).val(timeValue);
      }
    });

    syncProductFormFields(nativeDate, timeValue);
    updateProductBuilderLock();

    const displayDate = formatDisplayDate(nativeDate);
    if ($('#wcr-open-modal small').length) {
      $('#wcr-open-modal small').text((displayDate || '') + (timeValue ? ' ' + timeValue : ''));
    }
  }

  function validateNativeDate($input) {
    if (typeof wcrRules === 'undefined') return;

    const nativeDate = $input.val();
    if (!nativeDate) return;

    const minDate = getMinDate();

    if (nativeDate < minDate || isClosedDate(nativeDate)) {
      $input.val('');
      return;
    }

    const row = getWeekdayRow(nativeDate);
    if (row && row.closed === 'yes') {
      $input.val('');
      return;
    }

    if (!isAllowedByProductRules(nativeDate)) {
      $input.val('');
    }
  }

  function openModal() {
    $('#wcr-popup').addClass('is-open');
    $('body').addClass('wcr-modal-open');
    applyMinDates();
  }

  function closeModal() {
    $('#wcr-popup').removeClass('is-open');
    $('body').removeClass('wcr-modal-open');
  }

  function hasValidSelection() {
    return !!(getActiveNativeDate() && getActiveTimeValue());
  }


  function productBuilderLockEnabled() {
    return typeof wcrRules !== 'undefined' && !!wcrRules.isProduct && wcrRules.requireSelectionBeforeBuild !== false;
  }

  function getProductBuilderLockMessage() {
    return 'Vælg dato og tidspunkt, før du sammensætter din menu.';
  }

  function ensureProductBuilderLockNotice($form) {
    let $notice = $form.prev('.wcr-product-builder-lock');

    if (!$notice.length) {
      $notice = $(
        '<div class="wcr-product-builder-lock" role="status">' +
          '<div class="wcr-product-builder-lock__title">Vælg levering først</div>' +
          '<div class="wcr-product-builder-lock__text"></div>' +
          '<button type="button" class="button wcr-product-builder-lock__button">Vælg dato og tidspunkt</button>' +
        '</div>'
      );

      $form.before($notice);
    }

    $notice.find('.wcr-product-builder-lock__text').text(getProductBuilderLockMessage());
    return $notice;
  }

  function getLockableProductControls($form) {
    return $form
      .find('input, select, textarea, button')
      .not('[type="hidden"]')
      .not('[name="wcr_delivery_date"]')
      .not('[name="wcr_delivery_time"]')
      .not('[name="wcr_nonce"]')
      .not('.wcr-product-builder-lock__button')
      .not('#wcr-open-modal')
      .not('[data-wcr-open-modal="1"]');
  }

  function updateProductBuilderLock() {
    if (!productBuilderLockEnabled()) return;

    const isUnlocked = hasValidSelection();

    $('form.cart').each(function () {
      const $form = $(this);
      const $notice = ensureProductBuilderLockNotice($form);
      const $controls = getLockableProductControls($form);

      if (isUnlocked) {
        $form.removeClass('wcr-menu-builder-is-locked');
        $notice.hide();

        $controls.each(function () {
          const $control = $(this);
          if ($control.attr('data-wcr-locked') === '1') {
            $control.prop('disabled', false).removeAttr('data-wcr-locked');
          }
        });

        return;
      }

      $form.addClass('wcr-menu-builder-is-locked');
      $notice.show();

      $controls.each(function () {
        const $control = $(this);
        if (!$control.prop('disabled')) {
          $control.attr('data-wcr-locked', '1').prop('disabled', true);
        }
      });
    });
  }

  function initAdminDatepickers($scope) {
    if (!$.fn.datepicker) return;

    const $target = $scope && $scope.length ? $scope.find('.wcr-datepicker') : $('.wcr-datepicker');

    $target.each(function () {
      const $input = $(this);

      if ($input.hasClass('hasDatepicker')) {
        return;
      }

      $input.datepicker({
        dateFormat: 'dd/mm/yy',
        firstDay: 1
      });
    });
  }

  function getNextClosedDateIndex() {
    let maxIndex = -1;

    $('#wcr-closed-dates-list')
      .find('input[name^="wcr_closed_dates["]')
      .each(function () {
        const name = $(this).attr('name') || '';
        const match = name.match(/wcr_closed_dates\[(\d+)\]/);

        if (match && typeof match[1] !== 'undefined') {
          maxIndex = Math.max(maxIndex, parseInt(match[1], 10));
        }
      });

    return maxIndex + 1;
  }

  function bindAdminClosedDates() {
    initAdminDatepickers();

    $(document).on('click', '#wcr-add-date-row', function (e) {
      e.preventDefault();

      const $list = $('#wcr-closed-dates-list');
      const template = $('#wcr-date-row-template').html();

      if (!$list.length || !template) {
        return;
      }

      const nextIndex = getNextClosedDateIndex();
      const html = template.replace(/__INDEX__/g, String(nextIndex));
      const $row = $(html);

      $list.append($row);
      initAdminDatepickers($row);
      $row.find('.wcr-date-input').trigger('focus');
    });

    $(document).on('click', '.wcr-remove-date', function (e) {
      e.preventDefault();

      const $list = $('#wcr-closed-dates-list');
      const $rows = $list.find('.wcr-date-row');
      const $row = $(this).closest('.wcr-date-row');

      if ($rows.length <= 1) {
        $row.find('input[type="text"]').val('');
        $row.find('input[type="checkbox"]').prop('checked', true);
        return;
      }

      $row.remove();
    });

    $(document).on('change', 'input[name*="[pickup_only]"]', function () {
      if ($(this).is(':checked')) {
        $(this).closest('tr').find('input[name*="[closed]"]').prop('checked', false);
      }
    });

    $(document).on('change', 'input[name*="[closed]"]', function () {
      if ($(this).is(':checked')) {
        $(this).closest('tr').find('input[name*="[pickup_only]"]').prop('checked', false);
      }
    });
  }

  if (typeof wcrAdmin !== 'undefined' && wcrAdmin.isAdmin) {
    bindAdminClosedDates();
    return;
  }

  applyMinDates();
  ensureProductFormFields();
  syncProductFormFields(getActiveNativeDate(), getActiveTimeValue());
  updateProductBuilderLock();

  $(document).on('change', '.wcr-delivery-date-native', function () {
    const $input = $(this);
    validateNativeDate($input);

    const nativeDate = $input.val();
    const $wrap = $input.closest('form, .wcr-box');
    const $time = $wrap.find('[name="wcr_delivery_time"]').first();

    if ($time.length) {
      const currentTime = $time.val();
      $time.html(buildTimeOptions(nativeDate, currentTime));

      let newTime = currentTime;
      if (newTime && !$time.find('option[value="' + newTime + '"]').length) {
        newTime = '';
        $time.val('');
      } else if (newTime) {
        $time.val(newTime);
      }

      syncAll(nativeDate, newTime);
    } else {
      syncProductFormFields(nativeDate, '');
    }
  });

  $(document).on('change', '[name="wcr_delivery_time"]', function () {
    const $wrap = $(this).closest('form, .wcr-box');
    const nativeDate = $wrap.find('.wcr-delivery-date-native').first().val();
    syncAll(nativeDate, $(this).val());
  });

  $(document).on('click', '.wcr-overlay, .wcr-close', function () {
    closeModal();
  });

  $(document).on('click', '#wcr-open-modal, [data-wcr-open-modal="1"], .wcr-product-builder-lock__button', function () {
    openModal();
  });

  function shouldAutoOpenPopup() {
    if (typeof wcrRules === 'undefined') {
      return false;
    }

    if (wcrRules.forcePopup) {
      return true;
    }

    // On product pages/menu builders we must not trust localStorage alone.
    // Mobile browsers can keep localStorage from an earlier visit even when the
    // WooCommerce session no longer has a valid date/time. In that case the
    // popup must open again before the customer can build the menu.
    if (productBuilderLockEnabled() && !hasValidSelection()) {
      return true;
    }

    let saved = false;
    try {
      saved = localStorage.getItem('wcr_delivery_saved') === 'yes';
    } catch (e) {}

    if (wcrRules.saved === 'yes') {
      saved = true;
    }

    return !saved;
  }

  if (shouldAutoOpenPopup()) {
    setTimeout(function () {
      openModal();
    }, 150);
  }

  // iOS/Safari can restore pages from bfcache without running ready again.
  // Re-check when the page is shown, so the product stays locked and popup opens
  // if date/time is still missing.
  window.addEventListener('pageshow', function () {
    updateProductBuilderLock();

    if (shouldAutoOpenPopup()) {
      setTimeout(function () {
        openModal();
      }, 150);
    }
  });

  $(document).on('submit', '.wcr-form, .wcr-box form, form:has(.wcr-box)', function () {
    try {
      localStorage.setItem('wcr_delivery_saved', 'yes');
    } catch (e) {}
  });

  $(document).on('click', '.single_add_to_cart_button', function () {
    syncProductFormFields(getActiveNativeDate(), getActiveTimeValue());
  updateProductBuilderLock();
  });

  $(document).on('submit', 'form.cart', function (e) {
    const nativeDate = getActiveNativeDate();
    const timeValue = getActiveTimeValue();

    syncProductFormFields(nativeDate, timeValue);

    if (!nativeDate || !timeValue) {
      e.preventDefault();
      updateProductBuilderLock();
      openModal();
    }
  });
});
