<?php
// WASMER: api.php
$api_user = "shinmon_admin";
$api_pass = "sigma_secret_key_369";

// Block all unauthorized IPv4/IPv6 & devices
if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== $api_user || $_SERVER['PHP_AUTH_PW'] !== $api_pass) {
    header('WWW-Authenticate: Basic realm="ShinMon Secure API"');
    header('HTTP/1.0 401 Unauthorized');
    die("Access Denied: Your IP/Device is blocked by ShinMon Firewall.");
}

$token_str = $_GET['token'] ?? 'demo';
$wasmer_url = "https://" . $_SERVER['HTTP_HOST'];

// Stalker Portal Details
$mac = "00:1A:79:2B:25:CE";
$api_url = "http://starshare.online/portal.php";
$ua = "Mozilla/5.0 (QtEmbedded; U; Linux; C) MAG200 stbapp ver: 2 rev: 250";

function stalker_api($url, $mac, $ua, $params, $token = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . "?" . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    $headers = ["Cookie: mac=" . urlencode($mac), "X-User-Agent: Model: MAG250"];
    if ($token) $headers[] = "Authorization: Bearer " . $token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// Fetch channels (API logic directly on Wasmer to save InfinityFree load)
$handshake = stalker_api($api_url, $mac, $ua, ['type' => 'stb', 'action' => 'handshake']);
$p_token = $handshake['js']['token'] ?? '';
stalker_api($api_url, $mac, $ua, ['type' => 'stb', 'action' => 'get_profile'], $p_token);
$channels_data = stalker_api($api_url, $mac, $ua, ['type' => 'itv', 'action' => 'get_all_channels'], $p_token);
$channels = $channels_data['js']['data'] ?? [];

echo "#EXTM3U\n";
foreach ($channels as $ch) {
    if (!isset($ch['name']) || !isset($ch['cmd'])) continue;
    $id = $ch['id'];
    $name = trim($ch['name']);
    echo "#EXTINF:-1 tvg-id=\"$id\" tvg-logo=\"\", $name\n";
    // Link directly points to Wasmer's Heavy Proxy
    echo $wasmer_url . "/play.php?token=" . $token_str . "&ch=" . $id . "\n";
}
?>
