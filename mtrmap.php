<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mtrReport'])) {
    $mtrReport = $_POST['mtrReport'];
    $ipAddresses = parseMtrReport($mtrReport);
    $coordinates = convertToCoordinates($ipAddresses);
    generateMap($coordinates);
}

function parseMtrReport($mtrReport) {
    $ipAddresses = array();

    // Split the report into lines
    $lines = explode("\n", $mtrReport);

    // Loop through the lines and extract IP addresses and hostnames
    foreach ($lines as $line) {
        // Match an IP address or a hostname
        if (preg_match('/\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}|[a-zA-Z0-9][a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,})\b/', $line, $matches)) {
            // Add the match to the array
            $ipAddresses[] = $matches[1];
        }
    }

    return $ipAddresses;
}
function convertToCoordinates($ipAddresses) {
    $coordinates = [];
    
    foreach ($ipAddresses as $ip) {
        // Convert the hostname to an IP address if necessary
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = gethostbyname($ip);
        }

        $url = "http://ip-api.com/json/{$ip}?fields=lat,lon";
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if ($data && isset($data['lat']) && isset($data['lon'])) {
            $coordinates[] = [
                'lat' => $data['lat'],
                'lng' => $data['lon']
            ];
        }
    }

    return $coordinates;
}

function generateMap($coordinates) {
    $center = $coordinates[0];
    $jsCoordinates = json_encode($coordinates);
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MTR Report to Google Maps</title>
    <style>
        #map {
            height: 100%;
            width: 100%;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <div id="map"></div>
    <script>
        function initMap() {
            var map = new google.maps.Map(document.getElementById('map'), {
                zoom: 4,
                center: {lat: {$center['lat']}, lng: {$center['lng']}}
            });

            var coordinates = {$jsCoordinates};
            var routePath = new google.maps.Polyline({
                path: coordinates,
                geodesic: true,
                strokeColor: '#FF0000',
                strokeOpacity: 1.0,
                strokeWeight: 2
            });

            routePath.setMap(map);

            // Add markers for each coordinate with a delay
            for (var i = 0; i < coordinates.length; i++) {
                (function(i) {
                    setTimeout(function() {
                        addMarker(coordinates[i], map, i);
                    }, i * 100);
                })(i);
            }
        }

        // Function to add markers
        function addMarker(coord, map, index) {
            var marker = new google.maps.Marker({
                position: coord,
                map: map,
                label: (index + 1).toString()
            });
        }
    </script>
    <script async defer
        src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap&callback=initMap">
    </script>
</body>
</html>
HTML;
}
?>
