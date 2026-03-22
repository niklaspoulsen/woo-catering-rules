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

  function parseDotOrSlashDate(str) {
    if (!str) return '';
    let m = String(str).trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (m) return `${m[3]}-${m[2]}-${m[1]}`;

    m = String(str).trim().match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
    if (m) return `${m[3]}-${m[2]}-${m[1]}`;

    m = String(str).trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (m) return str;

    return '';
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

  function getMinDate() {
    return (typeof wcrRules !== 'undefined' && wcrRules.minDate) ? wcrRules.minDate : (wcrRules.today || '');
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

  function isDateSelectable(nativeDate) {
    if (!nativeDate) return false;

    const minDate = getMinDate();
    if (minDate && nativeDate < minDate) {
      return false;
    }

    if (isClosedDate(nativeDate)) {
      return false;
    }

    const row = getWeekdayRow(nativeDate);
    if (row && row.closed === 'yes') {
      return false;
    }

    if (!isAllowedByProductRules(nativeDate)) {
      return false;
    }

    return true;
  }

  function buildTimeOptions(nativeDate, selectedValue) {
    let html = '<option value="">Vælg tidspunkt</option>';
    if (!nativeDate || typeof wcrRules === 'undefined') return html;
    if (!isDateSelectable(nativeDate)) return html;

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
      const maxAllowed = sorted[sorted.length - 1];
      if (maxAllowed) {
        $('.wcr-delivery-date-native').attr('max', maxAllowed);
      }
    }
  }

  function setFieldValidity($input) {
    const nativeDate = $input.val();
    if (!nativeDate) {
      $input[0].setCustomValidity('');
      return;
    }

    if (!isAllowedByProductRules(nativeDate)) {
      $input[0].setCustomValidity('Den valgte dato er ikke tilladt for dette produkt.');
      return;
    }

    if (!isDateSelectable(nativeDate)) {
      $input[0].setCustomValidity('Den valgte dato kan ikke bestilles.');
      return;
    }

    $input[0].setCustomValidity('');
  }

  function syncAll(nativeDate, timeValue) {
    $('.wcr-delivery-date-native').val(nativeDate);

    $('[name="wcr_delivery_time"]').each(function () {
      $(this).html(buildTimeOptions(nativeDate, timeValue));
      if (timeValue) {
        $(this).val(timeValue);
      }
    });

    const displayDate = formatDisplayDate(nativeDate);
    if ($('#wcr-open-modal small').length) {
      $('#wcr-open-modal small').text((displayDate || '') + (timeValue ? ' ' + timeValue : ''));
    }
  }

  function validateNativeDate($input) {
    if (typeof wcrRules === 'undefined') return;

    let nativeDate = $input.val();
    if (!nativeDate) return;

    nativeDate = parseDotOrSlashDate(nativeDate) || nativeDate;

    if (!isDateSelectable(nativeDate)) {
      $input.val('');
      setFieldValidity($input);
      return;
    }

    $input.val(nativeDate);
    setFieldValidity($input);
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

  applyMinDates();

  if (typeof wcrRules !== 'undefined' && wcrRules.savedDate) {
    syncAll(wcrRules.savedDate, wcrRules.savedTime || '');
  }

  $(document).on('input change', '.wcr-delivery-date-native', function () {
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
    }
  });

  $(document).on('focus', '.wcr-delivery-date-native', function () {
    applyMinDates();
    setFieldValidity($(this));
  });

  $(document).on('change', '[name="wcr_delivery_time"]', function () {
    const $wrap = $(this).closest('form, .wcr-box');
    const nativeDate = $wrap.find('.wcr-delivery-date-native').first().val();
    syncAll(nativeDate, $(this).val());
  });

  $(document).on('click', '.wcr-overlay, .wcr-close', function () {
    closeModal();
  });

  $(document).on('click', '#wcr-open-modal, [data-wcr-open-modal="1"]', function () {
    openModal();
  });

  if (typeof wcrRules !== 'undefined') {
    let saved = false;
    try {
      saved = localStorage.getItem('wcr_delivery_saved') === 'yes';
    } catch (e) {}

    if (wcrRules.saved === 'yes') saved = true;

    if (!saved || wcrRules.forcePopup) {
      openModal();
    }
  }

  $(document).on('submit', '.wcr-form, .wcr-box form, form:has(.wcr-box)', function () {
    const $date = $(this).find('.wcr-delivery-date-native').first();
    if ($date.length) {
      validateNativeDate($date);
      if (!$date.val()) {
        return false;
      }
    }

    try {
      localStorage.setItem('wcr_delivery_saved', 'yes');
    } catch (e) {}
  });
});
