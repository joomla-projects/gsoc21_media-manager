<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Image
 *
 * @copyright   (C) 2017 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\CMS\Image;

\defined('JPATH_PLATFORM') or die;

/**
 * Class to manipulate an image.
 *
 * @since  1.7.3
 */
class Image
{
	/**
	 * @const  integer
	 * @since  2.5.0
	 */
	const SCALE_FILL = 1;

	/**
	 * @const  integer
	 * @since  2.5.0
	 */
	const SCALE_INSIDE = 2;

	/**
	 * @const  integer
	 * @since  2.5.0
	 */
	const SCALE_OUTSIDE = 3;

	/**
	 * @const  integer
	 * @since  2.5.0
	 */
	const CROP = 4;

	/**
	 * @const  integer
	 * @since  2.5.0
	 */
	const CROP_RESIZE = 5;

	/**
	 * @const  integer
	 * @since  2.5.0
	 */
	const SCALE_FIT = 6;

	/**
	 * @const  string
	 * @since  3.4.2
	 */
	const ORIENTATION_LANDSCAPE = 'landscape';

	/**
	 * @const  string
	 * @since  3.4.2
	 */
	const ORIENTATION_PORTRAIT = 'portrait';

	/**
	 * @const  string
	 * @since  3.4.2
	 */
	const ORIENTATION_SQUARE = 'square';

	/**
	 * @var    resource  The image resource handle.
	 * @since  2.5.0
	 */
	protected $handle;

	/**
	 * @var    string  The source image path.
	 * @since  2.5.0
	 */
	protected $path = null;

	/**
	 * @var    array  Whether or not different image formats are supported.
	 * @since  2.5.0
	 */
	protected static $formats = [];

	/**
	 * @var    boolean  Flag if an image should use the best quality available.  Disable for improved performance.
	 * @since  3.7.0
	 */
	protected $generateBestQuality = true;

	/**
	 * Class constructor.
	 *
	 * @param   mixed  $source  Either a file path for a source image or a GD resource handler for an image.
	 *
	 * @since   1.7.3
	 * @throws  \RuntimeException
	 */
	public function __construct($source = null)
	{
		// Verify that GD support for PHP is available.
		if (!\extension_loaded('gd'))
		{
			// @codeCoverageIgnoreStart
			throw new \RuntimeException('The GD extension for PHP is not available.');

			// @codeCoverageIgnoreEnd
		}

		// Determine which image types are supported by GD, but only once.
		if (empty(static::$formats))
		{
			$info = gd_info();
			static::$formats[IMAGETYPE_JPEG] = $info['JPEG Support'];
			static::$formats[IMAGETYPE_PNG]  = $info['PNG Support'];
			static::$formats[IMAGETYPE_GIF]  = $info['GIF Read Support'];
			static::$formats[IMAGETYPE_WEBP] = $info['WebP Support'];
		}

		/**
		 * If the source input is a resource, set it as the image handle.
		 * TODO: Remove check for resource when we only support PHP 8
		 */
		if ($source && (\is_object($source) && get_class($source) == 'GdImage')
			|| (\is_resource($source) && get_resource_type($source) == 'gd'))
		{
			$this->handle = $source;
		}
		elseif (!empty($source) && \is_string($source))
		{
			// If the source input is not empty, assume it is a path and populate the image handle.
			$this->loadFile($source);
		}
	}

	/**
	 * Get the image resource handle
	 *
	 * @return  resource
	 *
	 * @since   3.8.0
	 * @throws  \LogicException if an image has not been loaded into the instance
	 */
	public function getHandle()
	{
		// Make sure the resource handle is valid.
		if (!$this->isLoaded())
		{
			throw new \LogicException('No valid image was loaded.');
		}

		return $this->handle;
	}

