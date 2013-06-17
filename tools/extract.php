<?php

require_once('../includes/rest-tools/class-rest-tools.php');

$master_root = 'http://live.dbpedia.org/page/Category:Stations_by_London_Underground_line';
$master_info = make_data_url($master_root);

$disused_root = 'http://live.dbpedia.org/page/Category:Disused_London_Underground_stations';
$disused_info = make_data_url($disused_root);

$ignore_list = array(
	'http://live.dbpedia.org/data/WikiProject_London_Transport/How_to_write_about_stations.json',
	'http://live.dbpedia.org/data/Category:Never_constructed_Northern_Heights_extension_stations.json',
	'http://live.dbpedia.org/data/Category:Never_constructed_Great_Northern_and_Strand_Railway_stations.json',
	'http://live.dbpedia.org/data/List_of_former_and_unopened_London_Underground_stations.json',
	'http://live.dbpedia.org/data/Category:Disused_London_Underground_station_maps.json',
	'http://live.dbpedia.org/data/Category:Former_single_platform_tube_stations.json',
	'http://live.dbpedia.org/data/Category:Croxley_Rail_Link.json',
	'http://live.dbpedia.org/data/Edgware_Road_tube_station_(Circle,_District_and_Hammersmith_&_City_lines).json'
);

$lines_list = array(
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
);

$geojson = array();
$stations = array();

update_master_list($master_info);
update_line_list($disused_info);
fixup_stations($stations);
make_geojson($stations);


function update_master_list($parent_info) {
	//error_log('update_master_list++');
	$data = get_json($parent_info);
	
	foreach ($data['live.dbpedia.org']['json'] as $url => $meta) {
		$line_info = make_data_url($url);
		
		if ($line_info['live.dbpedia.org']['url'] == $parent_info['live.dbpedia.org']['url']) {
			//error_log('Skipping parent reference for ' . $parent_info['url']);
		}
		
		else {
			//error_log('Processing master info for ' . $line_info['url']);
			update_line_list($line_info);
		}
	}
	//error_log('update_master_list--');
}

function update_line_list($parent_info) {
	//error_log('--> update_line_list++');
	$data = get_json($parent_info);
	
	foreach ($data['live.dbpedia.org']['json'] as $url => $meta) {
		global $ignore_list;
		
		$station_info = make_data_url($url);
		
		//error_log('--> Parent URL: ' . $parent_info['url']);
		//error_log('--> Station URL: ' . $station_info['url']);

		if (array_search($station_info['live.dbpedia.org']['url'], $ignore_list) !== FALSE) {
			//error_log('--> Ignoring line info for ' . $station_info['url']);
		}

		else if ($station_info['live.dbpedia.org']['url'] == $parent_info['live.dbpedia.org']['url']) {
			//error_log('--> Skipping parent reference for ' . $parent_info['url']);
		}
		
		else {
			//error_log('--> Processing line info for ' . $station_info['url']);
			update_station_entry($station_info);
		}
	}
	//error_log('--> update_line_list--');
}

