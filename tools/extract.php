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
	
	$attr_list = array('name', 'lat', 'long', 'lines', 'abstract', 'thumbnail', 'depiction', 'dates', 'info');
	
	$attrs = $live;
	foreach ($attr_list as $attr) {
		error_log('------> Processing attr ' . $attr);
		if (empty($attrs[$attr]) && !empty($dbpedia[$attr])) {
			$attrs[$attr] = $dbpedia[$attr];
		}
		//error_log('Attr: ' . $attr);
	}

	if (isset($dbpedia['abstract']) && isset($live['abstract'])) {
		if (strlen($dbpedia['abstract']) > strlen($live['abstract'])) {
			$attrs['abstract'] = $dbpedia['abstract'];
		}
	}

	return $attrs;
}

function dump_attrs($attrs) {
	error_log('Name: ' . $attrs['name']);
	error_log('Coords: ' . $attrs['long'] . ',' . $attrs['lat']);
	error_log('Lines: ' . implode(',', $attrs['lines']));
	if (isset($attrs['dates'])) {
		error_log('Start: ' . $attrs['dates']['start']);
		error_log('End: ' . (isset($attrs['end']) ? $attrs['end'] : '???'));
	}
	if (isset($attrs['info'])) {
		foreach ($attrs['info'] as $line => $date) {
			error_log('Line: ' . $line . ' (' . $date['start'] . '-' . $date['end'] . ')');
		}
	}
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

	$start = $end = NULL;
	
	if (property_exists($meta, 'http://dbpedia.org/property/start')) {
		$start = $attrs['start'] = intval($meta->{'http://dbpedia.org/property/start'}[0]->value);
	}

	if (property_exists($meta, 'http://dbpedia.org/property/end')) {
		$end = $attrs['end'] = intval($meta->{'http://dbpedia.org/property/end'}[0]->value);
	}

	$dates = NULL;
	if ($start || $end) {
		$dates = array();
		if ($start)
			$dates['start'] = $start;
		if ($end)
			$dates['end'] = $end;
	}

	$lines = array();
	$info = NULL;
	if ($dates) {
		$info = array();
	}

	if (property_exists($meta, 'http://dbpedia.org/property/line')) {
		foreach ($meta->{'http://dbpedia.org/property/line'} as $index => $line) {
			if (in_array($line->value, $lines_list)) {
				//error_log ('Valid line: ' . $line->value);
				$lines[] = $line->value;
				if ($dates) {
					$info[$line->value] = $dates;
				}
			}
			else {
				//error_log ('Invalid line: ' . $line->value);
			}
		}
	}
	if ($dates)
		$attrs['dates'] = $dates;
	$attrs['lines'] = $lines;
	if ($info)
		$attrs['info'] = $info;
		
	if (property_exists($meta, 'http://dbpedia.org/ontology/abstract')) {
		$attrs['abstract'] = get_abstract($meta->{'http://dbpedia.org/ontology/abstract'});
	}

	if (property_exists($meta, 'http://dbpedia.org/ontology/thumbnail')) {
		$attrs['thumbnail'] = $meta->{'http://dbpedia.org/ontology/thumbnail'}[0]->value;
	}

	if (property_exists($meta, 'http://xmlns.com/foaf/0.1/depiction')) {
		$attrs['depiction'] = $meta->{'http://xmlns.com/foaf/0.1/depiction'}[0]->value;
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
	// Unbuilt stations
	
	$key = 'Bushey Heath';
	unset($stations[$key]);
	$key = 'Elstree South';
	unset($stations[$key]);
	$key = 'Watford West';
	unset($stations[$key]);
	$key = 'Ascot Road';
	unset($stations[$key]);
	$key = 'Watford General Hospital';
	unset($stations[$key]);
	$key = 'Watford Hospital';
	unset($stations[$key]);
	
	// TODO
	$key = 'Bank-Monument';
	unset($stations[$key]);
	$key = 'Bank and Monument';
	unset($stations[$key]);

	// Interchange stations
	$key = 'Acton Town';
	$stations[$key]['dates']['start'] = 1879;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Piccadilly']['start'] = 1932;
	
	$key = 'Aldgate';
	$stations[$key]['dates']['start'] = 1876;
	$stations[$key]['info']['Metropolitan']['start'] = 1876;
	$stations[$key]['info']['Circle']['start'] = 1949;

	$key = 'Aldgate East';
	$stations[$key]['dates']['start'] = 1884;
	$stations[$key]['info']['Metropolitan']['start'] = 1884;
	$stations[$key]['info']['Metropolitan']['start'] = 1990;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	
	$key = 'Baker Street';
	$stations[$key]['dates']['start'] = 1863;
	$stations[$key]['info']['Bakerloo']['start'] = 1906;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
	$stations[$key]['info']['Metropolitan']['start'] = 1863;

	$key = 'Barbican';
	$stations[$key]['dates']['start'] = 1865;
	$stations[$key]['info']['Metropolitan']['start'] = 1865;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;

	$key = 'Barking';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = 1902;
	$stations[$key]['info']['Metropolitan']['start'] = 1936;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;

	$key = 'Bond Street';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Central']['start'] = 1900;
	$stations[$key]['info']['Jubilee']['start'] = 1979;

	$key = 'Bow Road';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = 1902;
	$stations[$key]['info']['Metropolitan']['start'] = 1936;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;

	$key = 'Bromley-by-Bow';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = 1902;
	$stations[$key]['info']['Metropolitan']['start'] = 1936;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;

	$key = 'Charing Cross';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Bakerloo']['start'] = 1979;
	$stations[$key]['info']['Northern']['start'] = 1907;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
	$stations[$key]['info']['Jubilee']['end'] = 1999;

	$key = 'Canada Water';
	$stations[$key]['dates']['start'] = '1999';
	$stations[$key]['info']['East London']['start'] = 1999;
	$stations[$key]['info']['East London']['end'] = 2007;
	$stations[$key]['info']['Jubilee']['start'] = 1999;

	$key = 'Eastcote';
	$stations[$key]['dates']['start'] = 1904;
	$stations[$key]['info']['Metropolitan']['start'] = 1904;
	$stations[$key]['info']['District']['start'] = 1910;
	$stations[$key]['info']['District']['end'] = 1933;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;
	
	$key = 'East Ham';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = 1902;
	$stations[$key]['info']['Metropolitan']['start'] = 1936;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	
	$key = 'Edgware Road (Circle, District and Hammersmith & City lines)';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.52005;
	$stations[$key]['long'] = -0.16782;
	$stations[$key]['lines'] = array('Metropolitan', 'Circle', 'District', 'Hammersmith & City');
	$stations[$key]['dates']['start'] = 1863;
	$stations[$key]['info']['Metropolitan']['start'] = 1863;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['District']['start'] = 1868;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	$stations[$key]['abstract'] = 'Edgware Road tube station on the Circle, District and Hammersmith & City lines is a London Underground station on the corner of Chapel Street and Cabbell Street Road in Travelcard zone 1. A separate station of the same name but on the Bakerloo line, is located about 150 metres away on the opposite side of Marylebone Road. There have been proposals in the past to rename one of the Edgware Road stations to avoid confusion. Neither of these should be confused with the Edgware tube station on the Northern line.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/1/11/EdgwareRdHammersmith.jpg/120px-EdgwareRdHammersmith.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/1/11/EdgwareRdHammersmith.jpg';

	$key = 'Bank';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.51342;
	$stations[$key]['long'] = -0.08897;
	$stations[$key]['lines'] = array('Waterloo & City', 'Central', 'Northern');
	$stations[$key]['dates']['start'] = 1898;
	$stations[$key]['info']['Waterloo & City']['start'] = 1884;
	$stations[$key]['info']['Central']['start'] = 1900;
	$stations[$key]['info']['Northern']['start'] = 1900;
	$stations[$key]['abstract'] = 'Bank and Monument are interlinked London Underground and Docklands Light Railway stations that form a public transport complex spanning the length of King William Street in the City of London. Bank station, named after the Bank of England, opened in 1900 at Bank junction and is served by the Central, Northern and Waterloo and City lines, and the Docklands Light Railway.';
	
	$key = 'Monument';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.51069;
	$stations[$key]['long'] = -0.08595;
	$stations[$key]['lines'] = array('Metropolitan', 'District', 'Circle');
	$stations[$key]['dates']['start'] = 1884;
	$stations[$key]['info']['Metropolitan']['start'] = 1884;
	$stations[$key]['info']['Metropolitan']['end'] = 1949;
	$stations[$key]['info']['District']['start'] = 1884;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['abstract'] = 'Bank and Monument are interlinked London Underground and Docklands Light Railway stations that form a public transport complex spanning the length of King William Street in the City of London. Bank station, named after the Bank of England, opened in 1900 at Bank junction and is served by the Central, Northern and Waterloo and City lines, and the Docklands Light Railway.';

	$key = 'Embankment';
	$stations[$key]['dates']['start'] = 1870;
	$stations[$key]['lines'][] = 'Metropolitan';
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['District']['start'] = 1870;
	$stations[$key]['info']['Metropolitan']['start'] = 1870;
	$stations[$key]['info']['Metropolitan']['end'] = 1949;
	$stations[$key]['info']['Bakerloo']['start'] = 1906;
	$stations[$key]['info']['Northern']['start'] = 1914;
	
	$key = 'Euston';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;
	$stations[$key]['info']['Victoria']['start'] = 1907;

	$key = 'Euston Square';
	$stations[$key]['dates']['start'] = 1863;
	$stations[$key]['info']['Metropolitan']['start'] = 1863;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	
	$key = 'Elephant & Castle';
	$stations[$key]['dates']['start'] = 1890;
	$stations[$key]['info']['Bakerloo']['start'] = 1906;
	$stations[$key]['info']['Northern']['start'] = 1890;

	$key = 'Farringdon';
	$stations[$key]['dates']['start'] = 1863;
	$stations[$key]['info']['Metropolitan']['start'] = 1863;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;

	$key = 'Goldhawk Road';
	$stations[$key]['dates']['start'] = 1864;
	$stations[$key]['info']['Metropolitan']['start'] = 1864;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	$stations[$key]['info']['Circle']['start'] = 2009;

	$key = 'Great Portland Street';
	$stations[$key]['dates']['start'] = 1863;
	$stations[$key]['info']['Metropolitan']['start'] = 1863;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	
	$key = 'Green Park';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = 1906;
	$stations[$key]['info']['Victoria']['start'] = 1969;
	$stations[$key]['info']['Jubilee']['start'] = 1979;

	$key = 'Hammersmith (Hammersmith & City and Circle lines)';
	$stations[$key] = $stations['Hammersmith'];
	unset($stations['Hammersmith']);
	$stations[$key]['name'] = $key;
	$stations[$key]['dates']['start'] = 1864;
	$stations[$key]['info']['Metropolitan']['start'] = 1990;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	$stations[$key]['info']['Circle']['start'] = 2009;

	$key = 'Hammersmith (Piccadilly and District lines)';
	$stations[$key]['name'] = $key;
	$stations[$key]['dates']['start'] = 1874;
	$stations[$key]['lat'] = 51.492699;
	$stations[$key]['long'] = -0.224400;
	$stations[$key]['lines'] = array('District', 'Piccadilly');
	$stations[$key]['info']['District']['start'] = 1874;
	$stations[$key]['info']['Piccadilly']['start'] = 1908;
	$stations[$key]['abstract'] = 'Hammersmith tube station is a London Underground station in Hammersmith. It is on the District Line line between Barons Court and Ravenscourt Park, and on the Piccadilly Line between Barons Court and Acton Town or Turnham Green at very early morning and late evening hours. The station is in Travelcard Zone 2. The Hammersmith and City Line\'s and Circle Line\'s station of the same name is a separate station to the north-west. The two stations are separated by Hammersmith Broadway.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/c/c5/Piccadilly_Line_platforms_at_Hammersmith_D+P_station.jpg/200px-Piccadilly_Line_platforms_at_Hammersmith_D+P_station.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/c/c5/Piccadilly_Line_platforms_at_Hammersmith_D+P_station.jpg';
	

	$key = 'Hillingdon';
	$stations[$key]['dates']['start'] = 1923;
	$stations[$key]['info']['Metropolitan']['start'] = 1923;
	$stations[$key]['info']['District']['start'] = 1923;
	$stations[$key]['info']['District']['end'] = 1933;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;

	$key = 'Holborn';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = 1906;
	$stations[$key]['info']['Central']['start'] = 1933;
	
	$key = 'Ickenham';
	$stations[$key]['dates']['start'] = 1905;
	$stations[$key]['info']['Metropolitan']['start'] = 1905;
	$stations[$key]['info']['District']['start'] = 1910;
	$stations[$key]['info']['District']['end'] = 1933;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;

	$key = 'King\'s Cross St. Pancras';
	$stations[$key]['dates']['start'] = 1863;
	$stations[$key]['info']['Metropolitan']['start'] = 1863;
	$stations[$key]['info']['Piccadilly']['start'] = 1906;
	$stations[$key]['info']['Northern']['start'] = 1907;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['Victoria']['start'] = 1968;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;

	$key = 'Ladbroke Grove';
	$stations[$key]['dates']['start'] = 1864;
	$stations[$key]['info']['Metropolitan']['start'] = 1864;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	$stations[$key]['info']['Circle']['start'] = 2009;

	$key = 'Latimer Road';
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['info']['Metropolitan']['start'] = 1868;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	$stations[$key]['info']['Circle']['start'] = 2009;

	$key = 'Leicester Square';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = 1906;
	$stations[$key]['info']['Northern']['start'] = 1906;

	$key = 'Liverpool Street';
	$stations[$key]['dates']['start'] = 1875;
	$stations[$key]['info']['Metropolitan']['start'] = 1875;
	$stations[$key]['info']['Central']['start'] = 1912;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	
	$key = 'London Bridge';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Northern']['start'] = 1900;
	$stations[$key]['info']['Jubilee']['start'] = 1999;

	$key = 'Mile End';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = 1902;
	$stations[$key]['info']['Metropolitan']['start'] = 1936;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Central']['start'] = 1946;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;

	$key = 'Moorgate';
	$stations[$key]['dates']['start'] = 1865;
	$stations[$key]['info']['Metropolitan']['start'] = 1865;
	$stations[$key]['info']['Northern']['start'] = 1900;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;

	$key = 'Oxford Circus';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Central']['start'] = 1900;
	$stations[$key]['info']['Bakerloo']['start'] = 1906;
	$stations[$key]['info']['Victoria']['start'] = 1969;

	$key = 'Paddington';
	$stations[$key]['dates']['start'] = 1863;
	$stations[$key]['info']['Metropolitan']['start'] = 1863;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	$stations[$key]['info']['District']['start'] = 1868;
	$stations[$key]['info']['Circle']['start'] = 1949;
	
	$key = 'Piccadilly Circus';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Bakerloo']['start'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = 1906;

	$key = 'Plaistow';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = 1902;
	$stations[$key]['info']['Metropolitan']['start'] = 1936;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;

	$key = 'Rayners Lane';
	$stations[$key]['dates']['start'] = 1904;
	$stations[$key]['info']['Metropolitan']['start'] = 1904;
	$stations[$key]['info']['District']['start'] = 1910;
	$stations[$key]['info']['District']['end'] = 1933;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;
	
	$key = 'Royal Oak';
	$stations[$key]['dates']['start'] = 1871;
	$stations[$key]['info']['Metropolitan']['start'] = 1871;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	$stations[$key]['info']['Circle']['start'] = 2009;
	
	$key = 'Ruislip';
	$stations[$key]['dates']['start'] = 1904;
	$stations[$key]['info']['Metropolitan']['start'] = 1904;
	$stations[$key]['info']['District']['start'] = 1910;
	$stations[$key]['info']['District']['end'] = 1933;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;
	
	$key = 'Ruislip Manor';
	$stations[$key]['dates']['start'] = 1912;
	$stations[$key]['info']['Metropolitan']['start'] = 1912;
	$stations[$key]['info']['District']['start'] = 1912;
	$stations[$key]['info']['District']['end'] = 1933;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;
	
	$key = 'Shepherd\'s Bush Market';
	$stations[$key]['lat'] = 51.50557999999999;
	$stations[$key]['long'] = -0.22635;
	$stations[$key]['dates']['start'] = 1864;
	$stations[$key]['info']['Metropolitan']['start'] = 1864;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	$stations[$key]['info']['Circle']['start'] = 2009;
	
	$key = 'South Kensington';
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Piccadilly']['start'] = 1906;
	$stations[$key]['info']['Metropolitan']['end'] = 1949;
	$stations[$key]['info']['Circle']['start'] = 1949;
	
	$key = 'Stepney Green';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = 1902;
	$stations[$key]['info']['Metropolitan']['start'] = 1936;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;

	$key = 'Stratford';
	$stations[$key]['dates']['start'] = 1946;
	$stations[$key]['info']['Central']['start'] = 1946;
	$stations[$key]['info']['Jubilee']['start'] = 1999;

	$key = 'Stockwell';
	$stations[$key]['dates']['start'] = 1890;
	$stations[$key]['info']['Northern']['start'] = 1890;
	$stations[$key]['info']['Victoria']['start'] = 1971;

	$key = 'Tottenham Court Road';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Central']['start'] = 1900;
	$stations[$key]['info']['Northern']['start'] = 1907;

	$key = 'Upton Park';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = 1902;
	$stations[$key]['info']['Metropolitan']['start'] = 1936;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;

	$key = 'Uxbridge';
	$stations[$key]['dates']['start'] = 1904;
	$stations[$key]['info']['Metropolitan']['start'] = 1904;
	$stations[$key]['info']['District']['start'] = 1910;
	$stations[$key]['info']['District']['end'] = 1933;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;

	$key = 'Warren Street';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;
	$stations[$key]['info']['Victoria']['start'] = 1968;

	$key = 'Waterloo';
	$stations[$key]['dates']['start'] = 1898;
	$stations[$key]['info']['Bakerloo']['start'] = 1906;
	$stations[$key]['info']['Northern']['start'] = 1926;
	$stations[$key]['info']['Waterloo & City']['start'] = 1898;
	$stations[$key]['info']['Jubilee']['start'] = 1999;
	
	$key = 'Westbourne Park';
	$stations[$key]['dates']['start'] = 1866;
	$stations[$key]['info']['Metropolitan']['start'] = 1886;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	$stations[$key]['info']['Circle']['start'] = 2009;
	
	$key = 'West Ham';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = 1902;
	$stations[$key]['info']['Metropolitan']['start'] = 1936;
	$stations[$key]['info']['Metropolitan']['end'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	$stations[$key]['info']['Jubilee']['start'] = 1999;

	$key = 'Westminster';
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['District']['start'] = 1868;
	$stations[$key]['info']['Jubilee']['start'] = 1999;
	
	$key = 'Whitechapel';
	$stations[$key]['dates']['start'] = 1876;
	$stations[$key]['info']['East London']['start'] = 1876;
	$stations[$key]['info']['East London']['end'] = 2007;
	$stations[$key]['info']['District']['start'] = 1884;
	$stations[$key]['info']['Metropolitan']['start'] = 1906;
	$stations[$key]['info']['Metropolitan']['start'] = 1990;
	$stations[$key]['info']['Hammersmith & City']['start'] = 1990;
	
	$key = 'Wood Lane';
	$stations[$key]['dates']['start'] = 2008;
	$stations[$key]['info']['Hammersmith & City']['start'] = 2008;
	$stations[$key]['info']['Circle']['start'] = 2009;
	
	fixup_bakerloo_stations($stations);
	fixup_central_stations($stations);
	fixup_district_stations($stations);
	fixup_east_london_stations($stations);
	fixup_hammersmith_city_stations($stations);
	fixup_jubilee_stations($stations);
	fixup_metropolitan_stations($stations);
	fixup_northern_stations($stations);
	fixup_piccadilly_stations($stations);
	fixup_victoria_stations($stations);
}

function fixup_bakerloo_stations(&$stations) {
	$key = 'Trafalgar Square';
	$stations[$key]['name'] = $key;
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['dates']['end'] = 1979;
	$stations[$key]['lines'] = array('Bakerloo');
	$stations[$key]['abstract'] = $stations['Charing Cross']['abstract'];
	$stations[$key]['thumbnail'] = $stations['Charing Cross']['thumbnail'];
	$stations[$key]['depiction'] = $stations['Charing Cross']['depiction'];
	$stations[$key]['info']['Bakerloo']['start'] = 1906;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['lat'] = 51.50772;
	$stations[$key]['long'] = -0.12752;
	
	$key = 'Wembley Central';
	$stations[$key]['dates']['start'] = 1917;
	$stations[$key]['info']['Bakerloo']['start'] = 1917;
	
	$key = 'Maida Vale';
	$stations[$key]['dates']['start'] = 1915;
	$stations[$key]['info']['Bakerloo']['start'] = 1915;
	
	$key = 'North Wembley';
	$stations[$key]['dates']['start'] = 1917;
	$stations[$key]['info']['Bakerloo']['start'] = 1917;

	$key = 'South Kenton';
	$stations[$key]['dates']['start'] = 1933;
	$stations[$key]['info']['Bakerloo']['start'] = 1933;
	
	$key = 'Kilburn Park';
	$stations[$key]['dates']['start'] = 1915;
	$stations[$key]['info']['Bakerloo']['start'] = 1915;
	
	$key = 'Harlesden';
	$stations[$key]['dates']['start'] = 1917;
	$stations[$key]['info']['Bakerloo']['start'] = 1917;
	
	$key = 'Marylebone';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Bakerloo']['start'] = 1907;
	
	$key = 'Willesden Junction';
	$stations[$key]['dates']['start'] = 1915;
	$stations[$key]['info']['Bakerloo']['start'] = 1915;
	
	$key = 'Kenton';
	$stations[$key]['dates']['start'] = 1917;
	$stations[$key]['info']['Bakerloo']['start'] = 1917;
	
	$key = 'Kensal Green';
	$stations[$key]['dates']['start'] = 1915;
	$stations[$key]['info']['Bakerloo']['start'] = 1915;

	$key = 'Regent\'s Park';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Bakerloo']['start'] = 1906;
	
	$key = 'Lambeth North';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Bakerloo']['start'] = 1906;
	
	$key = 'Queen\'s Park';
	$stations[$key]['dates']['start'] = 1915;
	$stations[$key]['info']['Bakerloo']['start'] = 1915;
	
	$key = 'Warwick Avenue';
	$stations[$key]['dates']['start'] = 1915;
	$stations[$key]['info']['Bakerloo']['start'] = 1915;
	
	$key = 'Edgware Road';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Bakerloo']['start'] = 1907;

	$key = 'Harrow & Wealdstone';
	$stations[$key]['dates']['start'] = 1917;
	$stations[$key]['info']['Bakerloo']['start'] = 1917;
	
	$key = 'Watford Junction';
	$stations[$key]['name'] = $key;
	//$stations[$key]['start'] = 1917;
	//$stations[$key]['end'] = 1982;
	$stations[$key]['dates']['start'] = 1917;
	$stations[$key]['dates']['end'] = 1982;
	$stations[$key]['info']['Bakerloo']['start'] = 1917;
	$stations[$key]['info']['Bakerloo']['end'] = 1982;
	$stations[$key]['lines'] = array('Bakerloo');
	
	$key = 'Watford High Street';
	$stations[$key]['name'] = $key;
	//$stations[$key]['start'] = 1917;
	//$stations[$key]['end'] = 1982;
	$stations[$key]['dates']['start'] = 1917;
	$stations[$key]['dates']['end'] = 1982;
	$stations[$key]['info']['Bakerloo']['start'] = 1917;
	$stations[$key]['info']['Bakerloo']['end'] = 1982;
	$stations[$key]['lines'] = array('Bakerloo');

	$key = 'Bushey';
	$stations[$key]['name'] = $key;
	//$stations[$key]['start'] = 1917;
	//$stations[$key]['end'] = 1982;
	$stations[$key]['dates']['start'] = 1917;
	$stations[$key]['dates']['end'] = 1982;
	$stations[$key]['info']['Bakerloo']['start'] = 1917;
	$stations[$key]['info']['Bakerloo']['end'] = 1982;
	$stations[$key]['lat'] = 51.644001;
	$stations[$key]['long'] = -0.385000	;
	$stations[$key]['lines'] = array('Bakerloo');
	$stations[$key]['abstract'] = 'Bushey railway station serves the towns of Bushey and Oxhey and is situated on the Watford DC Line, 8 km (5.0 mi) north of Harrow & Wealdstone. The station was renamed from "Bushey & Oxhey" to "Bushey" on 6 May 1974, even though it is actually sited in the neighbouring town of Oxhey, and the nearest part of Bushey (Bushey Village) is over a mile away. Even so it was late in the 1980s before signage at the station reflected this change.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/a/aa/Bushey_station_east_building.JPG/200px-Bushey_station_east_building.JPG';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/a/aa/Bushey_station_east_building.JPG';

	$key = 'Carpenders Park';
	$stations[$key]['name'] = $key;
	//$stations[$key]['start'] = 1919;
	//$stations[$key]['end'] = 1982;
	$stations[$key]['dates']['start'] = 1919;
	$stations[$key]['dates']['end'] = 1982;
	$stations[$key]['info']['Bakerloo']['start'] = 1919;
	$stations[$key]['info']['Bakerloo']['end'] = 1982;
	$stations[$key]['lat'] = 51.629002;
	$stations[$key]['long'] = -0.386000;
	$stations[$key]['lines'] = array('Bakerloo');
	$stations[$key]['abstract'] = 'Carpenders Park railway station lies between the Hertfordshire suburb of Carpenders Park and the South Oxhey housing estate, 3 km (1.9 mi) south of Watford Junction on the Watford DC Line. London Underground\'s Bakerloo Line trains served the station from 16 April 1917 until 24 September 1982. London Overground services from London Euston currently serve this station. The station is an island platform reached by a subway. This has exits to both the Carpenders Park (east) and South Oxhey (west) estates. The station was originally further north than the current site and was a wooden two platform structure with a footbridge. The original station was built to serve the nearby golf course. Ticket Barriers were installed in early 2010.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/b/b0/Carpendarspark999.JPG/200px-Carpendarspark999.JPG';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/b/b0/Carpendarspark999.JPG';

	$key = 'Hatch End';
	$stations[$key]['name'] = $key;
	//$stations[$key]['start'] = 1917;
	//$stations[$key]['end'] = 1982;
	$stations[$key]['dates']['start'] = 1917;
	$stations[$key]['dates']['end'] = 1982;
	$stations[$key]['info']['Bakerloo']['start'] = 1917;
	$stations[$key]['info']['Bakerloo']['end'] = 1982;
	$stations[$key]['lat'] = 51.602402;
	$stations[$key]['long'] = -0.356400;
	$stations[$key]['lines'] = array('Bakerloo');
	$stations[$key]['abstract'] = 'Hatch End railway station is in the London Borough of Harrow, in north London, and in Travelcard Zone 6, and is located at grid reference TQ130913. The station was built in 1911 to a design by architect Gerald Horsley, son of the painter John Calcott Horsley. It has two platforms. The northbound (down) platform is on the side of the ticket office and cafe. The southbound (up) platform is reached via a footbridge. This platform was originally an island platform with the other face on the adjacent down fast mainline. There was another island platform serving the up fast and down semi-fast lines and a further platform for the up semi-fasts. These other platforms fell out of use before the end of steam services on the mainline. A general rebuilding of the access to the two remaining platforms in use was built in the 1980s and a fence built along to shield waiting passengers from the fast trains.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Hatch_End_stn_building.JPG/200px-Hatch_End_stn_building.JPG';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/2/2a/Hatch_End_stn_building.JPG';

	$key = 'Headstone Lane';
	$stations[$key]['name'] = $key;
	//$stations[$key]['start'] = 1917;
	//$stations[$key]['end'] = 1982;
	$stations[$key]['dates']['start'] = 1917;
	$stations[$key]['dates']['end'] = 1982;
	$stations[$key]['info']['Bakerloo']['start'] = 1917;
	$stations[$key]['info']['Bakerloo']['end'] = 1982;
	$stations[$key]['lat'] = 51.609501;
	$stations[$key]['long'] = -0.368100;
	$stations[$key]['lines'] = array('Bakerloo');
	$stations[$key]['abstract'] = 'Headstone Lane is a railway station near Headstone, in the London Borough of Harrow. The station is in Travelcard Zone 5.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/e/ed/Headstone_Lane_stn_building.JPG/200px-Headstone_Lane_stn_building.JPG';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/e/ed/Headstone_Lane_stn_building.JPG';
	
	$key = 'Stonebridge Park';
	$stations[$key]['dates']['start'] = 1917;
	$stations[$key]['info']['Bakerloo']['start'] = 1917;
}

function fixup_central_stations(&$stations) {
	$key = 'Barkingside';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Bethnal Green';
	$stations[$key]['dates']['start'] = 1946;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Blake Hall';
	$stations[$key]['dates']['start'] = 1957;
	$stations[$key]['dates']['end'] = 1981;
	$stations[$key]['info']['Central']['start'] = 1957;
	$stations[$key]['info']['Central']['end'] = 1981;

	$key = 'Buckhurst Hill';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Chancery Lane';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Chigwell';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Debden';
	$stations[$key]['dates']['start'] = 1949;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'East Acton';
	$stations[$key]['dates']['start'] = 1920;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Epping';
	$stations[$key]['dates']['start'] = 1949;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Fairlop';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Gants Hill';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Grange Hill';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Greenford';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Hainault';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Hanger Lane';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Holland Park';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Lancaster Gate';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Leyton';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Leytonstone';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Loughton';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Marble Arch';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Newbury Park';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'North Acton';
	$stations[$key]['dates']['start'] = 1923;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Northolt';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'North Weald';
	$stations[$key]['dates']['start'] = 1957;
	$stations[$key]['dates']['end'] = 1994;
	$stations[$key]['info']['Central']['start'] = 1957;
	$stations[$key]['info']['Central']['end'] = 1994;

	$key = 'Ongar';
	$stations[$key]['dates']['start'] = 1957;
	$stations[$key]['dates']['end'] = 1994;
	$stations[$key]['info']['Central']['start'] = 1957;
	$stations[$key]['info']['Central']['end'] = 1994;

	$key = 'Perivale';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Queensway';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Redbridge';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Roding Valley';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Ruislip Gardens';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'St. Paul\'s';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Shepherd\'s Bush';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'South Ruislip';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'South Woodford';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Theydon Bois';
	$stations[$key]['dates']['start'] = 1949;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Wanstead';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'West Acton';
	$stations[$key]['dates']['start'] = 1923;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'West Ruislip';
	$stations[$key]['dates']['start'] = 1948;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'White City';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Woodford';
	$stations[$key]['dates']['start'] = 1947;
	$stations[$key]['info']['Central']['start'] = $stations[$key]['dates']['start'];

	$key = 'Wood Lane (Central line)';
	$stations[$key]['name'] = $key;
	$stations[$key]['dates']['start'] = 1908;
	$stations[$key]['dates']['end'] = 1947;
	$stations[$key]['lat'] = 51.509113;
	$stations[$key]['long'] = -0.224361;
	$stations[$key]['lines'] = array('Central');
	$stations[$key]['info']['Central']['start'] = 1908;
	$stations[$key]['info']['Central']['end'] = 1947;
	$stations[$key]['abstract'] = 'Wood Lane tube station is a disused station on the Central Line of the London Underground. It was built to serve the Franco-British Exhibition of 1908 and the 1908 Summer Olympics. The location of the station was confined and its configuration was awkward, requiring alterations on a number of occasions to meet operational requirements. A station of the same name is located on the Hammersmith &amp; City line.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/3/36/Wood_Lane_%28Central_line%29_tube_station_2001.png/320px-Wood_Lane_%28Central_line%29_tube_station_2001.png';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/3/36/Wood_Lane_%28Central_line%29_tube_station_2001.png';
}

function fixup_district_stations(&$stations) {
	$key = 'Becontree';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Blackfriars';
	$stations[$key]['dates']['start'] = 1870;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Circle']['start'] = 1949;

	$key = 'Cannon Street';
	$stations[$key]['dates']['start'] = 1884;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = 1949;
	$stations[$key]['info']['Circle']['start'] = 1949;

	$key = 'Chiswick Park';
	$stations[$key]['dates']['start'] = 1879;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Dagenham East';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Dagenham Heathway';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Ealing Broadway';
	$stations[$key]['dates']['start'] = 1879;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Central']['start'] = 1920;

	$key = 'Earl\'s Court';
	$stations[$key]['dates']['start'] = 1869;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Piccadilly']['start'] = 1906;

	$key = 'East Putney';
	$stations[$key]['dates']['start'] = 1889;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Elm Park';
	$stations[$key]['dates']['start'] = 1935;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Fulham Broadway';
	$stations[$key]['dates']['start'] = 1880;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Gunnersbury';
	$stations[$key]['dates']['start'] = 1877;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = 1906;

	$key = 'High Street Kensington';
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['start'] = 1871;
	$stations[$key]['info']['Metropolitan']['end'] = 1949;
	$stations[$key]['info']['Circle']['start'] = 1949;

	$key = 'Hornchurch';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Hounslow Barracks';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.473017;
	$stations[$key]['long'] = -0.385410;
	$stations[$key]['dates']['start'] = 1884;
	$stations[$key]['dates']['end'] = 1925;
	$stations[$key]['lines'] = array('District');
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = $stations[$key]['dates']['end'];
	$stations[$key]['abstract'] = 'The station was opened by the Metropolitan District Railway (MDR, now the District line) on 21 July 1884. The station was originally named Hounslow Barracks in reference to the Cavalry Barracks, Hounslow south of the station on Beavers Lane. The station was the terminus of a single track branch line constructed from the MDR\'s existing route to Hounslow Town station (now closed) on Hounslow High Street.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/3/3b/Hounslow_West_Station.jpg/120px-Hounslow_West_Station.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/archive/3/3b/20110318192730%21Hounslow_West_Station.jpg/120px-Hounslow_West_Station.jpg';
	
	$key = 'Hounslow Town';
	$stations[$key]['dates']['start'] = 1883;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Kensington (Olympia)';
	$stations[$key] = $stations['Kensington'];
	unset($stations['Kensington']);
	$stations[$key]['name'] = $key;
	$stations[$key]['dates']['start'] = 1864;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['start'] = 1872;
	$stations[$key]['info']['Metropolitan']['end'] = 1905;

	$key = 'Kew Gardens';
	$stations[$key]['dates']['start'] = 1877;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = 1906;
	
	$key = 'Mansion House';
	$stations[$key]['dates']['start'] = 1871;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Circle']['start'] = 1949;

	$key = 'Notting Hill Gate';
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Central']['start'] = 1900;
	$stations[$key]['info']['District']['start'] = 1926;
	$stations[$key]['info']['Circle']['start'] = 1949;

	$key = 'Osterley & Spring Grove';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.483033;
	$stations[$key]['long'] = -0.349568;
	$stations[$key]['dates']['start'] = 1883;
	$stations[$key]['dates']['end'] = 1925;
	$stations[$key]['lines'] = array('District');
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = $stations[$key]['dates']['end'];
	$stations[$key]['abstract'] = 'The station was opened as Osterley for Spring Grove but this was soon shortened to Osterley. There is some confusion over the name, with the board at the front of the station showing Osterley Park & Spring Grove but the platform signs just showing Osterley. From the outset tickets showed the name as Osterley. As London expanded during the 20th century the station was unable to cope with increased passenger numbers so a new larger station was built on the Great West Road. Soldiers were billeted in the old station building during WW2 but since 1967 it has been a bookshop.';

	$key = 'Parsons Green';
	$stations[$key]['dates']['start'] = 1880;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Putney Bridge';
	$stations[$key]['dates']['start'] = 1880;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Ravenscourt Park';
	$stations[$key]['dates']['start'] = 1877;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = 1906;

	$key = 'Richmond';
	$stations[$key]['dates']['start'] = 1887;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Sloane Square';
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Circle']['start'] = 1949;
	
	$key = 'South Acton';
	$stations[$key]['dates']['start'] = 1905;
	$stations[$key]['dates']['end'] = 1959;
	$stations[$key]['lines'] = array('District');
	$stations[$key]['info']['District']['start'] = 1905;
	$stations[$key]['info']['District']['end'] = 1959;

	$key = 'Southfields';
	$stations[$key]['dates']['start'] = 1889;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'St. James\'s Park';
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Circle']['start'] = 1949;

	$key = 'Stamford Brook';
	$stations[$key]['dates']['start'] = 1877;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = 1906;

	$key = 'Temple';
	$stations[$key]['dates']['start'] = 1870;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = 1949;
	$stations[$key]['info']['Circle']['start'] = 1949;

	$key = 'Tower Hill';
	$stations[$key]['dates']['start'] = 1967;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Circle']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Turnham Green';
	$stations[$key]['dates']['start'] = 1877;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = 1963;

	$key = 'Upminster';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Upminster Bridge';
	$stations[$key]['dates']['start'] = 1902;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Upney';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];

	$key = 'Victoria';
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Circle']['start'] = 1949;
	$stations[$key]['info']['Victoria']['start'] = 1968;

	$key = 'West Brompton';
	$stations[$key]['dates']['start'] = 1869;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];


	$key = 'West Kensington';
	$stations[$key]['dates']['start'] = 1874;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Wimbledon';
	$stations[$key]['dates']['start'] = 1889;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	unset($stations[$key]['lines']['Circle']);
	
	$key = 'Wimbledon Park';
	$stations[$key]['dates']['start'] = 1889;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
}

function fixup_east_london_stations(&$stations) {
	$key = 'Shoreditch';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.522795;
	$stations[$key]['long'] = -0.070798;
	$stations[$key]['dates']['start'] = 1869;
	$stations[$key]['dates']['end'] = 2006;
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['info']['East London']['start'] = 1869;
	$stations[$key]['info']['East London']['end'] = 2006;
	$stations[$key]['abstract'] = 'Shoreditch tube station was a London Underground station in the London Borough of Tower Hamlets in east London. It was in Travelcard Zone 2. The station closed permanently at the end of traffic on 9 June 2006. It was the northern terminus of the East London Line, with latterly a single platform alongside a single track that ran next to the disused Bishopsgate Goods Yard. Until the late 1960s the East London Line connected with the main line railway to Liverpool Street (and Bishopsgate until 1916) just north of Shoreditch station. The site of the link is still visible from the end of the platform and from Greater Anglia main line trains between Stratford and Liverpool Street. The station was one of only a handful on the network with a single platform and a single track layout, though it originally had two tracks and platforms. The preceding station was Whitechapel, which was the northern terminus of the East London Line until the line closed for extension in December 2007.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/c/c2/Shoreditch_tube_station_lar.jpg/300px-Shoreditch_tube_station_lar.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/c/c2/Shoreditch_tube_station_lar.jpg';


	$key = 'Shadwell';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.5112;
	$stations[$key]['long'] = -0.05698;
	$stations[$key]['dates']['start'] = 1876;
	$stations[$key]['dates']['end'] = 2007;
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['info']['East London']['start'] = 1876;
	$stations[$key]['info']['East London']['end'] = 2007;
	$stations[$key]['abstract'] = 'Shadwell railway station is on the East London Line of London Overground, between Whitechapel to the north and Wapping to the south. It is located near to Shadwell DLR station. The station is in Zone 2. The Overground station is underground (the DLR station is on a viaduct). The Overground platforms are decorated with enamel panels designed by Sarah McMenemy in 1995.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/d/d9/Shadwell_station_%28East_London_Line%29_south_entrance_April2010.jpg/240px-Shadwell_station_%28East_London_Line%29_south_entrance_April2010.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/d/d9/Shadwell_station_%28East_London_Line%29_south_entrance_April2010.jpg';

	$key = 'Wapping';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.504398;
	$stations[$key]['long'] = -0.055800;
	$stations[$key]['dates']['start'] = 1869;
	$stations[$key]['dates']['end'] = 2007;
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['info']['East London']['start'] = 1869;
	$stations[$key]['info']['East London']['end'] = 2007;
	$stations[$key]['abstract'] = 'Wapping railway station is on the northern bank of the river Thames in Wapping, East London, England. It is in Zone 2, and on the East London Line of London Overground between Shadwell and Rotherhithe. After recent temporary closures for remodelling, the station reopened for preview services on 27 April 2010 for services to New Cross and New Cross Gate, and from 23 May 2010 trains to/from New Cross Gate were extended to West Croydon / Crystal Palace.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/4/44/Wapping_station_building_April2010.jpg/240px-Wapping_station_building_April2010.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/4/44/Wapping_station_building_April2010.jpg';

	$key = 'Rotherhithe';
	$stations[$key]['dates']['start'] = 1869;
	$stations[$key]['dates']['end'] = 2007;
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['info']['East London']['start'] = 1869;
	$stations[$key]['info']['East London']['end'] = 2007;

	$key = 'Canada Water';
	$stations[$key]['lines'][] = 'East London';
	
	$key = 'Surrey Quays';
	$stations[$key]['dates']['start'] = 1869;
	$stations[$key]['dates']['end'] = 2007;
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['info']['East London']['start'] = 1869;
	$stations[$key]['info']['East London']['end'] = 2007;

	$key = 'New Cross Gate';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.475498;
	$stations[$key]['long'] = -0.040200;
	$stations[$key]['dates']['start'] = 1839;
	$stations[$key]['dates']['end'] = 2007;
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['info']['East London']['start'] = 1839;
	$stations[$key]['info']['East London']['end'] = 2007;
	$stations[$key]['abstract'] = 'New Cross Gate station is a railway station in New Cross, London, on the Brighton Main Line and the East London Line. It is about 600 metres west of New Cross station. It is in Travelcard Zone 2, and is operated by London Overground.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/8/87/New_Cross_Gate_station.jpg/240px-New_Cross_Gate_station.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/8/87/New_Cross_Gate_station.jpg';

	$key = 'New Cross';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.476601;
	$stations[$key]['long'] = -0.032700;
	$stations[$key]['dates']['start'] = 1839;
	$stations[$key]['dates']['end'] = 2007;
	$stations[$key]['lines'] = array('East London');
	$stations[$key]['info']['East London']['start'] = 1839;
	$stations[$key]['info']['East London']['end'] = 2007;
	$stations[$key]['abstract'] = 'New Cross railway station is a railway station in New Cross, London, England, and is in London Travelcard Zone 2. The platforms are lettered A to D so as to differentiate them from those at New Cross Gate. Platform D is used exclusively by London Overground services. Ticket barriers control access to all platforms.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/f/f4/East_London_Line_terminus%2C_New_Cross_-_geograph.org.uk_-_481877.jpg/220px-East_London_Line_terminus%2C_New_Cross_-_geograph.org.uk_-_481877.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/f/f4/East_London_Line_terminus%2C_New_Cross_-_geograph.org.uk_-_481877.jpg';
}

function fixup_hammersmith_city_stations(&$stations) {
	//$stations['Shepherd\'s Bush Market']['lat'] = 51.50557999999999;
	//$stations['Shepherd\'s Bush Market']['long'] = -0.22635;
}

function fixup_jubilee_stations(&$stations) {
	$key = 'Bermondsey';
	$stations[$key]['lat'] = 51.49795;
	$stations[$key]['long'] = -0.06373999999999999;
	$stations[$key]['dates']['start'] = 1999;
	$stations[$key]['info']['Jubilee']['start'] = 1999;
	
	$key = 'Canary Wharf';
	$stations[$key]['dates']['start'] = 1999;
	$stations[$key]['info']['Jubilee']['start'] = 1999;
	
	$key = 'Canning Town';
	$stations[$key]['dates']['start'] = 1999;
	$stations[$key]['info']['Jubilee']['start'] = 1999;

	$key = 'Canons Park';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['Metropolitan']['start'] = 1932;
	$stations[$key]['info']['Metropolitan']['end'] = 1939;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;

	$key = 'Dollis Hill';
	$stations[$key]['dates']['start'] = 1909;
	$stations[$key]['info']['Metropolitan']['start'] = 1909;
	$stations[$key]['info']['Metropolitan']['end'] = 1939;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
	
	$key = 'Finchley Road';
	$stations[$key]['dates']['start'] = 1879;
	$stations[$key]['info']['Metropolitan']['start'] = 1879;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
	
	$key = 'Kilburn';
	$stations[$key]['dates']['start'] = 1879;
	$stations[$key]['info']['Metropolitan']['start'] = 1879;
	$stations[$key]['info']['Metropolitan']['end'] = 1939;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
	
	$key = 'Kingsbury';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['Metropolitan']['start'] = 1932;
	$stations[$key]['info']['Metropolitan']['end'] = 1939;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
	
	$key = 'Neasden';
	$stations[$key]['dates']['start'] = 1880;
	$stations[$key]['info']['Metropolitan']['start'] = 1880;
	$stations[$key]['info']['Metropolitan']['end'] = 1939;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;

	$key = 'North Greenwich';
	$stations[$key]['dates']['start'] = 1999;
	$stations[$key]['info']['Jubilee']['start'] = 1999;
	
	$key = 'Queensbury';
	$stations[$key]['dates']['start'] = 1934;
	$stations[$key]['info']['Metropolitan']['start'] = 1934;
	$stations[$key]['info']['Metropolitan']['end'] = 1939;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
	
	$key = 'Southwark';
	$stations[$key]['dates']['start'] = 1999;
	$stations[$key]['info']['Jubilee']['start'] = 1999;

	$key = 'St John\'s Wood';
	$stations[$key]['lat'] = 51.534721;
	$stations[$key]['long'] = -0.174167;
	$stations[$key]['dates']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;

	$key = 'Stanmore';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['Metropolitan']['start'] = 1932;
	$stations[$key]['info']['Metropolitan']['end'] = 1939;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
	
	$key = 'Swiss Cottage';
	$stations[$key]['dates']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
	
	$key = 'Wembley Park';
	$stations[$key]['dates']['start'] = 1880;
	$stations[$key]['info']['Metropolitan']['start'] = 1880;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
	
	$key = 'West Hampstead';
	$stations[$key]['dates']['start'] = 1879;
	$stations[$key]['info']['Metropolitan']['start'] = 1879;
	$stations[$key]['info']['Metropolitan']['end'] = 1939;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
	
	$key = 'Willesden Green';
	$stations[$key]['dates']['start'] = 1879;
	$stations[$key]['info']['Metropolitan']['start'] = 1879;
	$stations[$key]['info']['Metropolitan']['end'] = 1939;
	$stations[$key]['info']['Bakerloo']['start'] = 1939;
	$stations[$key]['info']['Bakerloo']['end'] = 1979;
	$stations[$key]['info']['Jubilee']['start'] = 1979;
}

function fixup_metropolitan_stations(&$stations) {
	$key = 'Amersham';
	$stations[$key]['dates']['start'] = 1892;
	$stations[$key]['info']['Metropolitan']['start'] = 1892;
	
	$key = 'Aylesbury';
	$stations[$key]['dates']['start'] = 1863;
	$stations[$key]['dates']['end'] = 1961;
	$stations[$key]['info']['Metropolitan']['start'] = 1863;
	$stations[$key]['info']['Metropolitan']['end'] = 1961;

	$key = 'Brill';
	$stations[$key]['dates']['start'] = 1899;
	$stations[$key]['dates']['end'] = 1935;
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = 1899;
	$stations[$key]['info']['Metropolitan']['end'] = 1935;

	$key = 'Chalfont & Latimer';
	$stations[$key]['dates']['start'] = 1889;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];

	$key = 'Chesham';
	$stations[$key]['dates']['start'] = 1889;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];

	$key = 'Chorleywood';
	$stations[$key]['dates']['start'] = 1889;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Croxley';
	$stations[$key]['dates']['start'] = 1925;
	$stations[$key]['info']['Metropolitan']['start'] = 1925;

	$key = 'Gloucester Road';
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = 1949;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Piccadilly']['start'] = 1906;
	$stations[$key]['info']['Circle']['start'] = 1949;
	
	$key = 'Granborough Road';
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = $stations[$key]['dates']['end'];

	$key = 'Great Missenden';
	$stations[$key]['dates']['end'] = 1961;
	$stations[$key]['info']['Metropolitan']['end'] = 1961;
	
	$key = 'Harrow-on-the-Hill';
	$stations[$key]['dates']['start'] = 1880;
	$stations[$key]['info']['Metropolitan']['start'] = 1880;
	
	$key = 'Lord\'s';
	$stations[$key]['dates']['end'] = 1939;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = 1939;

	$key = 'Moor Park';
	$stations[$key]['dates']['start'] = 1910;
	$stations[$key]['info']['Metropolitan']['start'] = 1910;
	
	$key = 'North Harrow';
	$stations[$key]['dates']['start'] = 1915;
	$stations[$key]['info']['Metropolitan']['start'] = 1915;
	
	$key = 'Northwick Park';
	$stations[$key]['dates']['start'] = 1923;
	$stations[$key]['info']['Metropolitan']['start'] = 1923;
	
	$key = 'Northwood';
	$stations[$key]['dates']['start'] = 1887;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Northwood Hills';
	$stations[$key]['dates']['start'] = 1933;
	$stations[$key]['info']['Metropolitan']['start'] = 1933;
	
	$key = 'Pinner';
	$stations[$key]['dates']['start'] = 1885;
	$stations[$key]['info']['Metropolitan']['start'] = 1885;
	
	$key = 'Preston Road';
	$stations[$key]['dates']['start'] = 1908;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Quainton Road';
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['dates']['end'] = 1936;
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = 1868;
	$stations[$key]['info']['Metropolitan']['end'] = 1936;

	$key = 'Rickmansworth';
	$stations[$key]['dates']['start'] = 1887;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];

	$key = 'Stoke Mandeville';
	$stations[$key]['dates']['end'] = 1961;
	$stations[$key]['info']['Metropolitan']['end'] = $stations[$key]['dates']['end'];

	$key = 'Swiss Cottage (Metropolitan line)';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.543740;
	$stations[$key]['long'] = -0.175363;
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['dates']['end'] = 1940;
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = 1868;
	$stations[$key]['info']['Metropolitan']['end'] = 1940;
	$stations[$key]['abstract'] = 'Swiss Cottage (Metropolitan Line) is a disused London Underground station. It was opened in 1868 as the northern terminus of the Metropolitan and St John\'s Wood Railway, the first northward branch extension from Baker Street of the Metropolitan Railway. From here (starting in 1879) the line was later extended north into Middlesex, Hertfordshire and Buckinghamshire reaching Watford, Aylesbury, Chesham and Uxbridge. In the mid 1930s the Metropolitan line was suffering congestion at the south end of its main route where trains from its many branches were struggling to share the limited capacity of its tracks between Finchley Road and Baker Street stations. To ease this congestion a new section of deep-level tunnel was constructed between Finchley Road and the Bakerloo line tunnels at Baker Street station. The Metropolitan line\'s Stanmore branch services were then transferred to the Bakerloo line on 20 November 1939 and diverted to run into Baker Street in the new tunnels, thus reducing the number of trains using the Metropolitan line\'s tracks. With the new deep tunnel route, a new Swiss Cottage Bakerloo line station was opened adjacent to the existing Metropolitan line\'s station and, for a time, these operated as a single station (platforms 1 and 2 were Metropolitan line, platforms 3 and 4 were Bakerloo line). This arrangement was short-lived, however, and the Metropolitan Line station was closed on 17 August 1940 as a wartime economy. With the opening of the Jubilee line in 1979, the Stanmore branch of the Bakerloo line, including the replacement Swiss Cottage station, was transferred to be part of the new Jubilee line.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/d/d0/I00007wq.jpg/200px-I00007wq.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/d/d0/I00007wq.jpg';

	$key = 'Tower of London';
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = $stations[$key]['dates']['end'];

	$key = 'Uxbridge Road';
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = $stations[$key]['dates']['end'];

	$key = 'Verney Junction';
	$stations[$key]['dates']['start'] = 1868;
	$stations[$key]['dates']['end'] = 1936;
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = 1868;
	$stations[$key]['info']['Metropolitan']['end'] = 1936;

	$key = 'Waddesdon';
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = $stations[$key]['dates']['end'];

	$key = 'Waddesdon Road';
	$stations[$key]['dates']['start'] = 1894;
	$stations[$key]['dates']['end'] = 1935;
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = $stations[$key]['dates']['end'];

	$key = 'Watford';
	$stations[$key]['dates']['start'] = 1961;
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Wendover';
	$stations[$key]['dates']['end'] = 1961;
	$stations[$key]['info']['Metropolitan']['end'] = $stations[$key]['dates']['end'];

	$key = 'Westcott';
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = $stations[$key]['dates']['end'];

	$key = 'West Harrow';
	$stations[$key]['lat'] = 51.57971000000001;
	$stations[$key]['long'] = -0.35338;
	$stations[$key]['dates']['start'] = 1913;
	$stations[$key]['info']['Metropolitan']['start'] = 1913;

	$key = 'Winslow Road';
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = $stations[$key]['dates']['end'];

	$key = 'Wood Siding';
	$stations[$key]['dates']['start'] = 1894;
	$stations[$key]['dates']['end'] = 1935;
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = $stations[$key]['dates']['end'];

	$key = 'Wotton';
	$stations[$key]['lines'] = array('Metropolitan');
	$stations[$key]['info']['Metropolitan']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Metropolitan']['end'] = $stations[$key]['dates']['end'];
}

