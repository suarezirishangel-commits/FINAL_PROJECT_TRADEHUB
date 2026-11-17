(function () {
  'use strict';

  var PHILIPPINES_BOUNDS = [[4.2, 115.5], [21.8, 128]];
  var POLL_INTERVAL = 10000;

  function isInPhilippines(latitude, longitude) {
    return Number.isFinite(latitude) &&
      Number.isFinite(longitude) &&
      latitude >= PHILIPPINES_BOUNDS[0][0] &&
      latitude <= PHILIPPINES_BOUNDS[1][0] &&
      longitude >= PHILIPPINES_BOUNDS[0][1] &&
      longitude <= PHILIPPINES_BOUNDS[1][1];
  }

  function timestampLabel(value) {
    if (!value) return '';
    var date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
  }

  function initializeMap(panel) {
    var canvas = panel.querySelector('[data-map-canvas]');
    var status = panel.querySelector('[data-map-status]');
    if (!canvas || !status) return;

    var map = null;
    var marker = null;
    var updateMarker = function () { return false; };
    if (window.L) {
      map = window.L.map(canvas, {
        minZoom: 5,
        maxBounds: PHILIPPINES_BOUNDS,
        maxBoundsViscosity: 1
      });

      window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
      }).addTo(map);
      map.fitBounds(PHILIPPINES_BOUNDS, { padding: [8, 8], maxZoom: 5 });

      updateMarker = function (latitude, longitude, center) {
        if (!isInPhilippines(latitude, longitude)) return false;
        var position = [latitude, longitude];
        if (!marker) {
          marker = window.L.marker(position).addTo(map);
        } else {
          marker.setLatLng(position);
        }
        if (center) {
          map.setView(position, 13);
        } else {
          map.panInside(position, { padding: [24, 24] });
        }
        return true;
      };

      var initialLatitude = Number(panel.dataset.latitude);
      var initialLongitude = Number(panel.dataset.longitude);
      if (panel.dataset.latitude && panel.dataset.longitude &&
          updateMarker(initialLatitude, initialLongitude, true)) {
        var lastUpdated = timestampLabel(panel.dataset.locationUpdatedAt);
        status.textContent = lastUpdated
          ? 'Last rider location update: ' + lastUpdated + '.'
          : 'Showing the rider’s last reported location.';
      }
    } else {
      status.textContent = 'The map could not be loaded. GPS tracking will still try to update.';
    }

    if (panel.dataset.mode === 'rider') {
      initializeRiderSharing(panel, updateMarker, status, Boolean(map));
    } else {
      initializeCustomerTracking(panel, updateMarker, status, Boolean(map));
    }

    if (map) {
      window.setTimeout(function () {
        map.invalidateSize();
      }, 100);
    }
  }

  function initializeRiderSharing(panel, updateMarker, mapStatus, mapAvailable) {
    var button = panel.querySelector('[data-gps-toggle]');
    var gpsStatus = panel.querySelector('[data-gps-status]');
    if (!button || !gpsStatus || button.disabled) return;

    var watchId = null;
    var lastSentAt = 0;
    var endpoint = panel.dataset.endpoint;
    var reference = panel.dataset.reference;

    function stopSharing(message) {
      if (watchId !== null) {
        window.navigator.geolocation.clearWatch(watchId);
        watchId = null;
      }
      button.textContent = 'Start live GPS';
      gpsStatus.textContent = message;
    }

    function sendLocation(latitude, longitude) {
      var body = new URLSearchParams({
        reference: reference,
        latitude: String(latitude),
        longitude: String(longitude)
      });

      window.fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
          'X-CSRF-TOKEN': panel.dataset.csrfToken
        },
        body: body.toString()
      }).then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok) {
            throw new Error(data.message || data.error || 'Location could not be shared.');
          }
          updateMarker(latitude, longitude, false);
          var updated = timestampLabel(data.updated_at);
          gpsStatus.textContent = updated ? 'Live location shared at ' + updated + '.' : 'Live location shared.';
          mapStatus.textContent = mapAvailable
            ? 'Your location is shared with this order’s customer.'
            : 'Your GPS location is shared, but the map could not be loaded.';
        });
      }).catch(function (error) {
        gpsStatus.textContent = error.message || 'Unable to share your location. Please try again.';
      });
    }

    button.addEventListener('click', function () {
      if (watchId !== null) {
        stopSharing('Location sharing is off.');
        return;
      }
      if (!window.navigator.geolocation) {
        gpsStatus.textContent = 'This browser does not support GPS location sharing.';
        return;
      }

      button.textContent = 'Stop live GPS';
      gpsStatus.textContent = 'Requesting location permission…';
      watchId = window.navigator.geolocation.watchPosition(function (position) {
        var latitude = position.coords.latitude;
        var longitude = position.coords.longitude;
        if (!isInPhilippines(latitude, longitude)) {
          gpsStatus.textContent = 'Your GPS position is outside the map area, so it was not shared.';
          return;
        }

        var now = Date.now();
        if (now - lastSentAt < 8000) return;
        lastSentAt = now;
        sendLocation(latitude, longitude);
      }, function (error) {
        stopSharing(error.message || 'Unable to read your GPS location. Check browser location permission.');
      }, {
        enableHighAccuracy: true,
        maximumAge: 5000,
        timeout: 15000
      });
    });

    window.addEventListener('beforeunload', function () {
      if (watchId !== null) window.navigator.geolocation.clearWatch(watchId);
    });
  }

  function initializeCustomerTracking(panel, updateMarker, status, mapAvailable) {
    var endpoint = panel.dataset.endpoint;
    var reference = panel.dataset.reference;
    var currentStatus = panel.dataset.status;

    function refreshLocation() {
      var url = endpoint + '?reference=' + encodeURIComponent(reference);
      window.fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
      }).then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok) {
            throw new Error(data.message || data.error || 'Unable to load delivery location.');
          }
          currentStatus = data.status;
          if (data.latitude !== null && data.longitude !== null) {
            var displayed = updateMarker(Number(data.latitude), Number(data.longitude), false);
            var updated = timestampLabel(data.location_updated_at);
            if (displayed) {
              status.textContent = updated
                ? 'Rider location last updated: ' + updated + '.'
                : 'Rider location received.';
            } else {
              status.textContent = mapAvailable
                ? 'Rider location received, but it is outside the map area.'
                : 'Rider location received, but the map could not be loaded.';
            }
          } else if (currentStatus === 'cancelled') {
            status.textContent = 'This order was cancelled; live location is unavailable.';
          } else if (currentStatus === 'completed') {
            status.textContent = 'Delivery completed. No rider location is currently available.';
          } else {
            status.textContent = 'Waiting for the rider to share a GPS location.';
          }
        });
      }).catch(function () {
        status.textContent = 'Unable to refresh the delivery location. Retrying shortly.';
      });
    }

    refreshLocation();
    window.setInterval(refreshLocation, POLL_INTERVAL);
  }

  document.querySelectorAll('[data-delivery-map]').forEach(initializeMap);
}());
