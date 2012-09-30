<?php
namespace ERD\ImagineExtensionsBundle\Filter;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\BoxInterface;
use Imagine\Filter\FilterInterface;

/**
 * This class resizes images so that the result, rather than being some fixed thumbnail size, 
 * has a fixed width (usually to correspond to the grid) and it's height is a multiple
 * of the baseline rhythm. To make this height fall on the baseline, some cropping may be needed,
 * but no more than one baseline's worth.
 * 
 * If the image will something around it (e.g. a border above it), that thing's height can be
 * taken into account when calculating the image's height so that the whole thing adds up to a
 * multiple of the baseline.
 * 
 * @todo consider adding a mode that pads the image to get to the nearest baseline rather than 
 * cropping it. What exactly this would look like (e.g. how it would distribute the padding -- 
 * just on the bottom or are the other sides fair game too?) is TBD.
 */
class ElasticThumbnail implements FilterInterface
{ 
    protected $width;
    
    protected $baseline;
    
    protected $heightModifer;

    /**
     * Constructs the Thumbnail filter with given width, height and mode
     *
     * @param integer $finalWidth The width of the resulting thumbnail
     * @param integer $baseline The height of the typographic baseline
     * @param integer $heightModifier A + or - integer applied to the height value at the end 
     * of all the calculations. good for things like an image pushed down by a 1px border.  
     */
    public function __construct($finalWidth, $baseline = '28', $heightModifier = 0)
    {
        if($finalWidth < 1) { throw new \InvalidArgumentException('$finalWidth must be at least 1.'); }
        
        $this->width         = (int) $finalWidth;
        $this->baseline      = (int) $baseline;
        $this->heightModifer = (int) $heightModifier;
    }

    /**
     * @see Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image)
    {
        //returns an image size object (a Box) who's size is the final width
        $imgSize = $image->getSize()->scale($this->width / $image->getSize()->getWidth());
        
        //then replace that Box with a new one that uses the old width with the final height
        $imgSize = new Box($imgSize->getWidth(), ($imgSize->getHeight() - ($imgSize->getHeight() % $this->baseline) + $this->heightModifer));
        
        //and return an image that's cropped as necessary (with THUMBNAIL_OUTBOUND) to fit that final size
        return $image->thumbnail($imgSize, ImageInterface::THUMBNAIL_OUTBOUND);
    }
}