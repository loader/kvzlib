<?php
function phptParts($str) {
    // Load the sections of the test file.
    $section_text = array(
        'TEST'   => '',
        'SKIPIF' => '',
        'GET'    => '',
        'COOKIE' => '',
        'POST_RAW' => '',
        'POST'   => '',
        'UPLOAD' => '',
        'ARGS'   => '',
    );
    
    $lines = explode("\n", $str);
    $line  = array_shift($lines);
    
    $borked = false;
    $bork_info = '';
    if (strncmp('--TEST--', $line, 8)) {
        $bork_info = "tests must start with --TEST-- [$file]";
        $borked = true;
    }
    $section = 'TEST';
    $secfile = false;
    $secdone = false;
    foreach ($lines as $line) {
        // Match the beginning of a section.
        if (preg_match('/^--([_A-Z]+)--/', $line, $r)) {
            $section = $r[1];
            $section_text[$section] = '';
            $secfile = $section == 'FILE' || $section == 'FILEEOF';
            $secdone = false;
            continue;
        }
        
        // Add to the section text.
        if (!$secdone) {
            $section_text[$section] .= $line;
        }

        // End of actual test?
        if ($secfile && preg_match('/^===DONE===$/', $line)) {
            $secdone = true;
        }        
    }
    return $section_text;
}

// Check file
if ($filepath = $argv[1]) {
    $filepath = realpath($filepath);
    if (!file_exists($filepath)) {
        die($filepath." does not exist.");
    }
}

if ($filepath) {
    $buf      = file_get_contents($filepath);
    $sections = phptParts($buf);
    print_r($sections); 
}
?>