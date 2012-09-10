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
 * VERSION: v1.0.11
 *
 * OVERVIEW:
 *
 * Creates a thumbnail on the fly for a .jpg or a .png image.
 * It can also cache the thumbnail for better performance.
 */

 
// Uncomment for standalone usage
/*$thumb = new X_Image_Thumbnailer($_GET);
$thumb->show();*/

class X_Image_Thumbnailer {
	// Variables you can change:
	// Cache: turn off for development, but don't forget to turn it back on
	private $_cache = true;
	// '' is a cachefile, TODO: check not on extension, but on image type itself
	private $_safeExtensions = array('jpg', 'jpeg', 'png', 'gif', '');
	private $_defaults = array(
		// Image path (relative to root)
		'img' => '',
		
		// Image path (full path, not an url)
		'fullpath' => '',
		
		// Desired width
		'w' => '0',
		
		// Desired height
		'h' => '0',
		
		// If true, the thumbnailer will calculate the dimensions based on the original images.
		// If false it will use the passed dimensions. This can result in vertical or horisontal bars
		'resize' => 'true',
		
		// Cropping: if set to true, the original image is cropped
		'fill' => 'true',
		
		// Background color of the image
		'bg' => 'ffffff',
		
		// Foreground color (used to color the cross on empty picture)
		'fg' => 'eeeeee',
		
		// Stroke width
		'boldness' => '2',
		
		// Default image (relative to root)
		'default' => '',
		
		// .jpg quality
		'quality' => '90',
		
		// Donload image name without the extension. If empty, no image download is initiated
		'download' => '',
		
		// Keep transparent .png's or make the bars around the image transparent
		'transparent' => 'true',
		
		// Convert to image type ".jpg", ".jpeg", ".png"
		'type' => '',
		
		// Currently only "clearcache" is supported, it clears the thumbnail cache ;)
		'action' => '',
		
		// Client-side cache time in days
		'cachetime' => '7',
		
		// Position of the image inside the thumbnail: "left", "right", "center", "top", "bottom"
		'pos' => 'center',
		
		// Enlarge the image to fit the thumbnail
		// If set to false, The original image will not be scaled if it's smaller than the thumbnailer
		'enlarge' => 'true',
	);
	
	// Project root path
	private $_root = '';
	
	// Cache
	private $_cachePath = '';
	private $_cachedFileName = '';
	private $_cachedFileExists = false;
	
	// Params
	private $_options = array();
	
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
		$this->_pathSeparator = (PHP_OS === 'WINNT') ? '\\' : '/';
		
		// Make fullpath
		$this->_makeFullPath();
		$this->_checkIfFileIsSafe();
		
		if ($this->_options['action'] === 'clearcache') {
			$this->clearThumbCache();
		}
		
