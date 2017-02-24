<?php

namespace bluemoehre;

use LogicException;
use RuntimeException;
use UnexpectedValueException;

/**
 * @license GNU General Public License http://www.gnu.org/licenses/licenses.html#GPL
 * @author BlueMöhre <bluemoehre@gmx.de>
 * @copyright 2012-2017 BlueMöhre
 * @link http://www.github.com/bluemoehre
 *
 * This code is based upon the really great FLAC-Project by Josh Coalson
 * http://flac.sourceforge.net
 */
class Flac
{
    const E_FILE_OPEN = 10;
    const E_FILE_CLOSE = 11;
    const E_FILE_READ = 12;
    const E_FILE_TYPE = 13;
    const E_METADATA_BLOCK = 20;
    const E_METADATA_BLOCK_DATA = 21;
    const E_PARAMETER = 30;

    const METADATA_BLOCK_STREAMINFO = 0;
    const METADATA_BLOCK_PADDING = 1;
    const METADATA_BLOCK_APPLICATION = 2;
    const METADATA_BLOCK_SEEKTABLE = 3;
    const METADATA_BLOCK_VORBIS_COMMENT = 4;
    const METADATA_BLOCK_CUESHEET = 5;
    const METADATA_BLOCK_PICTURE = 6;

    const BLOCK_SIZE_MIN = 16; // FLAC specifies a minimum block size of 16
    const BLOCK_SIZE_MAX = 65535; // FLAC specifies a maximum block size of 65535
    const SAMPLE_RATE_MIN = 1; // Sample rate of 0 is invalid
    const SAMPLE_RATE_MAX = 655350;  // The maximum sample rate is limited by the structure of frame headers to 655350Hz.


    /** @var string */
    protected $filename;

    /** @var resource */
    protected $fileHandle;

    /**
     * Amount of metadata blocks per type
     *
     * @var array[int]int
     */
    protected $metadataBlockCounts = [
        self::METADATA_BLOCK_STREAMINFO => 0,
        self::METADATA_BLOCK_PADDING => 0,
        self::METADATA_BLOCK_APPLICATION => 0,
        self::METADATA_BLOCK_SEEKTABLE => 0,
        self::METADATA_BLOCK_VORBIS_COMMENT => 0,
        self::METADATA_BLOCK_CUESHEET => 0,
        self::METADATA_BLOCK_PICTURE => 0
    ];

    /**
     * The minimum block size (in samples) used in the stream.
     *
     * @var int
     */
    protected $streamBlockSizeMin;

    /**
     * The maximum block size (in samples) used in the stream.
     * (Minimum blocksize == maximum blocksize) implies a fixed-blocksize stream.
     *
     * @var int
     */
    protected $streamBlockSizeMax;

    /**
     * The minimum frame size (in bytes) used in the stream. May be 0 to imply the value is not known.
     *
     * @var int
     */
    protected $streamFrameSizeMin;

    /**
     * The maximum frame size (in bytes) used in the stream. May be 0 to imply the value is not known.
     *
     * @var int
     */
    protected $streamFrameSizeMax;

    /** @var int */
    protected $sampleRate;

    /** @var int */
    protected $channels;

    /** @var int */
    protected $bitsPerSample;

    /** @var int */
    protected $totalSamples;

    /**
     * Total audio length in seconds
     *
     * @var float
     */
    protected $duration;

    /**
     * MD5 signature of the unencoded audio data.
     *
     * @var string
     */
    protected $audioMd5;

    /** @var array[string]mixed */
    protected $vorbisComment;


    /**
     * @param string $file
     * @throws RuntimeException if the file could not be accessed
     * @throws UnexpectedValueException if the file is no "FLAC"
     */
    public function __construct($file)
    {
        $this->filename = $file;

        if (!$this->fileHandle = @fopen($file, 'rb')) {
            throw new RuntimeException('Cannot access file "' . $file . '"', self::E_FILE_OPEN);
        }

        if ($this->read(4) != 'fLaC') {
            throw new UnexpectedValueException('Invalid file type. File is not FLAC!', self::E_FILE_TYPE);
        }

        $this->fetchMetadataBlocks();
    }

    /**
     * @throws RuntimeException if the file handle cannot be released
     */
    public function __destruct()
    {
        if (!fclose($this->fileHandle)) {
            throw new RuntimeException('Could not release file handle of "' . $this->filename . '"', self::E_FILE_CLOSE);
        }
    }