	/**
	 * Method to return a properties object for an image given a filesystem path.
	 *
	 * The result object has values for image width, height, type, attributes, mime type, bits, and channels.
	 *
	 * @param   string  $path  The filesystem path to the image for which to get properties.
	 *
	 * @return  \stdClass
	 *
	 * @since   2.5.0
	 * @throws  \InvalidArgumentException
	 * @throws  \RuntimeException
	 */
	public static function getImageFileProperties($path)
	{
		// Make sure the file exists.
		if (!is_file($path))
		{
			throw new \InvalidArgumentException('The image file does not exist.');
		}

		// Get the image file information.
		$info = getimagesize($path);

		if (!$info)
		{
			throw new Exception\UnparsableImageException('Unable to get properties for the image.');
		}

		// Build the response object.
		return (object) [
			'width'       => $info[0],
			'height'      => $info[1],
			'type'        => $info[2],
			'attributes'  => $info[3],
			'bits'        => $info['bits'] ?? null,
			'channels'    => $info['channels'] ?? null,
			'mime'        => $info['mime'],
			'filesize'    => filesize($path),
			'orientation' => self::getOrientationString((int) $info[0], (int) $info[1]),
		];
	}

	/**
	 * Method to detect whether an image's orientation is landscape, portrait or square.
	 *
	 * The orientation will be returned as a string.
	 *
	 * @return  mixed   Orientation string or null.
	 *
	 * @since   3.4.2
	 */
	public function getOrientation()
	{
		if ($this->isLoaded())
		{
			return self::getOrientationString($this->getWidth(), $this->getHeight());
		}

		return null;
	}

	/**
	 * Compare width and height integers to determine image orientation.
	 *
	 * @param   integer  $width   The width value to use for calculation
	 * @param   integer  $height  The height value to use for calculation
	 *
	 * @return  string   Orientation string
	 *
	 * @since   3.4.2
	 */
	private static function getOrientationString(int $width, int $height): string
	{
		switch (true)
		{
			case ($width > $height) :
				return self::ORIENTATION_LANDSCAPE;

			case ($width < $height) :
				return self::ORIENTATION_PORTRAIT;

			default:
				return self::ORIENTATION_SQUARE;
		}
	}

	/**
	 * Method to generate different sized versions of current image. It allows creation by resizing or
	 * cropping the original image.
	 *
	 * @param   array    $imageSizes      array of strings. Example: $imageSizes = array('1200x800','800x600');
	 * @param   integer  $creationMethod  1-3 resize $scaleMethod | 4 create by cropping | 5 resize then crop
	 * @param   boolean  $thumbs          true to generate thumbs, false to generate responsive images
	 *
	 * @return  array
	 *
	 * @since   2.5.0
	 * @throws  \LogicException
	 * @throws  \InvalidArgumentException
	 */
	public function generateMultipleSizes($imageSizes = null, $creationMethod = self::SCALE_INSIDE, $thumbs = false)
	{
		// Make sure the resource handle is valid.
		if (!$this->isLoaded())
		{
			throw new \LogicException('No valid image was loaded.');
		}

		// Process images
		$imagesGenerated = [];

		if (!empty($imageSizes))
		{
			foreach ($imageSizes as $imageSize)
			{
				// Desired image size
				$size = explode('x', strtolower($imageSize));

				if (\count($size) != 2)
				{
					throw new \InvalidArgumentException('Invalid image size received: ' . $imageSize);
				}

				$imageWidth  = $size[0];
				$imageHeight = $size[1];

				switch ($creationMethod)
				{
					case self::CROP:
						$image = $this->crop($imageWidth, $imageHeight, null, null, true);
						break;

					case self::CROP_RESIZE:
						$image = $this->cropResize($imageWidth, $imageHeight, true);
						break;

					default:
						$image = $this->resize($imageWidth, $imageHeight, true, $creationMethod);
						break;
				}

				// Store the image in the results array
				$imagesGenerated[] = $image;
			}
		}

		return $imagesGenerated;
	}

