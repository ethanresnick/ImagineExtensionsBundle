<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of stuff
 *
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jun 4, 2012 Ethan Resnick Design
 */
class stuff
{
    
}

 /**
   * Calculates sharpness factor to be used to sharpen an image based on the
   * area of the source image and the area of the destination image
   *
   * @since 2.0
   * @author Ryan Rud
   * @link http://adryrun.com
   *
   * @param integer $sourceArea Area of source image
   * @param integer $destinationArea Area of destination image
   * @return integer Sharpness factor
   */
  /*private function calculateASharpnessFactor($sourceArea, $destinationArea)
  {
    $final  = sqrt($destinationArea) * (750.0 / sqrt($sourceArea));
    $a      = 52;
    $b      = -0.27810650887573124;
    $c      = .00047337278106508946;

    $result = $a + $b * $final + $c * $final * $final;

    return max(round($result), 0);
  }

  final protected function isSharpeningDesired()
  {
    if ($this->isJPEG()) {
      return true;
    } else {
      return false;
    }
  }
  /**
   * @param integer $sharpness
   * @return array
   * @since 2.0
   */
  /*private function sharpenMatrix($sharpness)
  {
    return array(
      array(-1, -2, -1),
      array(-2, $sharpness + 12, -2),
      array(-1, -2, -1)
    );
  }
   
  public function sharpen()
  {
    if ($this->isSharpeningDesired()) {
      imageconvolution(
          $this->getImage(),
          $this->sharpenMatrix($this->getSharpeningFactor()),
          $this->getSharpeningFactor(),
          0
      );
    }

    return $this;
  }
   * 
   * 
  public function optimize()
  {
    $colors = $this->isPalette();
    if ($colors !== false) {
      $this->trueColorToPalette(false, count($colors));
    }
    return $this;
  }
 */