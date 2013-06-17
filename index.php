<html>
<head>
	<title>Maps | A Geographically Correct Tube Map</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.5/leaflet.css" />
	<!--[if lte IE 8]>
	<link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.5/leaflet.ie.css" />
	<![endif]-->
	<link rel="stylesheet" href="css/style.css" type="text/css" />
	<script src="http://cdn.leafletjs.com/leaflet-0.5/leaflet.js"></script>
	<!--
	<script type="text/javascript" src="http://maps.stamen.com/js/tile.stamen.js?v1.2.1"></script>
	-->
	<script type="text/javascript" src="http://leaflet-extras.github.io/leaflet-providers/leaflet-providers.js"></script>
	<script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>
	<script type="text/javascript" src="js/stations.js"></script>
	
</head>
<body>
	<div id="header"><a href="/rude/index.php">A Geographically Correct Tube Map</a></div>
	<div id="map"></div>
	<div id="footer">
		<div id="credits">
			<a href="/">More Maps</a>. This is a code thing by <a href="http://www.garygale.com/" target="_blank">Gary Gale</a>, made out of PHP, HTML, CSS and jQuery. <a href="/images/signpost-icon.png" target="_blank">Signpost icon</a> <a href="http://creativecommons.org/licenses/by-sa/3.0" target="_blank">CC BY SA 3.0</a>; based on an original by <a href="http://mapicons.nicolasmollet.com/" target="_blank">Nicolas Mollet</a>.
		</div>
		<div id="attribution">
			&copy; <a href="http://www.garygale.com/" target="_blank">Gary Gale</a>; content licensed under <a href="http://creativecommons.org/licenses/by/3.0" target="_blank">CC BY 3.0</a>; code licensed under a <a href="http://opensource.org/licenses/BSD-2-Clause" target="_blank">BSD license</a>. <a href="http://maps.stamen.com/" target="_blank">Map tiles</a> by <a href="http://stamen.com/" target="_blank">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0" target="_blank">CC BY 3.0</a>. &copy; <a href="http://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors.
		</div>
	</div>
</body>
