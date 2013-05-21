<?php
namespace ERD\ImagineExtensionsBundle\ColorManagement;

/**
* Description of ColorConverter
* 
* Imagine's color class assumes it's the last word on color--it's final and doesn't implement
* an interface--even though it's only RGBa. So there's no way to work natively in Imagine with
* Lab or HSV or any other color model and, worse Imagine requires instances of its Color in 
* various places. So this is only useful as a utility that returns raw values, not any sort of 
* objects.
*
* @author Ethan Resnick Design <hi@ethanresnick.com>
* @copyright Jun 4, 2012 Ethan Resnick Design
*/
class ColorConverter implements ColorConverterInterface
{
    const XYZ_X = 'X';
    const XYZ_Y = 'Y';
    const XYZ_Z = 'Z';

    /**
     * @param integer $int
     * @return array With keys ColorConverter::RGB_RED, ColorConverter::RGB_GREEN, and ColorConverter::RGB_BLUE
     */
    public function colorIndexToRGB($int)
    {
        $a  = (255 - (($int >> 24) & 0xFF)) / 255; //0xFF = 255 = 1111111
        $r  = (($int >> 16) & 0xFF) * $a;
        $g  = (($int >> 8) & 0xFF) * $a;
        $b  = ($int & 0xFF) * $a;

        return array(self::RGB_RED => $r, self::RGB_GREEN => $g, self::RGB_BLUE => $b);
    }

    /**
    * @param array $rgb With keys ColorConverter::RGB_RED, ColorConverter::RGB_GREEN, and ColorConverter::RGB_BLUE
    * @return array With keys ColorConverter::XYZ_X, ColorConverter::XYZ_Y, and ColorConverter::XYZ_Z
    * @link http://easyrgb.com/index.php?X=MATH&H=02#text2
    */
    public function RGBtoXYZ(array $rgb)
    {
        if(!$this->validateRGB($rgb))
        {
            throw new \InvalidArgumentException('Your $rgb array is invalid.');
        }

        $r  = $rgb[self::RGB_RED]   / 255;
        $g  = $rgb[self::RGB_GREEN] / 255;
        $b  = $rgb[self::RGB_BLUE]  / 255;

        $r = ($r > 0.04045) ? pow((($r + 0.055) / 1.055), 2.4)*100 : ($r / 12.92)*100; 
        $g = ($g > 0.04045) ? pow((($g + 0.055) / 1.055), 2.4)*100 : ($g / 12.92)*100;
        $b = ($b > 0.04045) ? pow((($b + 0.055) / 1.055), 2.4)*100 : ($b / 12.92)*100;

        //Observer. = 2°, Illuminant = D65
        return array(self::XYZ_X => ($r * 0.4124 + $g * 0.3576 + $b * 0.1805), 
                     self::XYZ_Y => ($r * 0.2126 + $g * 0.7152 + $b * 0.0722),
                     self::XYZ_Z => ($r * 0.0193 + $g * 0.1192 + $b * 0.9505)
                );
    }

    /**
    * @param array $xyz With keys ColorConverter::XYZ_X, ColorConverter::XYZ_Y, and ColorConverter::XYZ_Z
    * @return array With keys ColorConverter::LAB_L, ColorConverter::LAB_A, and ColorConverter::LAB_B
    * @link http://www.easyrgb.com/index.php?X=MATH&H=05#text5
    */
    public function XYZtoHunterLab(array $xyz)
    {
        if(!$this->validateXYZ($xyz))
        {
            throw new \InvalidArgumentException('Your $xyz array is invalid.');
        }

        // No luminance.
        if ($xyz[self::XYZ_Y] == 0) { return array(self::LAB_L=>0, self::LAB_A=>0, self::LAB_B=>0); }

        return array(self::LAB_L => 10 * sqrt($xyz['Y']), 
                     self::LAB_A => 17.5 * (((1.02 * $xyz['X']) - $xyz['Y']) / sqrt($xyz['Y'])),
                     self::LAB_B => 7 * (($xyz['Y'] - (0.847 * $xyz['Z'])) / sqrt($xyz['Y']))
                );
    }