function fixup_northern_stations(&$stations) {
	$key = 'Angel';
	$stations[$key]['dates']['start'] = 1901;
	$stations[$key]['info']['Northern']['start'] = 1901;

	$key = 'Archway';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;

	$key = 'Belsize Park';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;

	$key = 'Balham';
	$stations[$key]['dates']['start'] = 1926;
	$stations[$key]['info']['Northern']['start'] = 1926;

	$key = 'Borough';
	$stations[$key]['dates']['start'] = 1890;
	$stations[$key]['info']['Northern']['start'] = 1890;

	$key = 'Brent Cross';
	$stations[$key]['dates']['start'] = 1923;
	$stations[$key]['info']['Northern']['start'] = 1923;

	$key = 'Burnt Oak';
	$stations[$key]['dates']['start'] = 1924;
	$stations[$key]['info']['Northern']['start'] = 1924;

	$key = 'Camden Town';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;

	$key = 'Chalk Farm';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;

	$key = 'Clapham Common';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Northern']['start'] = 1900;

	$key = 'Clapham North';
	$stations[$key]['dates']['start'] = 1900;
	$stations[$key]['info']['Northern']['start'] = 1900;

	$key = 'Clapham South';
	$stations[$key]['dates']['start'] = 1926;
	$stations[$key]['info']['Northern']['start'] = 1926;
	
	$key = 'Colindale';
	$stations[$key]['lat'] = 51.59542;
	$stations[$key]['long'] = -0.24989;
	$stations[$key]['dates']['start'] = 1924;
	$stations[$key]['info']['Northern']['start'] = 1924;

	$key = 'Colliers Wood';
	$stations[$key]['dates']['start'] = 1926;
	$stations[$key]['info']['Northern']['start'] = 1926;

	$key = 'East Finchley';
	$stations[$key]['dates']['start'] = 1939;
	$stations[$key]['info']['Northern']['start'] = 1939;

	$key = 'Edgware';
	$stations[$key]['dates']['start'] = 1924;
	$stations[$key]['info']['Northern']['start'] = 1924;

	$key = 'Finchley Central';
	$stations[$key]['dates']['start'] = 1940;
	$stations[$key]['info']['Northern']['start'] = 1940;

	$key = 'Golders Green';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;

	$key = 'Goodge Street';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;

	$key = 'High Barnet';
	$stations[$key]['dates']['start'] = 1940;
	$stations[$key]['info']['Northern']['start'] = 1940;

	$key = 'Highgate';
	$stations[$key]['dates']['start'] = 1941;
	$stations[$key]['info']['Northern']['start'] = 1941;

	$key = 'Kennington';
	$stations[$key]['dates']['start'] = 1890;
	$stations[$key]['info']['Northern']['start'] = 1890;

	$key = 'Kentish Town';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;

	$key = 'King William Street';
	$stations[$key]['name'] = $key;
	$stations[$key]['lat'] = 51.510300;
	$stations[$key]['long'] = -0.086944;
	$stations[$key]['dates']['start'] = 1890;
	$stations[$key]['dates']['end'] = 1900;
	$stations[$key]['lines'] = array('Northern');
	$stations[$key]['info']['Northern']['start'] = 1890;
	$stations[$key]['info']['Northern']['end'] = 1900;
	$stations[$key]['abstract'] = 'King William Street was the original but short-lived northern terminus of the City & South London Railway (C&SLR), the first deep tube underground railway in London and one of the component parts of the London Underground\'s Northern Line. It was in the City of London, on King William Street, just south of the present Monument station. When the station was in operation the next station south was Borough and the southern terminus of the line was Stockwell. King William Street opened on 18 December 1890 and was constructed from a large masonry station tunnel accessed from the surface by a lift shaft or spiral staircase. Two platforms were provided, one on each side of the single, central track—one for passengers entering and one for passengers leaving the trains—a system later referred to as the Spanish solution. The station tunnel itself is situated beneath Monument Street and runs east-west across King William Street, ending beneath Arthur Street. The approach running tunnels had sharp curves and steep gradients in order to dive underneath the River Thames while remaining under public rights-of-way, in particular Swan Lane and Arthur Street. The combination of station layout and poor alignment of the running tunnels severely limited the capacity of the station and in the years after opening a number of initiatives were made to improve operations. In 1895 a central island platform with tracks each side was constructed to enable two trains to occupy the station at once; however, capacity remained restricted. When the line was extended northwards to Moorgate station, new running tunnels on a different alignment, but still beneath Borough High Street, were constructed running from below St George the Martyr\'s Church, north of Borough station to a new station at London Bridge station and onwards to an alternative City station at Bank. Under the river Thames the present running tunnels of the northern line are situated to the east of London Bridge, whereas the King William St tunnels pass to the west of the bridge, the southbound tunnel below the northbound as the line passes under the Thames. The station closed on 24 February 1900. The original station building was demolished in the 1930s, although the parts of the station below ground were converted for use as a public air-raid shelter during World War II. Access today is via a manhole in the basement of Regis House, a modern day office building, where the original cast iron spiral staircase leads down to platform level. The lift shaft was infilled with concrete during the construction of the original Regis House. The original running tunnels north of Borough tube station remain, although when the Jubilee Line Extension was built in the late 1990s the old southbound tunnel was cut through as part of the construction works at London Bridge station in order to provide the lift shaft situated at the south end of the northern line platforms. These running tunnels now serve as a ventilation shaft for the station and the openings for several adits to the old running tunnels can be seen in the roofs of the Northern Line platform tunnels and in the central concourse between them. A construction shaft between London Bridge and King William Street, beneath Old Swan Wharf, now serves as a pump shaft for the disused sections of running tunnels. It is no longer possible to walk through between the two stations as the old C&SLR running tunnels have been blocked off with concrete bulkheads either side of the River Thames.';
	$stations[$key]['thumbnail'] = 'http://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Site_of_King_William_Street_Underground_Station.jpg/200px-Site_of_King_William_Street_Underground_Station.jpg';
	$stations[$key]['depiction'] = 'http://upload.wikimedia.org/wikipedia/commons/a/ab/Site_of_King_William_Street_Underground_Station.jpg';

	$key = 'Hampstead';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;

	$key = 'Hendon Central';
	$stations[$key]['dates']['start'] = 1923;
	$stations[$key]['info']['Northern']['start'] = 1923;

	$key = 'Mill Hill East';
	$stations[$key]['dates']['start'] = 1941;
	$stations[$key]['info']['Northern']['start'] = 1941;

	$key = 'Morden';
	$stations[$key]['dates']['start'] = 1926;
	$stations[$key]['info']['Northern']['start'] = 1926;

	$key = 'Mornington Crescent';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;

	$key = 'Old Street';
	$stations[$key]['dates']['start'] = 1904;
	$stations[$key]['info']['Northern']['start'] = 1904;

	$key = 'Oval';
	$stations[$key]['dates']['start'] = 1890;
	$stations[$key]['info']['Northern']['start'] = 1890;
	
	$key = 'South Wimbledon';
	$stations[$key]['dates']['start'] = 1926;
	$stations[$key]['info']['Northern']['start'] = 1926;

	$key = 'Tooting Bec';
	$stations[$key]['dates']['start'] = 1926;
	$stations[$key]['info']['Northern']['start'] = 1926;

	$key = 'Tooting Broadway';
	$stations[$key]['dates']['start'] = 1926;
	$stations[$key]['info']['Northern']['start'] = 1926;

	$key = 'Totteridge and Whetstone';
	$stations[$key]['dates']['start'] = 1940;
	$stations[$key]['info']['Northern']['start'] = 1940;

	$key = 'Tufnell Park';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Northern']['start'] = 1907;

	$key = 'West Finchley';
	$stations[$key]['dates']['start'] = 1940;
	$stations[$key]['info']['Northern']['start'] = 1940;

	$key = 'Woodside Park';
	$stations[$key]['dates']['start'] = 1940;
	$stations[$key]['info']['Northern']['start'] = 1940;
}

