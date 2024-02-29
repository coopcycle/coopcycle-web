import React from 'react'
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import 'chart.js/auto'; // ideally we should only import the component that we need: https://react-chartjs-2.js.org/docs/migration-to-v4/#tree-shaking
import { Bar } from 'react-chartjs-2';
import moment from 'moment'

const COLORS_SERIES = ['#FF6492', '#141446', '#7A77FF'];
const commonOptions = {
  maintainAspectRatio: false,
  scales: {
  }
};

import { getCubeDateRange } from '../utils'

const renderChart = ({ resultSet, error }) => {
  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (!resultSet) {
    return <Spin />;
  }

  const data = {
    labels: resultSet.categories().map((c) => moment(c.x).format('ll')),
    datasets: resultSet.series().map((s, index) => ({
      label: 'Number of tasks',
      data: s.series.map((r) => r.value),
      backgroundColor: COLORS_SERIES[index],
      fill: false,
    })),
  };
  const options = {
    ...commonOptions,
    legend: {
      display: false
    },
    scales: {
      x: { ...commonOptions.scales.x, stacked: true },
    },
  };
  return <Bar data={data} options={options} />;

};

const Chart = ({ cubejsApi, dateRange }) => {

  return (
    <QueryRenderer
      query={{
        "measures": [
          "Task.count"
        ],
        "timeDimensions": [
          {
            "dimension": "Task.intervalEndAt",
            "granularity": "day",
            "dateRange": getCubeDateRange(dateRange)
          }
        ],
        "filters": [
          {
            "member": "Task.status",
            "operator": "notEquals",
            "values": [
              "CANCELLED"
            ]
          }
        ],
        "order": {
          "Task.date": "asc"
        }
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

export default Chart
