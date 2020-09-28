<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Redis;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200921100753 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private $prefix;

    public function getDescription() : string
    {
        return 'Remove notifications with empty message from Redis';
    }

    private function getEmptyUuids($key)
    {
        $uuids = [];

        $it = NULL;
        /* Don't ever return an empty array until we're done iterating */
        $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        while ($arr_keys = $this->redis->hScan($key, $it)) {
            foreach ($arr_keys as $str_field => $str_value) {
                $data = json_decode($str_value, true);
                $isEmpty = !isset($data['message']) || empty($data['message']);
                if ($isEmpty) {
                    $uuids[] = $str_field;
                }
            }
        }

        return $uuids;
    }

    private function withoutPrefix($key)
    {
        return str_replace("{$this->prefix}:", '', $key);
    }

    public function up(Schema $schema) : void
    {
        $this->redis  = $redis  = $this->container->get(Redis::class);
        $this->prefix = $prefix = $this->container->getParameter('database_name');

        $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);

        $it = NULL;
        while ($keys = $redis->scan($it, sprintf('%s:user:*:notifications_data', $this->prefix))) {
            foreach ($keys as $key) {
                $hashKey = $this->withoutPrefix($key);
                $listKey = str_replace(':notifications_data', ':notifications', $hashKey);
                foreach ($this->getEmptyUuids($hashKey) as $uuid) {
                    $redis->lRem($listKey, $uuid, 0);
                    $redis->hDel($hashKey, $uuid);
                }
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
