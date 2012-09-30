<?php
namespace ERD\ImagineExtensionsBundle\Filter\AvalancheLoader;

use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use Avalanche\Bundle\ImagineBundle\Imagine\Filter\Loader\LoaderInterface;
use Imagine\Image\Color;
use ERD\ImagineExtensionsBundle\Filter\BackgroundFill;

/**
 * Description of BackgroundFillLoader
 *
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jun 3, 2012 Ethan Resnick Design
 */
class BackgroundFillLoader implements LoaderInterface
{
    public function load(array $options = array())
    {
        $alpha = isset($options['alpha']) ? $options['alphpa'] : 0;
            
        return new BackgroundFill(new Color($options['color'], $alpha));
    }
}