	/**
	 * Method to create different sized versions of current image and save them to disk. It allows creation
	 * by resizing or cropping the original image.
	 *
	 * @param   array    $imageSizes      array of strings. Example: $imageSizes = array('1200x800','800x600');
	 * @param   integer  $creationMethod  1-3 resize $scaleMethod | 4 create by cropping | 5 resize then crop
	 * @param   boolean  $thumbs          true to generate thumbs, false to generate responsive images
	 *
	 * @return  array
	 *
	 * @since   2.5.0
	 * @throws  \LogicException
	 * @throws  \InvalidArgumentException
	 */
	public function createMultipleSizes($imageSizes = null, $creationMethod = self::SCALE_INSIDE, $thumbs = false)
	{
		// Make sure the resource handle is valid.
		if (!$this->isLoaded())
		{
			throw new \LogicException('No valid image was loaded.');
		}

		// We create a responsive or thumbs folder in the current image folder
		$destFolder = \dirname($this->getPath()) . ($thumbs ? '/thumbs' : '/responsive');

		// Check destination
		if (!is_dir($destFolder) && (!is_dir(\dirname($destFolder)) || !@mkdir($destFolder)))
		{
			throw new \InvalidArgumentException('Folder does not exist and cannot be created: ' . $destFolder);
		}

		// Process images
		$imagesCreated = [];

		if ($images = $this->generateMultipleSizes($imageSizes, $creationMethod))
		{
			// Parent image properties
			$imgProperties = static::getImageFileProperties($this->getPath());

			// Get image filename and extension.
			$pathInfo      = pathinfo($this->getPath());
			$filename      = $pathInfo['filename'];
			$fileExtension = $pathInfo['extension'] ?? '';

			foreach ($images as $image)
			{
				// Get image properties
				$imageWidth  = $image->getWidth();
				$imageHeight = $image->getHeight();

				// Generate image name
				$imageFileName = $filename . '_' . $imageWidth . 'x' . $imageHeight . '.' . $fileExtension;

				// Save image file to disk
				$imageFileName = $destFolder . '/' . $imageFileName;

				if ($image->toFile($imageFileName, $imgProperties->type))
				{
					// Return Image object with image path to ease further manipulation
					$image->path = $imageFileName;
					$imagesCreated[] = $image;
				}
			}
		}

		return $imagesCreated;
	}

	/**
	 * Method to delete different sized versions of current image from disk.
	 *
	 * @param   boolean  $thumbs  true to delete thumbs, false to delete responsive images
	 *
	 * @return  array
	 *
	 * @since   4.1.0
	 * @throws  \LogicException
	 */
	public function deleteMultipleSizes($thumbs = false)
	{
		// Make sure the resource handle is valid.
		if (!$this->isLoaded())
		{
			throw new \LogicException('No valid image was loaded.');
		}

		$image = explode("/", $this->getPath());

		// Get path to the responsive images
		$imagesPath = implode("/", array_slice($image, 0, count($image) - 1)) . ($thumbs ? '/thumbs' : '/responsive');

		$imagesDeleted = [];

		if ($imageFiles = scandir($imagesPath))
		{
			foreach ($imageFiles as $imageFile)
			{
				// Match sizes part of image: images/joomla_800x600.png - _800x600, then remove it: images/joomla.png
				if (preg_replace('/_[^_][0-9]+x[0-9]+[^.]*/', '', $imageFile) === end($image))
				{
					unlink($imagesPath . '/' . $imageFile);
					$imagesDeleted[] = $imageFile;
				}
			}
		}

		return $imagesDeleted;
	}

