<?php
$API_BASE_URL = 'https://api.openstreetmap.org/api/0.6/notes.json';
$USER_AGENT = 'Harry\'s notes as KML tool';

function get_url($url) {
  if (function_exists('curl_version')) {
    $content = get_curl_style($url);
  } else {
    $content = get_filecontent_style($url);
  }
  if ($content===FALSE) die("Failed to get url: <a href='$url'>$url</a>");
  if (strlen($content)==0) die("Empty string fetching url $url");
  return $content;
}
function get_curl_style($url) {
  global $USER_AGENT;
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url );
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'X-Forwarded-For: ' . $_SERVER['REMOTE_ADDR'],
      'User-Agent: ' . $USER_AGENT
    ));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  //curl_setopt($ch,CURLOPT_FAILONERROR,true);
  $data = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code<200 || $code>=300) {
    die("Notes API responded with code $code \"$data\" (URL $url)");
  }
  return $data;
}
function get_filecontent_style($url) {
  global $USER_AGENT;
  $options  = array('http' => array('user_agent' => $USER_AGENT ));
  $context  = stream_context_create($options);
  $response = file_get_contents($url, false, $context);
  return $response;
}

// Given a geojson feature (parsed array representation) return a kml placemark
function geojson_feature_2_kml_placemark($geojson_feature, $colour_scheme) {
  $description = '';

  foreach ($geojson_feature['properties']['comments'] as $comment) {
    if ($description != '') $description .= "<hr>";
    $user = array_key_exists('user', $comment) ? $comment['user'] : 'anon';
    $description .= "$user ";
    $description .= $comment['action'] . ' on ';
    $description .= $comment['date'];
    $description .= preg_replace('/\s+/', ' ', $comment['html']);
  }
  $note_id = $geojson_feature['properties']['id'];
  $description .= "<a href=\"https://www.openstreetmap.org/note/$note_id\">view note $note_id</a>";

  if (count($geojson_feature['properties']['comments'])==0) {
    // https://github.com/openstreetmap/openstreetmap-website/issues/1203
    $name = 'empty note';
  } else {
    $name = $geojson_feature['properties']['comments'][0]['text'];
  }
  $name = htmlspecialchars(preg_replace('/\s+/', ' ', substr($name, 0, 50)));

  $lon = $geojson_feature['geometry']['coordinates'][0];
  $lat = $geojson_feature['geometry']['coordinates'][1];

  if (in_array($colour_scheme, array('red', 'yellow', 'blue', 'green', 'purple', 'orange', 'brown', 'pink'))) {
    // Plain colour colour scheme
    $colour = $colour_scheme;
  }

  $output  = "    <name>$name</name>\n";
  $output .= "    <description><![CDATA[$description]]></description>\n";
  if ($colour_scheme!='none') $output .= "    <styleUrl>#placemark-$colour</styleUrl>\n";
  $output .= "    <Point><coordinates>$lon,$lat,0</coordinates></Point>";

  return "  <Placemark>\n$output\n  </Placemark>\n";
}

function validate_bbox($bbox_param) {
  if (strpos($bbox_param, ',') === false) die('bad bbox format. no commas');
  $bbox_array = explode(',', $bbox_param);
  if (count($bbox_array) != 4) die('bad bbox format. wrong number of CSV values');
  $min_lon = $bbox_array[0];
  $min_lat = $bbox_array[1];
  $max_lon = $bbox_array[2];
  $max_lat = $bbox_array[3];
  if ($min_lon < -180 || $min_lon > 180) die('bad bbox format. bad min lon');
  if ($max_lon < -180 || $max_lon > 180) die('bad bbox format. bad max lon');
  if ($min_lat < -90 || $min_lat > 90) die('bad bbox format. bad min lat');
  if ($max_lat < -90 || $max_lat > 90) die('bad bbox format. bad max lat');
  if ($min_lat > $max_lat) die('bad bbox format. min_lat > max_lat');
  if ($min_lon > $max_lon) die('bad bbox format. min_lon > max_lon');

  // Check bbox size
  $width = $max_lon - $min_lon;
  $height = $max_lat - $min_lat;
  // https://github.com/openstreetmap/openstreetmap-website/blob/master/config/example.application.yml#L33
  if ($width * $height > 25) die('bbox too big. Max 25 square degrees');
}

// ------

if (!isset($_GET['bbox'])) die('missing bbox param');
$bbox = $_GET['bbox'];
validate_bbox($bbox);

$limit = isset($_GET['limit']) ? $_GET['limit'] : 1000;
if (!ctype_digit($limit)) die('bad limit param');
if ($limit > 2000) die('limit over the limit');
if ($limit < 1) die('limit must be >0');

$closed = isset($_GET['closed']) ? $_GET['closed'] : 0;
if (!ctype_digit($closed)) die('bad closed param');

$colour_scheme = isset($_GET['colour']) ? $_GET['colour'] : 'none';
if (!in_array($colour_scheme, array('none', 'red', 'yellow', 'blue', 'green', 'purple', 'orange', 'brown', 'pink'))) die('invalid colour scheme');

$download_param = isset($_GET['download']) ? $_GET['download'] : 'true';
$download = filter_var($download_param, FILTER_VALIDATE_BOOLEAN);

$url = "$API_BASE_URL?bbox=$bbox&limit=$limit&closed=$closed";

$notes_data_str = get_url($url);
// $notes_data_str = file_get_contents("london-notes.json");

$notes_data = json_decode($notes_data_str, true);
if (!$notes_data['features']) die("OSM notes API response has no 'features'. URL: $url");

if ($download) {
  header('Content-Description: File Transfer');
  header('Content-Type: application/vnd.google-earth.kml+xml');
  header('Content-Disposition: attachment; filename=osm-notes.kml');
  header('Content-Transfer-Encoding: binary');
  header('Connection: Keep-Alive');
  header('Expires: 0');
  header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
} else {
  print '<pre>';
}

?>
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
  <name>OpenStreetMap Notes</name>
  <description>Notes on problems/ommisions/additions to OpenStreetMap which often require somebody to gather information on-the-ground. Notes API URL: <?php /*echo $url; */ ?></description>
  <?php
$i = 0;
foreach ($notes_data['features'] as $note_feature) {
  $i++;
  print geojson_feature_2_kml_placemark($note_feature, $colour_scheme);
}
?>
</Document>
</kml>
