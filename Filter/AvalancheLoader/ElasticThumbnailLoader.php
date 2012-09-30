<?php
namespace ERD\ImagineExtensionsBundle\Filter\AvalancheLoader;

use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use ERD\ImagineExtensionsBundle\Filter\ElasticThumbnail;

/**
 * ElasticThumbnail Loader
 *
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright May 17, 2012 Ethan Resnick Design
 */
class ElasticThumbnailLoader implements \Avalanche\Bundle\ImagineBundle\Imagine\Filter\Loader\LoaderInterface
{
    /**
     * @param array $options An associative array containing keys: 'finalWidth', 'baseline', and, optionally, 'heightModifier'
     *                       'baseline' is the vertical rhythm in px; 'heightModifier' is a positive or negative value added to the
     *                       image's calculated height to allow it to account for borders, padding, etc while still falling on rhythm.
     * @return \ERD\ImagineExtensionsBundle\Filter\ElasticThumbnail 
     */
    public function load(array $options = array())
    {
        if(!isset($options['heightModifier'])) { $options['heightModifier'] = 0; }
        
        return new ElasticThumbnail($options['finalWidth'], $options['baseline'], $options['heightModifier']);
        
    }
}
