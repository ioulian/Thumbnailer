<?php
/**
 *  _____ _   _ _   _ __  __ ____  _   _    _    ___ _     _____ ____  
 * |_   _| | | | | | |  \/  | __ )| \ | |  / \  |_ _| |   | ____|  _ \ 
 *   | | | |_| | | | | |\/| |  _ \|  \| | / _ \  | || |   |  _| | |_) |
 *   | | |  _  | |_| | |  | | |_) | |\  |/ ___ \ | || |___| |___|  _ < 
 *   |_| |_| |_|\___/|_|  |_|____/|_| \_/_/   \_\___|_____|_____|_| \_\
 *                                     By Ioulian Alexeev, me@alexju.be
 * 
 *
 * VERSION: v1.0.28
 *
 * OVERVIEW:
 *
 * Creates a thumbnail on the fly for a .jpg or a .png image.
 * It can also cache the thumbnail for better performance.
 *
 * Thanks to: https://github.com/chrisbliss18 for the ico generation code
 */

 
// Uncomment for standalone usage
/*$thumb = new Thumbnailer($_GET);
$thumb->show();*/

class Thumbnailer {
    // Variables you can change:
    // Cache: turn off for development, but don't forget to turn it back on
    private $_cache = true;
    // '' is a cachefile, TODO: check not on extension, but on image type itself
    private $_safeExtensions = array('jpg', 'jpeg', 'png', 'gif', 'tmp', '');
    private $_defaults = array(
        // Image path (relative to root)
        'img' => ['', 'string'],
        
        // Image path (full path, not an url)
        'fullpath' => ['', 'string'],
        
        // Desired width
        'w' => [0, 'int'],
        
        // Desired height
        'h' => [0, 'int'],
        
        // If true, the thumbnailer will calculate the dimensions based on the original images.
        // If false it will use the passed dimensions. This can result in vertical or horisontal bars
        'resize' => [true, 'bool'],
        
        // Cropping: if set to true, the original image is cropped
        'fill' => [true, 'bool'],
        
        // Background color of the image
        'bg' => ['ffffff', 'string'],
        
        // Foreground color (used to color the cross on empty picture)
        'fg' => ['eeeeee', 'string'],
        
        // Stroke width
        'boldness' => [2, 'int'],
        
        // Default image (relative to root)
        'default' => ['', 'string'],
        
        // .jpg quality
        'quality' => [90, 'int'],
        
        // Donload image name without the extension. If empty, no image download is initiated
        'download' => ['', 'string'],
        
        // Keep transparent .png's or make the bars around the image transparent
        'transparent' => [true, 'bool'],
        
        // Convert to image type ".jpg", ".jpeg", ".png", ".ico"
        'type' => ['', 'string'],
        
        // Currently only "clearcache" is supported, it clears the thumbnail cache ;)
        'action' => ['', 'string'],
        
        // Client-side cache time in days
        'cachetime' => [7, 'float'],
        
        // Client cache.
        // If set to true, there will be no request sent to the server by the client
        // If set to false, there will be a request, but there will be a check on modified date and if needed, only the 304 code will be sent back
        'clientcache' => [false, 'bool'],
        
        // Position of the image inside the thumbnail: "left", "right", "center", "top", "bottom"
        'pos' => [['center'], 'array,string'],
        
        // Enlarge the image to fit the thumbnail
        // If set to false, The original image will not be scaled if it's smaller than the thumbnailer
        'enlarge' => [true, 'bool'],
        
        // Scales the thumbnail
        // 100x50px &scale=2 = 200x100px
        'scale' => [1, 'float'],
        
        // Image filters
        // You can pass an array with filters
        // Filters will be applied by their sorting in the array
        'filter' => [[], 'array,string'],
        
        // Mirror image
        // You can pass a string or an array of these values:
        // 'horizontal' or 'vertical' (you can pass both)
        'mirror' => [[], 'array,string'],
        
        // ICO format sizes
        // Pass array with the sizes you want to put into an .ico file
        'ico_sizes' => [[256, 128, 64, 32, 16], 'array,int'],
        
        // Add a color overlay on the image
        // Pass an array of the hex color and an int (0-255) for the transparency of the color
        // 0 = 100% transparent, 255 = 0% transparent
        // &color_overlay=f00,127 will add a red overlay over the image with 50% transparency
        'color_overlay' => [[], 'array,string'],
        
        // Forces the script to generate a new thumbnail and update it's cache even if the cache exists
        'force_update' => [false, 'bool'],
    );

