<?php

/**
 * Method for creating a base string from an array and base URI.
 * @param string $baseURI the URI of the request to twitter
 * @param array $params the OAuth associative array
 * @return string the encoded base string
**/
function buildBaseString($baseURI, $params){

$r = array(); //temporary array
    ksort($params); //sort params alphabetically by keys
    foreach($params as $key=>$value){
        $r[] = "$key=" . rawurlencode($value); //create key=value strings
    }//end foreach                

    return "POST&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r)); //return complete base string
}//end buildBaseString()

/**
 * Method for creating the composite key.
 * @param string $consumerSecret the consumer secret authorized by Twitter
 * @param string $requestToken the request token from Twitter
 * @return string the composite key.
**/
function getCompositeKey($consumerSecret, $requestToken){
    return rawurlencode($consumerSecret) . '&' . rawurlencode($requestToken);
}//end getCompositeKey()

/**
 * Method for building the OAuth header.
 * @param array $oauth the oauth array.
 * @return string the authorization header.
**/
function buildAuthorizationHeader($oauth){
    $r = 'Authorization: OAuth '; //header prefix

    $values = array(); //temporary key=value array
    foreach($oauth as $key=>$value)
        $values[] = "$key=\"" . rawurlencode($value) . "\""; //encode key=value string

    $r .= implode(', ', $values); //reassemble
    return $r; //return full authorization header
}//end buildAuthorizationHeader()

/**
 * Method for sending a request to Twitter.
 * @param array $oauth the oauth array
 * @param string $baseURI the request URI
 * @return string the response from Twitter
**/
function sendRequest($oauth, $baseURI){
    $header = array( buildAuthorizationHeader($oauth), 'Expect:'); //create header array and add 'Expect:'

    $options = array(CURLOPT_HTTPHEADER => $header, //use our authorization and expect header
                           CURLOPT_HEADER => false, //don't retrieve the header back from Twitter
                           CURLOPT_URL => $baseURI, //the URI we're sending the request to
                           CURLOPT_POST => true, //this is going to be a POST - required
                           CURLOPT_RETURNTRANSFER => true, //return content as a string, don't echo out directly
                           CURLOPT_SSL_VERIFYPEER => false); //don't verify SSL certificate, just do it

    $ch = curl_init(); //get a channel
    curl_setopt_array($ch, $options); //set options

    if(isset($_SESSION["twitter_oauth_verifier"])){
      $post_data = "oauth_verifier=".$_SESSION["twitter_oauth_verifier"];
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data); 
    }

    $response = curl_exec($ch); //make the call
    curl_close($ch); //hang up

    return $response;
}//end sendRequest()


//get request token
$nonce = time();
$timestamp = time();
if(isset($_SESSION["twitter_oauth_token"]) && isset($_SESSION["twitter_oauth_verifier"])){
  $oauth = array('oauth_consumer_key' => $args->twitter_consumer_key,
                 'oauth_nonce' => $nonce,
                 'oauth_signature_method' => 'HMAC-SHA1',
                 'oauth_timestamp' => $timestamp,
                 'oauth_token' => $_SESSION["twitter_oauth_token"],
                 'oauth_version' => '1.0');
} else{
  $oauth = array('oauth_callback' => $twitter_oauth_callback,
                 'oauth_consumer_key' => $args->twitter_consumer_key,
                 'oauth_nonce' => $nonce,
                 'oauth_signature_method' => 'HMAC-SHA1',
                 'oauth_timestamp' => $timestamp,
                 'oauth_version' => '1.0');
}



$consumerSecret = $args->twitter_consumer_secret; //put your actual consumer secret here, it will look something like 'MCD8BKwGdgPHvAuvgvz4EQpqDAtx89grbuNMRd7Eh98'

$baseString = buildBaseString($baseURI, $oauth); //build the base string

if(isset($_SESSION["twitter_oauth_token"]) && isset($_SESSION["twitter_oauth_verifier"])){
  $compositeKey = getCompositeKey($consumerSecret, $_SESSION["twitter_oauth_token"]); //first request, no request token yet
} else{
  $compositeKey = getCompositeKey($consumerSecret, null); //first request, no request token yet
}


$oauth_signature = base64_encode(hash_hmac('sha1', $baseString, $compositeKey, true)); //sign the base string

$oauth['oauth_signature'] = $oauth_signature; //add the signature to our oauth array

$response = sendRequest($oauth, $baseURI); //make the call

//parse response into associative array
$responseArray = array();
$parts = explode('&', $response);
foreach($parts as $p){
    $p = explode('=', $p);
    $responseArray[$p[0]] = $p[1];    
}//end foreach

//get oauth_token from response
$oauth_token = $responseArray['oauth_token'];
?>