    /**
     * Fetches all metadata
     */
    protected function fetchMetadataBlocks()
    {
        $isLastMetadataBlock = false;
        $this->audioMd5;

        while (!$isLastMetadataBlock && !feof($this->fileHandle)) {
            $metadataBlockHeader = unpack('nlast_type/X/X/Nlength', $this->read(4));
            $isLastMetadataBlock = (bool)($metadataBlockHeader['last_type'] >> 15); // the first bit defines if this is the last meta block
            $metadataBlockType = $metadataBlockHeader['last_type'] >> 8 & 127; // bits 2-8 (7bit) of 16 define the block type
            $metadataBlockLength = $metadataBlockHeader['length'] & 16777215; // bits 9-32 (24bit) of 32 define block length

            // Streaminfo
            if ($metadataBlockType == self::METADATA_BLOCK_STREAMINFO) {

                if (array_sum($this->metadataBlockCounts) > 0) {
                    throw new UnexpectedValueException('METADATA_BLOCK_STREAMINFO must be the first metadata block', self::E_METADATA_BLOCK);
                }

                if ($this->metadataBlockCounts[self::METADATA_BLOCK_STREAMINFO] > 0) {
                    throw new UnexpectedValueException('METADATA_BLOCK_STREAMINFO must occur only once', self::E_METADATA_BLOCK);
                }

                $metadataBlockData = unpack(
                    'nminBlockSize/nmaxBlockSize/NminFrameSize/X/NmaxFrameSize/X/N2samplerate_channels_bitrate_samples/H32md5',
                    $this->read($metadataBlockLength)
                );
                $metadataBlockData['samplerate_channels_bitrate_samples'] = $metadataBlockData['samplerate_channels_bitrate_samples1'] << 32 | $metadataBlockData['samplerate_channels_bitrate_samples2'];
                $sampleRate = $metadataBlockData['samplerate_channels_bitrate_samples'] >> 44;

                if ($metadataBlockData['minBlockSize'] < self::BLOCK_SIZE_MIN) {
                    throw new UnexpectedValueException(
                        sprintf('Minimum block size of %d is less than the allowed minimum of %d', $metadataBlockData['minBlockSize'], self::BLOCK_SIZE_MIN),
                        self::E_METADATA_BLOCK_DATA
                    );
                }

                if ($metadataBlockData['maxBlockSize'] > self::BLOCK_SIZE_MAX) {
                    throw new UnexpectedValueException(
                        sprintf('Maximum block size of %d is more than the allowed maximum of %d', $metadataBlockData['maxBlockSize'], self::BLOCK_SIZE_MAX),
                        self::E_METADATA_BLOCK_DATA
                    );
                }

                if ($metadataBlockData['minBlockSize'] > $metadataBlockData['maxBlockSize']) {
                    throw new UnexpectedValueException(
                        sprintf('Minimum block size of %d must not be more than maximum block size of %d', $metadataBlockData['minBlockSize'], $metadataBlockData['maxBlockSize']),
                        self::E_METADATA_BLOCK_DATA
                    );
                }

                if ($sampleRate < self::SAMPLE_RATE_MIN || $sampleRate > self::SAMPLE_RATE_MAX) {
                    throw new UnexpectedValueException(
                        sprintf('Sample rate of %d is invalid. It must be within the range of %d-%d.', $sampleRate, self::SAMPLE_RATE_MIN, self::SAMPLE_RATE_MAX),
                        self::E_METADATA_BLOCK_DATA
                    );
                }

                if (!preg_match('/^[0-9a-f]{32}$/', $metadataBlockData['md5'])) {
                    throw new UnexpectedValueException('Invalid MD5 hash', self::E_METADATA_BLOCK_DATA);
                }

                $this->metadataBlockCounts[self::METADATA_BLOCK_STREAMINFO]++;
                $this->streamBlockSizeMin = $metadataBlockData['minBlockSize'];
                $this->streamBlockSizeMax = $metadataBlockData['maxBlockSize'];
                $this->streamFrameSizeMin = $metadataBlockData['minFrameSize'] >> 8;
                $this->streamFrameSizeMax = $metadataBlockData['maxFrameSize'] >> 8;
                $this->sampleRate = $sampleRate;
                $this->channels = ($metadataBlockData['samplerate_channels_bitrate_samples'] >> 41 & 7) + 1;
                $this->bitsPerSample = ($metadataBlockData['samplerate_channels_bitrate_samples'] >> 36 & 31) + 1;
                $this->totalSamples = $metadataBlockData['samplerate_channels_bitrate_samples'] & 68719476735;
                $this->duration = $this->totalSamples / $this->sampleRate;
                $this->audioMd5 = $metadataBlockData['md5'];
            }

            // Padding
            elseif ($metadataBlockType == self::METADATA_BLOCK_PADDING) {
                $this->metadataBlockCounts[self::METADATA_BLOCK_PADDING]++;
                fseek($this->fileHandle, $metadataBlockLength, SEEK_CUR);
            }

            // Application
            elseif ($metadataBlockType == self::METADATA_BLOCK_APPLICATION) {
                $this->metadataBlockCounts[self::METADATA_BLOCK_APPLICATION]++;
                fseek($this->fileHandle, $metadataBlockLength, SEEK_CUR);
            }

            // Seektable
            elseif ($metadataBlockType == self::METADATA_BLOCK_SEEKTABLE) {
                $this->metadataBlockCounts[self::METADATA_BLOCK_SEEKTABLE]++;
                fseek($this->fileHandle, $metadataBlockLength, SEEK_CUR);
            }

            // Vorbis Comment
            elseif ($metadataBlockType == self::METADATA_BLOCK_VORBIS_COMMENT) {
                $this->metadataBlockCounts[self::METADATA_BLOCK_VORBIS_COMMENT]++;
                $this->vorbisComment = [];

                $metadataBlockRaw = $this->read($metadataBlockLength);
                $rawPosition = 0;

                $metadataBlockData = unpack('V', substr($metadataBlockRaw, $rawPosition, 4));
                $this->vorbisComment['vendorLength'] = $metadataBlockData[1];
                $rawPosition += 4;

                $this->vorbisComment['vendorString'] = substr($metadataBlockRaw, $rawPosition, $this->vorbisComment['vendorLength']);
                $rawPosition += $this->vorbisComment['vendorLength'];

                $metadataBlockData = unpack('V', substr($metadataBlockRaw, $rawPosition, 4));
                $commentsLength = $metadataBlockData[1];
                $rawPosition += 4;

                for ($i = 0; $i < $commentsLength; $i++) {
                    $metadataBlockData = unpack('V', substr($metadataBlockRaw, $rawPosition, 4));
                    $commentSize = $metadataBlockData[1];
                    $rawPosition += 4;

                    $comment = substr($metadataBlockRaw, $rawPosition, $commentSize);
                    $rawPosition += $commentSize;

                    if (!$delimiterPosition = strpos($comment, '=')) {
                        throw new UnexpectedValueException('Vorbis comment must contain "=" as delimiter', self::E_METADATA_BLOCK_DATA);
                    }

                    $field = strtolower(substr($comment, 0, $delimiterPosition));
                    $value = substr($comment, $delimiterPosition + 1);

                    $this->vorbisComment['comments'][$field][] = $value;
                }
            }

            // Cuesheet
            elseif ($metadataBlockType == self::METADATA_BLOCK_CUESHEET) {
                $this->metadataBlockCounts[self::METADATA_BLOCK_CUESHEET] += 1;
                fseek($this->fileHandle, $metadataBlockLength, SEEK_CUR);
            }

            // Picture
            elseif ($metadataBlockType == self::METADATA_BLOCK_PICTURE) {
                $this->metadataBlockCounts[self::METADATA_BLOCK_PICTURE] += 1;
                fseek($this->fileHandle, $metadataBlockLength, SEEK_CUR);
            }

            elseif ($metadataBlockType > 126) {
                throw new UnexpectedValueException(
                    sprintf('Invalid metadata block type: %d', $metadataBlockType),
                    self::E_METADATA_BLOCK
                );
            }

        }

    }