function fixup_piccadilly_stations(&$stations) {
	$key = 'Aldwych';
	//error_log(var_export($stations[$key], true));
	unset($stations[$key]['start']);
	unset($stations[$key]['end']);
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['dates']['end'] = 1994;
	$stations[$key]['lines']  = array('Piccadilly');
	$stations[$key]['info']['Piccadilly']['start'] = 1907;
	$stations[$key]['info']['Piccadilly']['end'] = 1994;
	unset($stations[$key]['info']['Jubilee']);

	$key = 'Alperton';
	$stations[$key]['dates']['start'] = 1903;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = 1932;
	$stations[$key]['info']['Piccadilly']['start'] = 1932;

	$key = 'Arnos Grove';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'Arsenal';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Barons Court';
	$stations[$key]['dates']['start'] = 1874;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Piccadilly']['start'] = 1906;

	$key = 'Bounds Green';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Caledonian Road';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'Cockfosters';
	$stations[$key]['dates']['start'] = 1933;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'Covent Garden';
	$stations[$key]['dates']['start'] = 1907;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'Ealing Common';
	$stations[$key]['dates']['start'] = 1879;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Piccadilly']['start'] = 1932;

	$key = 'Finsbury Park';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['Victoria']['start'] = 1968;

	$key = 'Hatton Cross';
	$stations[$key]['lat'] = 51.46673999999999;
	$stations[$key]['long'] = -0.42317;
	$stations[$key]['dates']['start'] = 1975;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'Heathrow Terminals 1, 2, 3';
	$stations[$key]['dates']['start'] = 1977;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'Heathrow Terminal 4';
	$stations[$key]['dates']['start'] = 1986;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Heathrow Terminal 5';
	$stations[$key]['dates']['start'] = 2008;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Holloway Road';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'Hounslow East';
	$stations[$key]['dates']['start'] = 1909;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = 1964;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;

	$key = 'Hounslow Central';
	$stations[$key]['dates']['start'] = 1886;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = 1964;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;

	$key = 'Hounslow West';
	$stations[$key]['dates']['start'] = 1925;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = 1964;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;

	$key = 'Hyde Park Corner';
	$stations[$key]['lat'] = 51.50303;
	$stations[$key]['long'] = -0.15242;
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'Knightsbridge';
	$stations[$key]['lat'] = 51.50167;
	$stations[$key]['long'] = -0.16048;
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = 1906;
	
	$key = 'Manor House';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'North Ealing';
	$stations[$key]['dates']['start'] = 1903;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = 1932;
	$stations[$key]['info']['Piccadilly']['start'] = 1932;

	$key = 'Northfields';
	$stations[$key]['dates']['start'] = 1883;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = 1964;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;

	$key = 'Osterley';
	$stations[$key]['dates']['start'] = 1925;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = 1964;
	$stations[$key]['info']['Piccadilly']['start'] = 1933;

	$key = 'Park Royal';
	$stations[$key]['dates']['start'] = 1903;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = 1932;
	$stations[$key]['info']['Piccadilly']['start'] = 1932;

	$key = 'Russell Square';
	$stations[$key]['dates']['start'] = 1906;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'South Ealing';
	$stations[$key]['dates']['start'] = 1883;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = 1964;
	$stations[$key]['info']['Piccadilly']['start'] = 1935;

	$key = 'Oakwood';
	$stations[$key]['dates']['start'] = 1933;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'South Harrow';
	$stations[$key]['dates']['start'] = 1903;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = 1932;
	$stations[$key]['info']['Piccadilly']['start'] = 1932;

	$key = 'Southgate';
	$stations[$key]['dates']['start'] = 1933;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'Sudbury Hill';
	$stations[$key]['dates']['start'] = 1903;
	$stations[$key]['info']['District']['start'] = $stations[$key]['dates']['start'];
	$stations[$key]['info']['District']['end'] = 1932;
	$stations[$key]['info']['Piccadilly']['start'] = 1932;
	
	$key = 'Turnpike Lane';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

	$key = 'Wood Green';
	$stations[$key]['dates']['start'] = 1932;
	$stations[$key]['info']['Piccadilly']['start'] = $stations[$key]['dates']['start'];

}