function update_station_entry($parent_info) {
	global $stations;
	//error_log('----> update_station_entry++');
	$data = get_json($parent_info);
	
	$live_meta = NULL;
	$dbpedia_meta = NULL;
	
	//error_log('----> Checking live.dbpedia.org');
	foreach ($data['live.dbpedia.org']['json'] as $uri => $meta) {
		//error_log('------> Checking ' . $uri);
		$info = make_data_url($uri);
		//error_log('------> Current URL ' . $info['live.dbpedia.org']['url']);
		//error_log('------> Parent URL ' . $parent_info['live.dbpedia.org']['url']);
		
		if ($info['live.dbpedia.org']['url'] === $parent_info['live.dbpedia.org']['url']) {
			error_log('------> Selected ' . $uri);
			$live_meta = $meta;
			break;
		}
		else {
			//error_log('------> Skipping ' . $uri);
		}
	}
	//error_log('----> Finished live.dbpedia.org');

	//error_log('----> Checking dbpedia.org');
	foreach ($data['dbpedia.org']['json'] as $uri => $meta) {
		//error_log('------> Checking ' . $uri);
		$info = make_data_url($uri);
		//error_log('------> Current URL ' . $info['dbpedia.org']['url']);
		//error_log('------> Parent URL ' . $parent_info['dbpedia.org']['url']);

		if ($info['dbpedia.org']['url'] === $parent_info['dbpedia.org']['url']) {
			error_log('----> Selected ' . $uri);
			$dbpedia_meta = $meta;
			break;
		}
		else {
			//error_log('----> Skipping ' . $uri);
		}
	}
	//error_log('----> Finished dbpedia.org');
	
	if (!empty($live_meta)) {
		$live_attrs = get_station_attrs($live_meta);
		//error_log('');
		//error_log('----> Attributes (live.dbpedia.org)');
		//dump_attrs($live_attrs);
	}		
	if (!empty($dbpedia_meta)) {
		$dbpedia_attrs = get_station_attrs($dbpedia_meta);
		//error_log('');
		//error_log('----> Attributes (dbpedia.org)');
		//dump_attrs($dbpedia_attrs);
	}

	$attrs = merge_attrs($live_attrs, $dbpedia_attrs);
	error_log('');
	error_log('----> Attributes (merged)');
	dump_attrs($attrs);
	
	if (!isset($stations[$attrs['name']])) {
		error_log('----> Adding entry for ' . $attrs['name']);
		$stations[$attrs['name']] = $attrs;
	}
	else {
		error_log ('----> Possible duplicate entry for ' . $attrs['name']);
	}
	//error_log('----> update_station_entry--');
}

function merge_attrs($live, $dbpedia) {
	if (empty($live) && !empty($dbpedia)) {
		return $dbpedia;
	}
	
	if (!empty($live) && empty($dbpedia)) {
		return $live;
	}
	
	$attr_list = array('name', 'lat', 'long', 'lines', 'start', 'end', 'abstract', 'thumbnail', 'depiction');
	
	$attrs = $live;
	foreach ($attr_list as $attr) {
		if (empty($attrs[$attr]) && !empty($dbpedia[$attr])) {
			$attrs[$attr] = $dbpedia[$attr];
		}
		//error_log('Attr: ' . $attr);
	}

	if (strlen($dbpedia['abstract']) > strlen($live['abstract'])) {
		$attrs['abstract'] = $dbpedia['abstract'];
	}
	return $attrs;
}

function dump_attrs($attrs) {
	error_log('Name: ' . $attrs['name']);
	error_log('Coords: ' . $attrs['long'] . ',' . $attrs['lat']);
	error_log('Lines: ' . implode(',', $attrs['lines']));
	error_log('Start: ' . $attrs['start']);
	error_log('End: ' . $attrs['end']);
	error_log('Abstract: ' . $attrs['abstract']);
	error_log('Thumbnail: ' . $attrs['thumbnail']);
	error_log('Depiction: ' . $attrs['depiction']);
}

