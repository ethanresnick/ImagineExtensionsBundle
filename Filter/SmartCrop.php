<?php
namespace ERD\ImagineExtensionsBundle\Filter;
use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use ERD\ImagineExtensionsBundle\ColorManagement\ColorConverterInterface;
use Imagine\Image\Point;
use Imagine\Image\BoxInterface;

/**
 * Description of SmartCrop
 * 
 * Borrowed heavily from Joe Lencioni's <joe@shiftingpixel.com> SmartCropper class
 * 
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jun 4, 2012 Ethan Resnick Design
 */
class SmartCrop implements FilterInterface
{
    //These act as array keys that all the internal methods use to ensure consistency. Useful,
    //even though it breaks encapsulation a bit. (But who cares if these values are public.)
    const OFFSET_NEAR = 0;
    const OFFSET_FAR  = 1;

    const PIXEL_LAB             = 0;
    const PIXEL_DELTA_E         = 1;
    const PIXEL_INTERESTINGNESS = 2;


    //////////////////////////////////// CONSTRUCTOR PARAMS ///////////////////////////////////
    /**
     * @var BoxInterface A box representing the cropped image's final size.
     */
    protected $cropSize;
  
    /**
     * @var ColorConverterInterface A helper object for converting colors as needed within this class. 
     */
    protected $colorConverter;


    //// APPLY() PARAMS (Lets the same instance be reused by just subbing in a new image.) ////
    /**
     * @var ImageInterface The image being cropped. Passed to {@link apply()} when this is run.
     */
    protected $image;

    /**
     * @var Box The image size. Stored so we only need to retrieve it from the image once. 
     */
    protected $imageSize;

    
    //////////////////////////////////// INTERNAL PROPERTIES //////////////////////////////////
    /**
     * @var array Stores information about the color (and interestingness) of each pixel.
     * 
     * Is calculated lazily (to save memory) by various internal functions. Looks roughly like:
     * array(
     *   x1 => array(
     *    x1y1  => array(
     *      self::PIXEL_LAB => array(l, a, b),
     *      self::PIXEL_DELTA_E => array(TL, TC, TR, LC, LR, BL, BC, BR),
     *      self::PIXEL_INTERESTINGNESS   => computedInterestingness
     *    ),
     *    x1y2  => array( ... ),
     *    ...
     *   ),
     *   x2 => array( ... ),
     * );
     */
    protected $colors;


    /**
     * Constructs the SmartCrop filter with given crop size and a color converter helper object
     *
     * @param BoxInterface The size of the cropped image.
     * @param ColorConverterInterface $colorConverter  
     */
    public function __construct(BoxInterface $cropSize, ColorConverterInterface $colorConverter)
    {
        $this->cropSize       = $cropSize; 
        $this->colorConverter = $colorConverter; 
    }

    /**
     * @see Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image)
    {
        // Set image specific properties so internal methods can access them. (Crop size
        // should probably be here, but AvalancheImagine doesn't call the filters that way.)
        $this->image = $image;
        $this->imageSize = $image->getSize();
        
        //////////////////////// VALIDATE STUFF ////////////////////////////////
        if(!$this->imageSize->contains($this->cropSize)) 
        { 
            throw new \Exception('The crop size provided ('.$this->cropSize.') is too big for this image ('.$this->imageSize.').');
        }
        
        if(!($this->imageSize->getWidth() === $this->cropSize->getWidth() || $this->imageSize->getHeight()===$this->cropSize->getHeight()))
        {
            throw new \Exception('This class can only crop either the image\'s top and bottom or its left and right--not both.');
        }
        
        if(($this->cropSize === $this->imageSize)) { return $image; }

        //////////////////////////// AND GO ///////////////////////////////////
        // Set up colors as an array, which also empties it if apply()'s been called before.
        $this->colors = array();
        
        // Try contrast detection
        $o = $this->getSmartOffsetRows();

        if ($o === false) { return $image; }

        else if ($this->shouldCropTopAndBottom()) 
        {
            $crop = new Point(0, $o);
        }
        else 
        {
            $crop = new Point($o, 0);
        }
        
        return $image->crop($crop, $this->cropSize);
    }

   /**
    * Determines if the top and bottom need to be cropped, given that we're only going to crop
    * from either the top/bottom or the left/right--not both. The class assumes that one 
    * dimension has already been scaled down appropriately. 
    *  
    * @return boolean
    */
    protected function shouldCropTopAndBottom()
    {
        return ($this->imageSize->getHeight() > $this->cropSize->getHeight());
    }

