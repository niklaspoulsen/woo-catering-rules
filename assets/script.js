jQuery(function ($) {
  function initAdminDatepickers() {
    if (typeof wcrAdmin !== 'undefined' && wcrAdmin.isAdmin) {
      $('.wcr-datepicker').each(function () {
        if ($(this).hasClass('hasDatepicker')) return;
        $(this).datepicker({
          dateFormat: 'dd/mm/yy',
          firstDay: 1
        });
      });

      $('#wcr-add-date-row').on('click', function () {
        $('#wcr-closed-dates-list').append($('#wcr-date-row-template').html());
        initAdminDatepickers();
      });

      $(document).on('click', '.wcr-remove-date', function () {
        const rows = $('#wcr-closed-dates-list .wcr-date-row');
        if (rows.length <= 1) {
          rows.find('input').val('');
          return;
        }
        $(this).closest('.wcr-date-row').remove();
      });
    }
  }

  function parseDateString(str) {
    if (!str) return null;
    const p = str.split('/');
    if (p.length !== 3) return null;
    return new Date(parseInt(p[2], 10), parseInt(p[1], 10) - 1, parseInt(p[0], 10));
  }

  function formatDate(d) {
    return String(d.getDate()).padStart(2, '0') + '/' +
      String(d.getMonth() + 1).padStart(2, '0') + '/' +
      d.getFullYear();
  }

  function isClosedDate(dateObj) {
    const ds = formatDate(dateObj);
    if ((wcrRules.closedToday === 'yes') && ds === wcrRules.today) return true;
    return Array.isArray(wcrRules.closedDates) && wcrRules.closedDates.indexOf(ds) !== -1;
  }

  function getWeekdayRow(dateObj) {
    const weekday = dateObj.getDay();
    return wcrRules.storeHours && wcrRules.storeHours[weekday] ? wcrRules.storeHours[weekday] : null;
  }

  function isOpenWeekday(dateObj) {
    const row = getWeekdayRow(dateObj);
    if (!row) return true;
    return row.closed !== 'yes';
  }

  function beforeShowDay(date) {
    if (isClosedDate(date)) return [false, 'wcr-closed-day', 'Lukket dato'];
    if (!isOpenWeekday(date)) return [false, 'wcr-closed-weekday', 'Butikken er lukket'];
    return [true, '', ''];
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

  function buildTimeOptions(dateString, selectedValue) {
    let html = '<option value="">Vælg tidspunkt</option>';
    const dt = parseDateString(dateString);

    if (!dt) return html;

    const row = getWeekdayRow(dt) || { open: '00:00', close: '23:45', closed: 'no' };
    if (row.closed === 'yes') return html;

    const min = roundUpQuarter(row.open || '00:00');
    const max = roundDownQuarter(row.close || '23:45');

    quarterValuesBetween(min, max).forEach(function (v) {
      const selected = selectedValue === v ? ' selected' : '';
      html += '<option value="' + v + '"' + selected + '>' + v + '</option>';
    });

    return html;
  }

  function refreshTimeSelect($dateInput, $timeSelect) {
    const current = $timeSelect.val();
    $timeSelect.html(buildTimeOptions($dateInput.val(), current));
  }

  function initFrontendDatepickers() {
    if (typeof wcrRules === 'undefined') return;

    $('.wcr-datepicker').each(function () {
      if ($(this).hasClass('hasDatepicker')) {
        $(this).datepicker('destroy');
      }

      $(this).datepicker({
        dateFormat: 'dd/mm/yy',
        firstDay: 1,
        minDate: 0,
        beforeShowDay: beforeShowDay,
        onSelect: function () {
          $(this).trigger('change');
        }
      });
    });

    $('.wcr-form, .wcr-box').each(function () {
      const $wrap = $(this);
      const $date = $wrap.find('[name="wcr_delivery_date"]').first();
      const $time = $wrap.find('[name="wcr_delivery_time"]').first();
      if ($date.length && $time.length) {
        refreshTimeSelect($date, $time);
      }
    });
  }

  function openModal() {
    $('#wcr-popup').addClass('is-open');
    $('body').addClass('wcr-modal-open');
  }

  function closeModal() {
    $('#wcr-popup').removeClass('is-open');
    $('body').removeClass('wcr-modal-open');
  }

  initAdminDatepickers();
  initFrontendDatepickers();

  $(document).on('change', '[name="wcr_delivery_date"]', function () {
    const $wrap = $(this).closest('form, .wcr-box');
    const $time = $wrap.find('[name="wcr_delivery_time"]').first();
    if ($time.length) {
      refreshTimeSelect($(this), $time);
    }
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

    if (!saved) {
      openModal();
    }
  }

  $(document).on('submit', '.wcr-form', function () {
    try {
      localStorage.setItem('wcr_delivery_saved', 'yes');
    } catch (e) {}
  });
});
