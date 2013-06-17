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
	//var tiles = new L.StamenTileLayer('toner');
	// var tiles = L.tileLayer.provider('Acetate.basemap');
	var tiles = L.tileLayer.provider('Acetate.all');
	var coords = [];

	var lines_list = [
		'Bakerloo',
		'Jubilee',
		'Northern',
		'Metropolitan',
		'Hammersmith & City',
		'Piccadilly',
		'Circle',
		'District',
		'Central',
		'Victoria',
		'Waterloo & City',
		'East London'
	];

	var single_line = false;
	var all_layer = L.geoJson(stations, {
		onEachFeature: on_each_feature,
		pointToLayer: point_to_layer
	});

	var lines_layers = { 'All Lines' : all_layer };
	var current_line;
	single_line = true;
	
	for (var i=0; i<lines_list.length; i++) {
		current_line = lines_list[i];
		var layer = L.geoJson(stations, {
			onEachFeature: on_each_feature,
			pointToLayer: point_to_layer,
			filter: line_filter
		});
		var line_desc = current_line + ' Line';
		lines_layers[line_desc] = layer;
	}
	
	var options = {
		layers: [tiles, all_layer]
	};

	var map = new L.Map('map', options);
	L.control.layers(null, lines_layers, { collapsed: false }).addTo(map);
	
	var bounds = new L.LatLngBounds(coords);
	map.fitBounds(bounds);
	
	function line_filter(feature, layer) {
		return feature.properties.lines.indexOf(current_line) > -1;
	}
	
	function on_each_feature(feature, layer) {
		if (feature.properties && feature.properties.name) {
			var popup = '<div class="station-popup">';
			popup += '<p>' + feature.properties.name + '<br />';
			popup += '<em>' + feature.properties.lines.join(', ') + '</em><br />';
			popup += 'Status: ';
			if (feature.properties.open) {
				popup += 'Open';
			}
			else {
				popup += 'Closed (' + feature.properties.start + '-' + feature.properties.end + ')';
			}
			if (feature.properties.thumbnail) {
				popup += '<br />';
				popup += '<img src="' + feature.properties.thumbnail + '" + height="155" width="200" />';
			}
			popup += '</div>';
			layer.bindPopup(popup);
			layer.on('click', function(e) {
				e.target.openPopup();
			});
		}
	}
	
	function point_to_layer(feature, latlng) {
		var icon = new L.Icon({
			iconUrl: make_icon(feature.properties),
			iconSize: [32, 37]
		});
		var marker = new L.Marker(latlng, { icon: icon});
		coords.push(latlng);
		return marker;
	}
	
	function make_icon(properties) {
		var img = 'images/';
		var line_name;
		
		if (single_line || (properties && properties.lines && properties.lines.length == 1)) {
			if (single_line) {
				line_name = current_line;
			}
			else {
				line_name = properties.lines[0];
				
			}
			switch (line_name) {
				case 'Bakerloo':
					img += 'bakerloo';
					break;
				case 'Central':
					img += 'central';
					break;
				case 'Circle':
					img += 'circle';
					break;
				case 'District':
					img += 'district';
					break;
				case 'East London':
					img += 'east-london';
					break;
				case 'Hammersmith & City':
					img += 'hammersmith-city';
					break;
				case 'Jubilee':
					img += 'jubilee';
					break;
				case 'Metropolitan':
					img += 'metropolitan';
					break;
				case 'Northern':
					img += 'northern';
					break;
				case 'Piccadilly':
					img += 'piccadilly';
					break;
				case 'Victoria':
					img += 'victoria';
					break;
				case 'Waterloo & City':
					img += 'waterloo-city';
					break;
				default:
					break;
			}
			
			if (!properties.open) {
				img += '-closed';
			}
			img += '.png';
		}
		
		else {
			if (properties.open) {
				img += 'interchange.png';
			}
			
			else {
				img += 'interchange-closed.png';
			}
		}
		
		return img;
	}
	//map.setView([51.508460, -0.125337], 13);
});