function fixup_victoria_stations(&$stations) {
	$key = 'Blackhorse Road';
	$stations[$key]['dates']['start'] = 1968;
	$stations[$key]['info']['Victoria']['start'] = $stations[$key]['dates']['start'];

	$key = 'Brixton';
	$stations[$key]['dates']['start'] = 1971;
	$stations[$key]['info']['Victoria']['start'] = $stations[$key]['dates']['start'];

	//$stations[$key]['lines'] = array('Northern', 'Victoria');
	$key = 'Highbury & Islington';
	$stations[$key]['lines'] = array('Victoria');
	$stations[$key]['dates']['start'] = 1968;
	$stations[$key]['info']['Victoria']['start'] = $stations[$key]['dates']['start'];

	$key = 'Pimlico';
	$stations[$key]['dates']['start'] = 1972;
	$stations[$key]['info']['Victoria']['start'] = $stations[$key]['dates']['start'];

	$key = 'Seven Sisters';
	$stations[$key]['dates']['start'] = 1968;
	$stations[$key]['info']['Victoria']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Tottenham Hale';
	$stations[$key]['dates']['start'] = 1968;
	$stations[$key]['info']['Victoria']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Vauxhall';
	$stations[$key]['dates']['start'] = 1971;
	$stations[$key]['info']['Victoria']['start'] = $stations[$key]['dates']['start'];
	
	$key = 'Walthamstow Central';
	$stations[$key]['dates']['start'] = 1968;
	$stations[$key]['info']['Victoria']['start'] = $stations[$key]['dates']['start'];
	
	
}

