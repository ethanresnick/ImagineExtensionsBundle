<?php
namespace ERD\ImagineExtensionsBundle\ColorManagement;

/**
 * Description of ColorConverterInterface
 *
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jun 5, 2012 Ethan Resnick Design
 */
interface ColorConverterInterface
{
    const LAB_L = 'L';
    const LAB_A = 'a';
    const LAB_B = 'b';
    
    const RGB_RED   = 'R';
    const RGB_GREEN = 'G';
    const RGB_BLUE  = 'B';
    
    public function RGBToHunterLab(array $rgb);   
}