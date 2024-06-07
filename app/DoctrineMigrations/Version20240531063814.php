<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240531063814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove comments for deprecated json_array type to get ready for DBAL 3.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN delivery_import_queue.errors IS NULL');
        $this->addSql('COMMENT ON COLUMN delivery_quote.payload IS NULL');
        $this->addSql('COMMENT ON COLUMN edifact_message.metadata IS NULL');
        $this->addSql('COMMENT ON COLUMN failure_reason.metadata IS NULL');
        $this->addSql('COMMENT ON COLUMN incident_event.metadata IS NULL');
        $this->addSql('COMMENT ON COLUMN invitation.grants IS NULL');
        $this->addSql('COMMENT ON COLUMN pricing_rule_set.options IS NULL');
        $this->addSql('COMMENT ON COLUMN refund.data IS NULL');
        $this->addSql('COMMENT ON COLUMN restaurant.additional_properties IS NULL');
        $this->addSql('COMMENT ON COLUMN restaurant.stripe_connect_roles IS NULL');
        $this->addSql('COMMENT ON COLUMN restaurant.mercadopago_connect_roles IS NULL');
        $this->addSql('COMMENT ON COLUMN restaurant.stripe_payment_methods IS NULL');
        $this->addSql('COMMENT ON COLUMN restaurant_fulfillment_method.opening_hours IS NULL');
        $this->addSql('COMMENT ON COLUMN restaurant_fulfillment_method.options IS NULL');
        $this->addSql('COMMENT ON COLUMN reusable_packaging.data IS NULL');
        $this->addSql('COMMENT ON COLUMN store.additional_properties IS NULL');
        $this->addSql('COMMENT ON COLUMN sylius_order_event.data IS NULL');
        $this->addSql('COMMENT ON COLUMN sylius_order_event.metadata IS NULL');
        $this->addSql('COMMENT ON COLUMN sylius_order_loopeat_details.returns IS NULL');
        $this->addSql('COMMENT ON COLUMN sylius_order_loopeat_details.deliver IS NULL');
        $this->addSql('COMMENT ON COLUMN sylius_order_loopeat_details.pickup IS NULL');
        $this->addSql('COMMENT ON COLUMN task.metadata IS NULL');
        $this->addSql('COMMENT ON COLUMN task_event.data IS NULL');
        $this->addSql('COMMENT ON COLUMN task_event.metadata IS NULL');
        $this->addSql('COMMENT ON COLUMN task_rrule.template IS NULL');
        $this->addSql('COMMENT ON COLUMN time_slot.opening_hours IS NULL');
        $this->addSql('COMMENT ON COLUMN woopit_integration.product_types IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN restaurant_fulfillment_method.opening_hours IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN restaurant_fulfillment_method.options IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN edifact_message.metadata IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN task_rrule.template IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN task.metadata IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN time_slot.opening_hours IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN invitation.grants IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN delivery_quote.payload IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN pricing_rule_set.options IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN refund.data IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN task_event.data IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN task_event.metadata IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN woopit_integration.product_types IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN store.additional_properties IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN sylius_order_event.data IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN sylius_order_event.metadata IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN reusable_packaging.data IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN sylius_order_loopeat_details.returns IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN sylius_order_loopeat_details.deliver IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN sylius_order_loopeat_details.pickup IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN failure_reason.metadata IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN delivery_import_queue.errors IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN incident_event.metadata IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN restaurant.additional_properties IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN restaurant.stripe_connect_roles IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN restaurant.mercadopago_connect_roles IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN restaurant.stripe_payment_methods IS \'(DC2Type:json_array)\'');
    }
}