  /**
   * Determines the optimal number of rows in from the top or left to crop the source image
   * 
   * To smart crop an image, we calculate the difference between each pixel in each row and its
   * adjacent pixels. We add these up to determine how interesting each row is. Based on how
   * interesting each row is, we can determine whether or not to discard it.
   * 
   * @param ImageInterface $image
   * @return integer|boolean
   */
    protected function getSmartOffsetRows()
    {
        if ($this->shouldCropTopAndBottom()) 
        {
            $length           = $this->cropSize->getHeight();
            $lengthB          = $this->cropSize->getWidth();
            $originalLength   = $this->imageSize->getHeight();
        }
        else 
        {   
            $length           = $this->cropSize->getWidth();
            $lengthB          = $this->cropSize->getHeight();
            $originalLength   = $this->imageSize->getWidth();
        }


        // Offset will remember how far in from each side we are in the cropping game.
        $offset = array(self::OFFSET_NEAR => 0, self::OFFSET_FAR  => 0);

        $rowsToCrop = $originalLength - $length;

        // $pixelStep sacrifices accuracy for memory and speed. Essentially it acts as a spot-
        // checker and scales with the size of the cropped area. At $pixelStep < 4 we wouldn't 
        // save much speed as we'd still need to sample adjacent pixels, so we set it back to 1.
        $pixelStep  = round(sqrt($rowsToCrop * $lengthB) / 10);
        if ($pixelStep < 4) { $pixelStep = 1; }

        // Sets how much more interesting one side's row has to be than the other's for us to 
        // try hard to save it, even if that would mean accepting less strong rows around it.
        $tolerance  = 0.5;
        $upperTol   = 1 + $tolerance;
        $lowerTol   = 1 / $upperTol;

        // Prep vars for fighting the near and far rows.
        $returningChampion  = null;
        $ratio              = 1;

        // Loop over rows to check their interestingness, starting with the closest row and the 
        // farthest row and moving on from there until we've cropped as many as we need to.
        for ($rowsCropped = 0; $rowsCropped < $rowsToCrop; ++$rowsCropped) 
        {        
            // Fight the near and far rows. The stronger will remain standing.
            $a = $this->rowInterestingness($offset[self::OFFSET_NEAR], $pixelStep, $originalLength);
            $b = $this->rowInterestingness($originalLength - $offset[self::OFFSET_FAR] - 1, $pixelStep, $originalLength);

            //set $ratio as a's interestingness relative to b's, handling edge cases
            if ($a == 0 && $b == 0) { $ratio = 1; } 
            else if ($b == 0)       { $ratio = 1 + $a; } 
            else                    { $ratio = $a/$b; }

            if ($ratio > $upperTol) 
            {
                ++$offset[self::OFFSET_FAR];

                // Fightback. Winning side gets to go backwards through fallen rows to see if 
                // they are stronger.
                if ($returningChampion == self::OFFSET_NEAR) 
                {
                    $offset[self::OFFSET_NEAR]  -= ($offset[self::OFFSET_NEAR] > 0) ? 1 : 0;
                }
                else 
                {
                    $returningChampion  = self::OFFSET_NEAR;
                }
            } 
            
            else if ($ratio < $lowerTol) 
            {
                ++$offset[self::OFFSET_NEAR];

                if ($returningChampion == self::OFFSET_FAR) 
                {
                    $offset[self::OFFSET_FAR] -= ($offset[self::OFFSET_FAR] > 0) ? 1 : 0;
                } 
                else 
                {
                    $returningChampion  = self::OFFSET_FAR;
                }
            } 
            
            else 
            {
                // There is no strong winner, so discard rows from the side that
                // has lost the fewest so far. Essentially this is a draw.
                if ($offset[self::OFFSET_NEAR] > $offset[self::OFFSET_FAR]) 
                {
                    ++$offset[self::OFFSET_FAR];
                } 
                else 
                {
                    // Discard near
                    ++$offset[self::OFFSET_NEAR];
                }
                
                // No fightback for draws
                $returningChampion  = null;
            }
        }

        // Bounceback for potentially important details on the edge. This may possibly be 
        // better if the winning side fights a hard final push multiple-rows-at-stake battle  
        // where it stands the chance to gain ground.
        if ($ratio > (1 + ($tolerance * 1.25))) 
        {
            $offset[self::OFFSET_NEAR] -= round($length * .03);
        } 
        else if ($ratio < (1 / (1 + ($tolerance * 1.25)))) 
        {
            $offset[self::OFFSET_NEAR]  += round($length * .03);
        }

        return min($rowsToCrop, max(0, $offset[self::OFFSET_NEAR]));
    }

