<?php
namespace ERD\ImagineExtensionsBundle\Filter\AvalancheLoader;

use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use ERD\ImagineExtensionsBundle\Filter\MaxHeight;

/**
 * MaxHeight Loader
 *
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright May 17, 2012 Ethan Resnick Design
 */
class MaxHeightLoader implements \Avalanche\Bundle\ImagineBundle\Imagine\Filter\Loader\LoaderInterface
{
    public function load(array $options = array())
    {   
		if(!isset($options['height'])) { throw new \InvalidArgumentException('A \'height\' option is required.'); }

		if(!isset($options['crop']))
		{	
			return new MaxHeight($options['height']);
		}
	
		return new MaxHeight($options['height'], $options['crop']); 
    }
}
