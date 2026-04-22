<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>MarkerClusterer v3 Simple Example</title>
    <style >
        body {
            margin: 0;
            padding: 10px 20px 20px;
            font-family: Arial;
            font-size: 16px;
        }
        #map-container {
            padding: 6px;
            border-width: 1px;
            border-style: solid;
            border-color: #ccc #ccc #999 #ccc;
            -webkit-box-shadow: rgba(64, 64, 64, 0.5) 0 2px 5px;
            -moz-box-shadow: rgba(64, 64, 64, 0.5) 0 2px 5px;
            box-shadow: rgba(64, 64, 64, 0.1) 0 2px 5px;
            width: 600px;
        }
        #map {
            width: 600px;
            height: 400px;
        }
    </style>
<script type="text/javascript" src="https://maps.google.com/maps/api/js?sensor=true"></script>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.min.js"></script>
<script type="text/javascript" src="https://googlemaps.github.io/js-marker-clusterer/src/markerclusterer.js"></script>
<script type="text/javascript" src="jquery.ui.map.js"></script>
    <script>
        // We need to bind the map with the "init" event otherwise bounds will be null
        $('#map_canvas').gmap({'zoom': 2, 'disableDefaultUI':true}).bind('init', function(evt, map) {
            var bounds = map.getBounds();
            var southWest = bounds.getSouthWest();
            var northEast = bounds.getNorthEast();
            var lngSpan = northEast.lng() - southWest.lng();
            var latSpan = northEast.lat() - southWest.lat();
            for ( var i = 0; i < 1000; i++ ) {
                var lat = southWest.lat() + latSpan * Math.random();
                var lng = southWest.lng() + lngSpan * Math.random();
                $('#map_canvas').gmap('addMarker', {
                    'position': new google.maps.LatLng(lat, lng)
                }).click(function() {
                    $('#map_canvas').gmap('openInfoWindow', { content : 'Hello world!' }, this);
                });
            }
            $('#map_canvas').gmap('set', 'MarkerClusterer', new MarkerClusterer(map, $(this).gmap('get', 'markers')));
// To call methods in MarkerClusterer simply call
// $('#map_canvas').gmap('get', 'MarkerClusterer').callingSomeMethod();
        });
    </script>
</head>
<body>
<h3>A simple example of MarkerClusterer (100 markers)</h3>
<div id="map-container"><div id="map_canvas"></div></div>
</body>
</html>






<?php
/**
 * Created by PhpStorm.
 * User: qazi.iqbal
 * Date: 9/22/2017
 * Time: 9:49 AM
 */