    private $_possibleImageFilters = array(
        'negate' => array(
            'name' => IMG_FILTER_NEGATE,
        ),
        'grayscale' => array(
            'name' => IMG_FILTER_GRAYSCALE,
        ),
        'brightness' => array(
            'name' => IMG_FILTER_BRIGHTNESS,
            'numArgs' => 1
        ),
        'contrast' => array(
            'name' => IMG_FILTER_CONTRAST,
            'numArgs' => 1
        ),
        'colorize' => array(
            'name' => IMG_FILTER_COLORIZE,
            'numArgs' => 4
        ),
        'edgedetect' => array(
            'name' => IMG_FILTER_EDGEDETECT,
        ),
        'emboss' => array(
            'name' => IMG_FILTER_EMBOSS,
        ),
        'guassianblur' => array(
            'name' => IMG_FILTER_GAUSSIAN_BLUR,
        ),
        'blur' => array(
            'name' => IMG_FILTER_SELECTIVE_BLUR,
        ),
        'meanremoval' => array(
            'name' => IMG_FILTER_MEAN_REMOVAL,
        ),
        'smooth' => array(
            'name' => IMG_FILTER_SMOOTH,
            'numArgs' => 1
        ),
        'pixelate' => array(
            'name' => IMG_FILTER_PIXELATE,
            'numArgs' => 2
        ),
    );
    
    // Project root path
    private $_root = '';
    
    // Cache
    private $_cachePath = '';
    private $_cachedFileName = '';
    private $_cachedFileExists = false;

    private $_icoImages = [];
    
    // Params
    private $_options = [];
    
    // Generated image
    private $_image = null;
    
    // Path separator
    private $_pathSeparator = null;
    
    public function __construct($params = null, $root = null, $cacheRelativePath = '/tmp') {
        $this->_setOptions($params);
        
        // Init variables
        $this->_root = ($root === null) ? getcwd() : $root;
        $this->_makeDir($this->_root . $cacheRelativePath);
        $this->_cachePath = realpath($this->_root . $cacheRelativePath);
        $this->_pathSeparator = DIRECTORY_SEPARATOR;
        
        // Make fullpath
        $this->_makeFullPath();
        $this->_checkIfFileIsSafe();

        // Check if we need to increase image size for retina display
        $this->_checkIfRetinaResNeeded();
        
        if ($this->_options['action'] === 'clearcache') {
            $this->clearThumbCache();
        }
        
        return $this;
    }

    private function _checkIfRetinaResNeeded() {
        $retinaPart = '@2x.';
        if (strpos($this->_options['fullpath'], $retinaPart) !== false) {
            // Set the scale
            $this->setOption('scale', $this->_options['scale'] * 2);
        }

        // Remove the part from the image filename
        $this->_options['fullpath'] = str_replace($retinaPart, '.', $this->_options['fullpath']);
        $this->_options['img'] = str_replace($retinaPart, '.', $this->_options['img']);
    }
    
    /**
     * Sets path variables.
     * @return void
     */
    private function _makeFullPath() {
        if ($this->_options['fullpath'] === '') {
            $this->_options['img'] = str_replace('http://'.$_SERVER['HTTP_HOST'], '', $this->_options['img']);
            $this->_options['img'] = str_replace('https://'.$_SERVER['HTTP_HOST'], '', $this->_options['img']);

            if ($this->_isExternPath($this->_options['img'])) {
                $this->_options['fullpath'] = str_replace(' ', '%20', $this->_options['img']);
            } else if ($this->_options['img'] === '') {
                $this->_options['fullpath'] = $this->_root.$this->_options['default'];
            } else {
                $this->_options['fullpath'] = $this->_root.$this->_options['img'];
            }
        } else {
            $this->_options['img'] = str_replace($this->_root, '', $this->_options['fullpath']);
        }
    }
    
    /**
     * Checks if the file extension is safe for processing
     * @return void
     */
    private function _checkIfFileIsSafe() {
        if (file_exists($this->_options['fullpath'])) {
            $info = pathinfo($this->_options['fullpath']);
            if (isset($info['extension']) && !in_array(strtolower($info['extension']), $this->_safeExtensions)) {
                throw new Exception('Unsafe extension ".'.$info['extension'].'"! Aborting script.');
            }
        }
    }
    
    /**
    * Sets options, if some params are not passed, the defaults are used
    * @param string[] $params Option keys with values.
    */
    private function _setOptions($params) {
        foreach ($this->_defaults as $key => $value) {
            if (!array_key_exists($key, $params)) {
                // Use default
                $this->_options[$key] = $value[0];
            } else {
                $this->setOption($key, $params[$key]);
            }
        }
    }
    
