(function () {
  document.querySelectorAll('[data-order-map]').forEach(function (panel) {
    var status = panel.querySelector('[data-map-status]');
    var canvas = panel.querySelector('[data-map-canvas]');
    var map = null;
    var marker = null;
    var timer = null;
    var endpoint = new URL(panel.dataset.endpoint, window.location.href);
    endpoint.searchParams.set('ref', panel.dataset.reference);
    var philippinesBounds = [[4.2, 115.5], [21.8, 128]];

    function initializeMap() {
      if (!window.L) {
        status.textContent = 'The map could not load. Check your internet connection and refresh.';
        return false;
      }

      map = L.map(canvas, {
        minZoom: 3,
        maxBounds: philippinesBounds,
        maxBoundsViscosity: 1
      });
      map.fitBounds(philippinesBounds, { padding: [12, 12], maxZoom: 4 });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);
      return true;
    }

    function showLocation(data) {
      if (data.status === 'cancelled') {
        canvas.hidden = true;
        status.textContent = 'Order cancelled. Live tracking has ended.';
        return;
      }

      if (!data.rider_assigned) {
        canvas.hidden = true;
        status.textContent = 'Waiting for a rider to be assigned.';
        return;
      }

      if (data.latitude === null || data.longitude === null) {
        canvas.hidden = data.status === 'completed';
        if (data.status !== 'completed') {
          canvas.hidden = false;
          if (!map && !initializeMap()) return;
          if (map) map.invalidateSize();
        }
        status.textContent = data.status === 'completed'
          ? 'Delivery completed. No rider GPS location was recorded.'
          : data.status === 'out_for_delivery'
            ? 'The rider is on the way. Waiting for the first GPS update. The map shows the Philippines.'
            : 'Rider assigned. The map shows the Philippines until live GPS starts.';
        return;
      }

      var position = [data.latitude, data.longitude];
      var updatedAt = data.location_updated_at
        ? new Date(data.location_updated_at).toLocaleString()
        : 'just now';

      canvas.hidden = false;
      if (!map) {
        if (!initializeMap()) return;
        marker = L.marker(position).addTo(map);
      } else {
        if (!marker) marker = L.marker(position).addTo(map);
        else marker.setLatLng(position);
        map.invalidateSize();
      }

      status.textContent = (data.status === 'completed' ? 'Last rider location: ' : 'Live rider location updated: ') + updatedAt;
    }

    function refresh() {
      fetch(endpoint.toString(), {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' }
      }).then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok) throw new Error(data.error || 'Could not load delivery tracking.');
          showLocation(data);
          if (data.status === 'completed' || data.status === 'cancelled') {
            window.clearInterval(timer);
          }
        });
      }).catch(function () {
        status.textContent = 'Unable to load the rider location. Retrying shortly…';
      });
    }

    refresh();
    timer = window.setInterval(refresh, 10000);
  });
})();
