<?php
function CallAPI($method, $url, $data = false)
{
    $curl = curl_init();

    switch ($method) {
        case "POST":
            // voor het maken van een object gebruiken we POST, hiermee laten we dit ook aan cURL weten
            curl_setopt($curl, CURLOPT_POST, 1);

            // als er data nodig is voor het maken van een object, voegen we dit hier toe
            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        case "PUT":
            if(!$data){
                echo "Kan niet updaten zonder data";
                die(400);
            }
            // voor het updaten van een object gebruiken we PUT, hiermee laten we dit ook aan cURL weten
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

            break;
        case "PATCH":
            if(!$data){
                echo "Kan niet updaten zonder data";
                die(400);
            }
            // voor het updaten van een object gebruiken we PUT, hiermee laten we dit ook aan cURL weten
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

            break;
        case "DELETE":
            // voor het DELETE van een object gebruiken we DELETE, hiermee laten we dit ook aan cURL weten
            //AANVULLING DRB
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            break;
        default:
            // als er data aanwezig is vormen we een nieuwe URL door de data url parameters door te geven bv: http://api.be/index.php?param=value
            if ($data)
                $url = sprintf("%s?%s", $url, http_build_query($data));
    }

    // defineer de te halen URL
    curl_setopt($curl, CURLOPT_URL, $url);
    // we willen het resultaat opvangen en niet gewoon weergeven in $result
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    // als het meer of 3s duurt om data op te halen gaan we uit van een verkeerde verbinding of server timeout
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($curl, CURLOPT_TIMEOUT, 3);

    $result = curl_exec($curl);

    // Wanneer de API geen succesvol antwoord geeft kunnen we dat hier opvangen
    if (!curl_errno($curl)) {
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if(!($http_code=200||$http_code=201)){
            echo 'Unexpected HTTP code: ', $http_code, "\n";
            exit();
        }
    }else{
        echo "Request gaf volgende foutcode: ".curl_errno($curl)."<br>";
        echo "Melding: ".curl_error($curl);
    }

    curl_close($curl);

    // indien het geen json result is geven we gewoon het resultaat terug
    $jsonResult = json_decode($result, true);

    // lege array lijkt als null herkend te worden, dit willen we niet
    if($jsonResult!=null||is_array($jsonResult)){
        return $jsonResult;
    }else{
        return $result;
    }
}