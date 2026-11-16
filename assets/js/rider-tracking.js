(function () {
  var root = document.querySelector('[data-rider-tracking]');
  if (!root) return;

  var activeWatchId = null;
  var activeButton = null;
  var sending = false;
  var lastSentAt = 0;

  function setStatus(button, message) {
    var status = button.closest('.rider-gps-controls').querySelector('[data-gps-status]');
    status.textContent = message;
  }

  function stopSharing() {
    if (activeWatchId !== null) {
      navigator.geolocation.clearWatch(activeWatchId);
      activeWatchId = null;
    }
    if (activeButton) {
      activeButton.textContent = 'Start live GPS';
      activeButton.setAttribute('aria-pressed', 'false');
      activeButton = null;
    }
  }

  root.querySelectorAll('[data-gps-toggle]').forEach(function (button) {
    button.setAttribute('aria-pressed', 'false');
    button.addEventListener('click', function () {
      if (!navigator.geolocation) {
        setStatus(button, 'This browser does not support GPS location.');
        return;
      }

      if (activeButton === button) {
        stopSharing();
        setStatus(button, 'Live GPS sharing stopped. The customer can still see the last update.');
        return;
      }

      stopSharing();
      activeButton = button;
      button.textContent = 'Stop live GPS';
      button.setAttribute('aria-pressed', 'true');
      setStatus(button, 'Requesting location permission…');

      activeWatchId = navigator.geolocation.watchPosition(function (position) {
        var now = Date.now();
        if (sending || now - lastSentAt < 8000) return;

        sending = true;
        setStatus(button, 'GPS received. Sending location…');
        var body = new URLSearchParams({
          csrf_token: root.dataset.csrf,
          reference: button.dataset.reference,
          latitude: String(position.coords.latitude),
          longitude: String(position.coords.longitude)
        });

        fetch(root.dataset.endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
          body: body.toString()
        }).then(function (response) {
          return response.json().then(function (result) {
            if (!response.ok) throw new Error(result.error || 'Could not send GPS location.');
            lastSentAt = Date.now();
            setStatus(button, 'Live GPS sharing is on. Last sent: ' + new Date(result.updated_at).toLocaleTimeString());
          });
        }).catch(function (error) {
          setStatus(button, error.message);
        }).finally(function () {
          sending = false;
        });
      }, function (error) {
        var messages = {
          1: 'Location permission was denied. Allow location access in your browser settings.',
          2: 'The current location is unavailable. Try moving outdoors and retry.',
          3: 'GPS request timed out. Keep the page open and try again.'
        };
        setStatus(button, messages[error.code] || 'Unable to read the device location.');
        stopSharing();
      }, {
        enableHighAccuracy: true,
        maximumAge: 5000,
        timeout: 15000
      });
    });
  });
})();