    /**
    * Converts a color from RGB colorspace to CIE-L*ab colorspace
    * @param array $xyz With keys X, Y, and Z
    * @return array With keys L, a, and b
    * @link http://www.easyrgb.com/index.php?X=MATH&H=07#text7
    */
    public function XYZtoCIELAB(array $xyz)
    {
        if(!$this->validateXYZ($xyz))
        {
            throw new \InvalidArgumentException('Your $xyz array is invalid.');
        }

        $x = $xyz[self::XYZ_X] / 95.047;
        $y = $xyz[self::XYZ_Y] / 100;
        $z = $xyz[self::XYZ_Z] / 108.883;

        $x = ($x > 0.008856) ? pow($x, 1/3) : (7.787 * $x) + (16 / 116);
        $y = ($y > 0.008856) ? pow($y, 1/3) : (7.787 * $y) + (16 / 116);
        $z = ($z > 0.008856) ? pow($z, 1/3) : (7.787 * $z) + (16 / 116);

        return array(self::LAB_L => (116 * $y) - 16, self::LAB_A => 500 * ($x - $y), self::LAB_B => 200 * ($y - $z));
    }

    public function colorIndexToHunterLab($int)
    {
        return $this->XYZtoHunterLab($this->RGBtoXYZ($this->colorIndexToRGB($int)));
    }
    
    public function RGBToHunterLab(array $rgb)
    {
        return $this->XYZtoHunterLab($this->RGBtoXYZ($rgb));
    }

    /**
     * @param integer $a
     * @param integer $b
     * @return CIE-H° value
     */
    public function LABtoHue($a, $b)
    {
        $bias = 0;

        if ($a >= 0 && $b == 0) { return 0;   }
        if ($a <  0 && $b == 0) { return 180; }
        if ($a == 0 && $b >  0) { return 90;  }
        if ($a == 0 && $b <  0) { return 270; }

        if ($a >  0 && $b >  0) { $bias = 0;  }
        if ($a <  0)            { $bias = 180;}
        if ($a >  0 && $b <  0) { $bias = 360;}

        return (rad2deg(atan($b / $a)) + $bias);
    }

    protected function validateRGB($rgb)
    {
        if(!is_array($rgb)) { return false; }
    
        $validKeys = array(self::RGB_RED, self::RGB_GREEN, self::RGB_BLUE);
        //check for all keys and only the right keys, with values all between 0 and 255 inclusive
        return ($validKeys === array_filter(array_keys($rgb), function($val) { return ($val >= 0 && $val<=255); }));
    }

    protected function validateXYZ($xyz)
    {   
        //check that it's an array, and that it has the right keys and that only those
        return (is_array($xyz) && array(self::XYZ_X, self::XYZ_Y, self::XYZ_Z) === array_keys($xyz));
    }
    
    
    
/*** BELOW ARE A COUPLE COLOR DIFFERENCE CALCULATORS THAT I MIGHT DO SOMETHING WITH LATER. ***/

