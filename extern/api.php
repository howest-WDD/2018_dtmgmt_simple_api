<?php
//TODO: Add db table list.

// configureer php script
date_default_timezone_set('Europe/Brussels');
error_reporting(E_ALL);
ini_set('display_errors', 'On');

// importeer de settings
require_once('api.config.php');

if($allowCors){
    // allow cors
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Headers: POST, GET, PUT, DELETE, OPTIONS, HEAD, authorization');
    header('Access-Control-Allow-Methods: POST,GET,PUT,DELETE');
    header('Access-Control-Max-Age: 1000');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

// Method ophalen, wat routing voorzien en eventuele input lezen.
$method = $_SERVER['REQUEST_METHOD'];

// als er geen waarden in de url zijn opgegeven, geven we een fout.
if(!array_key_exists('PATH_INFO',$_SERVER)){
    api_error(400,"De api verwacht een url in de vorm van api.php/{tableName}/{Pkey}");
}

// de url opsplitsen in delen om zo onze routing uit te voeren.
$request = explode( '/', trim( $_SERVER['PATH_INFO'], '/' ) );

// als er data meegekomen is met de request, willen we dit als json parsen.
$input = json_decode( file_get_contents( 'php://input' ), true );

// verbinding maken met de database.
/*
 * @var mysqli
 */
$mysqli = mysqli_connect($db['host'], $db['user'], $db['pw'], $db['name'],$db['port']);
mysqli_set_charset($mysqli,'utf8');

// Geef een foutmelding wanneer de verbinding niet kan gemaakt worden.
if ($mysqli->connect_errno) {
    // In dit geval laat je de gebruiker best iets weten
    api_error(500,"Kon niet verbinden met de database.".PHP_EOL."Errno: " . $mysqli->connect_errno . PHP_EOL."Error: " . $mysqli->connect_error . PHP_EOL);
}

// het eerste element in $request is de table
// array_shift haalt (en verwijdert) het eerste element van een array
$table = preg_replace( '/[^+a-z0-9+_]+/i', '', array_shift( $request ) );
// als er geen table geselecteerd is, dan kunnen we hier stoppen en geven we een fout
if ( empty($table) ) {
    api_error(400,"Gelieve een table uit de database te kiezen, geen table opgegeven.");
}

// het tweede (nu het eerste in de array) is de eventuele key
$key = array_shift( $request );

// filter onze key tegen injection, meestal wordt enkel een numerische key toegelaten
if (!empty($key) ) {
    $key = mysqli_real_escape_string( $mysqli, $key );
}elseif ( $method === "PUT"||$method === "PATCH") {
    api_error(405,"Er werd geen key opgegeven voor het updaten. Er werd niets gewijzigd");
}elseif($method==="DELETE"){
    api_error(405,"Er werd geen key opgegeven voor het verwijderen. Er werd niets gewijzigd");
}

// enkel bij post of put mag er input zijn
if ( $method === "POST" || $method === "PUT"||$method === "PATCH" ) {
    // als er geen input is bij deze method is de request verkeerd
    if ( empty( $input ) ) {
        // POST van een lege input is verboden!
        api_error(400,"Er werd geen data meegestuurd om een create of update uit te voeren.");
    }

    // escape & filter van de input (onze ontvangen json)
    $columns = preg_replace( '/[^a-z0-9_]+/i', '', array_keys( $input ) );

    // elke value filteren op basis van onze verbinding
    $values = array_map(function($value) use ($mysqli) {
        if ( $value === null ) {
            return null;
        }

        return mysqli_real_escape_string( $mysqli, (string)$value );
    }, array_values( $input ));

    // Bouw het set gedeelte adhv de gefilterde columns
    $set = '';
    for ( $i = 0; $i < count( $columns ); $i ++ ) {
        $set .= ( $i > 0 ? ',' : '' ) . '`' . $columns[ $i ] . '`=';
        $set .= ( $values[ $i ] === null ? 'NULL' : '"' . $values[ $i ] . '"' );
    }
}

if ( $method !== "POST" && ! empty( $key ) ) {
    // get the primary key column for the where clause
    $stmt = prepare_statement($mysqli,"SHOW KEYS FROM ".$table." WHERE Key_name = 'PRIMARY'");

    // uitvoeren statement en resultaat ophalen
    $pkResult=execute_statement($stmt);

    // als er geen resultaat is, 500 internal server error
    if (!$pkResult) {
        api_error(500,"Er is geen primary key informatie beschikbaar.");
    }

    // geen pk gevonden
    if ( empty( $pkResult ) || !is_array( $pkResult ) || ! array_key_exists( "Column_name", $pkResult[0] ) || empty( $pkResult[0]['Column_name'] ) ) {
        api_error(500,"Onverwacht antwoord voor de primary key informatie.".PHP_EOL.print_r($pkResult,true));
    }

    $pk = $pkResult[0]['Column_name'];
}

// Maak een statement op basis van de method
switch ( $method ) {
    case 'GET':
        if($key){
            $stmt = prepare_statement($mysqli,"select * from ".$table." WHERE $pk=?");
            $stmt->bind_param("s",$key);
        }else{
            $stmt = prepare_statement($mysqli,"select * from ".$table);
        }
        break;
    case 'PUT':
        $stmt = prepare_statement($mysqli,"update ".$table." set $set where $pk=?");
        $stmt->bind_param("s",$key);
        break;
    case 'PATCH':
        $stmt = prepare_statement($mysqli,"update ".$table." set $set where $pk=?");
        $stmt->bind_param("s",$key);
        break;
    case 'POST':
        $stmt = prepare_statement($mysqli,"insert into ".$table." set $set");
        break;
    case 'DELETE':
        $stmt = prepare_statement($mysqli,"delete from ".$table." where $pk=?");
        $stmt->bind_param("s",$key);
        break;
}

$result = execute_statement($stmt);

if ( $method == 'GET' ) {
    if ($result==null) {
         api_return(array());
    }else{
        // wanneer er een key aanwezig was, verwachten we 1 object, geen array
        if($key){
            api_return($result[0]);
        }else{
            api_return($result);
        }
    }
} elseif ( $method == 'POST' ) {
    // de id wanneer het een post was
    http_response_code(201);
    header("location: ".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."/".$mysqli->insert_id);
    api_return($mysqli->insert_id);
} else {
    // alle andere geven het aantal betrokken records terug.
    api_return($mysqli->affected_rows);
}

/**
 * @param mysqli $mysqli
 * @param string $query
 * @return mysqli_stmt
 */
function prepare_statement(mysqli $mysqli, $query){
    // maak een statement en bind de variabelen
    $stmt = $mysqli->prepare($query);
    if($mysqli->error!==""){
        api_error(500,"Kan statement niet voorbereiden.".PHP_EOL.$query.PHP_EOL.$mysqli->error);
    }
    return $stmt;
}

/**
 * @param mysqli_stmt $stmt
 * @return array
 */
function execute_statement(mysqli_stmt $stmt){
    $stmt->execute();

    // stoppen met uitvoering en een foutmelding geven
    if(count($stmt->error_list)){
        api_error(500,"Kon het statement niet uitvoeren".PHP_EOL.print_r($stmt->error_list,false));
    }

    $result = $stmt->get_result();
    $data =null;
    if($result){
        $data=array();
        while($row = $result->fetch_assoc()){
            array_push($data,$row);
        }
    }

    return $data;
}

/**
 * @param int $httpStatusCode
 * @param string $debug_message
 * @param bool $isFatal
 */
function api_error($httpStatusCode, $debug_message, $isFatal=true){
    http_response_code($httpStatusCode);
    echo $debug_message;
    if($isFatal){
        exit;
    }
}

function api_return($data){
    header( 'Content-Type: application/json' );
    echo json_encode($data);
}