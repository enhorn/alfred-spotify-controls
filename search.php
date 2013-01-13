<?php
function arrayToXML($a) {
    if (! is_array($a)) {
        return false;
    }

    $items = new SimpleXMLElement("<items></items>");

    foreach($a as $b) {
        $c = $items->addChild('item');
        $c_keys = array_keys($b);
        foreach($c_keys as $key) {
            if ($key == 'uid') {
                $c->addAttribute('uid', $b[$key]);
            }
            elseif ($key == 'arg') {
                $c->addAttribute('arg', $b[$key]);
            }
            else {
                $c->addChild($key, $b[$key]);
            }
        }
    }

    return $items->asXML();
}

function fetchUrl($url) {
     $ch = curl_init();
     curl_setopt($ch, CURLOPT_URL, $url);
     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
     curl_setopt($ch, CURLOPT_TIMEOUT, 20);
     $retData = curl_exec($ch);
     curl_close($ch); 
     return $retData;
}

function get_track_artwork($track_id) {
     require_once('phpQuery-onefile.php');
     $track_url = 'http://open.spotify.com/track/' . $track_id;
     $html = fetchUrl($track_url);
     phpQuery::newDocument($html);
     $image_url = pq('meta[property=og:image')->attr('content');
     $image_url = str_replace('image', 'thumb', $image_url);
     return $image_url;
}

$query  = $argv[1];

if (strlen($query) < 3) {
    exit(1);
}

$tmp    = explode(' ', $query);
$type   = $tmp[0];

if ($type != 'artist' && $type != 'album' && $type != 'track') {
    $type = 'track';
} else {
    $query  = trim(str_replace($type, '', $query));
}

$type   = strtolower($type);
$key    = $type . 's';
$max    = 5;

$json = file_get_contents('http://ws.spotify.com/search/1/' . $type . '.json?q=' . urlencode($query));
$results = array();

if (! empty($json)) {

    $thumbsPath = '~/Library/Caches/AlfredSpotifySearchThumbs';
    shell_exec('mkdir -p ' . $thumbsPath);

    $json = json_decode($json);
    $x = 1;
    foreach ($json->{$key} as $k => $obj) {
        if ($x <= $max) {
            if ($type == 'artist') {
                $subtitle        = 'Artist';
                $autocomplete    = htmlentities($obj->name, ENT_QUOTES, 'UTF-8');
            }
            elseif ($type == 'album') {
                $subtitle        = htmlentities($obj->artists[0]->name, ENT_QUOTES, 'UTF-8');
                $autocomplete    = htmlentities($obj->artists[0]->name, ENT_QUOTES, 'UTF-8'). ' ' .htmlentities($obj->name, ENT_QUOTES, 'UTF-8');
            }
            else {
                $subtitle        = htmlentities($obj->artists[0]->name, ENT_QUOTES, 'UTF-8'). " - " .htmlentities($obj->album->name, ENT_QUOTES, 'UTF-8');
                $autocomplete    = htmlentities($obj->artists[0]->name, ENT_QUOTES, 'UTF-8'). ' ' .htmlentities($obj->album->name, ENT_QUOTES, 'UTF-8');
            }

            $hrefs = explode(':', $obj->href);
            $trackID = $hrefs[2];
            $thumbPath = $thumbsPath . '/' . $trackID . '.png';
            if (!file_exists($thumbPath)) {
                $artURL = get_track_artwork($trackID);
                exec('curl -s ' . $artURL . ' -o ' . $thumbPath);
            }

            array_push($results, array(
                'uid'             => $type,
                'arg'             => $obj->href,
                'title'           => htmlentities($obj->name, ENT_QUOTES, 'UTF-8'),
                'subtitle'        => $subtitle,
                //'icon'            => 'icon.png',
                'icon'            => $thumbPath,
                'autocomplete'    => $autocomplete
            ));

            $x += 1;
        }
    }
    print arrayToXML($results);
}