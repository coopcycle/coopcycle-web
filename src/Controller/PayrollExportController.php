<?php

namespace AppBundle\Controller;

use AppBundle\Service\Shift\PayrollExporter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class PayrollExportController extends AbstractController
{
    /**
     * Payroll variables (planned/worked/overtime hours, holiday days) as a
     * spreadsheet, for a whole month (?month=YYYY-MM, the default) or a
     * custom range (?after=YYYY-MM-DD&before=YYYY-MM-DD, both inclusive).
     * Lives under /api so the planning SPA can fetch it with its JWT and
     * trigger a client-side download.
     */
    #[Route(path: '/api/payroll_export', name: 'api_payroll_export', methods: ['GET'])]
    public function exportAction(Request $request, PayrollExporter $exporter, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_DISPATCHER');

        $format = $request->query->get('format', 'csv');
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new BadRequestHttpException('Expected format=csv|xlsx');
        }

        $after = $request->query->get('after');
        $before = $request->query->get('before');

        if (null !== $after || null !== $before) {
            if (null === $after || null === $before) {
                throw new BadRequestHttpException('Both after and before are required for a custom range');
            }
            if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $after) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $before)) {
                throw new BadRequestHttpException('Expected after/before as YYYY-MM-DD');
            }

            $start = new \DateTimeImmutable($after);
            $end = (new \DateTimeImmutable($before))->modify('+1 day');
            if ($end <= $start) {
                throw new BadRequestHttpException('before must be on or after after');
            }

            $filename = sprintf('payroll_%s_%s.%s', $after, $before, $format);
        } else {
            $month = $request->query->get('month', date('Y-m'));
            if (1 !== preg_match('/^\d{4}-\d{2}$/', $month)) {
                throw new BadRequestHttpException('Expected month=YYYY-MM');
            }

            $start = new \DateTimeImmutable(sprintf('%s-01', $month));
            $end = $start->modify('+1 month');
            $filename = sprintf('payroll_%s.%s', $month, $format);
        }

        $rows = $exporter->rows($start, $end);

        $header = [
            $translator->trans('payroll.export.username'),
            $translator->trans('payroll.export.name'),
            $translator->trans('payroll.export.planned_hours'),
            $translator->trans('payroll.export.worked_hours'),
            $translator->trans('payroll.export.overtime_hours'),
            $translator->trans('payroll.export.holiday_days'),
        ];

        if ('xlsx' === $format) {
            return $this->xlsxResponse($header, $rows, $filename);
        }

        return $this->csvResponse($header, $rows, $filename);
    }

    private function csvResponse(array $header, array $rows, string $filename): Response
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header);
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return new Response($content, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    private function xlsxResponse(array $header, array $rows, string $filename): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll');

        $sheet->fromArray($header, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        $line = 2;
        foreach ($rows as $row) {
            $sheet->fromArray(array_values($row), null, sprintf('A%d', $line++));
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $handle = fopen('php://temp', 'r+');
        (new Xlsx($spreadsheet))->save($handle);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