    /**
     * Reads $length bytes from the filename
     * @param int $length
     * @throws LogicException
     * @throws RuntimeException
     * @return string
     */
    protected function read($length)
    {
        if (!is_int($length) || $length < 1) {
            throw new LogicException('Argument must be positive integer', self::E_PARAMETER);
        }

        if (!$data = @fread($this->fileHandle, $length)) {
            throw new RuntimeException('Cannot not read from filename', self::E_FILE_READ);
        }

        return $data;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return array
     */
    public function getMetadataBlockCounts()
    {
        return $this->metadataBlockCounts;
    }

    /**
     * @return int
     */
    public function getStreamBlockSizeMin()
    {
        return $this->streamBlockSizeMin;
    }

    /**
     * @return int
     */
    public function getStreamBlockSizeMax()
    {
        return $this->streamBlockSizeMax;
    }

    /**
     * @return int
     */
    public function getStreamFrameSizeMin()
    {
        return $this->streamFrameSizeMin;
    }

    /**
     * @return int
     */
    public function getStreamFrameSizeMax()
    {
        return $this->streamFrameSizeMax;
    }

    /**
     * @return int
     */
    public function getSampleRate()
    {
        return $this->sampleRate;
    }

    /**
     * @return int
     */
    public function getChannels()
    {
        return $this->channels;
    }

    /**
     * @return int
     */
    public function getBitsPerSample()
    {
        return $this->bitsPerSample;
    }

    /**
     * @return int
     */
    public function getTotalSamples()
    {
        return $this->totalSamples;
    }

    /**
     * Audio length in seconds
     * @return float
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * @return string
     */
    public function getAudioMd5()
    {
        return $this->audioMd5;
    }

    /**
     * @return array
     */
    public function getVorbisComment()
    {
        return $this->vorbisComment;
    }

}