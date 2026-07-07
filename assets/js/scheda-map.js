(function () {
	document.addEventListener('DOMContentLoaded', function () {
		var mapEl = document.getElementById('bpgm-scheda-map'); if (!mapEl || typeof L === 'undefined' || typeof bpgmScheda === 'undefined') return;
		var map = L.map('bpgm-scheda-map', { zoomControl: true, scrollWheelZoom: false }).setView([bpgmScheda.lat, bpgmScheda.lng], 15);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
		var opts = {};
		if (bpgmScheda.markerIcon && bpgmScheda.markerIcon.trim() !== '') {
			opts.icon = L.icon({ iconUrl: bpgmScheda.markerIcon, iconSize: [32, 32], iconAnchor: [16, 32], popupAnchor: [0, -32] });
		}
		L.marker([bpgmScheda.lat, bpgmScheda.lng], opts).addTo(map).bindPopup(bpgmScheda.name).openPopup();
	});
})();
