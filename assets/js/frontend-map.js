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
			if (activeTag.size > 0 && !(p.tags || []).some(function (t) { return activeTag.has(t); })) return false;
			return true;
		}

		function renderMarkers() {
			markersLayer.clearLayers(); var bounds = [], markersBuffer = [];
			allFeatures.forEach(function (feature) {
				if (!featureMatchesFilters(feature)) return;
				var coords = feature.geometry.coordinates, latlng = [coords[1], coords[0]], props = feature.properties, marker;
				if (props.marker_icon && props.marker_icon.trim() !== '') {
					marker = L.marker(latlng, { icon: L.icon({ iconUrl: props.marker_icon, iconSize: [32, 32], iconAnchor: [16, 32], popupAnchor: [0, -32] }) });
				} else {
					marker = L.circleMarker(latlng, { radius: 9, weight: 2, color: '#fff', fillColor: colorForCategoria((props.categorie || [])[0]), fillOpacity: 0.9 });
				}
				marker.bindPopup(popupHtml(props)); markersBuffer.push(marker); bounds.push(latlng);
			});
			markersLayer.addLayers(markersBuffer);
			if (b = document.getElementById('bpgm-filter-counter')) b.textContent = bounds.length + ' di ' + allFeatures.length + ' gruppi';
			if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
		}

		function buildFilterGroup(label, items, activeSet, isTag) {
			var group = document.createElement('div'); group.className = 'bpgm-filter-group';
			var heading = document.createElement('strong'); heading.textContent = label; group.appendChild(heading);
			var list = document.createElement('div'); list.className = 'bpgm-filter-list';
			if (isTag) {
				var s = document.createElement('input'); s.className = 'bpgm-filter-search'; s.placeholder = 'Cerca tag…'; group.appendChild(s);
				s.addEventListener('input', function () { var q = s.value.toLowerCase(); list.querySelectorAll('.bpgm-filter-item').forEach(function (el) { el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none'; }); });
			}
			items.sort().forEach(function (item) {
				var lbl = document.createElement('label'); lbl.className = 'bpgm-filter-item';
				var cb = document.createElement('input'); cb.type = 'checkbox'; cb.value = item;
				cb.addEventListener('change', function () { if (cb.checked) activeSet.add(item); else activeSet.delete(item); renderMarkers(); });
				lbl.appendChild(cb);
				if (globalTermIcons[item]) {
					var img = document.createElement('img'); img.src = globalTermIcons[item]; img.style.width = '16px'; img.style.height = '16px'; img.style.objectFit = 'contain'; lbl.appendChild(img);
				}
				var txt = document.createElement('span'); txt.textContent = (isTag ? '#' : '') + item; lbl.appendChild(txt); list.appendChild(lbl);
			});
			group.appendChild(list); return group;
		}

		var url = bpgmFrontend.restUrl; if (bpgmFrontend.groupType) url += (url.includes('?') ? '&' : '?') + 'group_type=' + encodeURIComponent(bpgmFrontend.groupType);
		fetch(url).then(function (r) { return r.json(); }).then(function (geojson) {
			if (!geojson.features) return; allFeatures = geojson.features; globalTermIcons = geojson.term_icons || {};
			var cats = new Set(), tags = new Set(); allFeatures.forEach(function (f) { (f.properties.categorie || []).forEach(function (c) { cats.add(c); }); (f.properties.tags || []).forEach(function (t) { tags.add(t); }); });
			
			var panel = document.createElement('div'); panel.className = 'bpgm-filter-panel';
			var btn = document.createElement('button'); btn.className = 'bpgm-filter-toggle'; btn.textContent = 'Filtra gruppi'; panel.appendChild(btn);
			btn.addEventListener('click', function () { panel.classList.toggle('bpgm-filter-open'); });
			var body = document.createElement('div'); body.className = 'bpgm-filter-body';
			body.appendChild(buildFilterGroup('Categoria', Array.from(cats), activeCategorie, false));
			body.appendChild(buildFilterGroup('Tag', Array.from(tags), activeTag, true));
			var rst = document.createElement('button'); rst.className = 'bpgm-filter-reset'; rst.textContent = 'Azzera filtri'; body.appendChild(rst);
			rst.addEventListener('click', function () { activeCategorie.clear(); activeTag.clear(); panel.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = false; }); renderMarkers(); });
			var cnt = document.createElement('div'); cnt.id = 'bpgm-filter-counter'; cnt.className = 'bpgm-filter-counter'; body.appendChild(cnt);
			panel.appendChild(body); mapEl.parentNode.insertBefore(panel, mapEl); renderMarkers();
		});
	});
})();
