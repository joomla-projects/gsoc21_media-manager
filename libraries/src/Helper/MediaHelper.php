<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  (C) 2013 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Helper;

\defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Image\Image;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

/**
 * Media helper class
 *
 * @since  3.2
 */
class MediaHelper
{
	/**
	 * Responsive image size options
	 *
	 * @var    array
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $responsiveSizes = array('800x600', '600x400', '400x200');

	/**
	 * Checks if the file is an image
	 *
	 * @param   string  $fileName  The filename
	 *
	 * @return  boolean
	 *
	 * @since   3.2
	 */
	public static function isImage($fileName)
	{
		static $imageTypes = 'xcf|odg|gif|jpg|jpeg|png|bmp';

		return preg_match("/\.(?:$imageTypes)$/i", $fileName);
	}

	/**
	 * Gets the file extension for purposed of using an icon
	 *
	 * @param   string  $fileName  The filename
	 *
	 * @return  string  File extension to determine icon
	 *
	 * @since   3.2
	 */
	public static function getTypeIcon($fileName)
	{
		return strtolower(substr($fileName, strrpos($fileName, '.') + 1));
	}

	/**
	 * Get the Mime type
	 *
	 * @param   string   $file     The link to the file to be checked
	 * @param   boolean  $isImage  True if the passed file is an image else false
	 *
	 * @return  mixed    the mime type detected false on error
	 *
	 * @since   3.7.2
	 */
	public static function getMimeType($file, $isImage = false)
	{
		// If we can't detect anything mime is false
		$mime = false;

		try
		{
			if ($isImage && \function_exists('exif_imagetype'))
			{
				$mime = image_type_to_mime_type(exif_imagetype($file));
			}
			elseif ($isImage && \function_exists('getimagesize'))
			{
				$imagesize = getimagesize($file);
				$mime      = $imagesize['mime'] ?? false;
			}
			elseif (\function_exists('mime_content_type'))
			{
				// We have mime magic.
				$mime = mime_content_type($file);
			}
			elseif (\function_exists('finfo_open'))
			{
				// We have fileinfo
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mime  = finfo_file($finfo, $file);
				finfo_close($finfo);
			}
		}
		catch (\Exception $e)
		{
			// If we have any kind of error here => false;
			return false;
		}

		// If we can't detect the mime try it again
		if ($mime === 'application/octet-stream' && $isImage === true)
		{
			$mime = static::getMimeType($file, false);
		}

		// We have a mime here
		return $mime;
	}

	/**
	 * Checks the Mime type
	 *
	 * @param   string  $mime       The mime to be checked
	 * @param   string  $component  The optional name for the component storing the parameters
	 *
	 * @return  boolean  true if mime type checking is disabled or it passes the checks else false
	 *
	 * @since   3.7
	 */
	private function checkMimeType($mime, $component = 'com_media'): bool
	{
		$params = ComponentHelper::getParams($component);

		if ($params->get('check_mime', 1))
		{
			$allowedMime = $params->get(
				'upload_mime',
				'image/jpeg,image/gif,image/png,image/bmp,application/msword,application/excel,' .
					'application/pdf,application/powerpoint,text/plain,application/x-zip'
			);

			// Get the mime type configuration
			$allowedMime = array_map('trim', explode(',', $allowedMime));

			// Mime should be available and in the allowed list
			return !empty($mime) && \in_array($mime, $allowedMime);
		}

		// We don't check mime at all or it passes the checks
		return true;
	}