    /**
    * Sets option
    *
    * @param string $key Option key
    * @param string $value Option value
    */
    public function setOption($key, $value) {
        if (!isset($this->_defaults[$key])) {
            return;
        }

        $type = explode(',', $this->_defaults[$key][1]);
        $newValue = null;
        if ($type[0] === 'string') {
            $newValue = (string)$value;
        } else if ($type[0] === 'int' && is_numeric($value)) {
            $newValue = (int)$value;
        } else if ($type[0] === 'float' && is_numeric($value)) {
            $newValue = (float)$value;
        } else if ($type[0] === 'bool') {
            if (is_bool($value)) {
                $newValue = $value;
            } else if ($value === 1 || in_array($value, ['true', 'on', 'yes', 'y'])) {
                $newValue = true;
            } else if ($value === 0 || in_array($value, ['false', 'off', 'no', 'n'])) {
                $newValue = false;
            }
        } else if ($type[0] === 'array') {
            if (is_array($value)) {
                $newValue = $value;
            } else if (is_string($value)) {
                if (strpos($value, ',') !== false) {
                    $newValue = explode(',', $value);
                } else {
                    $newValue = [$value];
                }
            }

            foreach ($newValue as &$item) {
                if ($type[1] === 'string') {
                    $item = (string)$item;
                } else if ($type[1] === 'int') {
                    $item = (int)$item;
                }
            }
        }

        if ($newValue !== null) {
            $this->_options[$key] = $newValue;
        }
    }
    
    /**
    * Gets option
    *
    * @param string $key Option key
    * @return string $value Option value
    */
    public function getOption($key) {
        return $this->_options[$key];
    }

    private function _isCacheOld() {
        if ($this->_cachedFileExists && !$this->_isExternPath($this->_options['img'])) {
            if (file_exists($this->_options['fullpath']) &&
                    filemtime($this->_options['fullpath']) > filemtime($this->_cachePath.$this->_pathSeparator.$this->_cachedFileName)) {
                return true;
            }
        }

        return false;
    }
    
    /**
    * Checks if thumb-cache exists, if not it makes a new thumb and saves it.
    * @return Object Thumbnailer class
    */
    private function _handleThumbRequest() {
        // Check for stored cache
        $this->_cachedFileName = md5($_SERVER['QUERY_STRING'] . serialize($this->_options));
        $this->_cachedFileExists = file_exists($this->_cachePath.$this->_pathSeparator.$this->_cachedFileName);

        // Check if the cached image is older than the file. We can only check if the image is on current server
          
        
        // Create thumb
        if (!$this->_cache // Always generate a new thumb if cache is off
                || (!$this->_cachedFileExists && $this->_cache) // Cache is on but there's no cached image
                || ($this->_isCacheOld() && $this->_cache) // The new image file is newer than the cache
                || $this->_options['force_update'] === true // User forces to generate a new thumbnail
            ) {
            $this->_image = $this->makeThumb();
        }
        
        // Save thumbnail to cache if needed
        if ($this->_cache && (!$this->_cachedFileExists || $this->_isCacheOld() || $this->_options['force_update'] === true)) {
            $this->saveImage($this->_cachePath.$this->_pathSeparator.$this->_cachedFileName);

            if ($this->_options['type'] === 'ico') {
                $this->_image = null;
            } else {
                imagedestroy($this->_image);
            }
        }

        return $this;
    }
    
    /**
     * Sets headers before outputting the image
     * @return void
     */
    private function _setOutputHeaders() {
        // Set content type
        $contentType = 'jpeg';
        if ($this->_options['type'] !== '') {
            if ($this->_options['type'] === 'ico') {
                $contentType = 'x-icon';
            } else {
                $contentType = $this->_options['type'];
            }
        } else if ($this->_options['type'] === '') {
            if ($this->_cache) {
                $contentType = $this->_getImageType($this->_cachePath.$this->_pathSeparator.$this->_cachedFileName);
            } else {
                $contentType = $this->_getImageType($this->_options['fullpath']);
            }
        }
        header('Content-type: image/'.$contentType);
        
        // Check if user wants to download
        if ($this->_options['download'] !== '') {
            $ext = $contentType;
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }

            header('Content-Disposition: attachment; filename='.$this->_options['download'].'.'.$ext);
        }

