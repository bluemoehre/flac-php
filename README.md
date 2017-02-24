FLAC-PHP [![Build Status](https://travis-ci.org/bluemoehre/flac-php.svg?branch=master)](https://travis-ci.org/bluemoehre/flac-php)
========

Class for native reading FLAC's metadata in PHP.
Provides direct access to the Vorbis comment (artist, title, album, …) to fetch all desired information.

Installation
------------

This class can easily be installed via [Composer](https://getcomposer.org):  
`composer require bluemoehre/flac-php`

Alternatively you may include it the old fashioned way of downloading and adding it via  
`require 'Flac.php'`

Usage
-----
```php
<?php

use bluemoehre\Flac;

header('Content-Type: text/plain;charset=utf-8');

// benchmark start
$t = microtime(true);

$flac = new Flac('mySong.flac');

// benchmark end
$t = microtime(true) - $t;

echo 'Benchmark: ' . $t .'s' . "\n\n";
echo 'Filename: ' . $flac->getFilename() . "\n";
echo 'File size: ' . $flac->getFileSize() . " Bytes\n";
echo 'Meta-Blocks: '; print_r($flac->getMetadataBlockCounts()); echo "\n";
echo 'Sample Rate: ' . $flac->getSampleRate() . "\n";
echo 'Channels: ' . $flac->getChannels() . "\n";
echo 'Bits per sample: ' . $flac->getBitsPerSample() . "\n";
echo 'Total samples: ' . $flac->getTotalSamples() . "\n";
echo 'Duration: ' . $flac->getDuration() . "s\n";
echo 'MD5 checksum (audio data): ' . $flac->getAudioMd5() . "\n";
echo 'Vorbis-Comment: '; nl2br(print_r($flac->getVorbisComment())); echo "\n";
```

TODOs
-----
- Add getter for pictures


Technical information
---------------------
FLAC: https://xiph.org/flac/format.html  
Vorbis comment: https://www.xiph.org/vorbis/doc/v-comment.html