	/**
	 * Checks if the file can be uploaded
	 *
	 * @param   array   $file                File information
	 * @param   string  $component           The option name for the component storing the parameters
	 * @param   string  $allowedExecutables  Array of executable file types that shall be whitelisted
	 *
	 * @return  boolean
	 *
	 * @since   3.2
	 */
	public function canUpload($file, $component = 'com_media', $allowedExecutables = array())
	{
		$app    = Factory::getApplication();
		$params = ComponentHelper::getParams($component);

		if (empty($file['name']))
		{
			$app->enqueueMessage(Text::_('JLIB_MEDIA_ERROR_UPLOAD_INPUT'), 'error');

			return false;
		}

		if ($file['name'] !== File::makeSafe($file['name']))
		{
			$app->enqueueMessage(Text::_('JLIB_MEDIA_ERROR_WARNFILENAME'), 'error');

			return false;
		}

		$filetypes = explode('.', $file['name']);

		if (\count($filetypes) < 2)
		{
			// There seems to be no extension
			$app->enqueueMessage(Text::_('JLIB_MEDIA_ERROR_WARNFILETYPE'), 'error');

			return false;
		}

		array_shift($filetypes);

		// Media file names should never have executable extensions buried in them.
		$executable = array(
			'php', 'js', 'exe', 'phtml', 'java', 'perl', 'py', 'asp', 'dll', 'go', 'ade', 'adp', 'bat', 'chm', 'cmd', 'com', 'cpl', 'hta', 'ins', 'isp',
			'jse', 'lib', 'mde', 'msc', 'msp', 'mst', 'pif', 'scr', 'sct', 'shb', 'sys', 'vb', 'vbe', 'vbs', 'vxd', 'wsc', 'wsf', 'wsh', 'html', 'htm',
		);

		// Remove allowed executables from array
		if (count($allowedExecutables))
		{
			$executable = array_diff($executable, $allowedExecutables);
		}

		$check = array_intersect($filetypes, $executable);

		if (!empty($check))
		{
			$app->enqueueMessage(Text::_('JLIB_MEDIA_ERROR_WARNFILETYPE'), 'error');

			return false;
		}

		$filetype = array_pop($filetypes);

		$allowable = $params->get(
			'upload_extensions',
			'bmp,csv,doc,gif,ico,jpg,jpeg,odg,odp,ods,odt,pdf,png,ppt,txt,xcf,xls,BMP,' .
				'CSV,DOC,GIF,ICO,JPG,JPEG,ODG,ODP,ODS,ODT,PDF,PNG,PPT,TXT,XCF,XLS'
		);
		$allowable = array_map('trim', explode(',', $allowable));
		$ignored   = array_map('trim', explode(',', $params->get('ignore_extensions')));

		if ($filetype == '' || $filetype == false || (!\in_array($filetype, $allowable) && !\in_array($filetype, $ignored)))
		{
			$app->enqueueMessage(Text::_('JLIB_MEDIA_ERROR_WARNFILETYPE'), 'error');

			return false;
		}

		$maxSize = (int) ($params->get('upload_maxsize', 0) * 1024 * 1024);

		if ($maxSize > 0 && (int) $file['size'] > $maxSize)
		{
			$app->enqueueMessage(Text::_('JLIB_MEDIA_ERROR_WARNFILETOOLARGE'), 'error');

			return false;
		}

		if ($params->get('restrict_uploads', 1))
		{
			$images = array_map('trim', explode(',', $params->get('image_extensions')));

			if (\in_array($filetype, $images))
			{
				// If tmp_name is empty, then the file was bigger than the PHP limit
				if (!empty($file['tmp_name']))
				{
					// Get the mime type this is an image file
					$mime = static::getMimeType($file['tmp_name'], true);

					// Did we get anything useful?
					if ($mime != false)
					{
						$result = $this->checkMimeType($mime, $component);

						// If the mime type is not allowed we don't upload it and show the mime code error to the user
						if ($result === false)
						{
							$app->enqueueMessage(Text::sprintf('JLIB_MEDIA_ERROR_WARNINVALID_MIMETYPE', $mime), 'error');

							return false;
						}
					}
					// We can't detect the mime type so it looks like an invalid image
					else
					{
						$app->enqueueMessage(Text::_('JLIB_MEDIA_ERROR_WARNINVALID_IMG'), 'error');

						return false;
					}
				}
				else
				{
					$app->enqueueMessage(Text::_('JLIB_MEDIA_ERROR_WARNFILETOOLARGE'), 'error');

					return false;
				}
			}
			elseif (!\in_array($filetype, $ignored))
			{
				// Get the mime type this is not an image file
				$mime = static::getMimeType($file['tmp_name'], false);

				// Did we get anything useful?
				if ($mime != false)
				{
					$result = $this->checkMimeType($mime, $component);

					// If the mime type is not allowed we don't upload it and show the mime code error to the user
					if ($result === false)
					{
						$app->enqueueMessage(Text::sprintf('JLIB_MEDIA_ERROR_WARNINVALID_MIMETYPE', $mime), 'error');

						return false;
					}
				}
				// We can't detect the mime type so it looks like an invalid file
				else
				{
					$app->enqueueMessage(Text::_('JLIB_MEDIA_ERROR_WARNINVALID_MIME'), 'error');

					return false;
				}

				if (!Factory::getUser()->authorise('core.manage', $component))
				{
					$app->enqueueMessage(Text::_('JLIB_MEDIA_ERROR_WARNNOTADMIN'), 'error');

					return false;
				}
			}
		}

		$xss_check = file_get_contents($file['tmp_name'], false, null, -1, 256);

		$html_tags = array(
			'abbr', 'acronym', 'address', 'applet', 'area', 'audioscope', 'base', 'basefont', 'bdo', 'bgsound', 'big', 'blackface', 'blink',
			'blockquote', 'body', 'bq', 'br', 'button', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'comment', 'custom', 'dd', 'del',
			'dfn', 'dir', 'div', 'dl', 'dt', 'em', 'embed', 'fieldset', 'fn', 'font', 'form', 'frame', 'frameset', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'head', 'hr', 'html', 'iframe', 'ilayer', 'img', 'input', 'ins', 'isindex', 'keygen', 'kbd', 'label', 'layer', 'legend', 'li', 'limittext',
			'link', 'listing', 'map', 'marquee', 'menu', 'meta', 'multicol', 'nobr', 'noembed', 'noframes', 'noscript', 'nosmartquotes', 'object',
			'ol', 'optgroup', 'option', 'param', 'plaintext', 'pre', 'rt', 'ruby', 's', 'samp', 'script', 'select', 'server', 'shadow', 'sidebar',
			'small', 'spacer', 'span', 'strike', 'strong', 'style', 'sub', 'sup', 'table', 'tbody', 'td', 'textarea', 'tfoot', 'th', 'thead', 'title',
			'tr', 'tt', 'ul', 'var', 'wbr', 'xml', 'xmp', '!DOCTYPE', '!--',
		);

		foreach ($html_tags as $tag)
		{
			// A tag is '<tagname ', so we need to add < and a space or '<tagname>'
			if (stripos($xss_check, '<' . $tag . ' ') !== false || stripos($xss_check, '<' . $tag . '>') !== false)
			{
				$app->enqueueMessage(Text::_('JLIB_MEDIA_ERROR_WARNIEXSS'), 'error');

				return false;
			}
		}

		return true;
	}

