<html>
<head>
   <title>OpenStreetMap Notes as KML</title>

   <script src="https://code.jquery.com/jquery-1.12.0.min.js"></script>
   <script src="https://code.jquery.com/jquery-migrate-1.2.1.min.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.0.3/dist/leaflet.css" />
   <script src="https://unpkg.com/leaflet@1.0.3/dist/leaflet.js"></script>

   <meta name="viewport" content="user-scalable=no, width=device-width" />

   <style>
   html, body, #map { width:100%; height: 100%; margin:0; padding:0; }
   body { font-family: Verdana, Helvetica, Arial, sans-serif; }
   #msg { padding:5px; }
   #map { display:none; }
   #panel { padding:3px; position: absolute; bottom:2px; left:2px; background: LIGHTGREY; z-index:9999; }
   #getbtn { font-size:1.3em; padding: 10px 12px; text-align: center; }
   </style>

   <script>
var map;
var rect;

function init() {
  $('#msg').hide();
  $('#map').show();
  map = L.map('map');

  L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors',
    maxZoom: 15
  }).addTo(map);
  map.attributionControl.setPrefix('');

  // Add the keys method if missing (help with browser compat)
  if(!Object.keys) Object.keys = function(o){
    if (o !== Object(o))
      throw new TypeError('Object.keys called on non-object');
    var ret=[],p;
    for(p in o) if(Object.prototype.hasOwnProperty.call(o,p)) ret.push(p);
    return ret;
  }

  // map view before we get the location
  map.setView(new L.LatLng(51.505, -0.09), 3);

  // Add in a crosshair for the map
  var crosshairIcon = L.icon({
    iconUrl: 'crosshairs.png', iconSize: [100, 100], iconAnchor: [50, 50],
  });
  crosshair = new L.marker(map.getCenter(), {icon: crosshairIcon, clickable:false});
  crosshair.addTo(map);

  map.on('move',function(e) {
    crosshair.setLatLng(map.getCenter());
  });

  map.fire('move');

  getLocation();
}

// http://stackoverflow.com/a/901144/338265
function getParameterByName(name, url) {
  if (!url) url = window.location.href;
  name = name.replace(/[\[\]]/g, "\\$&");
  var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)", "i"),
      results = regex.exec(url);
  if (!results) return null;
  if (!results[2]) return '';
  return decodeURIComponent(results[2].replace(/\+/g, " "));
}

function getKML() {
  if (confirm('Requesting notes for the visible area up to a limit of 1000')) {
    bounds = map.getBounds()
    url = "./api.kml.php?" +
          "bbox=" + bounds.getWest() + ',' + bounds.getSouth() + ',' + bounds.getEast() + ',' + bounds.getNorth() +
          "&limit=1000" +
          "&closed=0";
    //alert(url);
    
    $('#map').hide();
    $('#msg').html('<h1>Generating KML download</h1>' +
    	           '<p>"open" the download using the MAPS.ME app when prompted</p>');
    $('#msg').show();
    
    window.location = url;
  }
}

function getLocation() {
  if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(showPosition);
  } else {
    // Geolocation is not supported by this browser
  }
}
function showPosition(position) {
    map.setView(new L.LatLng(position.coords.latitude, position.coords.longitude), 13);
}

   </script>
</head>

<body>
  <div id="msg">
    <h1>OpenStreetMap Notes as KML</h1>
    <p>This let's you grab all the OpenStreetMap Notes in an area and
    download them as KML. Such a download might be useful for various things,
    but mostly the idea is to easily load notes into MAPS.ME</p>

    <p>Don't have MAPS.ME? Install it
    <a href="https://itunes.apple.com/app/id510623322">for iPhone</a>
    or
    <a href="https://play.google.com/store/apps/details?id=com.mapswithme.maps.pro">for Android</a>
    and get it set up showing maps before proceeding</p>

    <input id='proceedbtn' type="button" value="Pick an area" onclick="init();">
  </div>
  <div id="map">
    <div id="panel">
      <input id='getbtn' type="button" value="Generate Notes KML" onclick="getKML();">
    </div>
  </div>
</body>
