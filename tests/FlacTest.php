<?php

use PHPUnit\Framework\TestCase ;
use bluemoehre\Flac;

class FlacTest extends TestCase
{
    public function test()
    {
        $flac = new Flac('fixtures/44100Hz-16bit-1ch.flac');
        $this->assertEquals(67590, $flac->getFileSize(), 'Filesize should be 67.590 Bytes');
        $this->assertEquals(1.0, $flac->getDuration(), 'Duration should be 1sec');
        $this->assertEquals(44100, $flac->getSampleRate(), 'Sample rate should be 44.1KHz');
        $this->assertEquals(1, $flac->getChannels(), 'Channel count should be 1');
        $this->assertEquals(16, $flac->getBitsPerSample());
        $this->assertEquals('874465dc8789a3047d91ffd456c185cf', $flac->getAudioMd5());
        $this->assertEquals(44100, $flac->getTotalSamples());
        $this->assertEquals(
            [
                'vendorLength' => 32,
                'vendorString' => 'reference libFLAC 1.3.1 20141125',
                'comments' => [
                    'title' => [
                        'Chirp / Square (non aliased)'
                    ],
                    'date' => [
                        2017
                    ],
                    'artist' => [
                        'Generator'
                    ]
                ],
            ],
            $flac->getVorbisComment()
        );
//        Embedded cuesheet : no

        $flac = new Flac('fixtures/44100Hz-24bit-1ch.flac');
        $this->assertEquals(111775, $flac->getFileSize(), 'Filesize should be 111.775 Bytes');
        $this->assertEquals(1.0, $flac->getDuration(), 'Duration should be 1sec');
        $this->assertEquals(44100, $flac->getSampleRate(), 'Sample rate should be 44.1KHz');
        $this->assertEquals(1, $flac->getChannels(), 'Channel count should be 1');
        $this->assertEquals(24, $flac->getBitsPerSample());
        $this->assertEquals('036e068f773b5bbe31d722c70350bf9e', $flac->getAudioMd5());
        $this->assertEquals(44100, $flac->getTotalSamples());
        $this->assertEquals(
            [
                'vendorLength' => 32,
                'vendorString' => 'reference libFLAC 1.3.1 20141125',
                'comments' => [
                    'title' => [
                        'Chirp / Square (non aliased)'
                    ],
                    'date' => [
                        2017
                    ],
                    'artist' => [
                        'Generator'
                    ]
                ],
            ],
            $flac->getVorbisComment()
        );
//        Embedded cuesheet : no

        $flac = new Flac('fixtures/192000Hz-16bit-2ch.flac');
        $this->assertEquals(492615, $flac->getFileSize(), 'Filesize should be 492.615 Bytes');
        $this->assertEquals(1.0, $flac->getDuration(), 'Duration should be 1sec');
        $this->assertEquals(192000, $flac->getSampleRate(), 'Sample rate should be 192KHz');
        $this->assertEquals(2, $flac->getChannels(), 'Channel count should be 2');
        $this->assertEquals(16, $flac->getBitsPerSample());
        $this->assertEquals('92cae790983d8e8e0d811f5b11118ba3', $flac->getAudioMd5());
        $this->assertEquals(192000, $flac->getTotalSamples());
        $this->assertEquals(
            [
                'vendorLength' => 32,
                'vendorString' => 'reference libFLAC 1.3.1 20141125',
                'comments' => [
                    'title' => [
                        'Chirp / Sawtooth'
                    ],
                    'date' => [
                        2017
                    ],
                    'artist' => [
                        'Generator'
                    ]
                ],
            ],
            $flac->getVorbisComment()
        );
//        Embedded cuesheet : no

        $flac = new Flac('fixtures/192000Hz-24bit-2ch.flac');
        $this->assertEquals(883514, $flac->getFileSize(), 'Filesize should be 883.514 Bytes');
        $this->assertEquals(1.0, $flac->getDuration(), 'Duration should be 1sec');
        $this->assertEquals(192000, $flac->getSampleRate(), 'Sample rate should be 192KHz');
        $this->assertEquals(2, $flac->getChannels(), 'Channel count should be 2');
        $this->assertEquals(24, $flac->getBitsPerSample());
        $this->assertEquals('270f45a5aa5b75dfb6acaa806eb08ba6', $flac->getAudioMd5());
        $this->assertEquals(192000, $flac->getTotalSamples());
        $this->assertEquals(
            [
                'vendorLength' => 32,
                'vendorString' => 'reference libFLAC 1.3.1 20141125',
                'comments' => [
                    'title' => [
                        'Chirp / Sawtooth'
                    ],
                    'date' => [
                        2017
                    ],
                    'artist' => [
                        'Generator'
                    ]
                ],
            ],
            $flac->getVorbisComment()
        );
//        Embedded cuesheet : no
    }
}