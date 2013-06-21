$(document).ready(function() {
	var stations = (function() {
		var geojson = null;
		$.ajax({
			'async': false,
			'global': false,
			'url': 'js/stations.geojson',
			'dataType': 'json',
			'success': function(data) {
				geojson = data;
			}
		});
		return geojson;
	})();

	var tiles = L.tileLayer.provider('Acetate.all');
	var lines = {
		'Bakerloo' : 'bakerloo',
		'Jubilee' : 'jubilee',
		'Northern' : 'northern',
		'Metropolitan' : 'metropolitan',
		'Hammersmith & City' : 'hammersmith-city',
		'Piccadilly' : 'piccadilly',
		'Circle' : 'circle',
		'District' : 'district',
		'Central' : 'central',
		'Victoria' : 'victoria',
		'Waterloo & City' : 'waterloo-city',
		'East London' : 'east-london'
	};
	
	var bc = [];
	var coords = [];
	var current_line;
	var layers = {};
	var layer;
	
	open_layer = L.geoJson(stations, {
		onEachFeature: make_popup,
		pointToLayer: make_marker_by_open,
		filter: filter_by_open
	});
	bc['Open'] = new L.LatLngBounds(coords);
	//console.log('Open bounding box: ' + bc['Open'].toBBoxString());
	if (!bc['Open'].isValid) {
		console.log('Oops, Open bounding box seems invalid');
	}
	layers['Open Stations'] = open_layer;

	coords.length = 0;
	layer = L.geoJson(stations, {
		onEachFeature: make_popup,
		pointToLayer: make_marker_by_closed,
		filter: filter_by_closed
	});
	//bc['Closed'] = coords;
	bc['Closed'] = new L.LatLngBounds(coords);
	//console.log('Closed bounding box: ' + bc['Closed'].toBBoxString());
	if (!bc['Closed'].isValid) {
		console.log('Oops, Closed bounding box seems invalid');
	}
	layers['Closed Stations'] = layer;
	
	for (line in lines) {
		coords.length = 0;
		//console.log('Handling line ' + line);
		current_line = line;
		layer = L.geoJson(stations, {
			onEachFeature: make_popup,
			pointToLayer: make_marker_by_line,
			filter: filter_by_line
		});
		//bc[line] = coords;
		bc[line] = new L.LatLngBounds(coords);
		//console.log(line + ' bounding box: ' + bc[line].toBBoxString());
		if (!bc[line].isValid) {
			console.log('Oops, ' + line + ' bounding box seems invalid');
		}
		
		layers[line + ' Line'] = layer;
	}
	
	var map_options = {
		layers: [tiles, open_layer]
	};
	var map = new L.Map('map', map_options);
	//var bounds = new L.LatLngBounds(coords);
	//map.fitBounds(bounds);
	
	//var open_bounds = new L.LatLngBounds(bc['Open']);
	map.fitBounds(bc['Open']);

	//L.control.layers(null, layers, { collapsed: false }).addTo(map);
	L.control.layers(layers, null, { collapsed: false }).addTo(map);

	$("[name='leaflet-base-layers']").change(function() {
		var label = $(this).parent().text();
		var comp = label.replace('Line', '');
		comp = comp.replace('Stations', '');
		comp = comp.trim();
		var bounds = bc[comp];
		if (!bounds.isValid) {
			console.log('Oops, ' + comp + ' bounding box seems invalid');
		}
		map.fitBounds(bounds);
		
	});
	
	function filter_by_line(feature, layer) {
		//console.log('filter_by_line');
		if (feature.properties.lines.indexOf(current_line) > -1) {
			//console.log('Found ' + current_line + ' in ' + feature.properties.name);
			return true;
		}
		else {
			return false;
		}
	}
	
	function filter_by_closed(feature, layer) {
		return feature.properties.closed_lines.length > 0;
	}
	
	function filter_by_open(feature, layer) {
		return feature.properties.open_lines.length > 0;
	}

	function make_popup(feature, layer) {
		var popup = '<div class="station-popup">';
		popup += '<p><strong>' + feature.properties.name + '</strong></p>';
		//var open_lines = '(none)';
		//var closed_lines = '(none)';
		/*if (feature.properties.open_lines.length > 0) {
			open_lines = [];
			for (line in feature.properties.open_lines) {
				console.log(feature.properties.name + ' ' + line);
				open_lines.push(line + ' (' + feature.properties.info[line]. start + ')');
			}
			open_lines = open_lines.join(', ');
		}
		if (feature.properties.closed_lines.length > 0) {
			closed_lines = feature.properties.closed_lines.join(', ');
		}*/

		var lines = [];
		for (line in feature.properties.info) {
			var desc = line + ' Line: Opened ' + feature.properties.info[line].start;
			if (feature.properties.info[line].hasOwnProperty('end')) {
				desc += ', Closed ' + feature.properties.info[line].end;
			}
			lines.push(desc);
		}

		popup += '<p>' + lines.join('<br />') + '</p>';
		if (feature.properties.thumbnail) {
			popup += '<img src="' + feature.properties.thumbnail + '" + height="155" width="200" />';
		}
		//if (feature.properties.abstract) {
			//popup += '<p>' + feature.properties.abstract + '</p>';
		//}
		//popup += 'Open Lines: ' + open_lines + '<br />';
		//popup += 'Closed Lines: ' + closed_lines + '<br />';
		popup += '</p>';
		popup += '</div>';
		
		layer.bindPopup(popup);
		layer.on('click', function(e) {
			e.target.openPopup();
		});
	}
	
	function make_marker_by_line(feature, latlng) {
		var icon_stub = null;
		if (feature.properties.open_lines.indexOf(current_line) > -1) {
			icon_stub = lines[current_line];
			//console.log(feature.properties.name + ' (' + current_line + ') is open');
			//if (feature.properties.open_lines.length == 1) {
			//	icon_stub = lines[current_line];
			//}
			//else {
			//	icon_stub = 'interchange';
			//}
		}
		
		else if (feature.properties.closed_lines.indexOf(current_line) > -1) {
			icon_stub = lines[current_line] + '-closed';
			//console.log(feature.properties.name + ' (' + current_line + ') is closed');
			//if (feature.properties.open_lines.length == 1) {
			//	icon_stub = lines[current_line] + '-closed';
			//}
			//else {
			//	icon_stub = 'interchange-closed';
			//}
		}

		if (icon_stub == null) {
			console.log('Bad icon for ' + feature.properties.name + ' (' + current_line + ')');
		}

		else {
			var icon_url = 'images/' + icon_stub + '.png';
			//console.log(current_line + '/' + feature.properties.name + ' = ' + icon_url);
			var icon = new L.Icon({
				iconUrl: 'images/' + icon_stub + '.png',
				iconSize: [32, 37]
			});
			coords.push(latlng);
			return new L.Marker(latlng, { icon : icon });
		}
	}
	
	function make_marker_by_closed(feature, latlng) {
		var icon_stub = null;

		if (feature.properties.closed_lines.length > 1) {
			icon_stub = 'interchange-closed';
		}
		else if (feature.properties.closed_lines.length == 1){
			var line = feature.properties.closed_lines[0];
			icon_stub = lines[line] + '-closed';
		}

		if (icon_stub == null) {
			console.log('No closed lines found for ' + feature.properties.name);
		}
		
		else {
			var icon_url = 'images/' + icon_stub + '.png';
			//console.log(current_line + '/' + feature.properties.name + ' = ' + icon_url);
			var icon = new L.Icon({
				iconUrl: 'images/' + icon_stub + '.png',
				iconSize: [32, 37]
			});
			coords.push(latlng);
			return new L.Marker(latlng, { icon : icon });
		}
	}
	
	function make_marker_by_open(feature, latlng) {
		var icon_stub = null;

		if (feature.properties.open_lines.length > 1) {
			icon_stub = 'interchange';
		}
		else if (feature.properties.open_lines.length == 1){
			var line = feature.properties.open_lines[0];
			icon_stub = lines[line];
		}

		if (icon_stub == null) {
			console.log('No open lines found for ' + feature.properties.name);
		}
		
		else {
			var icon_url = 'images/' + icon_stub + '.png';
			//console.log(current_line + '/' + feature.properties.name + ' = ' + icon_url);
			var icon = new L.Icon({
				iconUrl: 'images/' + icon_stub + '.png',
				iconSize: [32, 37]
			});
			coords.push(latlng);
			return new L.Marker(latlng, { icon : icon });
		}
	}
});