<?php

// EDIT THIS: your auth parameters
$signature = 'a2eefde33d';

// EDIT THIS: the query parameters
$url     = '<?php echo $filelocation;?>'; // URL to shrink
$format  = 'simple';            // output format: 'json', 'xml' or 'simple'

// EDIT THIS: the URL of the API file
$api_url = 'http://ersatz.xyz/yourls-api.php';

// Init the CURL session
$ch = curl_init();
curl_setopt( $ch, CURLOPT_URL, $api_url );
curl_setopt( $ch, CURLOPT_HEADER, 0 );            // No header in the result
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true ); // Return, do not echo result
curl_setopt( $ch, CURLOPT_POST, 1 );              // This is a POST request
curl_setopt( $ch, CURLOPT_POSTFIELDS, array(      // Data to POST
		'url'       => $url,
		'format'    => $format,
		'action'    => 'shorturl',
		'signature' => $signature,
	) );

// Fetch and return content
$data = curl_exec($ch);
curl_close($ch);

// Do something with the result. Here, we just echo it.
echo $data;

