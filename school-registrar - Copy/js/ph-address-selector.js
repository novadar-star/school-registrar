/**
 * Philippine Address Selector
 * Adapted from Wilfred V. Pine's ph-address-selector
 * Wired to the COJ enrollment form fields.
 *
 * Expects these IDs in the form:
 *   #enroll-region, #enroll-province, #enroll-city, #enroll-barangay
 *   Hidden text inputs: #enroll-region-text, #enroll-province-text,
 *                       #enroll-city-text, #enroll-barangay-text
 *
 * JSON files served from /ph-json/
 */

(function ($) {
  'use strict';

  // Base path — derived from the current page URL so it works in any subfolder
  // e.g. http://localhost/school-registrar/home.php → base = /school-registrar/ph-json/
  var BASE = (function () {
    var path = window.location.pathname;           // e.g. /school-registrar/home.php
    var dir  = path.substring(0, path.lastIndexOf('/') + 1); // e.g. /school-registrar/
    return dir + 'ph-json/';
  })();

  function resetSelect(sel, placeholder) {
    sel.empty().append(
      $('<option>', { value: '', disabled: true, selected: true, text: placeholder })
    );
  }

  // ── Load regions on page ready ──────────────────────────────
  $(function () {
    var $region = $('#enroll-region');
    resetSelect($region, 'Select Region');

    $.getJSON(BASE + 'region.json', function (data) {
      $.each(data, function (i, r) {
        $region.append($('<option>', { value: r.region_code, text: r.region_name }));
      });
    });

    // ── Region → Province ──────────────────────────────────────
    $region.on('change', function () {
      var code = $(this).val();
      var text = $(this).find('option:selected').text();
      $('#enroll-region-text').val(text);

      // Clear downstream
      resetSelect($('#enroll-province'), 'Select Province');
      resetSelect($('#enroll-city'),     'Select City / Municipality');
      resetSelect($('#enroll-barangay'), 'Select Barangay');
      $('#enroll-province-text, #enroll-city-text, #enroll-barangay-text').val('');

      // Remove error styling on region
      $(this).css({ borderColor: '', boxShadow: '' });
      $(this).closest('.ef-field').find('.ef-error, .field-error').remove();

      $.getJSON(BASE + 'province.json', function (data) {
        var filtered = data
          .filter(function (p) { return p.region_code == code; })
          .sort(function (a, b) { return a.province_name.localeCompare(b.province_name); });

        $.each(filtered, function (i, p) {
          $('#enroll-province').append($('<option>', { value: p.province_code, text: p.province_name }));
        });
      });
    });

    // ── Province → City ────────────────────────────────────────
    $('#enroll-province').on('change', function () {
      var code = $(this).val();
      var text = $(this).find('option:selected').text();
      $('#enroll-province-text').val(text);

      resetSelect($('#enroll-city'),     'Select City / Municipality');
      resetSelect($('#enroll-barangay'), 'Select Barangay');
      $('#enroll-city-text, #enroll-barangay-text').val('');

      $(this).css({ borderColor: '', boxShadow: '' });
      $(this).closest('.ef-field').find('.ef-error, .field-error').remove();

      $.getJSON(BASE + 'city.json', function (data) {
        var filtered = data
          .filter(function (c) { return c.province_code == code; })
          .sort(function (a, b) { return a.city_name.localeCompare(b.city_name); });

        $.each(filtered, function (i, c) {
          $('#enroll-city').append($('<option>', { value: c.city_code, text: c.city_name }));
        });
      });
    });

    // ── City → Barangay ────────────────────────────────────────
    $('#enroll-city').on('change', function () {
      var code = $(this).val();
      var text = $(this).find('option:selected').text();
      $('#enroll-city-text').val(text);

      resetSelect($('#enroll-barangay'), 'Select Barangay');
      $('#enroll-barangay-text').val('');

      $(this).css({ borderColor: '', boxShadow: '' });
      $(this).closest('.ef-field').find('.ef-error, .field-error').remove();

      $.getJSON(BASE + 'barangay.json', function (data) {
        var filtered = data
          .filter(function (b) { return b.city_code == code; })
          .sort(function (a, b) { return a.brgy_name.localeCompare(b.brgy_name); });

        $.each(filtered, function (i, b) {
          $('#enroll-barangay').append($('<option>', { value: b.brgy_code, text: b.brgy_name }));
        });
      });
    });

    // ── Barangay text capture ──────────────────────────────────
    $('#enroll-barangay').on('change', function () {
      var text = $(this).find('option:selected').text();
      $('#enroll-barangay-text').val(text);

      $(this).css({ borderColor: '', boxShadow: '' });
      $(this).closest('.ef-field').find('.ef-error, .field-error').remove();
    });
  });

})(jQuery);
