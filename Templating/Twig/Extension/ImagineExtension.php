<?php
namespace ERD\ImagineExtensionsBundle\Templating\Twig\Extension;
use Avalanche\Bundle\ImagineBundle\Imagine\CachePathResolver;
use Symfony\Bundle\FrameworkBundle\HttpKernel;
use Imagine\Image\ImagineInterface;
use Avalanche\Bundle\ImagineBundle\Templating\ImagineExtension as BaseExtension;

/**
 * Add more imagine capabilities to twig
 *
 * @author Ethan Resnick <hi@ethanresnick.com>
 * @copyright May 31, 2012 Ethan Resnick Design
 */
class ImagineExtension extends BaseExtension
{
    /**
     * @var An instance of the imagine service
     */
    protected $imagine;
    
    protected $kernel;
    
    protected $webRoot;
    
    public function __construct(CachePathResolver $cachePathResolver, ImagineInterface $imagine, HttpKernel $kernel, $webRoot)
    {
        $this->imagine = $imagine;
        $this->kernel  = $kernel;
        $this->webRoot = $webRoot;

        parent::__construct($cachePathResolver);
    }

    public function getFunctions()
    {
        return array_merge(
                parent::getFunctions(),
                array(
                    'image_dimensions' => new \Twig_Function_Method($this, 'dimensions'),
                    'image_ratio'      => new \Twig_Function_Method($this, 'ratio')
                ));
    }
    
    public function getFilters()
    {
        return array_merge(
                parent::getFilters(),
                array(
                    'apply_filters' => new \Twig_Filter_Method($this, 'applyFilters')
                ));
    }
    public function dimensions($path)
    {
        $size = $this->imagine->open($path)->getSize();
        return array('width'=>$size->getWidth(), 'height'=>$size->getHeight());
    }
    
    public function ratio($path)
    {
        $size = $this->dimensions($path);
        
        return $size['width']/$size['height'];
    }
    
    /**
     * Support composable filters.
     * 
     * In dev mode using composable filters might slow things down because the bundle, rather 
     * than simply returning the path that will output the filter result when requested, 
     * actually has to apply the filter immediately so that its result is available for the next
     * filter in the chain. 
     * 
     * However, this will have no slowdown in production when templates are cached, which they
     * almost always are/should be.
     */
    public function applyFilters($path, array $filters)
    {
        foreach($filters as $filter)
        {
            $path = $this->applyFilter($path, $filter);
            
            // Skip the expensive process of a kernel subrequest if we can verify in advance 
            // that the file exists.
            if(!file_exists($this->webRoot.$path))
            {
                try { $this->kernel->render($path); }
                //if an error occured, just continue (probably means the image was already cached).
                catch(\Exception $e) { }
            }
        }
        
        return $path;
    }
}