function get_station_attrs($meta) {
	global $lines_list;
	//error_log('----> get_station_attrs++');

	$attrs = array();
	
	if (property_exists($meta, 'http://www.w3.org/2003/01/geo/wgs84_pos#long')) {
		$attrs['long'] = $meta->{'http://www.w3.org/2003/01/geo/wgs84_pos#long'}[0]->value;
	}

	if (property_exists($meta, 'http://www.w3.org/2003/01/geo/wgs84_pos#lat')) {
		$attrs['lat'] = $meta->{'http://www.w3.org/2003/01/geo/wgs84_pos#lat'}[0]->value;
	}

	if (property_exists($meta, 'http://dbpedia.org/property/name')) {
		$attrs['name'] = $meta->{'http://dbpedia.org/property/name'}[0]->value;
	}

	$lines = array();
	if (property_exists($meta, 'http://dbpedia.org/property/line')) {
		foreach ($meta->{'http://dbpedia.org/property/line'} as $index => $line) {
			if (in_array($line->value, $lines_list)) {
				//error_log ('Valid line: ' . $line->value);
				$lines[] = $line->value;
			}
			else {
				//error_log ('Invalid line: ' . $line->value);
			}
		}
	}
	$attrs['lines'] = $lines;

	if (property_exists($meta, 'http://dbpedia.org/ontology/abstract')) {
		$attrs['abstract'] = get_abstract($meta->{'http://dbpedia.org/ontology/abstract'});
	}

	if (property_exists($meta, 'http://dbpedia.org/ontology/thumbnail')) {
		$attrs['thumbnail'] = $meta->{'http://dbpedia.org/ontology/thumbnail'}[0]->value;
	}

	if (property_exists($meta, 'http://xmlns.com/foaf/0.1/depiction')) {
		$attrs['depiction'] = $meta->{'http://xmlns.com/foaf/0.1/depiction'}[0]->value;
	}

	if (property_exists($meta, 'http://dbpedia.org/property/start')) {
		$attrs['start'] = $meta->{'http://dbpedia.org/property/start'}[0]->value;
	}

	if (property_exists($meta, 'http://dbpedia.org/property/end')) {
		$attrs['end'] = $meta->{'http://dbpedia.org/property/end'}[0]->value;
	}
	
	//error_log('----> get_station_attrs--');
	return $attrs;
}

function get_abstract($abstract) {
	foreach ($abstract as $index => $object) {
		if ($object->lang === 'en') {
			return $object->value;
		}
	}
	
	return NULL;
}

function make_url($components) {
	return $components['scheme'] . '://' . $components['host'] . '/' . urldecode($components['path']);
}

/*function make_data_url($source_url) {
	$components = parse_url($source_url);
	
	// Fixup hostnames to ensure that we're always talking to live.dbpedia.org
	if ($components['host'] === 'dbpedia.org') {
		$components['host'] = 'live.dbpedia.org';
	}
	//error_log('Path: ' . $components['path']);
	$path = explode('/', $components['path']);
	//error_log('Path components: ' . var_export($path, true));
	$path = array_values(array_filter($path));
	//error_log('Filtered path components: ' . var_export($path, true));
	$path[0] = 'data';
	$components['path'] = implode('/', $path) . '.json';
	//error_log('Rebuilt path: ' . $components['path']);
	
	$alt = $components;
	$alt['host'] = 'dbpedia.org';
	
	return array('url' => make_url($components), 'alt' => make_url($alt), 'file' => urldecode($components['path']));
}*/

function make_data_url($source_url) {
	//error_log('--> make_data_url++');
	$dbpedia_components = $live_components = parse_url($source_url);
	
	if (strpos($dbpedia_components['host'], 'dbpedia.org') !== FALSE) {
		$dbpedia_components['host'] = 'dbpedia.org';
	}
	if (strpos($live_components['host'], 'dbpedia.org') !== FALSE) {
		$live_components['host'] = 'live.dbpedia.org';
	}

	$dbpedia_path = $live_path = array_values(array_filter(explode('/', $dbpedia_components['path'])));
	$dbpedia_path[0] = $live_path[0] = 'data';
	
	$dbpedia_components['path'] = implode('/', $dbpedia_path) . '.json';
	$live_components['path'] = implode('/', $live_path) . '.json';
	
	array_unshift($dbpedia_path, $dbpedia_components['host']);
	array_unshift($live_path, $live_components['host']);
	
	//$dbpedia_components['path'] = implode('/', $dbpedia_path) . '.json';
	//$live_components['path'] = implode('/', $live_path) . '.json';
	
	//$dbpedia_path = implode('/', $dbpedia_path) . '.json';
	//$live_path = implode('/', $live_path) . '.json';

	//error_log('--> Source: ' . $source_url);
	//error_log('--> live.dbpedia.org: ' . make_url($live_components));
	//error_log('--> dbpedia.org: ' . make_url($dbpedia_components));
	
	//error_log('--> make_data_url--');
	return array(
		'dbpedia.org' => array(
			'url' => make_url($dbpedia_components),
			'file' => urldecode(implode('/',$dbpedia_path) . '.json')
		),
		'live.dbpedia.org' => array(
			'url' => make_url($live_components),
			'file' => urldecode(implode('/', $live_path) . '.json')
		)
	);
}

