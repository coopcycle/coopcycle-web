<?php

namespace AppBundle\Typesense\Converter;

use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Service\FilterService;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class ProductConverter
{
    private $uploaderHelper;
    private $imagineFilter;

    public function __construct(
        UploaderHelper $uploaderHelper,
        FilterService $imagineFilter,
    )
    {
        $this->uploaderHelper = $uploaderHelper;
        $this->imagineFilter = $imagineFilter;
    }

    public function getImageURL($product) : string | null
    {
        try {
            $productImage = $product->getImages()->filter(function ($image) {
                return $image->getRatio() === '1:1';
            })->first();
            if ($productImage) {
                $imagePath = $this->uploaderHelper->asset($productImage, 'imageFile');
                if (!empty($imagePath)) {
                    $filterName = sprintf('product_thumbnail_%s', str_replace(':', 'x', '1:1'));
                    return $this->imagineFilter->getUrlOfFilteredImage($imagePath, $filterName);
                }
            }
        } catch(NotLoadableException $e) {
            return null;
        }

        return null;
    }
}
