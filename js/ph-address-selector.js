/**
 * Philippine Address Selector — NCR cities only
 * Filters cities where region_desc === '13' (National Capital Region),
 * then loads barangays for the selected city.
 *
 * Expects these IDs in the form:
 *   #enroll-city, #enroll-barangay
 *   Hidden text inputs: #enroll-city-text, #enroll-barangay-text
 *
 * JSON files served from /ph-json/
 */

(function ($) {
  'use strict';

  var BASE = (function () {
    var path = window.location.pathname;
    var dir  = path.substring(0, path.lastIndexOf('/') + 1);
    return dir + 'ph-json/';
  })();

  function resetSelect(sel, placeholder) {
    sel.empty().append(
      $('<option>', { value: '', disabled: true, selected: true, text: placeholder })
    );
  }

  $(function () {
    var $city     = $('#enroll-city');
    var $barangay = $('#enroll-barangay');

    if (!$city.length) return; // not on enrollment page

    // Pre-selected values (for repopulation after form error)
    var preCity     = $('#enroll-city-text').val()     || '';
    var preBarangay = $('#enroll-barangay-text').val() || '';

    // ── Load NCR cities on page ready ───────────────────────
    // NCR cities have region_desc === '13'
    $.getJSON(BASE + 'city.json', function (data) {
      var filtered = data
        .filter(function (c) { return c.region_desc === '13'; })
        .sort(function (a, b) { return a.city_name.localeCompare(b.city_name); });

      $.each(filtered, function (i, c) {
        var opt = $('<option>', { value: c.city_code, text: c.city_name });
        if (c.city_name === preCity) opt.prop('selected', true);
        $city.append(opt);
      });

      // If a city was pre-selected, load its barangays immediately
      if (preCity) {
        var selectedCode = $city.find('option:selected').val();
        if (selectedCode) loadBarangays(selectedCode, preBarangay);
      }
    });

    // ── City → Barangay ────────────────────────────────────
    $city.on('change', function () {
      var code = $(this).val();
      var text = $(this).find('option:selected').text();
      $('#enroll-city-text').val(text);
      resetSelect($barangay, 'Select Barangay');
      $('#enroll-barangay-text').val('');
      $(this).css({ borderColor: '', boxShadow: '' });
      $(this).closest('.ef-field').find('.ef-error, .field-error').remove();
      if (code) loadBarangays(code, '');
    });

    function loadBarangays(cityCode, preSelect) {
      $.getJSON(BASE + 'barangay.json', function (data) {
        var filtered = data
          .filter(function (b) { return b.city_code == cityCode; })
          .sort(function (a, b) { return a.brgy_name.localeCompare(b.brgy_name); });

        $.each(filtered, function (i, b) {
          var opt = $('<option>', { value: b.brgy_code, text: b.brgy_name });
          if (b.brgy_name === preSelect) opt.prop('selected', true);
          $barangay.append(opt);
        });

        if (preSelect) {
          $('#enroll-barangay-text').val(preSelect);
        }
      });
    }

    // ── Barangay text capture ──────────────────────────────
    $barangay.on('change', function () {
      var text = $(this).find('option:selected').text();
      $('#enroll-barangay-text').val(text);
      $(this).css({ borderColor: '', boxShadow: '' });
      $(this).closest('.ef-field').find('.ef-error, .field-error').remove();
    });
  });

})(jQuery);
