<?php

namespace AppBundle\Typesense\Converter;

use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\HttpFoundation\RequestStack;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class ShopConverter
{
    private $uploaderHelper;
    private $imagineFilter;
    private $requestStack;

    public function __construct(
        UploaderHelper $uploaderHelper,
        FilterService $imagineFilter,
        RequestStack $requestStack
    )
    {
        $this->uploaderHelper = $uploaderHelper;
        $this->imagineFilter = $imagineFilter;
        $this->requestStack = $requestStack;
    }

    public function getImageURL($shop) : string | null
    {
        try {
            $imagePath = $this->uploaderHelper->asset($shop, 'imageFile');
            if (empty($imagePath)) {
                $imagePath = '/img/cuisine/default.jpg';
                $request = $this->requestStack->getCurrentRequest();
                if ($request) {
                    return $request->getUriForPath($imagePath);
                }
            } else {
                return $this->imagineFilter->getUrlOfFilteredImage($imagePath, 'restaurant_thumbnail');
            }
        } catch(NotLoadableException $e) {
            return null;
        }

        return null;
    }
}