function make_geojson(&$stations) {
	error_log('Found ' . count($stations) . ' unique stations');

	$geojson = array();
	$geojson['type'] = 'FeatureCollection';
	$geojson['features'] = array();

	foreach ($stations as $station) {
		if (!isset($station['name']) || empty($station['name'])) {
			error_log(var_export($station, true));
			exit('Missing name');
		}

		//$attr_list = array('name', 'lat', 'long', 'lines', 'start', 'end', 'abstract', 'thumbnail', 'depiction');

		if (!isset($station['lat']) || empty($station['lat'])) {
			exit('Missing latitude for ' . $station['name']);
		}

		if (!isset($station['long']) || empty($station['long'])) {
			exit('Missing longitude for ' . $station['name']);
		}

		if (!isset($station['lines']) || empty($station['lines'])) {
			exit('Missing lines for ' . $station['name']);
		}

		if (!isset($station['abstract']) || empty($station['abstract'])) {
			exit('Missing abstract for ' . $station['name']);
		}

		if (!isset($station['dates']) || empty($station['dates'])) {
			exit('Missing dates for ' . $station['name']);
		}

		if (!isset($station['dates']['start']) || empty($station['dates']['start'])) {
			exit('Missing start date for ' . $station['name']);
		}

		if (!isset($station['info']) || empty($station['info'])) {
			exit('Missing info for ' . $station['name']);
		}
		
		$feature = array();
		$feature['type'] = 'Feature';
		$feature['geometry'] = (object) array(
			'type' => 'Point',
			'coordinates' => array($station['long'], $station['lat'])
		);
		
		$properties = array();


		/*if (isset($station['dates'])) {
			$properties['dates'] = $station['dates'];
		}*/
		/*if (isset($station['info'])) {
			foreach ($station['info'] as $line => $meta) {
				if (isset($meta['start'])) {
					$properties['info'][$line] = $meta;
					if (isset($meta['end'])) {
						$properties['info'][$line]['status'] = 'closed';
					}

					elseif (!isset($meta['end'])) {
						$properties['info'][$line]['status'] = 'open';
					}
				}
				else {
					error_log('Missing start date for ' . $line . ' ' . $station['name']);
					error_log(var_export($station, true));
				}
			}
			//$properties['info'] = $station['info'];
		}*/
		
		$lines = array();
		$open_lines = array();
		$closed_lines = array();
		$info = array();
		foreach ($station['info'] as $line => $meta) {
			if (!isset($meta['start']) || empty($meta['start'])) {
				exit('Missing start date for ' . $station['name'] . ' on ' . $line);
			}
			
			$info[$line] = $meta;
			if (isset($meta['end']) && !empty($meta['end'])) {
				$status = 'closed';
				$closed_lines[] = $line;
			}
			else {
				$status = 'open';
				$open_lines[] = $line;
			}
			$lines[] = $line;
			$info[$line]['status'] = $status;
		}
		
		$properties['name'] = $station['name'];
		if (isset($station['dates']['end'])) {
			$properties['status'] = 'closed';
		}
		else {
			$properties['status'] = 'open';
		}
		$properties['dates'] = $station['dates'];
		$properties['info'] = $info;
		$properties['lines'] = $lines;
		$properties['open_lines'] = $open_lines;
		$properties['closed_lines'] = $closed_lines;
		$properties['abstract'] = $station['abstract'];

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