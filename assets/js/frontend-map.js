(function () {
	document.addEventListener('DOMContentLoaded', function () {
		var mapEl = document.getElementById('bpgm-map');
		if (!mapEl || typeof L === 'undefined') {
			return;
		}

		var PALETTE = ['#2a5a8a', '#c0392b', '#27ae60', '#e67e22', '#8e44ad', '#16a085', '#d35400', '#2c3e50', '#c2185b', '#00838f'];
		var colorCache = {};
		function colorForCategoria(nome) {
			if (!nome) { return '#777'; }
			if (colorCache[nome]) { return colorCache[nome]; }
			var hash = 0;
			for (var i = 0; i < nome.length; i++) {
				hash = (hash * 31 + nome.charCodeAt(i)) % PALETTE.length;
			}
			var colore = PALETTE[Math.abs(hash) % PALETTE.length];
			colorCache[nome] = colore;
			return colore;
		}

		var defaultLat  = bpgmFrontend.defaultLat || 41.9028;
		var defaultLng  = bpgmFrontend.defaultLng || 12.4964;
		var defaultZoom = bpgmFrontend.defaultZoom || 6;

		var map = L.map('bpgm-map').setView([defaultLat, defaultLng], defaultZoom);

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap contributors',
			maxZoom: 19
		}).addTo(map);

		var markersLayer = L.markerClusterGroup().addTo(map);
		var allFeatures = [];
		var globalTermIcons = {}; 
		var activeCategorie = new Set();
		var activeTag = new Set();

		function popupHtml(p) {
			var avatarHtml = p.avatar ? '<img src="' + p.avatar + '" class="bpgm-popup-avatar" alt="" />' : '';

			var catsHtml = '';
			if (p.categorie && p.categorie.length) {
				catsHtml += '<div class="bpgm-popup-badges">';
				p.categorie.forEach(function (c) {
					var iconUrl = globalTermIcons[c];
					var imgTag = iconUrl ? '<img src="' + iconUrl + '" style="width:12px; height:12px; object-fit:contain; margin-right:4px;" />' : '';
					var styleBg = iconUrl ? 'background:#2a5a8a;' : 'background:' + colorForCategoria(c) + ';';
					
					catsHtml += '<span class="bpgm-badge bpgm-badge-categoria" style="' + styleBg + ' display:inline-flex; align-items:center; padding:3px 8px;">' + imgTag + c + '</span>';
				});
				catsHtml += '</div>';
			}

			var tagsHtml = '';
			if (p.tags && p.tags.length) {
				tagsHtml += '<div class="bpgm-popup-badges">';
				p.tags.forEach(function (t) {
					var iconUrl = globalTermIcons[t];
					var imgTag = iconUrl ? '<img src="' + iconUrl + '" style="width:12px; height:12px; object-fit:contain; margin-right:4px;" />' : '';
					
					tagsHtml += '<span class="bpgm-badge bpgm-badge-tag" style="display:inline-flex; align-items:center; padding:3px 8px;">' + imgTag + '#' + t + '</span>';
				});
				tagsHtml += '</div>';
			}

			return '<div class="bpgm-popup">' + avatarHtml +
				'<h4><a href="' + p.permalink + '">' + p.name + '</a></h4>' +
				'<p>' + p.description + '</p>' +
				catsHtml + tagsHtml +
				'<p class="bpgm-popup-meta">' + p.members + ' membri</p>' +
				'</div>';
		}

		function featureMatchesFilters(feature) {
			var p = feature.properties;
			if (activeCategorie.size > 0) {
				var hasCat = (p.categorie || []).some(function (c) { return activeCategorie.has(c); });
				if (!hasCat) { return false; }
			}
			if (activeTag.size > 0) {
				var hasTag = (p.tags || []).some(function (t) { return activeTag.has(t); });
				if (!hasTag) { return false; }
			}
			return true;
		}

		function renderMarkers() {
			markersLayer.clearLayers();
			var bounds = [];
			var markersBuffer = [];

			allFeatures.forEach(function (feature) {
				if (!featureMatchesFilters(feature)) { return; }
				var coords = feature.geometry.coordinates;
				var latlng = [coords[1], coords[0]];
				var props = feature.properties;
				var marker;

				if (props.marker_icon && props.marker_icon.trim() !== '') {
					var customIcon = L.icon({
						iconUrl: props.marker_icon,
						iconSize: [32, 32],
						iconAnchor: [16, 32],
						popupAnchor: [0, -32]
					});
					marker = L.marker(latlng, { icon: customIcon });
				} else {
					var primaCategoria = (props.categorie || [])[0];
					marker = L.circleMarker(latlng, {
						radius: 9,
						weight: 2,
						color: '#fff',
						fillColor: colorForCategoria(primaCategoria),
						fillOpacity: 0.9
					});
				}

				marker.bindPopup(popupHtml(props));
				markersBuffer.push(marker);
				bounds.push(latlng);
			});

			markersLayer.addLayers(markersBuffer);
			updateCounter(bounds.length, allFeatures.length);

			if (bounds.length) {
				map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
			}
		}

		function updateCounter(visibili, totali) {
			var counterEl = document.getElementById('bpgm-filter-counter');
			if (counterEl) {
				counterEl.textContent = visibili + ' di ' + totali + ' gruppi';
			}
		}

		function buildFilterGroup(label, items, activeSet, isTagGroup) {
			var group = document.createElement('div');
			group.className = 'bpgm-filter-group';

			var heading = document.createElement('strong');
			heading.textContent = label;
			group.appendChild(heading);

			var search = null;
			if (isTagGroup) {
				search = document.createElement('input');
				search.type = 'text';
				search.placeholder = 'Cerca tag…';
				search.className = 'bpgm-filter-search';
				group.appendChild(search);
			}

			var list = document.createElement('div');
			list.className = 'bpgm-filter-list';

			items.sort(function (a, b) { return a.localeCompare(b, 'it'); }).forEach(function (item) {
				var wrapper = document.createElement('label');
				wrapper.className = 'bpgm-filter-item';

				var cb = document.createElement('input');
				cb.type = 'checkbox';
				cb.value = item;
				cb.addEventListener('change', function () {
					if (cb.checked) { activeSet.add(item); } else { activeSet.delete(item); }
					renderMarkers();
				});

				wrapper.appendChild(cb);

				if (globalTermIcons[item]) {
					var img = document.createElement('img');
					img.src = globalTermIcons[item];
					img.style.width = '16px';
					img.style.height = '16px';
					img.style.objectFit = 'contain';
					img.style.flexShrink = '0';
					wrapper.appendChild(img);
				} else if (!isTagGroup) {
					var swatch = document.createElement('span');
					swatch.className = 'bpgm-filter-swatch';
					swatch.style.background = colorForCategoria(item);
					wrapper.appendChild(swatch);
				}

				var testo = document.createElement('span');
				testo.textContent = (isTagGroup ? '#' : '') + item;
				wrapper.appendChild(testo);
				
				list.appendChild(wrapper);
			});

			group.appendChild(list);

			if (search) {
				search.addEventListener('input', function () {
					var q = search.value.trim().toLowerCase();
					list.querySelectorAll('.bpgm-filter-item').forEach(function (el) {
						var match = el.textContent.toLowerCase().indexOf(q) !== -1;
						el.style.display = match ? '' : 'none';
					});
				});
			}

			return group;
		}

		function buildFilterPanel(categorie, tags) {
			var panel = document.createElement('div');
			panel.id = 'bpgm-filter-panel';
			panel.className = 'bpgm-filter-panel';

			var toggleBtn = document.createElement('button');
			toggleBtn.type = 'button';
			toggleBtn.className = 'bpgm-filter-toggle';
			toggleBtn.textContent = 'Filtra gruppi';
			toggleBtn.addEventListener('click', function () {
				panel.classList.toggle('bpgm-filter-open');
			});

			var body = document.createElement('div');
			body.className = 'bpgm-filter-body';

			body.appendChild(buildFilterGroup('Categoria', categorie, activeCategorie, false));
			body.appendChild(buildFilterGroup('Tag', tags, activeTag, true));

			var resetBtn = document.createElement('button');
			resetBtn.type = 'button';
			resetBtn.className = 'bpgm-filter-reset';
			resetBtn.textContent = 'Azzera filtri';
			resetBtn.addEventListener('click', function () {
				activeCategorie.clear();
				activeTag.clear();
				panel.querySelectorAll('input[type=checkbox]').forEach(function (cb) { cb.checked = false; });
				renderMarkers();
			});
			body.appendChild(resetBtn);

			var counter = document.createElement('div');
			counter.id = 'bpgm-filter-counter';
			counter.className = 'bpgm-filter-counter';
			body.appendChild(counter);

			panel.appendChild(toggleBtn);
			panel.appendChild(body);
			mapEl.parentNode.insertBefore(panel, mapEl);
		}

		var url = bpgmFrontend.restUrl;
		if (bpgmFrontend.groupType) {
			url += (url.indexOf('?') === -1 ? '?' : '&') + 'group_type=' + encodeURIComponent(bpgmFrontend.groupType);
		}

		fetch(url)
			.then(function (res) { return res.json(); })
			.then(function (geojson) {
				if (!geojson.features) { return; }
				
				allFeatures = geojson.features;
				globalTermIcons = geojson.term_icons || {};

				var categorieSet = new Set();
				var tagSet = new Set();
				allFeatures.forEach(function (f) {
					(f.properties.categorie || []).forEach(function (c) { categorieSet.add(c); });
					(f.properties.tags || []).forEach(function (t) { tagSet.add(t); });
				});

				buildFilterPanel(Array.from(categorieSet), Array.from(tagSet));
				renderMarkers();
			})
			.catch(function (err) {
				console.error('BP Groups Map: errore nel caricamento dei gruppi', err);
			});
	});
})();
