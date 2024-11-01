$ch = curl_init("http://google.com");    // initialize curl handle
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
$data = curl_exec($ch);
print($data);