	/**
	 * Method to crop the current image.
	 *
	 * @param   mixed    $width      The width of the image section to crop in pixels or a percentage.
	 * @param   mixed    $height     The height of the image section to crop in pixels or a percentage.
	 * @param   integer  $left       The number of pixels from the left to start cropping.
	 * @param   integer  $top        The number of pixels from the top to start cropping.
	 * @param   boolean  $createNew  If true the current image will be cloned, cropped and returned; else
	 *                               the current image will be cropped and returned.
	 *
	 * @return  Image
	 *
	 * @since   2.5.0
	 * @throws  \LogicException
	 */
	public function crop($width, $height, $left = null, $top = null, $createNew = true)
	{
		// Sanitize width.
		$width = $this->sanitizeWidth($width, $height);

		// Sanitize height.
		$height = $this->sanitizeHeight($height, $width);

		// Autocrop offsets
		if (\is_null($left))
		{
			$left = round(($this->getWidth() - $width) / 2);
		}

		if (\is_null($top))
		{
			$top = round(($this->getHeight() - $height) / 2);
		}

		// Sanitize left.
		$left = $this->sanitizeOffset($left);

		// Sanitize top.
		$top = $this->sanitizeOffset($top);

		// Create the new truecolor image handle.
		$handle = imagecreatetruecolor($width, $height);

		// Allow transparency for the new image handle.
		imagealphablending($handle, false);
		imagesavealpha($handle, true);

		if ($this->isTransparent())
		{
			// Get the transparent color values for the current image.
			$rgba  = imagecolorsforindex($this->getHandle(), imagecolortransparent($this->getHandle()));
			$color = imagecolorallocatealpha($handle, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']);

			// Set the transparent color values for the new image.
			imagecolortransparent($handle, $color);
			imagefill($handle, 0, 0, $color);
		}

		if (!$this->generateBestQuality)
		{
			imagecopyresized($handle, $this->getHandle(), 0, 0, $left, $top, $width, $height, $width, $height);
		}
		else
		{
			imagecopyresampled($handle, $this->getHandle(), 0, 0, $left, $top, $width, $height, $width, $height);
		}

		// If we are cropping to a new image, create a new Image object.
		if ($createNew)
		{
			return new static($handle);
		}

		// Swap out the current handle for the new image handle.
		$this->destroy();

		$this->handle = $handle;

		return $this;
	}

	/**
	 * Method to apply a filter to the image by type.  Two examples are: grayscale and sketchy.
	 *
	 * @param   string  $type     The name of the image filter to apply.
	 * @param   array   $options  An array of options for the filter.
	 *
	 * @return  Image
	 *
	 * @since   2.5.0
	 * @see     \Joomla\CMS\Image\Filter
	 * @throws  \LogicException
	 */
	public function filter($type, array $options = [])
	{
		// Make sure the resource handle is valid.
		if (!$this->isLoaded())
		{
			throw new \LogicException('No valid image was loaded.');
		}

		// Get the image filter instance.
		$filter = $this->getFilterInstance($type);

		// Execute the image filter.
		$filter->execute($options);

		return $this;
	}

	/**
	 * Method to get the height of the image in pixels.
	 *
	 * @return  integer
	 *
	 * @since   2.5.0
	 * @throws  \LogicException
	 */
	public function getHeight()
	{
		return imagesy($this->getHandle());
	}

	/**
	 * Method to get the width of the image in pixels.
	 *
	 * @return  integer
	 *
	 * @since   2.5.0
	 * @throws  \LogicException
	 */
	public function getWidth()
	{
		return imagesx($this->getHandle());
	}

	/**
	 * Method to return the path
	 *
	 * @return	string
	 *
	 * @since	2.5.0
	 */
	public function getPath()
	{
		return $this->path;
	}

	/**
	 * Method to determine whether or not an image has been loaded into the object.
	 *
	 * @return  boolean
	 *
	 * @since   2.5.0
	 */
	public function isLoaded()
	{
		/**
		 * Make sure the resource handle is valid.
		 * TODO: Remove check for resource when we only support PHP 8
		 */
		if (!((\is_object($this->handle) && get_class($this->handle) == 'GdImage')
			|| (\is_resource($this->handle) && get_resource_type($this->handle) == 'gd')))
		{
			return false;
		}

		return true;
	}

	/**
	 * Method to determine whether or not the image has transparency.
	 *
	 * @return  boolean
	 *
	 * @since   2.5.0
	 * @throws  \LogicException
	 */
	public function isTransparent()
	{
		return imagecolortransparent($this->getHandle()) >= 0;
	}

	/**
	 * Method to load a file into the Image object as the resource.
	 *
	 * @param   string  $path  The filesystem path to load as an image.
	 *
	 * @return  void
	 *
	 * @since   2.5.0
	 * @throws  \InvalidArgumentException
	 * @throws  \RuntimeException
	 */
	public function loadFile($path)
	{
		// Destroy the current image handle if it exists
		$this->destroy();

		// Make sure the file exists.
		if (!is_file($path))
		{
			throw new \InvalidArgumentException('The image file does not exist.');
		}

		// Get the image properties.
		$properties = static::getImageFileProperties($path);

		// Attempt to load the image based on the MIME-Type
		switch ($properties->mime)
		{
			case 'image/gif':
				// Make sure the image type is supported.
				if (empty(static::$formats[IMAGETYPE_GIF]))
				{
					throw new \RuntimeException('Attempting to load an image of unsupported type GIF.');
				}

				// Attempt to create the image handle.
				$handle = imagecreatefromgif($path);
				$type = 'GIF';

				break;

			case 'image/jpeg':
				// Make sure the image type is supported.
				if (empty(static::$formats[IMAGETYPE_JPEG]))
				{
					throw new \RuntimeException('Attempting to load an image of unsupported type JPG.');
				}

				// Attempt to create the image handle.
				$handle = imagecreatefromjpeg($path);
				$type = 'JPEG';

				break;

			case 'image/png':
				// Make sure the image type is supported.
				if (empty(static::$formats[IMAGETYPE_PNG]))
				{
					throw new \RuntimeException('Attempting to load an image of unsupported type PNG.');
				}

				// Attempt to create the image handle.
				$handle = imagecreatefrompng($path);
				$type = 'PNG';

				break;

			case 'image/webp':
				// Make sure the image type is supported.
				if (empty(static::$formats[IMAGETYPE_WEBP]))
				{
					throw new \RuntimeException('Attempting to load an image of unsupported type WebP.');
				}

				// Attempt to create the image handle.
				$handle = imagecreatefromwebp($path);
				$type = 'WebP';

				break;

			default:
				throw new \InvalidArgumentException('Attempting to load an image of unsupported type ' . $properties->mime);
		}

		/**
		 * Check if handle has been created successfully
		 * TODO: Remove check for resource when we only support PHP 8
		 */
		if (!(\is_object($handle) || \is_resource($handle)))
		{
			throw new \RuntimeException('Unable to process ' . $type . ' image.');
		}

		$this->handle = $handle;

		// Set the filesystem path to the source image.
		$this->path = $path;
	}

	/**
	 * Method to resize the current image.
	 *
	 * @param   mixed    $width        The width of the resized image in pixels or a percentage.
	 * @param   mixed    $height       The height of the resized image in pixels or a percentage.
	 * @param   boolean  $createNew    If true the current image will be cloned, resized and returned; else
	 *                                 the current image will be resized and returned.
	 * @param   integer  $scaleMethod  Which method to use for scaling
	 *
	 * @return  Image
	 *
	 * @since   2.5.0
	 * @throws  \LogicException
	 */
	public function resize($width, $height, $createNew = true, $scaleMethod = self::SCALE_INSIDE)
	{
		// Sanitize width.
		$width = $this->sanitizeWidth($width, $height);

		// Sanitize height.
		$height = $this->sanitizeHeight($height, $width);

		// Prepare the dimensions for the resize operation.
		$dimensions = $this->prepareDimensions($width, $height, $scaleMethod);

		// Instantiate offset.
		$offset = new \stdClass;
		$offset->x = $offset->y = 0;

		// Center image if needed and create the new truecolor image handle.
		if ($scaleMethod == self::SCALE_FIT)
		{
			// Get the offsets
			$offset->x = round(($width - $dimensions->width) / 2);
			$offset->y = round(($height - $dimensions->height) / 2);

			$handle = imagecreatetruecolor($width, $height);

			// Make image transparent, otherwise canvas outside initial image would default to black
			if (!$this->isTransparent())
			{
				$transparency = imagecolorallocatealpha($this->getHandle(), 0, 0, 0, 127);
				imagecolortransparent($this->getHandle(), $transparency);
			}
		}
		else
		{
			$handle = imagecreatetruecolor($dimensions->width, $dimensions->height);
		}

		// Allow transparency for the new image handle.
		imagealphablending($handle, false);
		imagesavealpha($handle, true);

		if ($this->isTransparent())
		{
			// Get the transparent color values for the current image.
			$rgba = imagecolorsforindex($this->getHandle(), imagecolortransparent($this->getHandle()));
			$color = imagecolorallocatealpha($handle, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']);

			// Set the transparent color values for the new image.
			imagecolortransparent($handle, $color);
			imagefill($handle, 0, 0, $color);
		}

		if (!$this->generateBestQuality)
		{
			imagecopyresized(
				$handle,
				$this->getHandle(),
				$offset->x,
				$offset->y,
				0,
				0,
				$dimensions->width,
				$dimensions->height,
				$this->getWidth(),
				$this->getHeight()
			);
		}
		else
		{
			// Use resampling for better quality
			imagecopyresampled(
				$handle,
				$this->getHandle(),
				$offset->x,
				$offset->y,
				0,
				0,
				$dimensions->width,
				$dimensions->height,
				$this->getWidth(),
				$this->getHeight()
			);
		}

		// If we are resizing to a new image, create a new Image object.
		if ($createNew)
		{
			return new static($handle);
		}

		// Swap out the current handle for the new image handle.
		$this->destroy();

		$this->handle = $handle;

		return $this;
	}

	/**
	 * Method to crop an image after resizing it to maintain
	 * proportions without having to do all the set up work.
	 *
	 * @param   integer  $width      The desired width of the image in pixels or a percentage.
	 * @param   integer  $height     The desired height of the image in pixels or a percentage.
	 * @param   boolean  $createNew  If true the current image will be cloned, resized, cropped and returned.
	 *
	 * @return  Image
	 *
	 * @since   2.5.0
	 */
	public function cropResize($width, $height, $createNew = true)
	{
		$width   = $this->sanitizeWidth($width, $height);
		$height  = $this->sanitizeHeight($height, $width);

		$resizewidth = $width;
		$resizeheight = $height;

		if (($this->getWidth() / $width) < ($this->getHeight() / $height))
		{
			$resizeheight = 0;
		}
		else
		{
			$resizewidth = 0;
		}

		return $this->resize($resizewidth, $resizeheight, $createNew)->crop($width, $height, null, null, false);
	}

	/**
	 * Method to rotate the current image.
	 *
	 * @param   mixed    $angle       The angle of rotation for the image
	 * @param   integer  $background  The background color to use when areas are added due to rotation
	 * @param   boolean  $createNew   If true the current image will be cloned, rotated and returned; else
	 *                                the current image will be rotated and returned.
	 *
	 * @return  Image
	 *
	 * @since   2.5.0
	 * @throws  \LogicException
	 */
	public function rotate($angle, $background = -1, $createNew = true)
	{
		// Sanitize input
		$angle = (float) $angle;

		// Create the new truecolor image handle.
		$handle = imagecreatetruecolor($this->getWidth(), $this->getHeight());

		// Make background transparent if no external background color is provided.
		if ($background == -1)
		{
			// Allow transparency for the new image handle.
			imagealphablending($handle, false);
			imagesavealpha($handle, true);

			$background = imagecolorallocatealpha($handle, 0, 0, 0, 127);
		}

		// Copy the image
		imagecopy($handle, $this->getHandle(), 0, 0, 0, 0, $this->getWidth(), $this->getHeight());

		// Rotate the image
		$handle = imagerotate($handle, $angle, $background);

		// If we are resizing to a new image, create a new Image object.
		if ($createNew)
		{
			return new static($handle);
		}

		// Swap out the current handle for the new image handle.
		$this->destroy();

		$this->handle = $handle;

		return $this;
	}

	/**
	 * Method to flip the current image.
	 *
	 * @param   integer  $mode       The flip mode for flipping the image {@link http://php.net/imageflip#refsect1-function.imageflip-parameters}
	 * @param   boolean  $createNew  If true the current image will be cloned, flipped and returned; else
	 *                               the current image will be flipped and returned.
	 *
	 * @return  Image
	 *
	 * @since   3.4.2
	 * @throws  \LogicException
	 */
	public function flip($mode, $createNew = true)
	{
		// Create the new truecolor image handle.
		$handle = imagecreatetruecolor($this->getWidth(), $this->getHeight());

		// Copy the image
		imagecopy($handle, $this->getHandle(), 0, 0, 0, 0, $this->getWidth(), $this->getHeight());

		// Flip the image
		if (!imageflip($handle, $mode))
		{
			throw new \LogicException('Unable to flip the image.');
		}

		// If we are resizing to a new image, create a new Image object.
		if ($createNew)
		{
			// @codeCoverageIgnoreStart
			return new static($handle);

			// @codeCoverageIgnoreEnd
		}

		// Free the memory from the current handle
		$this->destroy();

		// Swap out the current handle for the new image handle.
		$this->handle = $handle;

		return $this;
	}

	/**
	 * Watermark the image
	 *
	 * @param   Image    $watermark     The Image object containing the watermark graphic
	 * @param   integer  $transparency  The transparency to use for the watermark graphic
	 * @param   integer  $bottomMargin  The margin from the bottom of this image
	 * @param   integer  $rightMargin   The margin from the right side of this image
	 *
	 * @return  Image
	 *
	 * @since   3.8.0
	 * @link    https://secure.php.net/manual/en/image.examples-watermark.php
	 */
	public function watermark(Image $watermark, $transparency = 50, $bottomMargin = 0, $rightMargin = 0)
	{
		imagecopymerge(
			$this->getHandle(),
			$watermark->getHandle(),
			$this->getWidth() - $watermark->getWidth() - $rightMargin,
			$this->getHeight() - $watermark->getHeight() - $bottomMargin,
			0,
			0,
			$watermark->getWidth(),
			$watermark->getHeight(),
			$transparency
		);

		return $this;
	}

	/**
	 * Method to write the current image out to a file or output directly.
	 *
	 * @param   mixed    $path     The filesystem path to save the image.
	 *                             When null, the raw image stream will be outputted directly.
	 * @param   integer  $type     The image type to save the file as.
	 * @param   array    $options  The image type options to use in saving the file.
	 *                             For PNG and JPEG formats use `quality` key to set compression level (0..9 and 0..100)
	 *
	 * @return  boolean
	 *
	 * @link    http://www.php.net/manual/image.constants.php
	 * @since   2.5.0
	 * @throws  \LogicException
	 */
	public function toFile($path, $type = IMAGETYPE_JPEG, array $options = [])
	{
		switch ($type)
		{
			case IMAGETYPE_GIF:
				return imagegif($this->getHandle(), $path);

			case IMAGETYPE_PNG:
				return imagepng($this->getHandle(), $path, (\array_key_exists('quality', $options)) ? $options['quality'] : 0);

			case IMAGETYPE_WEBP:
				return imagewebp($this->getHandle(), $path, (\array_key_exists('quality', $options)) ? $options['quality'] : 100);
		}

		// Case IMAGETYPE_JPEG & default
		return imagejpeg($this->getHandle(), $path, (\array_key_exists('quality', $options)) ? $options['quality'] : 100);
	}

	/**
	 * Method to get an image filter instance of a specified type.
	 *
	 * @param   string  $type  The image filter type to get.
	 *
	 * @return  ImageFilter
	 *
	 * @since   2.5.0
	 * @throws  \RuntimeException
	 */
	protected function getFilterInstance($type)
	{
		// Sanitize the filter type.
		$type = strtolower(preg_replace('#[^A-Z0-9_]#i', '', $type));

		// Verify that the filter type exists.
		$className = 'JImageFilter' . ucfirst($type);

		if (!class_exists($className))
		{
			$className = __NAMESPACE__ . '\\Filter\\' . ucfirst($type);

			if (!class_exists($className))
			{
				throw new \RuntimeException('The ' . ucfirst($type) . ' image filter is not available.');
			}
		}

		// Instantiate the filter object.
		$instance = new $className($this->getHandle());

		// Verify that the filter type is valid.
		if (!($instance instanceof ImageFilter))
		{
			throw new \RuntimeException('The ' . ucfirst($type) . ' image filter is not valid.');
		}

		return $instance;
	}

	/**
	 * Method to get the new dimensions for a resized image.
	 *
	 * @param   integer  $width        The width of the resized image in pixels.
	 * @param   integer  $height       The height of the resized image in pixels.
	 * @param   integer  $scaleMethod  The method to use for scaling
	 *
	 * @return  \stdClass
	 *
	 * @since   2.5.0
	 * @throws  \InvalidArgumentException  If width, height or both given as zero
	 */
	protected function prepareDimensions($width, $height, $scaleMethod)
	{
		// Instantiate variables.
		$dimensions = new \stdClass;

		switch ($scaleMethod)
		{
			case self::SCALE_FILL:
				$dimensions->width = (int) round($width);
				$dimensions->height = (int) round($height);
				break;

			case self::SCALE_INSIDE:
			case self::SCALE_OUTSIDE:
			case self::SCALE_FIT:
				$rx = ($width > 0) ? ($this->getWidth() / $width) : 0;
				$ry = ($height > 0) ? ($this->getHeight() / $height) : 0;

				if ($scaleMethod != self::SCALE_OUTSIDE)
				{
					$ratio = max($rx, $ry);
				}
				else
				{
					$ratio = min($rx, $ry);
				}

				$dimensions->width = (int) round($this->getWidth() / $ratio);
				$dimensions->height = (int) round($this->getHeight() / $ratio);
				break;

			default:
				throw new \InvalidArgumentException('Invalid scale method.');
		}

		return $dimensions;
	}

	/**
	 * Method to sanitize a height value.
	 *
	 * @param   mixed  $height  The input height value to sanitize.
	 * @param   mixed  $width   The input width value for reference.
	 *
	 * @return  integer
	 *
	 * @since   2.5.0
	 */
	protected function sanitizeHeight($height, $width)
	{
		// If no height was given we will assume it is a square and use the width.
		$height = ($height === null) ? $width : $height;

		// If we were given a percentage, calculate the integer value.
		if (preg_match('/^[0-9]+(\.[0-9]+)?\%$/', $height))
		{
			$height = (int) round($this->getHeight() * (float) str_replace('%', '', $height) / 100);
		}
		else
		// Else do some rounding so we come out with a sane integer value.
		{
			$height = (int) round((float) $height);
		}

		return $height;
	}

	/**
	 * Method to sanitize an offset value like left or top.
	 *
	 * @param   mixed  $offset  An offset value.
	 *
	 * @return  integer
	 *
	 * @since   2.5.0
	 */
	protected function sanitizeOffset($offset)
	{
		return (int) round((float) $offset);
	}

	/**
	 * Method to sanitize a width value.
	 *
	 * @param   mixed  $width   The input width value to sanitize.
	 * @param   mixed  $height  The input height value for reference.
	 *
	 * @return  integer
	 *
	 * @since   2.5.0
	 */
	protected function sanitizeWidth($width, $height)
	{
		// If no width was given we will assume it is a square and use the height.
		$width = ($width === null) ? $height : $width;

		// If we were given a percentage, calculate the integer value.
		if (preg_match('/^[0-9]+(\.[0-9]+)?\%$/', $width))
		{
			$width = (int) round($this->getWidth() * (float) str_replace('%', '', $width) / 100);
		}
		else
		// Else do some rounding so we come out with a sane integer value.
		{
			$width = (int) round((float) $width);
		}

		return $width;
	}

	/**
	 * Method to destroy an image handle and
	 * free the memory associated with the handle
	 *
	 * @return  boolean  True on success, false on failure or if no image is loaded
	 *
	 * @since   2.5.0
	 */
	public function destroy()
	{
		if ($this->isLoaded())
		{
			return imagedestroy($this->getHandle());
		}

		return false;
	}

	/**
	 * Method to call the destroy() method one last time
	 * to free any memory when the object is unset
	 *
	 * @see    Image::destroy()
	 * @since  2.5.0
	 */
	public function __destruct()
	{
		$this->destroy();
	}

	/**
	 * Method for set option of generate multiple sizes function
	 *
	 * @param   boolean  $quality  True for best quality. False for best speed.
	 *
	 * @return  void
	 *
	 * @since   3.7.0
	 */
	public function setSizesGenerate($quality = true)
	{
		$this->generateBestQuality = (boolean) $quality;
	}
}
