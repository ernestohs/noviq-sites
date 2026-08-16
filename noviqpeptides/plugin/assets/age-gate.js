(function () {
  var root = document.getElementById('noviq-age-gate');
  if (!root || root.classList.contains('is-dismissed')) {
    return;
  }

  var params = new URLSearchParams(window.location.search);
  if (params.get('noviq_age_refused') === '1') {
    var refused = root.querySelector('.noviq-age-gate__refused');
    var form = root.querySelector('.noviq-age-gate__form');
    if (refused) {
      refused.hidden = false;
    }
    if (form) {
      form.hidden = true;
    }
  }

  // Cookie present for this copy version: dismiss without waiting for PHP cookie parse edge cases.
  var version = root.getAttribute('data-version') || '1';
  var match = document.cookie.match(/(?:^|; )noviq_age_ok=([^;]*)/);
  if (match) {
    var value = decodeURIComponent(match[1]);
    if (value === version + ':1') {
      root.classList.add('is-dismissed');
      root.hidden = true;
    }
  }
})();
