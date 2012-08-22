<?PHP

/**
 * @license GNU General Public License http://www.gnu.org/licenses/licenses.html#GPL
 * @author BlueMöhre <bluemoehre@gmx.de>
 * @copyright 2012 BlueMöhre
 * @link http://www.github.com/bluemoehre
 *
 * This code is based upon the really great FLAC-Project by Josh Coalson
 * http://flac.sourceforge.net
 *
 * This code is formatted regarding the official code style (mostly ;)
 * http://github.com/php-fig/fig-standards/tree/master/accepted
 */
class Flac
{

    const ERR_FILE_INVALID = 'Invalid FLAC File.';
    const ERR_FILE_UNREADABLE = 'Cannot read file.';
    const ERR_METABLOCK_INVALID = 'Invalid meta block.';
    const ERR_META_INVALID = 'Invalid meta data.';
    const ERR_PARAMETER = 'Invalid parameter value.';

    const META_BLOCK_STREAMINFO = 0;
    const META_BLOCK_PADDING = 1;
    const META_BLOCK_APPLICATION = 2;
    const META_BLOCK_SEEKTABLE = 3;
    const META_BLOCK_VORBIS_COMMENT = 4;
    const META_BLOCK_CUESHEET = 5;
    const META_BLOCK_PICTURE = 6;


    protected $file = null;
    protected $fileHandle = null;
    protected $streamMetaBlocks = null;
    protected $streamMinBlockSize = null;
    protected $streamMaxBlockSize = null;
    protected $streamMinFrameSize = null;
    protected $streamMaxFrameSize = null;
    protected $streamSampleRate = null;
    protected $streamChannels = null;
    protected $streamBitsPerSample = null;
    protected $streamTotalSamples = null;
    protected $streamDuration = null;
    protected $streamMd5 = null;
    protected $vorbisComment = null;


    /**
     * @param string $file
     * @throws ErrorException
     */
    public function __construct($file)
    {
        $this->file = $file;
        if (!$this->fileHandle = @fopen($file,'rb')) throw new ErrorException(self::ERR_FILE_UNREADABLE, E_USER_ERROR);
        if ($this->read(4) != 'fLaC') throw new ErrorException(self::ERR_FILE_INVALID, E_USER_ERROR);
        $this->fetchMetaBlocks();
    }


    /**
     * Magic getter for all protected properties
     * @param string $property
     */
    public function __get($property)
    {
        return $this->$property;
    }


