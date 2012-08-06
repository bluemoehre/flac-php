FLAC-PHP
========

Class for native reading FLAC's meta data.

Example
-------

```php
<?php
require('php/flac.class.php');

header('Content-Type: text/plain;charset=utf-8');

$t = microtime(true);
$flac = new Flac('mySong.flac');
echo 'Meta-Blocks: '; print_r($flac->streamMetaBlocks); echo "\n";
echo 'Sample Rate: '.$flac->streamSampleRate."\n";
echo 'Channels: '.$flac->streamChannels."\n";
echo 'Bits per sample: '.$flac->streamBitsPerSample."\n";
echo 'Total samples: '.$flac->streamTotalSamples."\n";
echo 'Duration: '.$flac->streamDuration."\n";
echo 'MD5 checksum (audio data): '.$flac->streamMd5."\n";
echo 'Vorbis-Comment: ';nl2br(print_r($flac->vorbisComment)); echo "\n\n";
echo 'runtime: '.(microtime(true) - $t).'s';
```