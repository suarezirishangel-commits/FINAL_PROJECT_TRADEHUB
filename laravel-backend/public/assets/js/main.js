// TradeHub storefront behaviour: mobile navigation + qty guards.
(function () {
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('primary-nav');

  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Keep quantity inputs inside the product's available range without relying on the server.
  document.querySelectorAll('input[type="number"][data-max]').forEach(function (input) {
    input.addEventListener('change', function () {
      var max = parseInt(input.dataset.max, 10) || 1;
      var min = parseInt(input.min, 10) || 1;
      var value = parseInt(input.value, 10) || min;
      input.value = Math.min(Math.max(value, min), max);
    });
  });
})();
