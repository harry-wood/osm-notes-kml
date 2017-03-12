<?php
$API_BASE_URL = 'http://api.openstreetmap.org/api/0.6/notes.json';

function get_url($url) {
  if (function_exists('curl_version')) {
    $content = get_curl_style($url);
  } else {
    $content = get_filecontent_style($url);
  }
  if ($content===FALSE) die("Failed to get url: <a href='$url'>$url</a>");
  if (strlen($content)==0) die("Empty string fetching url $url");
//  if (strlen($content)<100) die("Content too short $url");
  return $content;
}
function get_curl_style($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url );
  //curl_setopt($ch, CURLOPT_HEADER, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $data = curl_exec($ch);
  curl_close($ch);
  return $data;
}
function get_filecontent_style($url) {
  $user_agent = "Harry's notes tool";
  $options  = array('http' => array('user_agent' => $user_agent ));
  $context  = stream_context_create($options);
  $response = file_get_contents($url, false, $context);
  return $response;
}

// Given a geojson feature (parsed array representation) return a kml placemark
function geojson_feature_2_kml_placemark($geojson_feature) {
  $description = '';

  foreach ($geojson_feature['properties']['comments'] as $comment) {
    if ($description != '') $description .= "<br><hr><br>";
    $user = array_key_exists('user', $comment) ? $comment['user'] : 'anon';
    $description .= "$user ";
    $description .= $comment['action'] . ' on ';
    $description .= $comment['date'];
    $description .= "<br><br>";
    $description .= "\"". htmlspecialchars(preg_replace('/\s+/', ' ', $comment['text'])) . "\"";
  }
  $note_id = $geojson_feature['properties']['id'];
  $description .= "<br><br>";
  $description .= "<a href=\"http://www.openstreetmap.org/note/$note_id\">view note</a>";
  
  $name = $geojson_feature['properties']['comments'][0]['text'];
  $name = htmlspecialchars(preg_replace('/\s+/', ' ', substr($name, 0, 50)));

  $lon = $geojson_feature['geometry']['coordinates'][0];
  $lat = $geojson_feature['geometry']['coordinates'][1];

  $output  = "    <name>$name</name>\n";
  $output .= "    <description><![CDATA[$description]]></description>\n";
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

$download = isset($_GET['download']) ? boolval($_GET['download']) : true;

$url = "$API_BASE_URL?bbox=$bbox&limit=$limit&closed=$closed";

$notes_data_str = get_url($url);
// $notes_data_str = file_get_contents("london-notes.json");

$notes_data = json_decode($notes_data_str, true);

if ($download) {
  header('Content-Description: File Transfer');
  header('Content-Type: application/vnd.google-earth.kml+xml');
  header('Content-Disposition: attachment; filename=osm-notes.kml');
  header('Content-Transfer-Encoding: binary');
  header('Connection: Keep-Alive');
  header('Expires: 0');
  header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
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
  print geojson_feature_2_kml_placemark($note_feature);
}
?>
</Document>
</kml>
