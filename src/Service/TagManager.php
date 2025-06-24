<?php

namespace AppBundle\Service;

use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Tagging;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TagManager
{
    private $entityManager;
    private $cache;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, CacheInterface $cache, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function getTags(TaggableInterface $taggable)
    {
        if (null === $taggable->getId()) {
            return [];
        }

        return $this->cache->get($this->getCacheKey($taggable), function (ItemInterface $item) use ($taggable) {

            // Cache for 1 day
            $item->expiresAfter(60 * 60 * 24);

            $tags = [];
            foreach ($this->getTagsForTaggable($taggable) as $tag) {

                $tags[] = [
                    'name' => $tag->getName(),
                    'slug' => $tag->getSlug(),
                    'color' => $tag->getColor(),
                ];
            }

            return $tags;
        });
    }

    private function addTag(TaggableInterface $taggable, string $tag): callable
    {
        return function (TaggableInterface $taggable) use ($tag): ?Tagging {

            if (null === $taggable->getId()) {

                return null;
            }

            $entity = $this->getTagEntity($tag);
            if (null === $entity) {

                return null;
            }

            $tagging = new Tagging();
            $tagging->setResourceClass($taggable->getTaggableResourceClass());
            $tagging->setResourceId($taggable->getId());
            $tagging->setTag($entity);

            return $tagging;
        };
    }

    private function removeTag(TaggableInterface $taggable, string $tag): callable
    {
        return function (TaggableInterface $taggable) use ($tag): ?Tagging {

            if (null === $taggable->getId()) {

                return null;
            }

            $entity = $this->getTagEntity($tag);
            if (null === $entity) {

                return null;
            }

            $taggingRepository = $this->entityManager->getRepository(Tagging::class);

            return $taggingRepository->findOneBy([
                'resourceClass' => $taggable->getTaggableResourceClass(),
                'resourceId' => $taggable->getId(),
                'tag' => $entity,
            ]);
        };
    }

    public function clearCache(TaggableInterface $taggable)
    {
        $this->cache->delete($this->getCacheKey($taggable));
    }

    public function untagAll(Tag $tag)
    {
        $taggingRepository = $this->entityManager->getRepository(Tagging::class);

        $taggings = $taggingRepository->findBy([
            'tag' => $tag,
        ]);

        foreach ($taggings as $tagging) {
            $this->entityManager->remove($tagging);
            $this->cache->delete($this->getCacheKey($tagging));
        }
    }

    private function getCacheKey($taggableOrTagging)
    {
        if ($taggableOrTagging instanceof Tagging) {
            $resourceClass = $taggableOrTagging->getResourceClass();
            $resourceId = $taggableOrTagging->getResourceId();
        } elseif ($taggableOrTagging instanceof TaggableInterface) {
            $resourceClass = $taggableOrTagging->getTaggableResourceClass();
            $resourceId = $taggableOrTagging->getId();
        } else {
            throw new \InvalidArgumentException(sprintf('$taggableOrTagging must be an instance of %s or %s',
                Tagging::class, TaggableInterface::class));
        }

        return sha1(sprintf('%s|%d', $resourceClass, $resourceId));
    }

    private function getTagsForTaggable(TaggableInterface $taggable)
    {
        $tagRepository = $this->entityManager->getRepository(Tag::class);

        $qb = $tagRepository
            ->createQueryBuilder('tag')
            ->join(Tagging::class, 'tagging', Expr\Join::WITH, 'tagging.resourceClass = :resourceClass AND tagging.tag = tag.id')
            ->andWhere('tagging.resourceId = :resourceId')
            ->setParameter('resourceClass', $taggable->getTaggableResourceClass())
            ->setParameter('resourceId', $taggable->getId());

        return $qb->getQuery()->getResult();
    }

    private function getTag($slug)
    {
        $cacheKey = sprintf('tag|%s', $slug);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($slug) {

            $qb = $this->entityManager
                ->getRepository(Tag::class)
                ->createQueryBuilder('tag')
                ->andWhere('tag.slug = :slug')
                ->setParameter('slug', $slug)
                ;

            $tag = $qb->getQuery()->getOneOrNullResult();

            // If the tag exists,
            // it will be cached *FOREVER*
            if (null !== $tag) {

                return [
                    'name'  => $tag->getName(),
                    'slug'  => $tag->getSlug(),
                    'color' => $tag->getColor(),
                ];
            }

            // If the tag does not exist,
            // we stop looking during 5 minutes
            $item->expiresAfter(5 * 60);

            return null;
        });
    }

    private function getTagEntity(string $slug)
    {
        // Do *NOT* cache entities, because they may have been detached.
        // This causes the following error:
        //
        // A new entity was found through the relationship 'AppBundle\Entity\Tagging#tag'
        // that was not configured to cascade persist operations for entity: AppBundle\Entity\Tag
        return $this->entityManager
            ->getRepository(Tag::class)->findOneBySlug($slug);
    }

    public function expand($tags)
    {
        $expanded = [];

        foreach ($tags as $slug) {
            $tag = $this->getTag($slug);
            if ($tag) {
                $expanded[] = $tag;
            }
        }

        return $expanded;
    }

    public function update(TaggableInterface $taggable): array
    {
        $added = [];
        $removed = [];

        $originalTags =
            array_map(fn ($tag) => $tag['slug'], $this->getTags($taggable));

        $newTags = $taggable->getTags();

        $this->logger->debug(sprintf('Original tags "%s", new tags "%s"',
            implode(' ', $originalTags),
            implode(' ', $newTags)
        ), [
            'taggable' => $taggable->getTaggableResourceClass(),
        ]);

        foreach ($originalTags as $originalTag) {
            if (!in_array($originalTag, $newTags)) {
                $this->logger->debug(sprintf('Tag "%s" has been removed', $originalTag));
                $removed[] = $this->removeTag($taggable, $originalTag);
            }
        }

        foreach ($newTags as $newTag) {
            if (!in_array($newTag, $originalTags)) {
                $this->logger->debug(sprintf('Tag "%s" has been added', $newTag));
                $added[] = $this->addTag($taggable, $newTag);
            }
        }

        return [
            $added,
            $removed
        ];
    }

    public function getAllTags(): array
    {
        return $this->entityManager
            ->getRepository(Tag::class)
            ->createQueryBuilder('t')
            ->select(
                't.name',
                't.slug',
                't.color'
            )
            ->getQuery()
            ->getArrayResult()
            ;
    }

    public function warmupCache(TaggableInterface ...$taggables)
    {
        $resourceClasses = [];
        $taggablesById = [];

        foreach ($taggables as $taggable) {
            $resourceClasses[] = $taggable->getTaggableResourceClass();
            $taggablesById[$taggable->getId()] = $taggable;
        }

        $resourceClasses = array_unique($resourceClasses);

        if (count($resourceClasses) > 1) {
            throw new \Exception('It is not possible to warmup cache of different classes.');
        }

        $taggingRepository = $this->entityManager->getRepository(Tagging::class);

        // Use a "fetch join" in DQL,
        // so that calling $tagging->getTag() doesn't fire an additional query
        $query = $this->entityManager->createQuery('SELECT tagging, tag FROM AppBundle\Entity\Tagging tagging JOIN tagging.tag tag WHERE tagging.resourceClass = :resource_class AND tagging.resourceId IN (:resource_ids)');
        $query->setParameter('resource_class', current($resourceClasses));
        $query->setParameter('resource_ids', array_map(fn($t) => $t->getId(), $taggables));

        $taggings = $query->getResult();

        $tagsByTaggable = new \SplObjectStorage();

        foreach ($taggings as $tagging) {
            $taggable = $taggablesById[$tagging->getResourceId()];
            $tags = isset($tagsByTaggable[$taggable]) ? $tagsByTaggable[$taggable] : [];

            $tagsByTaggable[$taggable] = array_merge($tags, [
                $tagging->getTag()
            ]);
        }

        foreach ($tagsByTaggable as $taggable) {

            $cacheKey = $this->getCacheKey($taggable);

            $tags = [];
            foreach ($tagsByTaggable[$taggable] as $tag) {
                $tags[] = [
                    'name' => $tag->getName(),
                    'slug' => $tag->getSlug(),
                    'color' => $tag->getColor(),
                ];
            }

            if (count($tags) === 0) {
                continue;
            }

            $cacheItem = $this->cache->getItem($cacheKey);
            if (!$cacheItem->isHit()) {
                // Cache for 1 day
                $cacheItem->expiresAfter(60 * 60 * 24);
                $cacheItem->set($tags);
                $this->cache->save($cacheItem);
            }
        }
    }
}
