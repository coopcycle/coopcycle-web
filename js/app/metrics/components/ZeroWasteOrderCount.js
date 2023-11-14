import React from 'react';
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Bar } from 'react-chartjs-2';
import moment from 'moment'

const commonOptions = {
  maintainAspectRatio: false,
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
  datasets: resultSet.series().map((s) => ({
    label: s.title,
    data: s.series.map((r) => r.value),
    backgroundColor: '#FF6492',
    fill: false,
  })),
};
const options = {
  ...commonOptions,
  plugins: {
    legend: {
      display: false
    },
  },
  scales: {
    x: {
      stacked: true,
    },
    y: {
      stacked: true,
      ticks: {
        precision: 0,
      },
    },
  },
};
return <Bar data={data} options={options} />;

};

const Chart = ({ cubejsApi, dateRange }) => {
  return (
    <QueryRenderer
      query={{
        "measures": [
          "Order.count"
        ],
        "timeDimensions": [
          {
            "dimension": "Order.shippingTimeRange",
            "granularity": "day",
            "dateRange": getCubeDateRange(dateRange),
          }
        ],
        "order": {
          "Order.shippingTimeRange": "asc"
        },
        "filters": [
          {
            "member": "Order.reusablePackagingEnabled",
            "operator": "equals",
            "values": [
              "1"
            ]
          },
          {
            "member": "Order.state",
            "operator": "equals",
            "values": [
              "fulfilled"
            ]
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
            "Order.shippingTimeRange.day"
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
