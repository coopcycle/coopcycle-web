<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001103226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add internal flag to sylius_promotion_coupon, to keep programmatically-minted coupons (e.g. referral rewards) out of /admin/promotions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_promotion_coupon ADD internal BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_promotion_coupon DROP internal');
    }
}
