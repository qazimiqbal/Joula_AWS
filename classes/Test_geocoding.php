
<?php
//echo "Hello";
$mystring = "5208 Ashley Dr. SW Lilburn GA 30047";
//$myadd = lookup($mystring);
function lookup($string){


    $string = str_replace (" ", "+", urlencode($string));
    echo $string."<BR>";
    $details_url = "https://maps.googleapis.com/maps/api/geocode/json?key=AIzaSyAf-iWek9Zn3J00tIgYVcz0FDuTQFe_TD8&address=".$string."&sensor=false";
    echo "<BR>".$details_url;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $details_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = json_decode(curl_exec($ch), true);

    // If Status Code is ZERO_RESULTS, OVER_QUERY_LIMIT, REQUEST_DENIED or INVALID_REQUEST
    if ($response['status'] != 'OK') {
        return null;
    }

    print_r($response);
    $geometry = $response['results'][0]['geometry'];

    $longitude = $geometry['location']['lat'];
    $latitude = $geometry['location']['lng'];

    $array = array(
        'latitude' => $geometry['location']['lng'],
        'longitude' => $geometry['location']['lat'],
        'location_type' => $geometry['location_type'],
    );

    return $array;

}
function geocode($address){
  
    // url encode the address
    $address = urlencode($address);
      
    // google map geocode api url
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key=AIzaSyAf-iWek9Zn3J00tIgYVcz0FDuTQFe_TD8&sensor=true";
    //$url = "https://maps.googleapis.com/maps/api/geocode/json?key=AIzaSyAf-iWek9Zn3J00tIgYVcz0FDuTQFe_TD8&address={".$string."}&sensor=true";
    //echo $url;
  
    // get the json response
    $resp_json = file_get_contents($url);
      
    // decode the json
    $resp = json_decode($resp_json, true);
  
    // response status will be 'OK', if able to geocode given address 
    if($resp['status']=='OK'){
  
        // get the important data
        $lati = isset($resp['results'][0]['geometry']['location']['lat']) ? $resp['results'][0]['geometry']['location']['lat'] : "";
        $longi = isset($resp['results'][0]['geometry']['location']['lng']) ? $resp['results'][0]['geometry']['location']['lng'] : "";
        $formatted_address = isset($resp['results'][0]['formatted_address']) ? $resp['results'][0]['formatted_address'] : "";
          
        // verify if data is complete
        if($lati && $longi && $formatted_address){
          
            // put the data in the array
            $data_arr = array();            
              
            array_push(
                $data_arr, 
                    $lati, 
                    $longi, 
                    $formatted_address
                );
              
            return $data_arr;
              
        }else{
            return false;
        }
          
    }
  
    else{
        echo "<strong>ERROR: {$resp['status']}</strong>";
        return false;
    }
}

//$city = 'San Francisco, USA';
$array = geocode($mystring);
print_r($array);

?>



