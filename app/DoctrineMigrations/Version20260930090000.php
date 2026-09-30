<?php

declare(strict_types=1);

namespace Application\Migrations;

use AppBundle\SearchQuery\CustomerSearch;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trigram-index sylius_customer for the "customer:" search autocomplete';
    }

    /**
     * CREATE INDEX CONCURRENTLY cannot run inside a transaction, and
     * sylius_customer is large and write-heavy in production - a plain
     * CREATE INDEX would block writes (including customer signup and every
     * checkout that touches the row) for the duration of the build.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    /**
     * Backs AppBundle\SearchQuery\OrdersAutocompleteController::customer(),
     * which the search bar calls on every keystroke. Without these, matching
     * is a sequential scan evaluating trigram similarity on every customer:
     * measured over 300k rows, the email/name lookup took 568ms and the
     * phone one 174ms, against 72ms and 2ms with the indexes in place.
     *
     * The definitions live in CustomerSearch, next to the DQL expressions
     * they have to stay identical to - see the note there. The phone index
     * additionally serves the "customer:" filter itself (see
     * AppBundle\SearchQuery\Orders), which normalizes a stored number the
     * same way and then matches a substring of it: a leading wildcard no
     * btree index could answer.
     */
    public function up(Schema $schema): void
    {
        foreach (CustomerSearch::indexDefinitions() as $name => $definition) {
            $this->addSql(sprintf(
                'CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s',
                $name,
                $definition
            ));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_keys(CustomerSearch::indexDefinitions()) as $name) {
            $this->addSql(sprintf('DROP INDEX CONCURRENTLY IF EXISTS %s', $name));
        }
    }
}