        if ($this->_cache === true) {
            $fileModifiedDate = filemtime($this->_cachePath.$this->_pathSeparator.$this->_cachedFileName);

            $headers = apache_request_headers();
            if (isset($headers['If-Modified-Since']) && strtotime($headers['If-Modified-Since']) === $fileModifiedDate) {
                // Client's cache IS current, so we just respond '304 Not Modified'.
                header('Last-Modified: '.gmdate('D, d M Y H:i:s', $fileModifiedDate).' GMT', true, 304);
                exit;
            } else {
                // Image not cached or cache outdated, we respond '200 OK' and output the image.
                header('Last-Modified: '.gmdate('D, d M Y H:i:s', $fileModifiedDate).' GMT', true, 200);
                header('Content-Length: '.filesize($this->_cachePath.$this->_pathSeparator.$this->_cachedFileName));
            }

            // Show image
            if ($this->_options['clientcache'] === true) {
                // Sets cache headers
                $cacheTime = 3600 * 24 * (int)$this->_options['cachetime'];
                header('Expires: '.gmdate("D, d M Y H:i:s", $fileModifiedDate + $cacheTime).' GMT');
                header('Cache-Control: max-age='.$cacheTime.', public');
                header('Pragma: public');
            }
        }
    }
    
    /**
     * Sets headers and outputs the image
     * @return void
     */
    public function show() {
        $this->_handleThumbRequest();
        $this->_setOutputHeaders();
        
        // Show image
        if ($this->_cache) {
            // Note to developers:
            // If you are using some sort of _GET, _POST, _COOKIE, ... parameters to set
            // the cache path or the root path, make sure you sanity check the path's so
            // file_get_contents doesn't ouput any other file on your server!
            echo file_get_contents($this->_cachePath.$this->_pathSeparator.$this->_cachedFileName);
        } else {
            $this->_toImage($this->_image);
        }
    }
    
    /**
    * Clears thumb cache
    * @return Object Thumbnailer class
    */
    public function clearThumbCache() {
        $dir = opendir($this->_cachePath);
        while (false !== ($file = readdir($dir))) {
            if ($file !== "."
                && $file !== ".."
                && $file !== ".svn"
                && $file !== ".git"
                ) {
                chmod($this->_cachePath.$this->_pathSeparator.$file, 0777);
                unlink($this->_cachePath.$this->_pathSeparator.$file) or die("Couldn't delete $this->_cachePath.'/'.$file<br />");
            }
        }
        closedir($dir);
        return $this;
    }

    /**
    * Makes a thumb from an image or generates dummy image
    *
    * @param string $src Image source
    * @return ImageResourceIdentifier
    */
    public function makeThumb() {
        $image = null;
        if ($this->_urlExists($this->_options['fullpath']) || file_exists($this->_options['fullpath'])) {
            if ($this->_options['type'] === "ico") {
                $this->_options['w'] = 256;
                $this->_options['h'] = 256;
                $this->_options['resize'] = false;
            }

            $image = $this->resizeImage();
            
            if ($this->_options['type'] === "ico") {
                $image = $this->_makeIco($image);
            }
        } else {
            $image = $this->makeDummyImage();
        }
        
        return $image;
    }

    /**
     * This function has been written by: https://github.com/chrisbliss18/php-ico/blob/master/class-php-ico.php
     */
    private function _makeIco($image) {
        foreach ($this->_options['ico_sizes'] as $size) {
            if (!in_array($size, [256, 128, 64, 32, 16])) {
                continue;
            }

            $newImage = imagecreatetruecolor((int)$size, (int)$size);
            
            imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            
            $source_width = imagesx($image);
            $source_height = imagesy($image);
            
            if (imagecopyresampled($newImage, $image, 0, 0, 0, 0, (int)$size, (int)$size, $source_width, $source_height) === false) {
                continue;
            }
            
            $this->_addIcoImage($newImage);
        }

        return $this->_getIcoData();
    }

    /**
     * Take a GD image resource and change it into a raw BMP format.
     * This function has been written by: https://github.com/chrisbliss18/php-ico/blob/master/class-php-ico.php
     * @param [type] $image [description]
     */
    private function _addIcoImage($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        $pixelData = [];
        
        $opacityData = [];
        $currentOpacityVal = 0;
        
        for ($y = $height - 1; $y >= 0; $y--) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat( $image, $x, $y );
                
                $alpha = ($color & 0x7F000000) >> 24;
                $alpha = (1 - ($alpha / 127)) * 255;
                
                $color &= 0xFFFFFF;
                $color |= 0xFF000000 & ($alpha << 24);
                
                $pixelData[] = $color;
                
                $opacity = ($alpha <= 127) ? 1 : 0;
                
                $currentOpacityVal = ($currentOpacityVal << 1) | $opacity;
                
                if ((($x + 1) % 32) == 0) {
                    $opacityData[] = $currentOpacityVal;
                    $currentOpacityVal = 0;
                }
            }
            
            if (($x % 32) > 0) {
                while (($x++ % 32) > 0) {
                    $currentOpacityVal = $currentOpacityVal << 1;
                }
                
                $opacityData[] = $currentOpacityVal;
                $currentOpacityVal = 0;
            }
        }
        
        $image_header_size = 40;
        $color_mask_size = $width * $height * 4;
        $opacity_mask_size = (ceil($width/ 32) * 4) * $height;
        
        
        $data = pack('VVVvvVVVVVV', 40, $width, ($height * 2), 1, 32, 0, 0, 0, 0, 0, 0);
        
        foreach ($pixelData as $color) {
            $data .= pack('V', $color);
        }
        
        foreach ($opacityData as $opacity ) {
            $data .= pack('N', $opacity);
        }
        
        $image = [
            'width' => $width,
            'height' => $height,
            'color_palette_colors' => 0,
            'bits_per_pixel' => 32,
            'size' => $image_header_size + $color_mask_size + $opacity_mask_size,
            'data' => $data,
        ];
        
        $this->_icoImages[] = $image;
    }

    /**
     * Generate the final ICO data by creating a file header and adding the image data.
     * This function has been written by: https://github.com/chrisbliss18/php-ico/blob/master/class-php-ico.php
     */
    private function _getIcoData() {
        if (!is_array($this->_icoImages) || empty($this->_icoImages)) {
            return false;
        }
        
        $data = pack('vvv', 0, 1, count($this->_icoImages));
        $pixel_data = '';
        
        $icon_dir_entry_size = 16;
        
        $offset = 6 + ($icon_dir_entry_size * count($this->_icoImages));
        
        foreach ($this->_icoImages as $image) {
            $data .= pack('CCCCvvVV', $image['width'], $image['height'], $image['color_palette_colors'], 0, 1, $image['bits_per_pixel'], $image['size'], $offset);
            $pixel_data .= $image['data'];
            
            $offset += $image['size'];
        }
        
        $data .= $pixel_data;
        unset($pixel_data);
        
        return $data;
    }
    
    /**
    * Checks if url is valid. Thanks to:
    * http://www.php.net/manual/en/function.fsockopen.php#39948
    *
    * @param string $link Url to check
    * @return bool
    */
    private function _urlExists($link) {        
        $url_parts = @parse_url($link);

        if (empty($url_parts["host"])) { 
            return false;
        }

        if (!empty($url_parts["path"])) {
            $documentpath = $url_parts["path"];
        } else {
            $documentpath = "/";
        }

        if (!empty($url_parts["query"])) {
            $documentpath .= "?" . $url_parts["query"];
        }

        $host = $url_parts["host"];
        $port = (isset($url_parts["port"])) ? $url_parts["port"] : "80";

        $socket = @fsockopen($host, $port, $errno, $errstr, 30);
        if (!$socket) {
            return false;
        } else {
            fwrite($socket, "HEAD ".$documentpath." HTTP/1.0\r\nHost: $host\r\n\r\n");
            $http_response = fgets($socket, 22);

            if (strpos($http_response, '200 OK') !== false || strpos($http_response, '302') !== false) {
                return true;
                fclose($socket);
            } else {
                return false;
            }
        }
    }
    
    /**
    * Checks if a string is a url or a path on the HDD
    *
    * @param string $path Path or Url to check
    * @return bool
    */
    private function _isExternPath($path) {
        return (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0);
    }
    
    /**
    * Makes a dummy image
    *
    * @param string $image Image source
    * @return ImageResourceIdentifier
    */
    public function makeDummyImage() {
        $background = $this->_hex2RGB($this->_options['bg']);
        $image = imagecreatetruecolor($this->_options['w'], $this->_options['h']);
        imagefill($image, 0, 0, imagecolorallocate($image, $background['red'], $background['green'], $background['blue']));
        $foreground = $this->_hex2RGB($this->_options['fg']);
        $accent = imagecolorallocate($image, $foreground['red'], $foreground['green'], $foreground['blue']);
        
        // Cross
        // Check if dimensions are valid
        $this->_options['w'] = ($this->_options['w'] > 0) ? $this->_options['w'] : '100';
        $this->_options['h'] = ($this->_options['h'] > 0) ? $this->_options['h'] : '100';
        
        $this->_drawLine($image, 0, 0, $this->_options['w'], $this->_options['h'], $accent, $this->_options['boldness']);
        $this->_drawLine($image, $this->_options['w'], 0, 0, $this->_options['h'], $accent, $this->_options['boldness']);
        
        return $image;
    }
    
    /**
    * Gets image type
    *
    * @param string $path Path to the image
    * @return string Image type
    */
    private function _getImageType($path) {
        if (file_exists($path) || $this->_urlExists($path)) {
            list($w, $h, $type) = getimagesize($path);
            
            switch ($type) { 
                case 'gif': 
                case IMG_GIF: 
                    return 'gif';
                    break;
                case 'jpeg':
                case 'jpg':
                case IMG_JPEG:
                    return 'jpeg';
                    break; 
                case 3:
                case 'png':
                case IMG_PNG:
                    return 'png';
                    break; 
                default: 
                    throw new Exception('Image type ('.$type.') not supported.');
            }
        }
        
        return 'png';
    }
    
    /**
    * Creates ImageResourceIdentifier from a file
    *
    * @return ImageResourceIdentifier
    */
    private function _createImageFromFile() {
        $type = $this->_getImageType($this->_options['fullpath']);
        switch ($type) {
            case 'gif':
                return imagecreatefromgif($this->_options['fullpath']); 
                break;
            case 'jpeg':
                return imagecreatefromjpeg($this->_options['fullpath']);
                break;
            case 'png':
                return imagecreatefrompng($this->_options['fullpath']);
                break;
        }
    }
    
    /**
    * Resizes image
    *
    * @return ImageResourceIdentifier
    */
    public function resizeImage() {
        // Get original values and calculate ratio
        list($widthOriginal, $heightOriginal) = getimagesize($this->_options['fullpath']);
        $ratioOriginal = $widthOriginal / $heightOriginal;
        
        // Sanity check
        if ($this->_options['w'] <= 0 && $this->_options['h'] <= 0) {
            $this->_options['w'] = $widthOriginal;
            $this->_options['h'] = $heightOriginal;
        }
        
        // Set max dimensions
        $widthMax = $this->_options['w'];
        $heightMax = $this->_options['h'];
        
        // Create image resource
        $img = $this->_createImageFromFile();
        
        // Resize the thumbnail
        if ($this->_options['resize'] === true) { // Resize = the size of the image will be calculated upon resize
            $ratio = 1;
            if ($widthMax <= 0) {
                $ratio = $heightMax / $heightOriginal;
            } else if ($heightMax <= 0) {
                $ratio = $widthMax / $widthOriginal;
            } else {
                $ratio = min($widthMax / $widthOriginal, $heightMax / $heightOriginal);
            }
            
            $widthNew = $widthOriginal * $ratio;
            $heightNew = $heightOriginal * $ratio;

            if ($this->_options['enlarge'] === false) {
                $widthNew = ($widthNew >= $widthOriginal) ? $widthOriginal : $widthNew;
                $heightNew = ($heightNew >= $heightOriginal) ? $heightOriginal : $heightNew;
            }
        } else if ($this->_options['resize'] === false) { // Do not resize = the size will be always the same
            if ($this->_options['fill'] === true) { // Fill = no bars around the image = the image is cropped
                if ($widthMax / $heightMax >= $ratioOriginal) {
                    $widthNew = $widthMax;
                    $heightNew = $widthMax / $ratioOriginal;
                } else {
                    $widthNew = $heightMax * $ratioOriginal;
                    $heightNew = $heightMax;
                }
            } else if ($this->_options['fill'] === false) { // The whole image is visible with bars
                if ($widthMax / $heightMax <= $ratioOriginal) {
                    $widthNew = $widthMax;
                    $heightNew = $widthMax / $ratioOriginal;
                } else {
                    $widthNew = $heightMax * $ratioOriginal;
                    $heightNew = $heightMax;
                }
            }
        }

        // Use original size if image is smaller and enlarge is set to false
        if ($this->_options['enlarge'] === false) {
            $widthNew = ($widthNew >= $widthOriginal) ? $widthOriginal : $widthNew;
            $heightNew = ($heightNew >= $heightOriginal) ? $heightOriginal : $heightNew;
        }

        // Position
        $x = $y = 0;
        if ($this->_options['resize'] === false) {
            $x = ($widthMax - $widthNew) / 2;
            $y = ($heightMax - $heightNew) / 2;
            
            if (in_array('left', $this->_options['pos'])) {
                $x = 0;
            } else if (in_array('right', $this->_options['pos'])) {
                $x = $widthMax - $widthNew;
            }

            if (in_array('top', $this->_options['pos'])) {
                $y = 0;
            } else if (in_array('bottom', $this->_options['pos'])) {
                $y = $heightMax - $heightNew;
            }
        }

        $x *= $this->_options['scale'];
        $y *= $this->_options['scale'];

        // Set thumbnail size
        $new = null;
        if ($this->_options['resize'] === true) {
            $new = imagecreatetruecolor($widthNew * $this->_options['scale'], $heightNew * $this->_options['scale']);
        } else if ($this->_options['resize'] === false) {
            $new = imagecreatetruecolor($widthMax * $this->_options['scale'], $heightMax * $this->_options['scale']);
        }
        
        // Preserve transparency
        if ($this->_options['transparent'] !== false &&
                ($this->_options['type'] !== 'jpg' ||
                    $this->_options['type'] !== 'jpeg')
            ) {
            $transparentIndex = imagecolortransparent($img);

            // If transparent index is set (for gif and png)
            if ($transparentIndex >= 0) {
                $transparentColor = imagecolorsforindex($img, $transparentIndex);
                $transparentIndex = imagecolorallocate($new, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
                imagefill($new, 0, 0, $transparentIndex);
                imagecolortransparent($new, $transparentIndex);
            } else if ($this->_getImageType($this->_options['fullpath']) === 'png' ||
                    $this->_options['type'] === 'png') {
                // If no transparent index is set, make one (only for png)
                imagealphablending($new, false);
                imagesavealpha($new, true);
                
                imagealphablending($img, true);
                $colorTransparent = imagecolorallocatealpha($new, 255, 255, 255, 127);
                imagefill($new, 0, 0, $colorTransparent);
            }
        }
        
        // Draw bg
        if ($this->_options['transparent'] === false ||
                ($this->_options['type'] === 'jpg' || $this->_options['type'] === 'jpeg') ||
                ($this->_options['type'] === '' && $this->_getImageType($this->_options['fullpath']) === 'jpeg')
            ) {
            $background = $this->_hex2RGB($this->_options['bg']);
            imagefill($new, 0, 0, imagecolorallocate($new, $background['red'], $background['green'], $background['blue']));
        }
        
        imagecopyresampled(
            $new,
            $img,
            $x,
            $y,
            0,
            0,
            round($widthNew * $this->_options['scale']),
            round($heightNew * $this->_options['scale']),
            round($widthOriginal),
            round($heightOriginal)
        );

        if (count($this->_options['color_overlay']) === 2) {
            $color = $this->_hex2RGB($this->_options['color_overlay'][0]);
            // value range 0 - 127 (opaque - transparent)
            // we use 0 - 255 (transparent - opaque)
            $alpha = floor((255 - (int)$this->_options['color_overlay'][1]) / 2);

            imagefilledrectangle(
                $new,
                0,
                0,
                round($widthNew * $this->_options['scale']),
                round($heightNew * $this->_options['scale']),
                imagecolorallocatealpha($new, $color['red'], $color['green'], $color['blue'], $alpha)
            );
        }

        $this->applyFilter($new);
        $new = $this->mirror($new);
        
        return $new;
    }

    public function applyFilter($img) {
        foreach ($this->_options['filter'] as $filter) {
            $options = explode(';', $filter);
            if (!isset($options[0]) || !isset($this->_possibleImageFilters[$options[0]])) {
                continue;
            }

            if (!isset($this->_possibleImageFilters[$options[0]]['numArgs'])) {
                imagefilter($img, $this->_possibleImageFilters[$options[0]]['name']);
            } else if ($this->_possibleImageFilters[$options[0]]['numArgs'] === 1) {
                imagefilter($img, $this->_possibleImageFilters[$options[0]]['name'],
                    (isset($options[1])) ? $options[1] : null);
            } else if ($this->_possibleImageFilters[$options[0]]['numArgs'] === 2) {
                imagefilter($img, $this->_possibleImageFilters[$options[0]]['name'],
                    (isset($options[1])) ? $options[1] : null,
                    (isset($options[2])) ? $options[2] : null);
            } else if ($this->_possibleImageFilters[$options[0]]['numArgs'] === 3) {
                imagefilter($img, $this->_possibleImageFilters[$options[0]]['name'],
                    (isset($options[1])) ? $options[1] : null,
                    (isset($options[2])) ? $options[2] : null,
                    (isset($options[3])) ? $options[3] : null);
            } else if ($this->_possibleImageFilters[$options[0]]['numArgs'] === 4) {
                imagefilter($img, $this->_possibleImageFilters[$options[0]]['name'],
                    (isset($options[1])) ? $options[1] : null,
                    (isset($options[2])) ? $options[2] : null,
                    (isset($options[3])) ? $options[3] : null,
                    (isset($options[4])) ? $options[4] : null);
            }
        }
    }

    public function mirror($img) {
        $needMirroring = false;
        $width = imagesx($img);
        $height = imagesy($img);

        $srcX = 0;
        $srcY = 0;
        $srcWidth = $width;
        $srcHeight = $height;

        if (in_array('horizontal', $this->_options['mirror'])) {
            $needMirroring = true;
            $srcX = $width - 1;
            $srcWidth = -$width;
        }

        if (in_array('vertical', $this->_options['mirror'])) {
            $needMirroring = true;
            $srcY = $height - 1;
            $srcHeight = -$height;
            
        }

        if ($needMirroring === false) {
            return $img;
        }

        $imgNew = imagecreatetruecolor($width, $height);
        if (imagecopyresampled($imgNew, $img, 0, 0, $srcX, $srcY, $width, $height, $srcWidth, $srcHeight)) {
            return $imgNew;
        }

        return $img;
    }
    
    /**
    * Makes and saves an image from data
    *
    * @param image $image Image you want to make
    * @param string $path Path to save the image to
    * @return ImageResourceIdentifier
    */
    private function _toImage($image, $path = null) {
        if ($this->_options['type'] === '') {
            $this->_options['type'] = $this->_getImageType($this->_options['fullpath']);
        }

        switch ($this->_options['type']) {
            case 'gif':
                return imagegif($image, $path);
                break;
            case 'png':
                return imagepng($image, $path);
                break;
            case 'ico':
                if ($path === null) {
                    echo $image;
                } else {
                    file_put_contents($path, $image);
                }
                break;
            case 'jpg':
            case 'jpeg':
                return imagejpeg($image, $path, $this->_options['quality']);
                break;
        }
    }
    
    /**
    * Saves an image
    *
    * @param image $image Image you want to save
    * @param string $name Name of the image
    * @return bool
    */
    public function saveImage($name) {
        // Make cache dir if needed
        $this->_makeDir($this->_cachePath);
        return $this->_toImage($this->_image, $name);
    }
    
    /**
     * Checks if dir exists and makes one if not
     * @param  string $dir Directory name
     * @return void
     */
    private function _makeDir($dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
            chmod($dir, 0777);
        }
    }
    
    /**
    * Draws a bold line
    *
    * http://www.php.net/manual/en/function.imageline.php#105038
    */
    private function _drawLine($image, $x1, $y1, $x2, $y2, $color, $radius) {
        $center = round($radius / 2);
        for ($i = 0; $i < $radius; $i++) {
            $a = $center - $i;
            if ($a < 0) {
                $a -= $a;
            }
            for ($j = 0; $j < $radius; $j++) {
                $b = $center - $j;
                if ($b < 0) {
                    $b -= $b;
                }
                $c = sqrt($a * $a + $b * $b);
                if ($c <= $radius) {
                    imageline($image, $x1 +$i, $y1+$j, $x2+$i, $y2+$j, $color); 
                }
            }
        } 
    }
    
    /**
    * Convert a hexa decimal color code to its RGB equivalent
    *
    * @param string $hexStr         (hexadecimal color value)
    * @param bool   $returnAsString (if set true, returns the value separated by the separator character. Otherwise returns associative array)
    * @param string $seperator      (to separate RGB values. Applicable only if second parameter is true.)
    * @return array or string (depending on second parameter. Returns False if invalid hex color value)
    */                                                                                                 
    private function _hex2RGB($hexStr, $returnAsString = false, $seperator = ',') {
        $hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $hexStr); // Gets a proper hex string
        $rgbArray = array();
        if (strlen($hexStr) == 6) { //If a proper hex code, convert using bitwise operation. No overhead... faster
            $colorVal = hexdec($hexStr);
            $rgbArray['red'] = 0xFF & ($colorVal >> 0x10);
            $rgbArray['green'] = 0xFF & ($colorVal >> 0x8);
            $rgbArray['blue'] = 0xFF & $colorVal;
        } elseif (strlen($hexStr) == 3) { //if shorthand notation, need some string manipulations
            $rgbArray['red'] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
            $rgbArray['green'] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
            $rgbArray['blue'] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
        } else {
            return false; //Invalid hex color code
        }
        return $returnAsString ? implode($seperator, $rgbArray) : $rgbArray; // returns the rgb string or the associative array
    }
}
