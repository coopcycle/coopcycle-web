<?php

namespace AppBundle\Service;

use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Tagging;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\Expr;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TagManager
{
    private $doctrine;
    private $cache;
    private $defaultGetTagsOptions = [
        'cache' => false,
    ];

    public function __construct(ManagerRegistry $doctrine, CacheInterface $cache)
    {
        $this->doctrine = $doctrine;
        $this->cache = $cache;
    }

    public function getAllTags()
    {
        return $this->doctrine->getRepository(Tag::class)->findAll();
    }

    public function getTags(TaggableInterface $taggable, array $options = [])
    {
        $tagRepository = $this->doctrine->getRepository(Tag::class);

        $opts = array_merge($this->defaultGetTagsOptions, $options);

        if ($opts['cache'] === true) {

            return $this->cache->get($this->getCacheKey($taggable), function (ItemInterface $item) use ($taggable) {

                $item->expiresAfter(300);

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

        return $this->getTagsForTaggable($taggable);
    }

    public function addTagsAndFlush(TaggableInterface $taggable, $tags)
    {
        foreach ($tags as $tag) {
            $this->addTag($taggable, $tag);
        }

        if (count($tags) > 0) {
            $this->doctrine->getManagerForClass(Tagging::class)->flush();
        }
    }

    public function addTag(TaggableInterface $taggable, Tag $tag)
    {
        $taggingEntityManager = $this->doctrine->getManagerForClass(Tagging::class);

        $tagging = new Tagging();
        $tagging->setResourceClass($taggable->getTaggableResourceClass());
        $tagging->setResourceId($taggable->getId());
        $tagging->setTag($tag);

        $taggingEntityManager->persist($tagging);

        $this->cache->delete($this->getCacheKey($taggable));
    }

    public function removeTag(TaggableInterface $taggable, Tag $tag)
    {
        $taggingRepository = $this->doctrine->getRepository(Tagging::class);
        $taggingEntityManager = $this->doctrine->getManagerForClass(Tagging::class);

        $tagging = $taggingRepository->findOneBy([
            'resourceClass' => $taggable->getTaggableResourceClass(),
            'resourceId' => $taggable->getId(),
            'tag' => $tag,
        ]);

        $taggingEntityManager->remove($tagging);

        $this->cache->delete($this->getCacheKey($taggable));
    }

    public function untagAll(Tag $tag)
    {
        $taggingRepository = $this->doctrine->getRepository(Tagging::class);
        $taggingEntityManager = $this->doctrine->getManagerForClass(Tagging::class);

        $taggings = $taggingRepository->findBy([
            'tag' => $tag,
        ]);

        foreach ($taggings as $tagging) {
            $taggingEntityManager->remove($tagging);
            $this->cache->delete($this->getCacheKey($tagging));
        }
    }

    public function fromSlugs(array $slugs)
    {
        if (count($slugs) === 0) {

            return $slugs;
        }

        $tagRepository = $this->doctrine->getRepository(Tag::class);

        $qb = $tagRepository->createQueryBuilder('tag');
        $qb->andWhere($qb->expr()->in('tag.slug', $slugs));

        return $qb->getQuery()->getResult();
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
        $tagRepository = $this->doctrine->getRepository(Tag::class);

        $qb = $tagRepository
            ->createQueryBuilder('tag')
            ->join(Tagging::class, 'tagging', Expr\Join::WITH, 'tagging.resourceClass = :resourceClass AND tagging.tag = tag.id')
            ->andWhere('tagging.resourceId = :resourceId')
            ->setParameter('resourceClass', $taggable->getTaggableResourceClass())
            ->setParameter('resourceId', $taggable->getId());

        return $qb->getQuery()->getResult();
    }
}