		return $this;
	}
	
	/**
	 * Sets path variables.
	 * @return void
	 */
	private function _makeFullPath() {
		if ($this->_options['fullpath'] === '') {
			if ($this->_isExternPath($this->_options['img'])) {
				$this->_options['fullpath'] = $this->_options['img'];
			} else if ($this->_options['img'] === '') {
				$this->_options['fullpath'] = $this->_root.$this->_options['default'];
			} else {
				$this->_options['fullpath'] = $this->_root.$this->_options['img'];
			}
		} else {
			$this->_options['img'] = str_replace($this->_root, "", $this->_options['fullpath']);
		}
	}
	
	/**
	 * Checks if the file extension is safe for processing
	 * @return void
	 */
	private function _checkIfFileIsSafe() {
		if (file_exists($this->_options['fullpath'])) {
			$info = pathinfo($this->_options['fullpath']);
			if (!in_array(strtolower($info['extension']), $this->_safeExtensions)) {
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
				$this->_options[$key] = $value;
			} else {
				$this->_options[$key] = $params[$key];
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
		$this->_options[$key] = $value;
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
	
	/**
	* Checks if thumb-cache exists, if not it makes a new thumb and saves it.
	* @return Object Thumbnailer class
	*/
	private function _handleThumbRequest() {
		// Check for stored cache
		// TODO: urlencode the parameters and not the querystring
		$this->_cachedFileName = md5(urlencode($_SERVER['QUERY_STRING']));
		$this->_cachedFileExists = file_exists($this->_cachePath.$this->_pathSeparator.$this->_cachedFileName);
		
		// Create thumb
		if (!$this->_cache // Always generate a new thumb if cache is off
			|| (!$this->_cachedFileExists && $this->_cache) // Cache is on but there's no cached image
		) {
			$this->_image = $this->makeThumb();
		}
		
		// Save thumbnail to cache if needed
		if (!$this->_cachedFileExists && $this->_cache) {
			$this->saveImage($this->_cachePath.$this->_pathSeparator.$this->_cachedFileName);
			imagedestroy($this->_image);
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
			$contentType = $this->_options['type'];
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

			header("Content-Disposition: attachment; filename=".$this->_options['download'].".".$ext);
		}

		// Show image
		if ($this->_cache) {
			// Sets cache headers
			$cacheTime = 3600 * 24 * (int)$this->_options['cachetime'];
			header('Expires: '.gmdate("D, d M Y H:i:s", time() + $cacheTime).' GMT');
			header('Cache-Control: max-age='.$cacheTime.', public');
			
			// TODO: check the time of the created cache file
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', time()).' GMT');
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
			$image = $this->resizeImage();
		} else {
			$image = $this->makeDummyImage();
		}
		
		return $image;
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
            
            if (ereg("200 OK", $http_response, $regs)) {
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
		$this->_options['w'] = ((int)$this->_options['w'] > 0) ? $this->_options['w'] : '100';
		$this->_options['h'] = ((int)$this->_options['h'] > 0) ? $this->_options['h'] : '100';
		
		$this->_drawLine($image, 0, 0, (int)$this->_options['w'], (int)$this->_options['h'], $accent, $this->_options['boldness']);
		$this->_drawLine($image, (int)$this->_options['w'], 0, 0, (int)$this->_options['h'], $accent, $this->_options['boldness']);
		
		return $image;
	}
	
	/**
	* Gets image type
	*
	* @param string $path Path to the image
	* @return string Image type
	*/
	private function _getImageType($path) {
		if (file_exists($path)) {
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
		if ((int)$this->_options['w'] <= 0 && (int)$this->_options['h'] <= 0) {
			$this->_options['w'] = $widthOriginal;
			$this->_options['h'] = $heightOriginal;
		}
		
		// Set max dimensions
		$widthMax = (int)$this->_options['w'];
		$heightMax = (int)$this->_options['h'];
		
		// Create image resource
		$img = $this->_createImageFromFile();
		
		// Resize the thumbnail
		if ($this->_options['resize'] === 'true') { // Resize = the size of the image will be calculated upon resize
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

			if ($this->_options['enlarge'] === 'false') {
				$widthNew = ($widthNew >= $widthOriginal) ? $widthOriginal : $widthNew;
				$heightNew = ($heightNew >= $heightOriginal) ? $heightOriginal : $heightNew;
			}
		} else if ($this->_options['resize'] === 'false') { // Do not resize = the size will be always the same
			if ($this->_options['fill'] === 'true') { // Fill = no bars around the image = the image is cropped
				if ($widthMax / $heightMax >= $ratioOriginal) {
					$widthNew = $widthMax;
					$heightNew = $widthMax / $ratioOriginal;
				} else {
					$widthNew = $heightMax * $ratioOriginal;
					$heightNew = $heightMax;
				}
			} else if ($this->_options['fill'] === 'false') { // The whole image is visible with bars
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
		if ($this->_options['enlarge'] === 'false') {
			$widthNew = ($widthNew >= $widthOriginal) ? $widthOriginal : $widthNew;
			$heightNew = ($heightNew >= $heightOriginal) ? $heightOriginal : $heightNew;
		}

		// Position
		$x = $y = 0;
		if ($this->_options['resize'] === 'false') {
			$x = ($widthMax - $widthNew) / 2;
			$y = ($heightMax - $heightNew) / 2;
			
			if ($this->_options['pos'] === 'left') {
				$x = 0;
			} else if ($this->_options['pos'] === 'right') {
				$x = $widthMax - $widthNew;
			} else if ($this->_options['pos'] === 'top') {
				$y = 0;
			} else if ($this->_options['pos'] === 'bottom') {
				$y = $heightMax - $heightNew;
			}
		}

		// Thumbnail
		$new = null;
		if ($this->_options['resize'] === 'true') {
			$new = imagecreatetruecolor($widthNew, $heightNew);
		} else if ($this->_options['resize'] === 'false') {
			$new = imagecreatetruecolor($widthMax, $heightMax);
		}
		
		// Preserve transparency
		if (($this->_getImageType($this->_options['fullpath']) === "png" || $this->_options['type'] === 'png')
			&& $this->_options['transparent'] !== 'false'
			&& ($this->_options['type'] !== 'jpg'
				|| $this->_options['type'] !== 'jpeg')
			) {
			imagealphablending($new, false);
			imagesavealpha($new, true);
			
			imagealphablending($img, true);
            $colorTransparent = imagecolorallocatealpha($new, 255, 255, 255, 127);
            imagefill($new, 0, 0, $colorTransparent);
            
		}
		
		// Draw bg
		if ($this->_options['transparent'] === 'false' ||
				($this->_options['type'] === 'jpg' || $this->_options['type'] === 'jpeg') ||
				($this->_options['type'] === '' && $this->_getImageType($this->_options['fullpath']) === "jpeg")
			) {
			$background = $this->_hex2RGB($this->_options['bg']);
			imagefill($new, 0, 0, imagecolorallocate($new, $background['red'], $background['green'], $background['blue']));
		}
		
		imagecopyresampled($new, $img, $x, $y, 0, 0, round($widthNew), round($heightNew), round($widthOriginal), round($heightOriginal));
		
		return $new;
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