import React from 'react';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  LineElement,
  PointElement,
  Filler,
  Tooltip,
  ChartOptions,
  TooltipItem,
} from 'chart.js';
import { Line } from 'react-chartjs-2';
import { useTranslation } from 'react-i18next';
import { DemandBucket } from '../../../api/types';

ChartJS.register(
  CategoryScale,
  LinearScale,
  LineElement,
  PointElement,
  Filler,
  Tooltip,
);

const DEMAND_COLOR = '#1677ff';
const COVERAGE_COLOR = '#52c41a';

type Props = {
  buckets: DemandBucket[];
};

/**
 * The demand-curve overlay: predicted demand (filled area) and the coverage the
 * generated shifts provide (stepped line), on one courier-unit axis. Where the
 * step line dips under the area, the day is understaffed — visible at a glance.
 */
export default function DemandCoverageChart({ buckets }: Props) {
  const { t } = useTranslation();

  const labels = buckets.map(b => `${b.hour}h`);

  const data = {
    labels,
    datasets: [
      {
        label: t('SHIFT_PLANNING_DEMAND'),
        data: buckets.map(b => b.demand),
        borderColor: DEMAND_COLOR,
        backgroundColor: 'rgba(22, 119, 255, 0.15)',
        fill: true,
        tension: 0.35,
        pointRadius: 0,
        borderWidth: 1.5,
      },
      {
        label: t('SHIFT_PLANNING_COVERAGE'),
        data: buckets.map(b => b.coverage),
        borderColor: COVERAGE_COLOR,
        backgroundColor: COVERAGE_COLOR,
        stepped: 'before' as const,
        fill: false,
        pointRadius: 0,
        borderWidth: 2,
      },
    ],
  };

  const options: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (ctx: TooltipItem<'line'>) =>
            `${ctx.dataset.label}: ${(ctx.parsed.y ?? 0).toFixed(2)}`,
        },
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { precision: 0, stepSize: 1 },
        grid: { color: 'rgba(0,0,0,0.05)' },
      },
      x: {
        grid: { display: false },
        ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
      },
    },
  };

  return <Line data={data} options={options} />;
}
