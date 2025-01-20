<?php

namespace AppBundle\Form;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\LoopEat\Client as LoopeatClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class ReusablePackagingChoiceLoader implements ChoiceLoaderInterface
{
    public function __construct(
        private LocalBusiness $restaurant,
        private LoopeatClient $loopeatClient,
        private EntityManagerInterface $entityManager,
        private $loopeatToteBagId = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        if ($this->restaurant->isLoopeatEnabled()) {

            // TODO Caching etc

            $formats = $this->loopeatClient->getFormats($this->restaurant);

            $added = 0;
            foreach ($formats as $format) {

                $qb = $this->entityManager->getRepository(ReusablePackaging::class)
                    ->createQueryBuilder('rp');
                $qb->andWhere('JSON_GET_FIELD_AS_TEXT(rp.data, \'id\') = :format_id');
                $qb->andWhere('rp.restaurant = :restaurant');
                $qb->setParameter('format_id', $format['id']);
                $qb->setParameter('restaurant', $this->restaurant);

                $reusablePackaging = $qb->getQuery()->getOneOrNullResult();

                if (null === $reusablePackaging) {
                    $reusablePackaging = new ReusablePackaging();
                    $reusablePackaging->setName(implode(' - ', [ $format['title'], $format['subtitle'] ]));
                    $reusablePackaging->setType(ReusablePackaging::TYPE_LOOPEAT);
                    $reusablePackaging->setData($format);
                    $reusablePackaging->setPrice(0);
                    $reusablePackaging->setOnHold(0);
                    $reusablePackaging->setOnHand(9999);
                    $reusablePackaging->setTracked(false);

                    $this->restaurant->addReusablePackaging($reusablePackaging);
                    $added++;
                }
            }

            if ($added > 0) {
                $this->entityManager->flush();
            }
        }

        $packagings = array_filter($this->restaurant->getReusablePackagings()->toArray(), function (ReusablePackaging $p) {
            $data = $p->getData();
            if (!empty($this->loopeatToteBagId) && (int) $data['id'] === (int) $this->loopeatToteBagId) {
                return false;
            }

            return true;
        });

        return new ArrayChoiceList(array_values($packagings), $value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        // Optimize
        if (empty($values)) {
            return [];
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        // Optimize
        if (empty($choices)) {
            return [];
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }
}
