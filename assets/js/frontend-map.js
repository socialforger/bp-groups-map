(function () {
	document.addEventListener('DOMContentLoaded', function () {
		var mapEl = document.getElementById('bpgm-map');
		if (!mapEl || typeof L === 'undefined') { return; }

		var PALETTE = ['#2a5a8a', '#c0392b', '#27ae60', '#e67e22', '#8e44ad', '#16a085', '#d35400', '#2c3e50', '#c2185b', '#00838f'];
		var colorCache = {};
		function colorForCategoria(nome) {
			if (!nome) return '#777'; if (colorCache[nome]) return colorCache[nome];
			var hash = 0; for (var i = 0; i < nome.length; i++) hash = (hash * 31 + nome.charCodeAt(i)) % PALETTE.length;
			var colore = PALETTE[Math.abs(hash) % PALETTE.length]; colorCache[nome] = colore; return colore;
		}

		var defaultLat = bpgmFrontend.defaultLat || 41.9028, defaultLng = bpgmFrontend.defaultLng || 12.4964, defaultZoom = bpgmFrontend.defaultZoom || 6;
		var map = L.map('bpgm-map').setView([defaultLat, defaultLng], defaultZoom);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);

		var markersLayer = L.markerClusterGroup().addTo(map);
		var allFeatures = [], globalTermIcons = {}, activeCategorie = new Set(), activeTag = new Set();

		function popupHtml(p) {
			var avatarHtml = p.avatar ? '<img src="' + p.avatar + '" class="bpgm-popup-avatar" />' : '';
			var catsHtml = '';
			if (p.categorie && p.categorie.length) {
				catsHtml += '<div class="bpgm-popup-badges">';
				p.categorie.forEach(function (c) {
					var iconUrl = globalTermIcons[c], imgTag = iconUrl ? '<img src="'+iconUrl+'" style="width:12px;height:12px;object-fit:contain;margin-right:4px;" />' : '';
					var styleBg = iconUrl ? 'background:#2a5a8a;' : 'background:' + colorForCategoria(c) + ';';
					catsHtml += '<span class="bpgm-badge bpgm-badge-categoria" style="' + styleBg + ' display:inline-flex; align-items:center; padding:3px 8px;">' + imgTag + c + '</span>';
				});
				catsHtml += '</div>';
			}
			var tagsHtml = '';
			if (p.tags && p.tags.length) {
				tagsHtml += '<div class="bpgm-popup-badges">';
				p.tags.forEach(function (t) {
					var iconUrl = globalTermIcons[t], imgTag = iconUrl ? '<img src="'+iconUrl+'" style="width:12px;height:12px;object-fit:contain;margin-right:4px;" />' : '';
					tagsHtml += '<span class="bpgm-badge bpgm-badge-tag" style="display:inline-flex; align-items:center; padding:3px 8px;">' + imgTag + '#' + t + '</span>';
				});
				tagsHtml += '</div>';
			}
			return '<div class="bpgm-popup">' + avatarHtml + '<h4><a href="' + p.permalink + '">' + p.name + '</a></h4><p>' + p.description + '</p>' + catsHtml + tagsHtml + '<p class="bpgm-popup-meta">' + p.members + ' membri</p></div>';
		}

		function featureMatchesFilters(f) {
			var p = f.properties;
			if (activeCategorie.size > 0 && !(p.categorie || []).some(function (c) { return activeCategorie.has(c); })) return false;
			if (activeTag.size > 0 && !(p.tags ||
