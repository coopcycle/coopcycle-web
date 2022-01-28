<?php

namespace AppBundle\Routing;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class EmbedAwareRouter implements WarmableInterface, ServiceSubscriberInterface, RouterInterface, RequestMatcherInterface
{
    /**
     * @var Router
     */
    private $router;

    private $requestStack;

    private static $allowedRouteNames = [
        'restaurant',
        'restaurant_add_product_to_cart',
        'restaurant_cart_address',
        'restaurant_cart',
        'restaurant_cart_clear_time',
        'restaurant_modify_cart_item_quantity',
        'restaurant_remove_from_cart',
        'order',
        'order_payment',
        'order_payment_select_method',
    ];

    public function __construct(Router $router, RequestStack $requestStack)
    {
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    public function getRouteCollection()
    {
        return $this->router->getRouteCollection();
    }

    public function warmUp(string $cacheDir)
    {
        return $this->router->warmUp($cacheDir);
    }

    public static function getSubscribedServices()
    {
        return [
            'routing.loader' => LoaderInterface::class,
        ];
    }

    public function setContext(RequestContext $context)
    {
        return $this->router->setContext($context);
    }

    public function getContext()
    {
        return $this->router->getContext();
    }

    public function matchRequest(Request $request)
    {
        return $this->router->matchRequest($request);
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH)
    {
        if (in_array($name, self::$allowedRouteNames)) {
            $request = $this->requestStack->getCurrentRequest();
            $hasEmbedParam = $request->query->has('embed')
                && ('' === $request->query->get('embed') || true === $request->query->getBoolean('embed'));
            if ($hasEmbedParam) {
                $parameters['embed'] = '1';
            }
        }

        return $this->router->generate($name, $parameters, $referenceType);
    }

    public function match(string $pathinfo)
    {
        return $this->router->match($pathinfo);
    }
}