function get_json($meta) {
	$content = array();
	foreach ($meta as $host => $info) {
		//error_log('----> Looking for file ' . $info['file']);
		if (file_exists($info['file'])) {
			//error_log('----> File exists');
			$raw = file_get_contents($info['file']);
			$content[$host] = array(
				'json' => json_decode($raw),
				'size' => strlen($raw)
			);
			//error_log('----> Read ' . strlen($raw) . ' bytes of data');
		}
		
		else {
			//error_log('----> Not found');
			//error_log('----> Getting ' . $info['url']);
			
			$rest = new RestTools();
			$raw = $rest->get($info['url']);
			$content[$host] = array(
				'json' => json_decode($raw),
				'size' => strlen($raw)
			);

			//error_log('----> Got ' . strlen($raw) . ' bytes of data');
			$dir = dirname($info['file']);
			//error_log('----> Checking dir ' . $dir);
			if (!file_exists($dir)) {
				//error_log('----> Making dir ' . $dir);
				$mode = 0777;
				$recursive = true;
				mkdir($dir, $mode, $recursive);
			}
			else {
				//error_log('----> Skipping dir creation');
			}
			file_put_contents($info['file'], $raw);
			//error_log('----> Wrote ' . strlen($raw) . ' bytes of data to ' . $info['file']);
		}
	}

	return $content;

	/*$file = 'data/' . basename($meta['file']);
	$url = $meta['url'];
	
	//error_log('get_json - file: ' . $file);
	//error_log('get_json - url: ' . $url);
	
	$content = NULL;
	
	if (file_exists($file)) {
		//error_log('Getting contents of ' . $file);
		$content = file_get_contents($file);
		//error_log('Got ' . strlen($content) . ' bytes of content');
	}
	
	else {
		//error_log('Getting content from ' . $url);
		$rest = new RestTools();
		$content = $rest->get($url);
		
		$alt = $rest->get($meta['alt']);
		if (strlen($alt) > strlen($content)) {
			$content = $alt;
		}
		//error_log('Got ' . strlen($content) . ' bytes of content');
		file_put_contents($file, $content);
	}

	return json_decode($content);*/
}

