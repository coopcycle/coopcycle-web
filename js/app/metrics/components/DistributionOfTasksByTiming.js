import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import 'antd/dist/antd.css';
import React from 'react';
import { Bar } from 'react-chartjs-2';
import {getCubeDateRange} from "../utils";
// import {formatDayDimension, getBackgroundColor, getLabel} from "../tasksGraphUtils";
import { useDeepCompareMemo } from 'use-deep-compare'

const COLORS_SERIES = [
  '#5b8ff9',
  '#5ad8a6',
  '#5e7092',
  '#f6bd18',
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
  },
  scales: {
    x: {
      ticks: {
        autoSkip: true,
        maxRotation: 0,
        padding: 12,
        minRotation: 0,
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
    labels: resultSet.categories().map((c) => c.x),
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
        "dimensions": [
          "Task.intervalDiff"
        ],
        "timeDimensions": [],
        "order": [
          [
            "Task.intervalDiff",
            "asc"
          ]
        ],
        "measures": [
          "Task.countDone"
        ],
        "segments": [
          `Task.${taskType.toLowerCase()}`
        ],
        "filters": [
          {
            "member": "Task.intervalEndAt",
            "operator": "inDateRange",
            "values": getCubeDateRange(dateRange)
          }
        ]
      }}
      cubejsApi={cubejsApi}
      resetResultSetOnChange={false}
      render={(props) => renderChart({
        ...props,
        chartType: 'bar',
        pivotConfig: {
          "x": [
            "Task.intervalDiff"
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
