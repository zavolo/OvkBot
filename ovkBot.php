<?php declare(strict_types=1);

const API_DOMAIN      = "openvk.su";
const TOKEN           = "";
const DEFAULT_HEADERS = [
    "User-Agent: Discovery/0.1",
];

function http(string $method, string $url, ?string $payload = NULL, array $headers = [], &$responseHeaders = NULL): ?string
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    if(!is_null($payload))
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(DEFAULT_HEADERS, $headers));
    curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    if($response === false) {
        trigger_error("Unexpected request error: " . curl_error($ch) . " №E" . curl_errno($ch), E_USER_WARNING);
        
        return NULL;
    }
    
    $headerSize      = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $headerSize);
    $body            = substr($response, $headerSize);
    
    curl_close($ch);
    return $body;
}


function request(string $method, array $payload = [])
{
    $payload = http_build_query(array_merge($payload, [
        "v" => "5.101",
        "access_token" => TOKEN,
    ]), "", "&", PHP_QUERY_RFC3986);
    
    $response = http("GET", "https://" . API_DOMAIN . "/method/$method?$payload");
    if(!$response)
        return NULL;
    
    $response = json_decode($response);
    if(!is_null($e = $response->error_msg ?? NULL)) {
        trigger_error("API Error: $e", E_USER_WARNING);
        
        return NULL;
    }
    
    return $response->response;
}

$serverMeta = request("messages.getLongPollServer", [ "need_pts" => 1 ]);
if(!$serverMeta)
    exit();

$server = "$serverMeta->server?act=a_check&key=$serverMeta->key";
echo "Server is: $server\r\n";

$alreadyAnswered = [];
while(true) {
    $response = http("GET", $server);
    if(!$response)
        exit("Could not fetch events :(");
    
    if($response === "[]")
        continue;
    
    $response = json_decode($response);
    foreach($response->updates as $update) {
        if($update[0] !== 4)
            continue;
        
        $id = end($update);
        if(in_array($id, $alreadyAnswered))
            continue;
        else if(sizeof($alreadyAnswered) > 1024)
            $alreadyAnswered = [];
        else
            $alreadyAnswered[] = $id;
        
        $peer    = (int) $update[2];
        $message = (string) $update[4];
        $reply   = call_user_func("handler", $peer, $message);
        
        request("messages.send", [
            "peer_id" => $peer,
            "message" => $reply,
        ]);
    }
    
    usleep(333334);
}

function handler(int $peer, string $message): string
{
    return "$peer sent me a $message, wowzer!!!";
}