    /**
     * Compute the Delta E 2000 value of two colors in the LAB colorspace
     *
     * @link http://en.wikipedia.org/wiki/Color_difference#CIEDE2000
     * @link http://easyrgb.com/index.php?X=DELT&H=05#text5
     * @param array $labA LAB color array
     * @param array $labB LAB color array
     * @return float
     */
    private function deltaE2000($labA, $labB)
    {
        $weightL  = 1; // Lightness
        $weightC  = 1; // Chroma
        $weightH  = 1; // Hue

        $xCA = sqrt($labA[self::LAB_A] * $labA[self::LAB_A] + $labA[self::LAB_B] * $labA[self::LAB_B]);
        $xCB = sqrt($labB[self::LAB_A] * $labB[self::LAB_A] + $labB[self::LAB_B] * $labB[self::LAB_B]);
        $xCX = ($xCA + $xCB) / 2;
        $xGX = 0.5 * (1 - sqrt((pow($xCX, 7)) / ((pow($xCX, 7)) + (pow(25, 7)))));
        $xNN = (1 + $xGX) * $labA[self::LAB_A];
        $xCA = sqrt($xNN * $xNN + $labA[self::LAB_B] * $labA[self::LAB_B]);
        $xHA = $this->LABtoHue($xNN, $labA[self::LAB_B]);
        $xNN = (1 + $xGX) * $labB[self::LAB_A];
        $xCB = sqrt($xNN * $xNN + $labB[self::LAB_B] * $labB[self::LAB_B]);
        $xHB = $this->LABtoHue($xNN, $labB[self::LAB_B]);
        $xDL = $labB[self::LAB_L] - $labA[self::LAB_L];
        $xDC = $xCB - $xCA;

        if (($xCA * $xCB) == 0) {
        $xDH = 0;
        } else {
        $xNN = round($xHB - $xHA, 12);
        if (abs($xNN) <= 180) {
            $xDH = $xHB - $xHA;
        } else {
            if ($xNN > 180) {
            $xDH = $xHB - $xHA - 360;
            } else {
            $xDH = $xHB - $xHA + 360;
            }
        } // if
        } // if

        $xDH = 2 * sqrt($xCA * $xCB) * sin(deg2rad($xDH / 2));
        $xLX = ($labA[self::LAB_L] + $labB[self::LAB_L]) / 2;
        $xCY = ($xCA + $xCB) / 2;

        if (($xCA *  $xCB) == 0) {
        $xHX = $xHA + $xHB;
        } else {
        $xNN = abs(round($xHA - $xHB, 12));
        if ($xNN >  180) {
            if (($xHB + $xHA) <  360) {
            $xHX = $xHA + $xHB + 360;
            } else {
            $xHX = $xHA + $xHB - 360;
            }
        } else {
            $xHX = $xHA + $xHB;
        } // if
        $xHX /= 2;
        } // if

        $xTX = 1 - 0.17 * cos(deg2rad($xHX - 30))
        + 0.24 * cos(deg2rad(2 * $xHX))
        + 0.32 * cos(deg2rad(3 * $xHX + 6))
        - 0.20 * cos(deg2rad(4 * $xHX - 63));

        $xPH = 30 * exp(- (($xHX  - 275) / 25) * (($xHX  - 275) / 25));
        $xRC = 2 * sqrt((pow($xCY, 7)) / ((pow($xCY, 7)) + (pow(25, 7))));
        $xSL = 1 + ((0.015 * (($xLX - 50) * ($xLX - 50)))
        / sqrt(20 + (($xLX - 50) * ($xLX - 50))));
        $xSC = 1 + 0.045 * $xCY;
        $xSH = 1 + 0.015 * $xCY * $xTX;
        $xRT = - sin(deg2rad(2 * $xPH)) * $xRC;
        $xDL = $xDL / $weightL * $xSL;
        $xDC = $xDC / $weightC * $xSC;
        $xDH = $xDH / $weightH * $xSH;

        $delta  = sqrt(pow($xDL, 2) + pow($xDC, 2) + pow($xDH, 2) + $xRT * $xDC * $xDH);
        return (is_nan($delta)) ? 1 : $delta / 100;
    }

    /**
     * Compute the Delta CMC value of two colors in the LAB colorspace
     *
     * @param array $labA LAB color array
     * @param array $labB LAB color array
     * @return float
     * @link http://easyrgb.com/index.php?X=DELT&H=06#text6
    */
    private function deltaCMC($labA, $labB)
    {
        // if $weightL is 2 and $weightC is 1, it means that the lightness
        // will contribute half as much importance to the delta as the chroma
        $weightL  = 2; // Lightness
        $weightC  = 1; // Chroma

        $xCA  = sqrt((pow($labA[self::LAB_A], 2)) + (pow($labA[self::LAB_B], 2)));
        $xCB  = sqrt((pow($labB[self::LAB_A], 2)) + (pow($labB[self::LAB_B], 2)));
        $xff  = sqrt((pow($xCA, 4)) / ((pow($xCA, 4)) + 1900));
        $xHA  = $this->LABtoHue($labA[self::LAB_A], $labA[self::LAB_B]);

        if ($xHA < 164 || $xHA > 345) {
        $xTT  = 0.36 + abs(0.4 * cos(deg2rad(35 + $xHA)));
        } else {
        $xTT  = 0.56 + abs(0.2 * cos(deg2rad(168 + $xHA)));
        }

        if ($labA[self::LAB_L] < 16) {
        $xSL  = 0.511;
        } else {
        $xSL  = (0.040975 * $labA[self::LAB_L]) / (1 + (0.01765 * $labA[self::LAB_L]));
        }

        $xSC = ((0.0638 * $xCA) / (1 + (0.0131 * $xCA))) + 0.638;
        $xSH = (($xff * $xTT) + 1 - $xff) * $xSC;
        $xDH = sqrt(pow($labB[self::LAB_A] - $labA[self::LAB_A], 2) + pow($labB[self::LAB_B] - $labA[self::LAB_B], 2) - pow($xCB - $xCA, 2));
        $xSL = ($labB[self::LAB_L] - $labA[self::LAB_L]) / $weightL * $xSL;
        $xSC = ($xCB - $xCA) / $weightC * $xSC;
        $xSH = $xDH / $xSH;

        $delta = sqrt(pow($xSL, 2) + pow($xSC, 2) + pow($xSH, 2));
        return (is_nan($delta)) ? 1 : $delta;
    }
}