function fixup_stations(&$stations) {
	$stations['St John\'s Wood']['lat'] = 51.534721;
	$stations['St John\'s Wood']['long'] = -0.174167;

	$stations['Bermondsey']['lat'] = 51.49795;
	$stations['Bermondsey']['long'] = -0.06373999999999999;

	$stations['Colindale']['lat'] = 51.59542;
	$stations['Colindale']['long'] = -0.24989;

	$stations['West Harrow']['lat'] = 51.57971000000001;
	$stations['West Harrow']['long'] = -0.35338;

	$stations['Shepherd\'s Bush Market']['lat'] = 51.50557999999999;
	$stations['Shepherd\'s Bush Market']['long'] = -0.22635;

	$stations['Knightsbridge']['lat'] = 51.50167;
	$stations['Knightsbridge']['long'] = -0.16048;

	$stations['Hyde Park Corner']['lat'] = 51.50303;
	$stations['Hyde Park Corner']['long'] = -0.15242;

	$stations['Hatton Cross']['lat'] = 51.46673999999999;
	$stations['Hatton Cross']['long'] = -0.42317;
	
	$stations['Verney Junction']['lines'] = array('Metropolitan');
	$stations['Quainton Road']['lines'] = array('Metropolitan');
	$stations['Brill']['lines'] = array('Metropolitan');
	$stations['Waddesdon']['lines'] = array('Metropolitan');
	$stations['Granborough Road']['lines'] = array('Metropolitan');
	$stations['Winslow Road']['lines'] = array('Metropolitan');
	$stations['Wood Siding']['lines'] = array('Metropolitan');
	$stations['Wotton']['lines'] = array('Metropolitan');
	$stations['Westcott']['lines'] = array('Metropolitan');
	$stations['Waddesdon Road']['lines'] = array('Metropolitan');
	$stations['South Acton']['lines'] = array('District');
	$stations['Tower of London']['lines'] = array('Metropolitan');
	$stations['Uxbridge Road']['lines'] = array('Metropolitan');
	$stations['Surrey Quays']['lines'] = array('East London');
	
	$stations['Highbury & Islington']['lines'] = array('Northern', 'Victoria');
	
	$key = 'Edgware Road (Circle, District and Hammersmith & City lines)';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.52005;
	$stations[$key]['long'] = -0.16782;
	$stations[$key]['lines'] = array('Circle', 'District', 'Hammersmith & City');
	$stations[$key]['abstract'] = 'Edgware Road tube station on the Circle, District and Hammersmith & City lines is a London Underground station on the corner of Chapel Street and Cabbell Street Road in Travelcard zone 1. A separate station of the same name but on the Bakerloo line, is located about 150 metres away on the opposite side of Marylebone Road. There have been proposals in the past to rename one of the Edgware Road stations to avoid confusion. Neither of these should be confused with the Edgware tube station on the Northern line.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/1/11/EdgwareRdHammersmith.jpg/120px-EdgwareRdHammersmith.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/1/11/EdgwareRdHammersmith.jpg';
	
	$key = 'Shoreditch';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.522795;
	$stations[$key]['long'] = -0.070798;
	$stations[$key]['start'] = '1869';
	$stations[$key]['end'] = '2006';
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['abstract'] = 'Shoreditch tube station was a London Underground station in the London Borough of Tower Hamlets in east London. It was in Travelcard Zone 2. The station closed permanently at the end of traffic on 9 June 2006. It was the northern terminus of the East London Line, with latterly a single platform alongside a single track that ran next to the disused Bishopsgate Goods Yard. Until the late 1960s the East London Line connected with the main line railway to Liverpool Street (and Bishopsgate until 1916) just north of Shoreditch station. The site of the link is still visible from the end of the platform and from Greater Anglia main line trains between Stratford and Liverpool Street. The station was one of only a handful on the network with a single platform and a single track layout, though it originally had two tracks and platforms. The preceding station was Whitechapel, which was the northern terminus of the East London Line until the line closed for extension in December 2007.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/c/c2/Shoreditch_tube_station_lar.jpg/300px-Shoreditch_tube_station_lar.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/c/c2/Shoreditch_tube_station_lar.jpg';

	$stations['Whitechapel']['lines'][] = 'East London';

	$key = 'Shadwell';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.5112;
	$stations[$key]['long'] = -0.05698;
	$stations[$key]['start'] = '1876';
	$stations[$key]['end'] = '2007';
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['abstract'] = 'Shadwell railway station is on the East London Line of London Overground, between Whitechapel to the north and Wapping to the south. It is located near to Shadwell DLR station. The station is in Zone 2. The Overground station is underground (the DLR station is on a viaduct). The Overground platforms are decorated with enamel panels designed by Sarah McMenemy in 1995.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/d/d9/Shadwell_station_%28East_London_Line%29_south_entrance_April2010.jpg/240px-Shadwell_station_%28East_London_Line%29_south_entrance_April2010.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/d/d9/Shadwell_station_%28East_London_Line%29_south_entrance_April2010.jpg';

	$key = 'Wapping';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.504398;
	$stations[$key]['long'] = -0.055800;
	$stations[$key]['start'] = '1869';
	$stations[$key]['end'] = '2007';
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['abstract'] = 'Wapping railway station is on the northern bank of the river Thames in Wapping, East London, England. It is in Zone 2, and on the East London Line of London Overground between Shadwell and Rotherhithe. After recent temporary closures for remodelling, the station reopened for preview services on 27 April 2010 for services to New Cross and New Cross Gate, and from 23 May 2010 trains to/from New Cross Gate were extended to West Croydon / Crystal Palace.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/4/44/Wapping_station_building_April2010.jpg/240px-Wapping_station_building_April2010.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/4/44/Wapping_station_building_April2010.jpg';

	$key = 'Rotherhithe';
	$stations[$key]['start'] = '1869';
	$stations[$key]['end'] = '2007';
	$stations[$key]['lines'] = array('East London');

	$key = 'Canada Water';
	$stations[$key]['lines'][] = 'East London';
	
	$key = 'Surrey Quays';
	$stations[$key]['start'] = '1869';
	$stations[$key]['end'] = '2007';
	$stations[$key]['lines'] = array('East London');

	$key = 'New Cross Gate';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.475498;
	$stations[$key]['long'] = -0.040200;
	$stations[$key]['start'] = '1839';
	$stations[$key]['end'] = '2007';
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['abstract'] = 'New Cross Gate station is a railway station in New Cross, London, on the Brighton Main Line and the East London Line. It is about 600 metres west of New Cross station. It is in Travelcard Zone 2, and is operated by London Overground.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/8/87/New_Cross_Gate_station.jpg/240px-New_Cross_Gate_station.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/8/87/New_Cross_Gate_station.jpg';

	$key = 'New Cross';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.476601;
	$stations[$key]['long'] = -0.032700;
	$stations[$key]['start'] = '1839';
	$stations[$key]['end'] = '2007';
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['abstract'] = 'New Cross railway station is a railway station in New Cross, London, England, and is in London Travelcard Zone 2. The platforms are lettered A to D so as to differentiate them from those at New Cross Gate. Platform D is used exclusively by London Overground services. Ticket barriers control access to all platforms.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/f/f4/East_London_Line_terminus%2C_New_Cross_-_geograph.org.uk_-_481877.jpg/220px-East_London_Line_terminus%2C_New_Cross_-_geograph.org.uk_-_481877.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/f/f4/East_London_Line_terminus%2C_New_Cross_-_geograph.org.uk_-_481877.jpg';
	
	$key = 'Aldwych';
	$stations[$key]['start'] = '1907';
	$stations[$key]['end'] = '1994';
	$stations[$key]['lines']  = array('Piccadilly');
	
	$key = 'Ongar';
	$stations[$key]['start'] = '1957';
	$stations[$key]['end'] = '1994';
	
	$key = 'North Weald';
	$stations[$key]['start'] = '1957';
	$stations[$key]['end'] = '1994';

	$key = 'Blake Hall';
	$stations[$key]['start'] = '1957';
	$stations[$key]['end'] = '1981';
	
	$key = 'Bushey Heath';
	unset($stations[$key]);
	$key = 'Elstree South';
	unset($stations[$key]);
	
	$key = 'King William Street';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.510300;
	$stations[$key]['long'] = -0.086944;
	$stations[$key]['start'] = '1890';
	$stations[$key]['end'] = '1900';
	$stations[$key]['lines'] = array('Northern');
	$stations[$key]['abstract'] = 'King William Street was the original but short-lived northern terminus of the City & South London Railway (C&SLR), the first deep tube underground railway in London and one of the component parts of the London Underground\'s Northern Line. It was in the City of London, on King William Street, just south of the present Monument station. When the station was in operation the next station south was Borough and the southern terminus of the line was Stockwell. King William Street opened on 18 December 1890 and was constructed from a large masonry station tunnel accessed from the surface by a lift shaft or spiral staircase. Two platforms were provided, one on each side of the single, central track—one for passengers entering and one for passengers leaving the trains—a system later referred to as the Spanish solution. The station tunnel itself is situated beneath Monument Street and runs east-west across King William Street, ending beneath Arthur Street. The approach running tunnels had sharp curves and steep gradients in order to dive underneath the River Thames while remaining under public rights-of-way, in particular Swan Lane and Arthur Street. The combination of station layout and poor alignment of the running tunnels severely limited the capacity of the station and in the years after opening a number of initiatives were made to improve operations. In 1895 a central island platform with tracks each side was constructed to enable two trains to occupy the station at once; however, capacity remained restricted. When the line was extended northwards to Moorgate station, new running tunnels on a different alignment, but still beneath Borough High Street, were constructed running from below St George the Martyr\'s Church, north of Borough station to a new station at London Bridge station and onwards to an alternative City station at Bank. Under the river Thames the present running tunnels of the northern line are situated to the east of London Bridge, whereas the King William St tunnels pass to the west of the bridge, the southbound tunnel below the northbound as the line passes under the Thames. The station closed on 24 February 1900. The original station building was demolished in the 1930s, although the parts of the station below ground were converted for use as a public air-raid shelter during World War II. Access today is via a manhole in the basement of Regis House, a modern day office building, where the original cast iron spiral staircase leads down to platform level. The lift shaft was infilled with concrete during the construction of the original Regis House. The original running tunnels north of Borough tube station remain, although when the Jubilee Line Extension was built in the late 1990s the old southbound tunnel was cut through as part of the construction works at London Bridge station in order to provide the lift shaft situated at the south end of the northern line platforms. These running tunnels now serve as a ventilation shaft for the station and the openings for several adits to the old running tunnels can be seen in the roofs of the Northern Line platform tunnels and in the central concourse between them. A construction shaft between London Bridge and King William Street, beneath Old Swan Wharf, now serves as a pump shaft for the disused sections of running tunnels. It is no longer possible to walk through between the two stations as the old C&SLR running tunnels have been blocked off with concrete bulkheads either side of the River Thames.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Site_of_King_William_Street_Underground_Station.jpg/200px-Site_of_King_William_Street_Underground_Station.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/a/ab/Site_of_King_William_Street_Underground_Station.jpg';
	
	// Bakerloo Line
	$key = 'Watford Junction';
	$stations[$key]['name'] = $key;
	$stations[$key]['start'] = '1917';
	$stations[$key]['end'] = '1982';
	$stations[$key]['lines'] = array('Bakerloo');
	
	$key = 'Watford High Street';
	$stations[$key]['name'] = $key;
	$stations[$key]['start'] = '1917';
	$stations[$key]['end'] = '1982';
	$stations[$key]['lines'] = array('Bakerloo');

	$key = 'Bushey';
	$stations[$key]['name'] = $key;
	$stations[$key]['start'] = '1917';
	$stations[$key]['end'] = '1982';
	$stations[$key]['lat'] = 51.644001;
	$stations[$key]['long'] = -0.385000	;
	$stations[$key]['lines'] = array('Bakerloo');
	$stations[$key]['abstract'] = 'Bushey railway station serves the towns of Bushey and Oxhey and is situated on the Watford DC Line, 8 km (5.0 mi) north of Harrow & Wealdstone. The station was renamed from "Bushey & Oxhey" to "Bushey" on 6 May 1974, even though it is actually sited in the neighbouring town of Oxhey, and the nearest part of Bushey (Bushey Village) is over a mile away. Even so it was late in the 1980s before signage at the station reflected this change.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/a/aa/Bushey_station_east_building.JPG/200px-Bushey_station_east_building.JPG';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/a/aa/Bushey_station_east_building.JPG';

	$key = 'Carpenders Park';
	$stations[$key]['name'] = $key;
	$stations[$key]['start'] = '1919';
	$stations[$key]['end'] = '1982';
	$stations[$key]['lat'] = 51.629002;
	$stations[$key]['long'] = -0.386000;
	$stations[$key]['lines'] = array('Bakerloo');
	$stations[$key]['abstract'] = 'Carpenders Park railway station lies between the Hertfordshire suburb of Carpenders Park and the South Oxhey housing estate, 3 km (1.9 mi) south of Watford Junction on the Watford DC Line. London Underground\'s Bakerloo Line trains served the station from 16 April 1917 until 24 September 1982. London Overground services from London Euston currently serve this station. The station is an island platform reached by a subway. This has exits to both the Carpenders Park (east) and South Oxhey (west) estates. The station was originally further north than the current site and was a wooden two platform structure with a footbridge. The original station was built to serve the nearby golf course. Ticket Barriers were installed in early 2010.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/b/b0/Carpendarspark999.JPG/200px-Carpendarspark999.JPG';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/b/b0/Carpendarspark999.JPG';

	$key = 'Hatch End';
	$stations[$key]['name'] = $key;
	$stations[$key]['start'] = '1917';
	$stations[$key]['end'] = '1982';
	$stations[$key]['lat'] = 51.602402;
	$stations[$key]['long'] = -0.356400;
	$stations[$key]['lines'] = array('Bakerloo');
	$stations[$key]['abstract'] = 'Hatch End railway station is in the London Borough of Harrow, in north London, and in Travelcard Zone 6, and is located at grid reference TQ130913. The station was built in 1911 to a design by architect Gerald Horsley, son of the painter John Calcott Horsley. It has two platforms. The northbound (down) platform is on the side of the ticket office and cafe. The southbound (up) platform is reached via a footbridge. This platform was originally an island platform with the other face on the adjacent down fast mainline. There was another island platform serving the up fast and down semi-fast lines and a further platform for the up semi-fasts. These other platforms fell out of use before the end of steam services on the mainline. A general rebuilding of the access to the two remaining platforms in use was built in the 1980s and a fence built along to shield waiting passengers from the fast trains.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Hatch_End_stn_building.JPG/200px-Hatch_End_stn_building.JPG';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/2/2a/Hatch_End_stn_building.JPG';

	$key = 'Headstone Lane';
	$stations[$key]['name'] = $key;
	$stations[$key]['start'] = '1917';
	$stations[$key]['end'] = '1982';
	$stations[$key]['lat'] = 51.609501;
	$stations[$key]['long'] = -0.368100;
	$stations[$key]['lines'] = array('Bakerloo');
	$stations[$key]['abstract'] = 'Headstone Lane is a railway station near Headstone, in the London Borough of Harrow. The station is in Travelcard Zone 5.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/e/ed/Headstone_Lane_stn_building.JPG/200px-Headstone_Lane_stn_building.JPG';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/e/ed/Headstone_Lane_stn_building.JPG';
}

