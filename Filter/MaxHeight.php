<?php
namespace ERD\ImagineExtensionsBundle\Filter;

use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\ImageInterface;
use Imagine\Image\BoxInterface;
use Imagine\Filter\FilterInterface;

/**
 * Crops an image by equal amounts on the top and bottom if its height exceeds the maxHeight provided
 */
class MaxHeight implements FilterInterface
{ 
    protected $maxHeight;

	protected $crop;

    /**
     * Constructs the Thumbnail filter with given width, height and mode
     *
     * @param integer $maxHeight The maximum height the final image will occupy
     * @param string $crop In cropping the images height, should MaxHeight crop 
     *                     'evenly', 'more-top', 'more-bottom', 'only-top', 'only-bottom'
     */
    public function __construct($maxHeight, $crop = 'evenly')
    {	
        $allowedCrops = array('evenly', 'more-top', 'more-bottom', 'only-top', 'only-bottom');

        if(!in_array($crop, $allowedCrops)) { throw new \InvalidArgumentException('$crop must be one of: '.implode(', ', $allowedCrops).'; "'.$crop.'" given.'); }
        if($maxHeight < 1) { throw new \InvalidArgumentException('$maxHeight must be at least 1.'); }
        
        $this->maxHeight = (int) $maxHeight;
        $this->crop      = $crop;
    }

    /**
     * (non-PHPdoc)
     * @see Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image)
    {
        $height = $image->getSize()->getHeight();
        $width  = $image->getSize()->getWidth();

        if($this->maxHeight >= $height) {return $image; }

        //an image size at the final width
        $top = $height - $this->maxHeight;

        switch($this->crop)
        {
            case 'only-top': //$top is already the right value here
                break;

            case 'only-bottom':
                $top = 0;
                break;

            case 'evenly':
                $top = round($top/2);
                break;

            case 'more-top':
                $top = round($top/1.5);
                break;

            case 'more-bottom':
                $top = round($top/3);
                break;
        }
        
        $cropStart = new Point(0, $top);
        $cropSize  = new Box($width, $this->maxHeight);

        return $image->crop($cropStart, $cropSize);
    }
}