	/**
	 * Calculate the size of a resized image
	 *
	 * @param   integer  $width   Image width
	 * @param   integer  $height  Image height
	 * @param   integer  $target  Target size
	 *
	 * @return  array  The new width and height
	 *
	 * @since   3.2
	 */
	public static function imageResize($width, $height, $target)
	{
		/*
		 * Takes the larger size of the width and height and applies the
		 * formula accordingly. This is so this script will work
		 * dynamically with any size image
		 */
		if ($width > $height)
		{
			$percentage = ($target / $width);
		}
		else
		{
			$percentage = ($target / $height);
		}

		// Gets the new value and applies the percentage, then rounds the value
		$width  = round($width * $percentage);
		$height = round($height * $percentage);

		return array($width, $height);
	}

	/**
	 * Counts the files and directories in a directory that are not php or html files.
	 *
	 * @param   string  $dir  Directory name
	 *
	 * @return  array  The number of media files and directories in the given directory
	 *
	 * @since   3.2
	 */
	public function countFiles($dir)
	{
		$total_file = 0;
		$total_dir  = 0;

		if (is_dir($dir))
		{
			$d = dir($dir);

			while (($entry = $d->read()) !== false)
			{
				if ($entry[0] !== '.' && strpos($entry, '.html') === false && strpos($entry, '.php') === false && is_file($dir . DIRECTORY_SEPARATOR . $entry))
				{
					$total_file++;
				}

				if ($entry[0] !== '.' && is_dir($dir . DIRECTORY_SEPARATOR . $entry))
				{
					$total_dir++;
				}
			}

			$d->close();
		}

		return array($total_file, $total_dir);
	}

