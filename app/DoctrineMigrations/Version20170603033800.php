<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20170603033800 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $queries = [
            "ALTER TABLE address ALTER id SET DEFAULT nextval('address_id_seq')",
            "ALTER TABLE api_user ALTER id SET DEFAULT nextval('api_user_id_seq')",
            "ALTER TABLE delivery ALTER id SET DEFAULT nextval('delivery_id_seq')",
            "ALTER TABLE product ALTER id SET DEFAULT nextval('product_id_seq')",
            "ALTER TABLE restaurant ALTER id SET DEFAULT nextval('restaurant_id_seq')",
            "ALTER TABLE cuisine ALTER id SET DEFAULT nextval('cuisine_id_seq')",
            "ALTER TABLE order_ ALTER id SET DEFAULT nextval('order__id_seq')",
            "ALTER TABLE order_event ALTER id SET DEFAULT nextval('order_event_id_seq')",
            "ALTER TABLE order_item ALTER id SET DEFAULT nextval('order_item_id_seq')",
            "ALTER TABLE product ALTER id SET DEFAULT nextval('product_id_seq')",
            "SELECT setval('api_user_id_seq', (SELECT MAX(id) FROM api_user))",
            "SELECT setval('restaurant_id_seq', (SELECT MAX(id) FROM restaurant))",
            "SELECT setval('cuisine_id_seq', (SELECT MAX(id) FROM cuisine))",
            "SELECT setval('delivery_id_seq', (SELECT MAX(id) FROM delivery))",
            "SELECT setval('delivery_address_id_seq', (SELECT MAX(id) FROM delivery_address))",
            "SELECT setval('order__id_seq', (SELECT MAX(id) FROM order_))",
            "SELECT setval('order_event_id_seq', (SELECT MAX(id) FROM order_event))",
            "SELECT setval('order_item_id_seq', (SELECT MAX(id) FROM order_item))",
            "SELECT setval('product_id_seq', (SELECT MAX(id) FROM product))",
            "SELECT setval('restaurant_id_seq', (SELECT MAX(id) FROM restaurant))",
        ];

        foreach ($queries as $sql) {
            # code...
            $this->addSql($sql);
        }
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {

    }
}