function make_geojson(&$stations) {
	error_log('Found ' . count($stations) . ' unique stations');

	$geojson = array();
	$geojson['type'] = 'FeatureCollection';
	$geojson['features'] = array();

	foreach ($stations as $station) {
		if (!isset($station['name']) || empty($station['name'])) {
			error_log('Missing name');
			error_log(var_export($station, true));
		}

		$attr_list = array('name', 'lat', 'long', 'lines', 'start', 'end', 'abstract', 'thumbnail', 'depiction');

		if (!isset($station['lat']) || empty($station['lat'])) {
			error_log('Missing latitude for ' . $station['name']);
		}

		if (!isset($station['long']) || empty($station['long'])) {
			error_log('Missing longitude for ' . $station['name']);
		}

		if (!isset($station['lines']) || empty($station['lines'])) {
			error_log('Missing lines for ' . $station['name']);
		}

		if (!isset($station['abstract']) || empty($station['abstract'])) {
			error_log('Missing abstract for ' . $station['name']);
		}

		$feature = array();
		$feature['type'] = 'Feature';
		$feature['geometry'] = (object) array(
			'type' => 'Point',
			'coordinates' => array($station['long'], $station['lat'])
		);
		
		$properties = array(
			'name' => $station['name'],
			'abstract' => $station['abstract'],
			'lines' => $station['lines']
		);
		if (isset($station['end'])) {
			$properties['open'] = false;
			$properties['start'] = $station['start'];
			$properties['end'] = $station['end'];
		}
		else {
			$properties['open'] = true;
		}

		if (isset($station['thumbnail'])) {
			$properties['thumbnail'] = $station['thumbnail'];
		}
		if (isset($station['depiction'])) {
			$properties['depiction'] = $station['depiction'];
		}

		$feature['properties'] = (object)$properties;

		$geojson['features'][] = (object)$feature;
	}
	
	$json = json_encode($geojson, (int)JSON_PRETTY_PRINT);
	file_put_contents('../js/stations.geojson', $json);
}
?>