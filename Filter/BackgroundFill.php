<?php
namespace ERD\ImagineExtensionsBundle\Filter;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\BoxInterface;
use Imagine\Filter\FilterInterface;
use Imagine\Image\Color;
use Imagine\Image\Fill\FillInterface;
use Imagine\Image\Point;
use Imagine\Image\PointInterface;

/**
 * Description of BackgroundFill
 *
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jun 3, 2012 Ethan Resnick Design
 */
class BackgroundFill implements FilterInterface, FillInterface
{
    protected $color;
    
    public function __construct(Color $color)
    {
        $this->color = $color;
    }
    
    /** required for calling fill() */
    public function getColor(PointInterface $position)
    {
        return $this->color;
    }

    /**
     * @see Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image)
    {
        return $image->copy()->fill($this)->paste($image, new Point(0, 0));
    }    
}

//testing, regex, sql