<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Organization;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Package as TaskPackage;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\TaskRepository;
use AppBundle\Entity\User;
use AppBundle\Entity\Vehicle;
use AppBundle\Utils\GeoUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

final class DV4CULDataExporter implements DataExporterInterface
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function export(\DateTime $start, \DateTime $end): string
    {
        $spreadsheet = new Spreadsheet();

        // Create worksheets

        $IN0_Sim = $spreadsheet->getSheet(0);
        $IN0_Sim->setTitle('IN0_Sim');

        $IN1_Orders = new Worksheet($spreadsheet, 'IN0_Orders');
        $spreadsheet->addSheet($IN1_Orders, 1);

        $IN2_Hubs = new Worksheet($spreadsheet, 'IN2_Hubs');
        $spreadsheet->addSheet($IN2_Hubs, 2);

        $IN3_TranspTypes = new Worksheet($spreadsheet, 'IN3_TranspTypes');
        $spreadsheet->addSheet($IN3_TranspTypes, 3);

        $IN4_Transp = new Worksheet($spreadsheet, 'IN4_Transp');
        $spreadsheet->addSheet($IN4_Transp, 4);

        // IN0_Sim

        $IN0_Sim->setCellValue('A1', 'idsim');
        $IN0_Sim->setCellValue('B1', 'decsim');

        $IN0_Sim->setCellValue('A2', '23'); // TODO Make this dynamic
        $IN0_Sim->setCellValue('B2', 'First simulation example'); // TODO Make this dynamic
        $IN0_Sim->setCellValue('B3', 'Parameters File Version');
        $IN0_Sim->setCellValue('C3', '1.4');

        $IN0_Sim->setCellValue('B4', 'timestamp');
        $IN0_Sim->setCellValue('C4', date('Y/m/d-H:i:s'));

        // IN1_Orders

        $qb = $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t');

        $qb = TaskRepository::addRangeClause($qb, $start, $end);
        $qb = $qb->join(Address::class, 'a', Expr\Join::WITH, 't.address = a.id');

        $qb
                ->select('t.id')
                ->addSelect('t.type')
                ->addSelect('t.doneAfter AS after')
                ->addSelect('t.doneBefore AS before')
                ->addSelect('t.weight')
                ->addSelect('a.geo')
                ->addSelect('a.postalCode');

        $tasks = $qb->getQuery()->getArrayResult();
        $taskIds = array_map(fn ($task) => $task['id'], $tasks);

        $getPackagesCountByTask = $this->getPackagesCountByTask($taskIds);

        $tasks = array_map(function ($task) use ($getPackagesCountByTask) {

            if (isset($getPackagesCountByTask[$task['id']])) {
                $task['packages'] = $getPackagesCountByTask[$task['id']];
            } else {
                $task['packages'] = 0;
            }

            return $task;

        }, $tasks);

        $IN1_Orders->setCellValueByColumnAndRow(1, 1, 'order_id');
        $IN1_Orders->setCellValueByColumnAndRow(2, 1, 'order_name');
        $IN1_Orders->setCellValueByColumnAndRow(3, 1, 'order_mode');
        $IN1_Orders->setCellValueByColumnAndRow(4, 1, 'order_idhub');
        $IN1_Orders->setCellValueByColumnAndRow(5, 1, 'order_lat');
        $IN1_Orders->setCellValueByColumnAndRow(6, 1, 'order_lon');
        $IN1_Orders->setCellValueByColumnAndRow(7, 1, 'order_cp');
        $IN1_Orders->setCellValueByColumnAndRow(8, 1, 'order_earlytime');
        $IN1_Orders->setCellValueByColumnAndRow(9, 1, 'order_latetime');
        $IN1_Orders->setCellValueByColumnAndRow(10, 1, 'order_servicetime');
        $IN1_Orders->setCellValueByColumnAndRow(11, 1, 'order_items');
        $IN1_Orders->setCellValueByColumnAndRow(12, 1, 'order_weight');
        $IN1_Orders->setCellValueByColumnAndRow(13, 1, 'order_bolactivo');

        $rowIndex = 2;
        foreach ($tasks as $task) {

            $coords = GeoUtils::asGeoCoordinates($task['geo']);

            $IN1_Orders->setCellValueByColumnAndRow(1, $rowIndex, $task['id']);
            $IN1_Orders->setCellValueByColumnAndRow(2, $rowIndex, $task['id']);
            $IN1_Orders->setCellValueByColumnAndRow(3, $rowIndex, $task['type'] === 'PICKUP' ? 'pickup' : 'delivery');
            $IN1_Orders->setCellValueByColumnAndRow(4, $rowIndex, '');
            $IN1_Orders->setCellValueByColumnAndRow(5, $rowIndex, $coords->getLatitude());
            $IN1_Orders->setCellValueByColumnAndRow(6, $rowIndex, $coords->getLongitude());
            $IN1_Orders->setCellValueByColumnAndRow(7, $rowIndex, $task['postalCode']);
            $IN1_Orders->setCellValueByColumnAndRow(8, $rowIndex, $task['after']->format('H:i'));
            $IN1_Orders->setCellValueByColumnAndRow(9, $rowIndex, $task['before']->format('H:i'));
            $IN1_Orders->setCellValueByColumnAndRow(10, $rowIndex, '0');
            $IN1_Orders->setCellValueByColumnAndRow(11, $rowIndex, $task['packages']);
            $IN1_Orders->setCellValueByColumnAndRow(12, $rowIndex, $task['weight'] ? ($task['weight'] / 1000) : '');
            $IN1_Orders->setCellValueByColumnAndRow(13, $rowIndex, '1');

            $rowIndex++;
        }

        // IN2_Hubs

        $IN2_Hubs->setCellValueByColumnAndRow(1, 1, 'hub_id');
        $IN2_Hubs->setCellValueByColumnAndRow(2, 1, 'hub_name');
        $IN2_Hubs->setCellValueByColumnAndRow(3, 1, 'hub_lat');
        $IN2_Hubs->setCellValueByColumnAndRow(4, 1, 'hub_lon');
        $IN2_Hubs->setCellValueByColumnAndRow(5, 1, 'hub_cp');
        $IN2_Hubs->setCellValueByColumnAndRow(6, 1, 'hub_earlytime');
        $IN2_Hubs->setCellValueByColumnAndRow(7, 1, 'hub_latetime');

        $IN2_Hubs->setCellValueByColumnAndRow(1, 2, '1');
        $IN2_Hubs->setCellValueByColumnAndRow(2, 2, 'Rayon9 - Pôle Image');
        $IN2_Hubs->setCellValueByColumnAndRow(3, 2, '50.63317950768193');
        $IN2_Hubs->setCellValueByColumnAndRow(4, 2, '5.587222795744197');
        $IN2_Hubs->setCellValueByColumnAndRow(5, 2, '4020');
        $IN2_Hubs->setCellValueByColumnAndRow(6, 2, '09:00');
        $IN2_Hubs->setCellValueByColumnAndRow(7, 2, '17:30');

        // IN3_TranspTypes

        $vehiclesQb = $this->entityManager->getRepository(Vehicle::class)
            ->createQueryBuilder('v');

        $vehicles = $vehiclesQb->getQuery()->getResult();

        $IN3_TranspTypes->setCellValueByColumnAndRow(1, 1, 'transptype_id');
        $IN3_TranspTypes->setCellValueByColumnAndRow(2, 1, 'transptype_name');
        $IN3_TranspTypes->setCellValueByColumnAndRow(3, 1, 'transptype_capacityitems');
        $IN3_TranspTypes->setCellValueByColumnAndRow(4, 1, 'transptype_capacityweight');
        $IN3_TranspTypes->setCellValueByColumnAndRow(5, 1, 'transptype_speed');
        $IN3_TranspTypes->setCellValueByColumnAndRow(6, 1, 'transptype_fixcost');
        $IN3_TranspTypes->setCellValueByColumnAndRow(7, 1, 'transptype_kmcost');
        $IN3_TranspTypes->setCellValueByColumnAndRow(8, 1, 'transptype_hourcost');
        $IN3_TranspTypes->setCellValueByColumnAndRow(9, 1, 'transptype_co2emissions');

        $rowIndex = 2;
        foreach ($vehicles as $vehicle) {
            $IN3_TranspTypes->setCellValueByColumnAndRow(1, $rowIndex, $vehicle->getId());
            $IN3_TranspTypes->setCellValueByColumnAndRow(2, $rowIndex, $vehicle->getName());
            $IN3_TranspTypes->setCellValueByColumnAndRow(3, $rowIndex, $vehicle->getVolumeUnits());
            $IN3_TranspTypes->setCellValueByColumnAndRow(4, $rowIndex, $vehicle->getMaxWeight());
            $IN3_TranspTypes->setCellValueByColumnAndRow(5, $rowIndex, '0');
            $IN3_TranspTypes->setCellValueByColumnAndRow(6, $rowIndex, '0');
            $IN3_TranspTypes->setCellValueByColumnAndRow(7, $rowIndex, '0');
            $IN3_TranspTypes->setCellValueByColumnAndRow(8, $rowIndex, '0');
            $IN3_TranspTypes->setCellValueByColumnAndRow(9, $rowIndex, '0');

            $rowIndex++;
        }

        // IN4_Transp

        $IN4_Transp->setCellValueByColumnAndRow(1, 1, 'transp_id');
        $IN4_Transp->setCellValueByColumnAndRow(2, 1, 'transp_idtransptype');
        $IN4_Transp->setCellValueByColumnAndRow(3, 1, 'transp_idhub');
        $IN4_Transp->setCellValueByColumnAndRow(4, 1, 'transp_num');

        $rowIndex = 2;
        $transpId = 1;
        foreach ($vehicles as $vehicle) {
            $IN4_Transp->setCellValueByColumnAndRow(1, $rowIndex, $transpId);
            $IN4_Transp->setCellValueByColumnAndRow(2, $rowIndex, $vehicle->getId());
            $IN4_Transp->setCellValueByColumnAndRow(3, $rowIndex, '1');
            $IN4_Transp->setCellValueByColumnAndRow(4, $rowIndex, '1');

            $rowIndex++;
            $transpId++;
        }

        // ---

        $tempnam = tempnam(sys_get_temp_dir(), 'coopcycle_dv4cul_export');

        $writer = new XlsxWriter($spreadsheet);
        $writer->save($tempnam);

        $contents = file_get_contents($tempnam);
        unlink($tempnam);

        return $contents;
    }

    public function getContentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function getFilename(\DateTime $start, \DateTime $end): string
    {
        return sprintf('deliveries-dv4cul-%s-%s.xlsx', $start->format('Y-m-d'), $end->format('Y-m-d'));
    }

    private function getPackagesCountByTask(array $taskIds)
    {
        $qb = $this->entityManager
            ->getRepository(TaskPackage::class)
            ->createQueryBuilder('tp');

        $qb
            ->select('IDENTITY(tp.task) AS task')
            ->addSelect('SUM(tp.quantity) AS count')
            ->andWhere(
                $qb->expr()->in('IDENTITY(tp.task)', $taskIds)
            )
            ->groupBy('task');

        $results = $qb->getQuery()->getArrayResult();

        $packagesByTask = [];
        foreach ($results as $row) {
            $packagesByTask[$row['task']] = $row['count'];
        }

        return $packagesByTask;
    }
}