  /**
   * Calculate the interestingness value of a row of pixels
   *
   * @param integer $row
   * @param integer $pixelStep Number of pixels to jump after each step when comparing interestingness
   * @param integer $originalLength Number of rows in the original image
   * @return float
   */
  protected function rowInterestingness($row, $pixelStep, $originalLength)
  {
    $interestingness  = 0;
    $max              = 0;

    $topBottom = $this->shouldCropTopAndBottom();
    $rowSize   = ($topBottom) ? $this->imageSize->getWidth() : $this->imageSize->getHeight();
    
    // 2nd line of each loop used to be $i += min($row, $originalLength - $row, $originalLength * .04);
    // because content at the very edge of an image tends to be less interesting than content toward  
    // the center, so we were giving it a little extra push away from the edge. Not needed.
    if($topBottom)
    {
        for ($totalPixels = 0; $totalPixels < $rowSize; $totalPixels += $pixelStep) 
        {
            $i  = $this->pixelInterestingness($totalPixels, $row);

            $max              = max($i, $max);
            $interestingness  += $i;
        }
    }
    else
    {
        for ($totalPixels = 0; $totalPixels < $rowSize; $totalPixels += $pixelStep) 
        {
            $i  = $this->pixelInterestingness($row, $totalPixels);

            $max              = max($i, $max);
            $interestingness  += $i;
        }
    }
        
    return $interestingness + (($max - ($interestingness / ($totalPixels / $pixelStep))) * ($totalPixels / $pixelStep));
  }

  /**
   * Get the interestingness value of a pixel
   *
   * @param integer $x x-axis position of pixel to calculate
   * @param integer $y y-axis position of pixel to calculate
   * @return float
   */
  protected function pixelInterestingness($x, $y)
  {
    if (!isset($this->colors[$x][$y][self::PIXEL_INTERESTINGNESS])) 
    {
        // Ensure this pixel's color information has already been loaded
        $this->loadPixelInfo($x, $y);

        // Calculate each neighboring pixel's Delta E in relation to this
        // pixel
        $this->calculateDeltas($x, $y);

        // Calculate the interestingness of this pixel based on neighboring
        // pixels' Delta E in relation to this pixel
        $this->calculateInterestingness($x, $y);
    }

    return $this->colors[$x][$y][self::PIXEL_INTERESTINGNESS];
  }

  /**
   * Load the color information of the requested pixel into the $colors array
   *
   * @param integer $x x-axis position of pixel to calculate
   * @param integer $y y-axis position of pixel to calculate
   * @return boolean
   */
  protected function loadPixelInfo($x, $y)
  {
    $point = new Point($x, $y);
    
    if(!$point->in($this->imageSize)) { return false; }

    //initialize stuff
    if (!isset($this->colors[$x]))     { $this->colors[$x] = array();     }
    if (!isset($this->colors[$x][$y])) { $this->colors[$x][$y] = array(); }

    if (!isset($this->colors[$x][$y][self::PIXEL_INTERESTINGNESS]) && !isset($this->colors[$x][$y][self::PIXEL_LAB]))
    {
        $color = $this->image->getColorAt($point);
        $color = array(
                    ColorConverterInterface::RGB_RED   => $color->getRed(), 
                    ColorConverterInterface::RGB_GREEN => $color->getGreen(), 
                    ColorConverterInterface::RGB_BLUE  => $color->getBlue()
                );
        
        $this->colors[$x][$y][self::PIXEL_LAB]  = $this->colorConverter->RGBToHunterLab($color);
    }

    return true;
  }

