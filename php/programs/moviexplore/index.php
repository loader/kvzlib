<?php
/*
 * Requires imdbphp which is installable through apt:
 *  wget -O- http://apt.izzysoft.de/izzysoft.asc | apt-key add -
 *  deb http://apt.izzysoft.de/ubuntu generic universe
 *  aptitude install imdbphp
 *
 */

if (!defined('DIR_ROOT')) {
    define(DIR_ROOT, dirname(__FILE__));
}

if (!defined('DIR_KVZLIB')) {
    $lookIn = array(
        '/home/kevin/workspace/plutonia-kvzlib',
        DIR_ROOT.'/ext/kvzlib',
    );

    foreach($lookIn as $dir) {
        if (is_dir($dir)) {
            define('DIR_KVZLIB', $dir);
            break;
        }
    }

    if (!defined('DIR_KVZLIB')) {
        trigger_error('KvzLib not found in either: '.implode(', ', $lookIn), E_USER_ERROR);
    }
}

define('IMDBPHP_CONFIG',DIR_ROOT.'/config/imdb.php');

ini_set("include_path", DIR_KVZLIB.":".DIR_ROOT.":".ini_get("include_path"));

require_once DIR_KVZLIB.'/php/classes/KvzShell.php';
require_once DIR_KVZLIB.'/php/all_functions.php';
require_once DIR_ROOT.'/libs/crawler.php';
require_once DIR_ROOT.'/libs/movie.php';
require_once 'imdb.class.php';

$Crawler = new Crawler(array(
    'dir' => '/data/moviesHD',
    'minSize' => '600M',
    'cachedir' => DIR_ROOT.'/cache',
    'photodir' => DIR_ROOT.'/images',
));
$Movies = $Crawler->crawl();
foreach($Movies as $file=>$movieDetails) {
    print_r($movieDetails);
}
?>