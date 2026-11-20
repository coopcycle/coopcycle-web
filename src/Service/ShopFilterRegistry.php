<?php

namespace AppBundle\Service;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusiness\Collection as ShopCollection;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\UI\HomepageBlock;
use AppBundle\Entity\Cuisine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\Icons\Exception\IconNotFoundException;
use Symfony\UX\Icons\IconRendererInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The filters offered when browsing the shops.
 *
 * When the homepage has been customized with the homepage editor, its shop
 * collections drive the list, so that the homepage and the shops page share
 * the same structure. Otherwise a default set is used, built from what the
 * instance actually has.
 *
 * Shared by the /shops page and by the API, so that the website and the
 * mobile apps offer the same filters, translated the same way.
 */
class ShopFilterRegistry
{
    /**
     * The size the icons are rendered at. Clients are free to scale them —
     * every icon carries a viewBox — but they need a number to start from.
     */
    private const ICON_SIZE = 48;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LocalBusinessRepository $shopRepository,
        private SettingsManager $settingsManager,
        private TranslatorInterface $translator,
        private IconRendererInterface $iconRenderer)
    {}

    /**
     * The cuisines worth offering as filters: those at least one shop serves.
     *
     * Same shape as getCategories(), so a client can treat a cuisine as one
     * more filter, plus the icon the /shops page shows above the label.
     * Sorted by label, which is how they read as a strip; the order of
     * getCategories() is the instance's own and is left alone.
     *
     * @return array<int, array{id: string, filter: string, value: string, label: string, icon: string|null}>
     */
    public function getCuisines(): array
    {
        $cuisines = array_map(
            fn (Cuisine $cuisine) => $this->makeCategory(
                'cuisine',
                $cuisine->getName(),
                $cuisine->getName(),
                'cuisines'
            ) + ['icon' => $this->renderIcon($cuisine)],
            $this->shopRepository->findCuisinesByFilters()
        );

        usort($cuisines, fn (array $a, array $b) => $a['label'] <=> $b['label']);

        return $cuisines;
    }

    /**
     * The cuisine's icon as SVG markup, rather than as the name of an icon in
     * someone else's icon set: the website resolves those names server side
     * too, and asking every app to reach iconify.design for them would put a
     * third party between a customer and the shops list.
     */
    private function renderIcon(Cuisine $cuisine): ?string
    {
        if (!$cuisine->hasIcon()) {
            return null;
        }

        try {
            // An explicit size, because the bundle's default is "1em" and
            // there is no font size to resolve that against outside a browser.
            return $this->iconRenderer->renderIcon($cuisine->getIcon(), [
                'width' => self::ICON_SIZE,
                'height' => self::ICON_SIZE,
            ]);
        } catch (IconNotFoundException) {
            // A name that no icon set answers to is a configuration slip, not
            // a reason to drop the cuisine from the list.
            return null;
        }
    }

    /**
     * @return array<int, array{id: string, filter: string, value: string, label: string}>
     */
    public function getCategories(): array
    {
        $categories = $this->getHomepageCategories();

        return count($categories) > 0 ? $categories : $this->getDefaultCategories();
    }

    private function getHomepageCategories(): array
    {
        if (!$this->settingsManager->getBoolean('homepage_published')) {
            return [];
        }

        $blocks = $this->entityManager->getRepository(HomepageBlock::class)
            ->findBy([], ['position' => 'ASC']);

        $categories = [];

        foreach ($blocks as $block) {

            if ('shop_collection' !== $block->type) {
                continue;
            }

            $component = $block->data['component'] ?? null;
            // An empty args object round-trips through PHP as a JSON array
            $args = is_array($block->data['args'] ?? null) ? $block->data['args'] : [];

            $category = match ($component) {
                'newest' => $this->makeCategory('category', 'new', 'homepage.shops.new'),
                'featured' => $this->makeCategory('category', 'featured', 'homepage.featured'),
                'exclusive' => $this->makeCategory('category', 'exclusive', 'homepage.exclusive'),
                // The homepage banner-like wording is too long for a filter chip
                'zerowaste' => $this->makeCategory('category', 'zerowaste', 'restaurant.list.tags.zerowaste'),
                'edenred' => $this->makeCategory('category', 'edenred', 'homepage.edenred'),
                'cuisine' => isset($args['cuisine']) ?
                    $this->makeCategory('cuisine', $args['cuisine'], $args['cuisine'], 'cuisines') : null,
                'type' => isset($args['type']) ? $this->makeTypeCategory($args['type']) : null,
                'custom' => isset($args['slug']) ? $this->makeCollectionCategory($args['slug']) : null,
                default => null,
            };

            if (null !== $category) {
                $categories[] = $category;
            }
        }

        // The same collection may be used by several blocks (with different sort options)
        return array_values(array_column($categories, null, 'id'));
    }

    private function getDefaultCategories(): array
    {
        $categories = [];

        $types = array_keys($this->shopRepository->countByType());
        if (count($types) > 1) {
            foreach ($types as $type) {
                if ($category = $this->makeTypeCategory(LocalBusiness::getKeyForType($type))) {
                    $categories[] = $category;
                }
            }
        }

        $categories[] = $this->makeCategory('category', 'new', 'homepage.shops.new');

        if ($this->shopRepository->countZeroWaste() > 0) {
            $categories[] = $this->makeCategory('category', 'zerowaste', 'restaurant.list.tags.zerowaste');
        }

        if ($this->shopRepository->countFeatured() > 0) {
            $categories[] = $this->makeCategory('category', 'featured', 'homepage.featured');
        }

        if ($this->shopRepository->countExclusive() > 0) {
            $categories[] = $this->makeCategory('category', 'exclusive', 'homepage.exclusive');
        }

        return $categories;
    }

    private function makeTypeCategory(string $typeKey): ?array
    {
        $type = LocalBusiness::getTypeForKey($typeKey);

        if (null === $type) {
            return null;
        }

        return $this->makeCategory('type', $typeKey, LocalBusiness::getTransKeyForType($type));
    }

    private function makeCollectionCategory(string $slug): ?array
    {
        $collection = $this->entityManager->getRepository(ShopCollection::class)
            ->findOneBy(['slug' => $slug]);

        if (null === $collection) {
            return null;
        }

        return [
            'id' => sprintf('collection|%s', $slug),
            'filter' => 'collection',
            'value' => $slug,
            'label' => $collection->getTitle(),
        ];
    }

    private function makeCategory(string $filter, string $value, string $transKey, ?string $domain = null): array
    {
        return [
            'id' => sprintf('%s|%s', $filter, $value),
            'filter' => $filter,
            'value' => $value,
            'label' => $this->translator->trans($transKey, [], $domain),
        ];
    }
}
