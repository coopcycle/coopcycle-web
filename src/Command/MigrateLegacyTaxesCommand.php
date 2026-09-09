<?php

namespace AppBundle\Command;

use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Moves an instance's products off its legacy tax categories.
 *
 * Legacy instances carry tax categories of their own making, whose rates have
 * no country ("tva_livraison", "ust", "iva", "vat"...). The current system uses
 * a shared, country-scoped catalogue instead, and COOPCYCLE_LEGACY_TAXES exists
 * only to keep the old ones visible. Repointing the products at the equivalent
 * BASE_* category is what has to happen before that flag can go.
 *
 * Equivalence is by amount, in the instance's own country: a product taxed at
 * 10% stays taxed at 10%, which is the only property that matters for money
 * already invoiced. The semantic categories (FOOD, DRINK...) are deliberately
 * not used here, because choosing between them is a judgement each co-op has to
 * make and getting it wrong changes the rate.
 *
 * Nothing is deleted. Historical adjustments reference the legacy rates by
 * origin_code, and removing those rows would strip the tax breakdown from every
 * past order.
 */
class MigrateLegacyTaxesCommand extends Command
{
    /**
     * Categories that are not products, and are never migrated.
     */
    private const SERVICE_CATEGORIES = ['SERVICE', 'SERVICE_TAX_EXEMPT'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $country
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('coopcycle:taxes:migrate-legacy')
            ->setDescription('Repoint products from the legacy tax categories onto the country-scoped ones')
            ->addOption(
                'force', null,
                InputOption::VALUE_NONE,
                'Actually write the changes; without it the command only reports what it would do'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title(sprintf('Legacy tax categories (country: %s)', $this->country));

        $legacy = $this->getLegacyCategoriesInUse();

        if (empty($legacy)) {
            $io->success('No product uses a legacy tax category, nothing to migrate.');

            return Command::SUCCESS;
        }

        $baseRates = $this->getBaseRatesByAmount();

        if (empty($baseRates)) {
            $io->error(sprintf(
                'No BASE_* tax rate is defined for country "%s", so there is nothing to migrate onto.',
                $this->country
            ));

            return Command::FAILURE;
        }

        $plan = [];
        $unmatched = [];

        foreach ($legacy as $row) {

            $amount = self::normalizeAmount($row['amount']);

            if (!isset($baseRates[$amount])) {
                $unmatched[] = $row;
                continue;
            }

            $plan[] = $row + ['target' => $baseRates[$amount]];
        }

        $io->table(
            ['legacy category', 'rate', 'amount', 'products', 'target category', 'target rate'],
            array_map(fn (array $row): array => [
                $row['category_code'],
                $row['rate_code'],
                sprintf('%s%%', $row['amount'] * 100),
                $row['variants'],
                $row['target']->getCategory()->getCode(),
                $row['target']->getCode(),
            ], $plan)
        );

        // A legacy rate whose amount does not exist in the country's catalogue
        // cannot be migrated mechanically: someone has to decide what it should
        // become, and that decision changes what customers are charged.
        if (!empty($unmatched)) {
            $io->error('No equivalent rate for these, migrate them by hand:');
            $io->table(
                ['legacy category', 'rate', 'amount', 'products'],
                array_map(fn (array $row): array => [
                    $row['category_code'],
                    $row['rate_code'],
                    sprintf('%s%%', $row['amount'] * 100),
                    $row['variants'],
                ], $unmatched)
            );

            return Command::FAILURE;
        }

        if (!$force) {
            $io->note(sprintf(
                '%d products would be repointed. Re-run with --force to apply.',
                array_sum(array_column($plan, 'variants'))
            ));

            return Command::SUCCESS;
        }

        $migrated = 0;
        foreach ($plan as $row) {
            $migrated += $this->migrate($row['category_code'], $row['target']->getCategory());
            $io->writeln(sprintf(
                '  %s -> %s',
                $row['category_code'],
                $row['target']->getCategory()->getCode()
            ));
        }

        $io->success(sprintf(
            '%d products repointed. The legacy categories and rates are left in place, '
                . 'past orders still reference them.',
            $migrated
        ));

        return Command::SUCCESS;
    }

    /**
     * Categories with a country-less rate and at least one product on them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getLegacyCategoriesInUse(): array
    {
        $sql = <<<SQL
            SELECT c.code AS category_code,
                   r.code AS rate_code,
                   r.amount AS amount,
                   count(DISTINCT v.id) AS variants
            FROM sylius_tax_category c
            JOIN sylius_tax_rate r ON r.category_id = c.id
            JOIN sylius_product_variant v ON v.tax_category_id = c.id
            WHERE (r.country IS NULL OR r.country = '')
              AND c.code NOT IN (:service_categories)
            GROUP BY c.code, r.code, r.amount
            ORDER BY count(DISTINCT v.id) DESC
            SQL;

        return $this->entityManager->getConnection()->executeQuery(
            $sql,
            ['service_categories' => self::SERVICE_CATEGORIES],
            ['service_categories' => \Doctrine\DBAL\ArrayParameterType::STRING]
        )->fetchAllAssociative();
    }

    /**
     * The country's base rates, keyed by amount, which is what makes a legacy
     * category and a new one equivalent.
     *
     * @return array<string, TaxRate>
     */
    private function getBaseRatesByAmount(): array
    {
        $rates = $this->entityManager->getRepository(TaxRate::class)
            ->createQueryBuilder('r')
            ->join(TaxCategory::class, 'c', \Doctrine\ORM\Query\Expr\Join::WITH, 'r.category = c.id')
            ->andWhere('c.code LIKE :base')
            ->andWhere('LOWER(r.country) = LOWER(:country)')
            ->setParameter('base', 'BASE%')
            ->setParameter('country', $this->country)
            ->getQuery()
            ->getResult();

        $byAmount = [];
        foreach ($rates as $rate) {
            $byAmount[self::normalizeAmount($rate->getAmount())] = $rate;
        }

        return $byAmount;
    }

    /**
     * The database returns a decimal as "0.20000" and Doctrine hydrates it as
     * 0.2, so both sides have to be brought to the same shape before they can
     * be compared as keys.
     */
    private static function normalizeAmount(mixed $amount): string
    {
        return number_format((float) $amount, 5, '.', '');
    }

    /**
     * Repointed through the ORM rather than with an UPDATE, so that the entity
     * listeners a product change normally triggers still run.
     */
    private function migrate(string $legacyCategoryCode, TaxCategory $target): int
    {
        $variants = $this->entityManager->getRepository(ProductVariant::class)
            ->createQueryBuilder('v')
            ->join(TaxCategory::class, 'c', \Doctrine\ORM\Query\Expr\Join::WITH, 'v.taxCategory = c.id')
            ->andWhere('c.code = :code')
            ->setParameter('code', $legacyCategoryCode)
            ->getQuery()
            ->toIterable();

        $count = 0;
        foreach ($variants as $variant) {
            $variant->setTaxCategory($target);
            $count++;

            if (0 === $count % 500) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();

        return $count;
    }
}
