<?php

namespace AppBundle\Sylius\Taxation;

use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\EntityRepository;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaxesHelper
{

    public function __construct(
        private RepositoryInterface|EntityRepository $taxRateRepository,
        private TranslatorInterface $translator,
        private SettingsManager $settingsManager,
        private string $country,
        private string $locale,
        private bool $legacyTaxes)
    {}

    public function getTaxTotals(OrderInterface $order, bool $itemsOnly = false): array
    {
        $taxRateCodes = [];
        foreach ($order->getItems() as $item) {
            foreach ($item->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $adj) {
                $taxRateCodes[] = $adj->getOriginCode();
            }
        }

        if (!$itemsOnly) {
            foreach ($order->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $adj) {
                $taxRateCodes[] = $adj->getOriginCode();
            }
        }

        $taxRateCodes = array_unique($taxRateCodes);
        $taxRates = array_map(
            fn(string $code) => $this->taxRateRepository->findOneBy(['code' => $code]),
            $taxRateCodes
        );

        $values = [];
        foreach ($taxRates as $taxRate) {

            $taxTotal = $order->getTaxTotalByRate($taxRate);

            if ($taxTotal > 0) {

                $key = sprintf('%s %d%%',
                    $this->translator->trans($taxRate->getName(), [], 'taxation'),
                    ($taxRate->getAmount() * 100)
                );

                if (isset($values[$key])) {
                    $values[$key] += $taxTotal;
                } else {
                    $values[$key] = $taxTotal;
                }
            }
        }

        return $values;
    }

    public function translate($code): string
    {
        $taxRate = $this->taxRateRepository->findOneBy(['code' => $code]);

        $formatter = new \NumberFormatter($this->locale, \NumberFormatter::PERCENT);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);

        return sprintf('%s %s',
            $this->translator->trans($taxRate->getName(), [], 'taxation'),
            $formatter->format($taxRate->getAmount())
        );
    }

    private static $baseTaxCategories = [
        'BASE_STANDARD',
        'BASE_INTERMEDIARY',
        'BASE_REDUCED',
    ];

    public function getBaseRates()
    {
        $qb = $this->taxRateRepository->createQueryBuilder('r');
        $qb
            ->join(TaxCategory::class, 'c', Expr\Join::WITH, 'r.category = c.id')
            ->andWhere('LOWER(r.country) = LOWER(:country)')
            ->andWhere(
                $qb->expr()->in('c.code', ':codes')
            )
            ->setParameter('country', $this->country)
            ->setParameter('codes', self::$baseTaxCategories)
            ->orderBy('r.amount', 'ASC')
            ;

        return $qb->getQuery()->getResult();
    }

    private static $baseRateCodeCache = [];

    public function getMatchingBaseRateCode($code)
    {
        if (!isset(self::$baseRateCodeCache[$code])) {

            $rate = $this->taxRateRepository->findOneBy(['code' => $code]);
            $baseRates = $this->getBaseRates();

            foreach ($baseRates as $baseRate) {
                if ($rate->getAmount() === $baseRate->getAmount()) {
                    self::$baseRateCodeCache[$code] = $baseRate->getCode();

                    return self::$baseRateCodeCache[$code];
                }
            }

            self::$baseRateCodeCache[$code] = null;
        }

        return self::$baseRateCodeCache[$code];
    }

    /**
     * @return string|null
     */
    public function getServiceTaxRateCode()
    {
        $subjectToVat = $this->settingsManager->get('subject_to_vat');
        $code = $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT';

        $qb = $this->taxRateRepository->createQueryBuilder('r');
        $qb
            ->join(TaxCategory::class, 'c', Expr\Join::WITH, 'r.category = c.id')
            ->andWhere('LOWER(r.country) = LOWER(:country)')
            ->andWhere('c.code = :code')
            ->setParameter('country', $this->country)
            ->setParameter('code', $code)
            ;

        $rate = $qb->getQuery()->getOneOrNullResult();

        if ($rate) {
            return $rate->getCode();
        }

        return null;
    }

    /**
     * Given a base rate code, returns
     */
    public function getAlternativeTaxRateCodes(string $baseRateCode): array
    {
        $subQuery = $this->taxRateRepository->createQueryBuilder('br');
        $subQuery->select('br.amount');
        $subQuery->andWhere('LOWER(br.country) = LOWER(:country)');
        $subQuery->andWhere('br.code = :base_rate_code');
        $subQuery->setParameter('country', $this->country);
        $subQuery->setParameter('base_rate_code', $baseRateCode);

        $amount = $subQuery->getQuery()->getSingleScalarResult();

        $qb = $this->taxRateRepository->createQueryBuilder('r');
        $qb->select('r.code');

        if ($this->legacyTaxes) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('r.country'),
                    $qb->expr()->eq('LOWER(r.country)', 'LOWER(:country)')
                )
            );
        } else {
            $qb->andWhere('LOWER(r.country) = LOWER(:country)');
        }

        $qb
            ->andWhere('r.amount = :amount')
            ->andWhere('r.code != :base_rate_code')
            ->setParameter('country', $this->country)
            ->setParameter('amount', $amount)
            ->setParameter('base_rate_code', $baseRateCode);

        return $qb->getQuery()->getSingleColumnResult();
    }
}