	/**
	 * Small helper function that properly converts any
	 * configuration options to their byte representation.
	 *
	 * @param   string|integer  $val  The value to be converted to bytes.
	 *
	 * @return integer The calculated bytes value from the input.
	 *
	 * @since 3.3
	 */
	public function toBytes($val)
	{
		switch ($val[\strlen($val) - 1])
		{
			case 'M':
			case 'm':
				return (int) $val * 1048576;
			case 'K':
			case 'k':
				return (int) $val * 1024;
			case 'G':
			case 'g':
				return (int) $val * 1073741824;
			default:
				return $val;
		}
	}

	/**
	 * Method to check if the given directory is a directory configured in FileSystem - Local plugin
	 *
	 * @param   string  $directory
	 *
	 * @return  boolean
	 *
	 * @since   4.0.0
	 */
	public static function isValidLocalDirectory($directory)
	{
		$plugin = PluginHelper::getPlugin('filesystem', 'local');

		if ($plugin)
		{
			$params = new Registry($plugin->params);

			$directories = $params->get('directories', '[{"directory": "images"}]');

			// Do a check if default settings are not saved by user
			// If not initialize them manually
			if (is_string($directories))
			{
				$directories = json_decode($directories);
			}

			foreach ($directories as $directoryEntity)
			{
				if ($directoryEntity->directory === $directory)
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Method to generate different-sized versions of form images
	 *
	 * @param   array  $images  images to have responsive versions
	 *
	 * @return  array  images for which responsive sizes are generated
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function generateFormResponsiveImages($images)
	{
		$imagesGenerated = [];

		foreach ($images as $image)
		{
			// Get image name (currently they are: imgName#joomlaImage://imgPath)
			$image->name  = HTMLHelper::cleanImageURL($image->name)->url;

			// Generate new responsive images if file exists
			if (is_file(JPATH_ROOT . '/' . $image->name))
			{
				$imgObj = new Image(JPATH_ROOT . '/' . $image->name);
				$imgObj->createMultipleSizes($image->sizes);

				$imagesGenerated[] = $image;
			}
		}

		return $imagesGenerated;
	}

	/**
	 * Method to generate different-sized versions of content images
	 *
	 * @param   string  $content   editor content
	 * @param   array   $sizes     array of strings. Example: $sizes = array('1200x800','800x600');
	 *
	 * @return  array   generated images
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function generateContentResponsiveImages($content, $sizes)
	{
		// Get src of img tag: <img src="images/joomla.png" /> - images/joomla.png
		$pattern = '/<*img[^>]*src *= *["\']?([^"\']*)/';

		// Get images from content and remove duplicates
		$images = preg_match_all($pattern, $content, $matched) ? array_unique($matched[1]) : [];

		$imagesGenerated = [];

		foreach ($images as $image)
		{
			// Generate new responsive images if file exists
			if (is_file(JPATH_ROOT . '/' . $image))
			{
				$sizes = static::getContentSizes($content, $image) ?? $sizes;

				$imgObj = new Image(JPATH_ROOT . '/' . $image);
				$imgObj->createMultipleSizes($sizes);

				$imagesGenerated[] = $image;
			}
		}

		return $imagesGenerated;
	}

	/**
	 * Method to generate a srcset attribute for an image
	 *
	 * @param   string  $imgSource  image source. Example: images/joomla_black.png
	 * @param   array   $sizes      array of strings. Example: $sizes = array('1200x800','800x600');
	 *
	 * @return  mixed   generated srcset attribute or false if not generated
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function generateSrcset($imgSource, $sizes)
	{
		$imgObj = new Image(JPATH_ROOT . '/' . $imgSource);

		$srcset = "";

		if ($images = $imgObj->generateMultipleSizes($sizes))
		{
			// Iterate through responsive images and generate srcset
			foreach ($images as $key => $image)
			{
				// Get source from path: PATH/images/joomla_800x600.jpg - images/joomla_800x600.jpg
				$imageSource = explode('/', $image->getPath(), 2)[1];

				// Insert srcset value for current responsive image: (img_name img_size, ...)
				$srcset .= sprintf(
					'%s %dw%s ', $imageSource, $image->getWidth(), $key !== count($images) - 1 ? ',' : ''
				);
			}
		}

		return !empty($srcset) ? $srcset : false;
	}

	/**
	 * Method to generate a sizes attribute for an image
	 *
	 * @param   string  $imgSource  image source. Example: images/joomla_black.png
	 *
	 * @return  string  generated sizes attribute or false if not generated
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function generateSizes($imgSource)
	{
		$imgObj = new Image(JPATH_ROOT . '/' . $imgSource);

		return sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $imgObj->getWidth());
	}

	/**
	 * Method to add srcset and sizes attributes to img tags of content
	 *
	 * @param   string  $content  content to which srcset attributes must be inserted
	 * @param   array   $sizes    array of strings. Example: $sizes = array('1200x800','800x600');
	 *
	 * @return  string  content with srcset attributes inserted
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function addContentSrcsetAndSizes($content, $sizes)
	{
		// Get src of img tags: <img src="images/joomla.png" /> - images/joomla.png and remove duplicates
		$images = preg_match_all('/<*img[^>]*src *= *["\']?([^"\']*)/', $content, $matched) ? array_unique($matched[1]) : [];

		// Generate srcset and sizes for all images
		foreach ($images as $image)
		{
			$sizes = static::getContentSizes($content, $image) ?? $sizes;

			if ($srcset = static::generateSrcset($image, $sizes))
			{
				// Remove previously generated attributes
				$content = preg_replace('/[' . preg_quote($image, '/') . ']*srcset *= *("?[^"]*" )/', '', $content);
				$content = preg_replace('/[' . preg_quote($image, '/') . ']*sizes *= *("?[^"]*" )/', '', $content);

				// Match all between <img and /> then insert srcset and sizes: <img src="" /> - <img src="" srcset="" sizes="">
				$content = preg_replace(
					'/(<img [^>]+' . preg_quote($image, '/') . '.*?) \/>/',
					'$1 srcset="' . $srcset . '" sizes="' . static::generateSizes($image) . '" />',
					$content
				);
			}
		}

		return $content;
	}

	/**
	 * Returns responsive image size options depending on parameters
	 *
	 * @param   int       $isCustom     1 if sizes are custom
	 * @param   stdClass  $sizeOptions  Responsive size options
	 *
	 * @return  array     Responsive image sizes
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getSizes($isCustom, $sizeOptions)
	{
		if (!$isCustom || ($isCustom && empty($sizeOptions)))
		{
			// Get plugin options
			$plugin = PluginHelper::getPlugin('content', 'responsiveimages');
			$params = new Registry($plugin->params);

			if (!$params->get('custom_sizes'))
			{
				return static::$responsiveSizes;
			}

			$sizeOptions = $params->get('custom_size_options');
		}

		// Create an array with custom sizes
		$customSizes = [];

		foreach ((array) $sizeOptions as $option)
		{
			if (isset($option->width) && isset($option->height))
			{
				$customSizes[] = $option->width . 'x' . $option->height;
			}
		}

		return $customSizes;
	}

	/**
	 * Returns custom responsive size options of a content image
	 *
	 * @param   string  $content  editor content
	 * @param   string  $image    image source
	 *
	 * @return  mixed   Custom sizes or null if not exists
	 *
	 * @since   4.1.0
	 */
	public static function getContentSizes($content, $image)
	{
		// Get data-jimage attribute value of image
		$sizesPattern = '/[' . preg_quote($image, '/') . ']*data-jimage *= *["\'](.*?)["\']/';
		$customSizes = preg_match($sizesPattern, $content, $matched) ? $matched[1] : null;

		return $customSizes ? array_unique(explode(',', (string) $customSizes)) : null;
	}
}
