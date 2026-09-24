<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clear the bogus product links the Zelty import stamped onto shared modifier option values';
    }

    public function up(Schema $schema): void
    {
        // A value's `product` means "this choice IS that dish", and the Zelty
        // import only ever creates such a value for a menu part — where the
        // value carries the dish's own Zelty id. Any other Zelty-imported
        // value pointing at a Zelty product is the mis-stamping done by
        // ZeltyProductMapper::linkOptionToProductIfNotExists(): the first dish
        // to use a shared modifier claimed all of its choices, which then got
        // disabled with that dish by DisabledProductListener.
        //
        // Values with no Zelty id are links an admin made by hand and are left
        // alone, as are menu-part values, whose ids match their product's.
        $this->addSql(
            "UPDATE sylius_product_option_value v
             SET product_id = NULL
             FROM sylius_product p
             WHERE v.product_id = p.id
               AND v.metadata->>'zelty_id' IS NOT NULL
               AND p.metadata->>'zelty_id' IS NOT NULL
               AND v.metadata->>'zelty_id' <> p.metadata->>'zelty_id'"
        );
    }

    public function down(Schema $schema): void
    {
        // The links deleted here were wrong to begin with and nothing records
        // which dish each one pointed at, so there is nothing to restore.
        $this->throwIrreversibleMigrationException();
    }
}
