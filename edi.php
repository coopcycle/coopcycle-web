<?php

require 'vendor/autoload.php';

use EDI\Parser;
use EDI\Reader;

$file = $argv[1];

$parser = new Parser();
$parser->load($file);

$parsed = $parser->get();

print_r($parsed);

$reader = new Reader();
$reader->setParsedFile($parser->get());

$nad = $reader->readEdiDataValue('NAD', 1);

var_dump($nad);
