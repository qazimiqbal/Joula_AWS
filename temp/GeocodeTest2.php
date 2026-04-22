<?php
?>
<!DOCTYPE html>
<!--
 @license
 Copyright 2019 Google LLC. All Rights Reserved.
 SPDX-License-Identifier: Apache-2.0
-->
<html>
<head>
    <title>Geocoding Service</title>
    <script>
        var geocoder;
        var map;
        function initialize() {
            geocoder = new google.maps.Geocoder();
             var latlng = new google.maps.LatLng(33.84515320919785, -84.13463229834817);
            var mapOptions = {
            zoom: 8,
            center: latlng
        }
map = new google.maps.Map(document.getElementById('map'), mapOptions);
}

function codeAddress() {
var address = document.getElementById('address').value;
geocoder.geocode( { 'address': address}, function(results, status) {
if (status == 'OK') {
    var lat = results[0].geometry.location.lat();
    var lng = results[0].geometry.location.lng();
    alert(lat+ ","+lng);

map.setCenter(results[0].geometry.location);


var marker = new google.maps.Marker({
map: map,
position: results[0].geometry.location
});
} else {
alert('Geocode was not successful for the following reason: ' + status);
}
});
}
    </script>
<body onload="initialize()">
<div id="map" style="width: 320px; height: 480px;"></div>
<div>
    <input id="address" type="textbox" value="Sydney, NSW">
    <input type="button" value="Encode" onclick="codeAddress()">
</div>
<script
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAf-iWek9Zn3J00tIgYVcz0FDuTQFe_TD8&callback=initMap&v=weekly"
        defer
></script>
</body>
</html>
