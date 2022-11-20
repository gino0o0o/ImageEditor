<?php

namespace Gino0o0o\ImageEditor;

class ImageEditor implements BaseImageEditor
{
	private string $path = '';

	private ?\GdImage $thumb = null;

	private const IMAGE_HANDLERS = [
		IMAGETYPE_JPEG => [
			'load' => 'imagecreatefromjpeg',
			'save' => 'imagejpeg',
			'quality' => 100
		],
		IMAGETYPE_PNG => [
			'load' => 'imagecreatefrompng',
			'save' => 'imagepng',
			'quality' => 0
		],
		IMAGETYPE_GIF => [
			'load' => 'imagecreatefromgif',
			'save' => 'imagegif',
			'quality' => null,
		]
	];

	public function __construct(string $path = '')
	{
		if (!extension_loaded('gd')) {
			throw new ImageEditorException("ImageEditor ha bisogno della librearia GD installata e configurata.");
		}

		$this->path = $path;
		$this->type = exif_imagetype($this->path);

		// if no valid type or no handler found -> exit
		if (!$this->type || !self::IMAGE_HANDLERS[$this->type]) {
			throw new ImageEditorException("ImageEditor non supporta il tipo di file $this->type");
		}

		// load the image with the correct loader
		$this->thumb = call_user_func(self::IMAGE_HANDLERS[$this->type]['load'], $this->path);

		// no image found at supplied location -> exit
		if (!$this->thumb) {
			throw new ImageEditorException("ImageEditor non è riuscito a caricare il file $this->path");
		}
	}

	/**
	 * Save the thumbnail to the disk
	 *
	 * @param   string  $destination  The complete path to save the image
	 *
	 * @return  bool                  True if the image is saved, false otherwise
	 */
	public function save(string $destination): bool
	{
		if (!$this->thumb) {
			throw new ImageEditorException("ImageEditor. Impossibile salvare il file.");
		}

		if( IMAGETYPE_GIF === $this->type ){

			return call_user_func(
				self::IMAGE_HANDLERS[$this->type]['save'],
				$this->thumb,
				$destination
			);
		}

		return call_user_func(
			self::IMAGE_HANDLERS[$this->type]['save'],
			$this->thumb,
			$destination,
			self::IMAGE_HANDLERS[$this->type]['quality']
		);
	}

	/**
	 * Resize the image
	 *
	 * @param   int  $thumb_width   The thumb width
	 * @param   int  $thumb_height  The thumb height
	 *
	 * @return  ImageEditor         Return the ImageEditor instance
	 */
	public function resize(int $thumb_width, int $thumb_height = null) : self
	{
		// 2. Create a thumbnail and resize the loaded $image
		// - get the image dimensions
		// - define the output size appropriately
		// - create a thumbnail based on that size
		// - set alpha transparency for GIFs and PNGs
		// - draw the final thumbnail

		// get original image width and height
		$src_width = imagesx($this->thumb);
		$src_height = imagesy($this->thumb);

		$dimensions = $this->get_dimensions($src_width, $src_height, $thumb_width, $thumb_height);

		if (!$dimensions) {
			$this->thumb = $this->thumb;
			return $this;
		}

		// create duplicate image based on calculated target size
		$thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);

		// set transparency options for GIFs and PNGs
		if (IMAGETYPE_GIF === $this->type || IMAGETYPE_PNG === $this->type) {

			// make image transparent
			imagecolortransparent(
				$thumbnail,
				imagecolorallocate($thumbnail, 0, 0, 0)
			);

			// additional settings for PNGs
			if ($this->type == IMAGETYPE_PNG) {
				imagealphablending($thumbnail, false);
				imagesavealpha($thumbnail, true);
			}
		}

		// copy entire source image to duplicate image and resize
		imagecopyresampled(
			$thumbnail,
			$this->thumb,
			$dimensions[0],
			$dimensions[1],
			$dimensions[2],
			$dimensions[3],
			$dimensions[4],
			$dimensions[5],
			$dimensions[6],
			$dimensions[7]
		);

		$this->thumb = $thumbnail;

		return $this;
	}

	/**
	 * Calculate the new dimensions for the image
	 *
	 * Gets the original and the target dimensions and calculate the new dimensions
	 * with also the crop start position
	 *
	 * @param   int    $orig_w  The original width
	 * @param   int    $orig_h  The original height
	 * @param   int    $dest_w  The destination width
	 * @param   int    $dest_h  The destination height
	 *
	 * @return  array|bool           Return an array in the image can be resized
	 * 								 Return false if the image can't be resized
	 */
	public function get_dimensions(int $orig_w, int $orig_h, int $dest_w, int $dest_h): array|bool
	{

		if ($orig_w < $dest_w && $orig_h < $dest_h) {
			// No resize need
			return false;
		}

		$aspect_ratio = $orig_w / $orig_h;
		$new_w        = min($dest_w, $orig_w);
		$new_h        = min($dest_h, $orig_h);

		if (!$new_w) {
			$new_w = (int) round($new_h * $aspect_ratio);
		}

		if (!$new_h) {
			$new_h = (int) round($new_w / $aspect_ratio);
		}

		$size_ratio = max($new_w / $orig_w, $new_h / $orig_h);

		$crop_w = round($new_w / $size_ratio);
		$crop_h = round($new_h / $size_ratio);

		$s_x = floor(($orig_w - $crop_w) / 2);
		$s_y = floor(($orig_h - $crop_h) / 2);

		// The new size has virtually the same dimensions as the original image.
		// Differences of 1px may be due to rounding and are ignored.
		if( $new_w === $dest_w - 1 )
		{
			$new_w = $dest_w;
		}

		if( $new_h === $dest_h - 1 )
		{
			$new_h = $dest_h;
		}

		return array(0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h);
	}

	/**
	 * Create a new ImageEditor object from a file
	 *
	 * @param   string       $path  The complete path of the source image
	 *
	 * @return  ImageEditor         The ImageEditor instance
	 */
	public static function fromFile(string $path): ImageEditor
	{
		return new ImageEditor($path);
	}
}
