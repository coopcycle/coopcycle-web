import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import 'antd/dist/antd.css';
import React from 'react';
import { Bar } from 'react-chartjs-2';
import {getCubeDateRange} from "../utils";
import { useDeepCompareMemo } from 'use-deep-compare';
import { formatDayDimension } from '../tasksGraphUtils'
// import {formatDayDimension, getBackgroundColor, getLabel} from "../tasksGraphUtils";

const COLORS_SERIES = [
  '#5b8ff9',
  '#5ad8a6',
  '#f6bd18',
  '#5e7092',
  '#6f5efa',
  '#6ec8ec',
  '#945fb9',
  '#ff9845',
  '#299796',
  '#fe99c3',
];

const commonOptions = {
  maintainAspectRatio: false,
  interaction: {
    intersect: false,
  },
  plugins: {
    legend: {
      position: 'bottom',
    },
    tooltip: {
      callbacks: {
        label: function(context) {
          let label = context.dataset.label || '';

          if (label) {
            label += ': ';
          }
          if (context.parsed.y !== null) {
            label += `${context.parsed.y}%`;
          }
          return label;
        }
      },
    },
  },
  scales: {
    x: {
      ticks: {
        autoSkip: true,
      },
    },
  },
};

const BarChartRenderer = ({ resultSet, pivotConfig }) => {
  const datasets = useDeepCompareMemo(
    () =>
      resultSet.series().map((s, index) => ({
        label: s.title,
        data: s.series.map((r) => r.value),
        backgroundColor: COLORS_SERIES[index],
        fill: false,
      })),
    [resultSet]
  );
  const data = {
    labels: resultSet.categories().map((c) => formatDayDimension(c.category)),
    datasets,
  };
  const stacked = !(pivotConfig.x || []).includes('measures');
  const options = {
    ...commonOptions,
    scales: {
      x: { ...commonOptions.scales.x, stacked },
      y: { ...commonOptions.scales.y, stacked },
    },
  };
  return <Bar type="bar" data={data} options={options} />;
};

const renderChart = ({ resultSet, error, pivotConfig }) => {
  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (!resultSet) {
    return <Spin />;
  }

  return <BarChartRenderer resultSet={resultSet} pivotConfig={pivotConfig} />;

};

const ChartRenderer = ({ cubejsApi, dateRange, taskType }) => {
  return (
    <QueryRenderer
      query={{
        "measures": [
          "Task.percentageTooEarly",
          "Task.percentageOnTime",
          "Task.percentageTooLate"
        ],
        "timeDimensions": [
          {
            "dimension": "Task.intervalEndAt",
            "granularity": "day",
            "dateRange": getCubeDateRange(dateRange)
          }
        ],
        "order": {
          "Task.intervalEndAt": "asc"
        },
        "filters": [],
        "dimensions": [],
        "limit": 5000,
        "segments": [
          `Task.${taskType.toLowerCase()}`
        ]
      }}
      cubejsApi={cubejsApi}
      resetResultSetOnChange={false}
      render={(props) => renderChart({
        ...props,
        chartType: 'bar',
        pivotConfig: {
          "x": [
            "Task.intervalEndAt.day"
          ],
          "y": [
            "measures"
          ],
          "fillMissingDates": true,
          "joinDateRange": false
        }
      })}
    />
  );
};

export default ChartRenderer
