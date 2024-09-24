import React from 'react';
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Bar } from 'react-chartjs-2';
import dayjs from 'dayjs'
import localeData from 'dayjs/plugin/localeData'

import { getCubeDateRange } from '../utils'

dayjs.extend(localeData);

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
  responsive: true,
  interaction: {
    intersect: false,
  },
  plugins: {
    legend: {
      display: false,
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

const BarChartRenderer = ({ resultSet, pivotConfig, fixedCosts }) => {

  // Convert monthly fixed costs to weekly
  const yearlyFixedCosts = fixedCosts * 12
  const dailyFixedCosts = yearlyFixedCosts / 365
  const weeklyFixedCosts = dailyFixedCosts * 7

  const datasets = resultSet.series(pivotConfig).map((s, index) => ({
    label: s.title,
    data: s.series.map((r) => (r.value - weeklyFixedCosts)),
    yValues: [s.key],
    backgroundColor: COLORS_SERIES[index],
    fill: false,
  }));

  const data = {
    labels: resultSet.categories(pivotConfig).map((c) => {
      return dayjs(c.x).format('DD MMM')
    }),
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

  return (
    <Bar
      type="bar"
      data={data}
      options={options}/>
  );
};

const renderChart = ({ resultSet, error, pivotConfig, fixedCosts }) => {
  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (!resultSet) {
    return <Spin />;
  }

  return (
    <BarChartRenderer
      resultSet={resultSet}
      pivotConfig={pivotConfig}
      fixedCosts={fixedCosts}
    />
);

};

const ChartRenderer = ({ cubejsApi, dateRange, fixedCosts = 0 }) => {

  return (
    <QueryRenderer
      query={{
        "measures": [
          "OrderExport.income"
        ],
        "timeDimensions": [
          {
            "dimension": "OrderExport.completed_at",
            "granularity": "week",
            "dateRange": getCubeDateRange(dateRange)
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
            "OrderExport.completed_at.week"
          ],
          "y": [
            "measures"
          ],
          "fillMissingDates": true,
          "joinDateRange": false
        },
        fixedCosts
      })}
    />
  );
};

export default ChartRenderer
