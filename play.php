<?php
// WASMER: play.php
if (!isset($_GET['token']) || !isset($_GET['ch'])) die("Invalid Stream");

$token_str = $_GET['token'];
$ch_id = $_GET['ch'];

// 1. Ask InfinityFree if Token is Valid (Cross-Server check)
// APNE INFINITYFREE DOMAIN KA NAAM YAHAN DAALO
$infinity_verify_url = "http://YOUR_INFINITY_FREE_DOMAIN.com/verify.php?token=" . $token_str;

$verify_ch = curl_init($infinity_verify_url);
curl_setopt($verify_ch, CURLOPT_RETURNTRANSFER, true);
$is_valid_status = trim(curl_exec($verify_ch));
curl_close($verify_ch);

// 2. Play Expired Video if token is invalid or expired
if ($is_valid_status !== "VALID" || $ch_id == 'expired') {
    header('Content-Type: video/mp2t');
    header('Access-Control-Allow-Origin: *');
    $expired_video = __DIR__ . '/expired.ts';
    if (file_exists($expired_video)) {
        readfile($expired_video);
    } else {
        die("Link Expired.");
    }
    exit;
}

// 3. Token is Valid -> Heavy Streaming from Portal
$mac = "00:1A:79:2B:25:CE";
$api_url = "http://starshare.online/portal.php";
$ua = "Mozilla/5.0 (QtEmbedded; U; Linux; C) MAG200 stbapp ver: 2 rev: 250";

// (Brief handshake logic for the stream proxy)
$ch = curl_init($api_url . "?type=stb&action=handshake");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: mac=$mac"]);
$hs = json_decode(curl_exec($ch), true);
$p_token = $hs['js']['token'] ?? '';
curl_close($ch);

// Get direct stream link for the channel
$ch = curl_init($api_url . "?type=itv&action=create_link&cmd=" . urlencode("ffrt http://localhost/ch/$ch_id"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: mac=$mac"]);
$link_data = json_decode(curl_exec($ch), true);
curl_close($ch);

$stream_url = $link_data['js']['cmd'] ?? '';
$stream_url = str_replace(['ffrt ', 'ffmpeg '], '', $stream_url);

if (empty($stream_url)) die("Stream Offline");

// M3U8 Rewrite Logic (Prevents buffering)
$ch = curl_init($stream_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, "VLC/3.0.0");
curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
$content = curl_exec($ch);
$final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); 
curl_close($ch);

if (strpos($content, '#EXTM3U') !== false) {
    header('Content-Type: application/vnd.apple.mpegurl');
    $base_path = dirname($final_url) . '/'; 
    $lines = explode("\n", $content);
    foreach ($lines as &$line) {
        $line = trim($line);
        if ($line && $line[0] !== '#') {
            if (!preg_match('/^http/', $line)) $line = $base_path . $line; 
        }
    }
    echo implode("\n", $lines);
} else {
    header("Location: " . $final_url);
}
?>