  /**
   * Calculates and stores each adjacent pixel's Delta E in relation to the pixel requested
   *
   * @param integer $x x-axis position of pixel to calculate
   * @param integer $y y-axis position of pixel to calculate
   * @return boolean True on success
   */
  protected function calculateDeltas($x, $y)
  {
    // Calculate each adjacent pixel's Delta E in relation to the current
    // pixel (top left, top center, top right, center left, center right,
    // bottom left, bottom center, and bottom right)

    if (!isset($this->colors[$x][$y][self::PIXEL_DELTA_E]['d-1-1'])) {
      $this->calculateDelta($x, $y, -1, -1);
    }
    if (!isset($this->colors[$x][$y][self::PIXEL_DELTA_E]['d0-1'])) {
      $this->calculateDelta($x, $y, 0, -1);
    }
    if (!isset($this->colors[$x][$y][self::PIXEL_DELTA_E]['d1-1'])) {
      $this->calculateDelta($x, $y, 1, -1);
    }
    if (!isset($this->colors[$x][$y][self::PIXEL_DELTA_E]['d-10'])) {
      $this->calculateDelta($x, $y, -1, 0);
    }
    if (!isset($this->colors[$x][$y][self::PIXEL_DELTA_E]['d10'])) {
      $this->calculateDelta($x, $y, 1, 0);
    }
    if (!isset($this->colors[$x][$y][self::PIXEL_DELTA_E]['d-11'])) {
      $this->calculateDelta($x, $y, -1, 1);
    }
    if (!isset($this->colors[$x][$y][self::PIXEL_DELTA_E]['d01'])) {
      $this->calculateDelta($x, $y, 0, 1);
    }
    if (!isset($this->colors[$x][$y][self::PIXEL_DELTA_E]['d11'])) {
      $this->calculateDelta($x, $y, 1, 1);
    }

    return true;
  }

  /**
   * Calculates and stores requested pixel's Delta E in relation to comparison pixel
   *
   * @param integer $xA x-axis position of pixel to calculate
   * @param integer $yA y-axis position of pixel to calculate
   * @param integer $xMove number of pixels to move on the x-axis to find comparison pixel
   * @param integer $yMove number of pixels to move on the y-axis to find comparison pixel
   * @return boolean
   */
  protected function calculateDelta($xA, $yA, $xMove, $yMove)
  {
    $xB = $xA + $xMove;
    $yB = $yA + $yMove;

    // Pixel is outside of the image, so we cant't calculate the Delta E
    if ($xB < 0 || $xB >= $this->imageSize->getWidth() || $yB < 0 || $yB >= $this->imageSize->getHeight()) 
    {
        return null;
    }

    if (!isset($this->colors[$xA][$yA][self::PIXEL_LAB])) 
    {
        $this->loadPixelInfo($xA, $yA);
    }

    if (!isset($this->colors[$xB][$yB][self::PIXEL_LAB])) 
    {
        $this->loadPixelInfo($xB, $yB);
    }

    $delta = $this->deltaE($this->colors[$xA][$yA][self::PIXEL_LAB], $this->colors[$xB][$yB][self::PIXEL_LAB]);

    $this->colors[$xA][$yA][self::PIXEL_DELTA_E]["d$xMove$yMove"] = $delta;

    $xBMove = $xMove * -1;
    $yBMove = $yMove * -1;
    $this->colors[$xB][$yB][self::PIXEL_DELTA_E]["d$xBMove$yBMove"] =& $this->colors[$xA][$yA][self::PIXEL_DELTA_E]["d$xMove$yMove"];

    return true;
  }

  /**
   * Calculates and stores a pixel's overall interestingness value
   *
   * @param integer $x x-axis position of pixel to calculate
   * @param integer $y y-axis position of pixel to calculate
   * @return boolean
   */
  protected function calculateInterestingness($x, $y)
  {
    // The interestingness is the average of the pixel's Delta E values
    $this->colors[$x][$y][self::PIXEL_INTERESTINGNESS]  = array_sum($this->colors[$x][$y][self::PIXEL_DELTA_E])
      / count(array_filter($this->colors[$x][$y][self::PIXEL_DELTA_E], 'is_numeric'));

    return true;
  }

  /**
   * @param array $labA A lab color array as returned by {@link $colorConverter}
   * @param array $labB A lab color array as returned by {@link $colorConverter}
   * @return float
   */
  private function deltaE($labA, $labB)
  {
    return sqrt(
          (pow($labA[ColorConverterInterface::LAB_L] - $labB[ColorConverterInterface::LAB_L], 2))
        + (pow($labA[ColorConverterInterface::LAB_A] - $labB[ColorConverterInterface::LAB_A], 2))
        + (pow($labA[ColorConverterInterface::LAB_B] - $labB[ColorConverterInterface::LAB_B], 2))
    );
  }
      
  /**
   * Destruct method. Try to clean up memory a little.
   * @return void
   */
  public function __destruct()
  {
    unset($this->colors);
  }
}