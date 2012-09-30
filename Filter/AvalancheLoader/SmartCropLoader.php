<?php
namespace ERD\ImagineExtensionsBundle\Filter\AvalancheLoader;

use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use ERD\ImagineExtensionsBundle\Filter\SmartCrop;
use ERD\ImagineExtensionsBundle\ColorManagement\ColorConverterInterface;
/**
 * Description of SmartCropLoader
 *
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jun 5, 2012 Ethan Resnick Design
 */
class SmartCropLoader implements \Avalanche\Bundle\ImagineBundle\Imagine\Filter\Loader\LoaderInterface
{
    protected $colorConverter;
    
    public function __construct(ColorConverterInterface $colorConverter)
    {
        $this->colorConverter = $colorConverter;
    }

    /**
     * @param array $options An associative array a 'cropHeight' and 'cropWidth' key
     * @return \ERD\ImagineExtensionsBundle\Filter\SmartCrop
     */
    public function load(array $options = array())
    {   
        return new SmartCrop(new Box($options['width'], $options['height']), $this->colorConverter);
        
    }    
}