    /**
     * Fetches all metadata from the file
     * @throws ErrorException
     * @throws Exception
     */
    protected function fetchMetaBlocks()
    {
        $this->streamMetaBlocks = array(
            self::META_BLOCK_STREAMINFO => 0,
            self::META_BLOCK_PADDING => 0,
            self::META_BLOCK_APPLICATION => 0,
            self::META_BLOCK_SEEKTABLE => 0,
            self::META_BLOCK_VORBIS_COMMENT => 0,
            self::META_BLOCK_CUESHEET => 0,
            self::META_BLOCK_PICTURE => 0
        );

        # read all meta blocks
        $lastMetaBlock = false;
        while (!$lastMetaBlock && !feof($this->fileHandle)){
            $metaBlockHeader = $this->read(4); # read block header at once
            $data = unpack('nlast_type/X/X/Nlength', $metaBlockHeader);
            if ($data['last_type'] >> 15) $lastMetaBlock = true; # the first bit defines if this is the last meta block
            $metaBlockType = $data['last_type'] >> 8 & 127; # bits 2-8 (7bit) of 16 define the block type
            $metaBlockLength = $data['length'] & 16777215; # bits 9-32 (24bit) of 32 define block length
            # Streaminfo
            if ($metaBlockType == self::META_BLOCK_STREAMINFO){
                if ($this->streamMetaBlocks[self::META_BLOCK_STREAMINFO] > 0) throw new ErrorException(self::ERR_META_INVALID, E_USER_ERROR); # STREAMINFO must be the first meta block and it can be specified only once
                $this->streamMetaBlocks[0] += 1;
                $format = 'nminBlockSize/nmaxBlockSize/NminFrameSize/X/NmaxFrameSize/X/N2samplerate_channels_bitrate_samples/H32md5';
                $data = unpack($format, $this->read($metaBlockLength)); # read whole block at once
                if ($data['minBlockSize'] < 16) throw new ErrorException(self::ERR_META_INVALID, E_USER_ERROR); # FLAC specifies a minimum block size of 16
                if ($data['maxBlockSize'] > 65535) throw new ErrorException(self::ERR_META_INVALID, E_USER_ERROR); # FLAC specifies a maximum block size of 65535
                if ($data['minBlockSize'] > $data['maxBlockSize']) throw new ErrorException(self::ERR_META_INVALID, E_USER_ERROR); # Minimum block size cannot be greater than maximum block size
                $this->streamMinBlockSize = $data['minBlockSize'];
                $this->streamMaxBlockSize = $data['maxBlockSize'];
                $this->streamMinFrameSize = $data['minFrameSize'] >> 8;
                $this->streamMaxFrameSize = $data['maxFrameSize'] >> 8;
                $data['samplerate_channels_bitrate_samples'] = $data['samplerate_channels_bitrate_samples1'] << 32 | $data['samplerate_channels_bitrate_samples2'];
                $sampleRate = $data['samplerate_channels_bitrate_samples'] >> 44;
                if ($sampleRate == 0 || $sampleRate > 655350) throw new ErrorException(self::ERR_META_INVALID, E_USER_ERROR); # Sample rate must be greater than 0 and less than 655351
                $this->streamSampleRate = $sampleRate;
                $this->streamChannels = ($data['samplerate_channels_bitrate_samples'] >> 41 & 7) + 1;
                $this->streamBitsPerSample = ($data['samplerate_channels_bitrate_samples'] >> 36 & 31) + 1;
                $this->streamTotalSamples = $data['samplerate_channels_bitrate_samples'] & 68719476735;
                $this->streamDuration = round($this->streamTotalSamples / $this->streamSampleRate);
                if (!preg_match('/^[0-9a-f]{32}$/', $data['md5'])) throw new ErrorException(self::ERR_META_INVALID, E_USER_ERROR);
                $this->streamMd5 = $data['md5'];
            }

            # Padding
            elseif ($metaBlockType == self::META_BLOCK_PADDING){
                $this->streamMetaBlocks[self::META_BLOCK_PADDING] += 1;
                fseek($this->fileHandle, $metaBlockLength, SEEK_CUR);
            }

            # Application
            elseif ($metaBlockType == self::META_BLOCK_APPLICATION){
                $this->streamMetaBlocks[self::META_BLOCK_APPLICATION] += 1;
                fseek($this->fileHandle, $metaBlockLength, SEEK_CUR);
            }

            # Seektable
            elseif ($metaBlockType == self::META_BLOCK_SEEKTABLE){
                $this->streamMetaBlocks[self::META_BLOCK_SEEKTABLE] += 1;
                fseek($this->fileHandle, $metaBlockLength, SEEK_CUR);
            }

            # Vorbis Comment
            elseif ($metaBlockType == self::META_BLOCK_VORBIS_COMMENT){
                $this->streamMetaBlocks[self::META_BLOCK_VORBIS_COMMENT] += 1;
                $this->vorbisComment = array();
                $raw = $this->read($metaBlockLength);
                $strpos = 0;
                $data = unpack('V', substr($raw, $strpos, 4));
                $this->vorbisComment['vendorLength'] = $data[1];
                $strpos += 4;
                $this->vorbisComment['vendorString'] = substr($raw, $strpos, $this->vorbisComment['vendorLength']);
                $strpos += $this->vorbisComment['vendorLength'];
                $data = unpack('V', substr($raw, $strpos, 4));
                $commentsLength = $data[1];
                $strpos += 4;
                for($i = 0; $i < $commentsLength; $i++){
                    $data = unpack('V', substr($raw, $strpos, 4));
                    $commentSize = $data[1];
                    $strpos += 4;
                    $comment = substr($raw, $strpos, $commentSize);
                    $strpos += $commentSize;
                    $pos = strpos($comment, '=');
                    if ($pos === false) throw new Exception(self::ERR_META_INVALID);
                    $field = strtolower(substr($comment, 0, $pos));
                    $value = substr($comment, $pos + 1);
                    $this->vorbisComment['comments'][$field][] = $value;
                }
            }

            # Cuesheet
            elseif ($metaBlockType == self::META_BLOCK_CUESHEET){
                $this->streamMetaBlocks[self::META_BLOCK_CUESHEET] += 1;
                fseek($this->fileHandle, $metaBlockLength, SEEK_CUR);
            }

            # Picture
            elseif ($metaBlockType == self::META_BLOCK_PICTURE){
                $this->streamMetaBlocks[self::META_BLOCK_PICTURE] += 1;
                fseek($this->fileHandle, $metaBlockLength, SEEK_CUR);
            }

            elseif ($metaBlockType > 126) throw new Exception(self::ERR_METABLOCK_INVALID, E_USER_ERROR);

        }

    }


    /**
     * Reads $length bytes from the file
     * @param int $length
     * @throws LogicException
     * @throws Exception
     * @return string
     */
    protected function read($length)
    {
        if ((int)$length < 1) throw new LogicException(self::ERR_PARAMETER, E_USER_ERROR);
        if (!$data = @fread($this->fileHandle, $length)) throw new Exception(self::ERR_FILE_UNREADABLE, E_USER_ERROR);
        return $data;
    }

}

?>
