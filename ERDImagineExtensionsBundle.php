<?php

namespace ERD\ImagineExtensionsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ERDImagineExtensionsBundle extends Bundle
{
    public function getParent()
    {
        return 'AvalancheImagineBundle';
    }
}
