import React from 'react'
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Line } from 'react-chartjs-2';
import moment from 'moment'

const COLORS_SERIES = ['#FF6492', '#141446', '#7A77FF'];
const commonOptions = {
  maintainAspectRatio: false,
};

const renderChart = ({ resultSet, error }) => {
  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (!resultSet) {
    return <Spin />;
  }

  const data = {
    labels: resultSet.categories().map((c) => moment(c.category).format('ll')),
    datasets: resultSet.series().map((s, index) => ({
      label: 'Average order total',
      data: s.series.map((r) => r.value),
      borderColor: COLORS_SERIES[index],
      fill: false,
    })),
  };
  const options = {
    ...commonOptions,
    scales: {
      xAxes: [
        {
          stacked: true,
        },
      ],
    },
  };
  return <Line data={data} options={options} />;

};

const Chart = ({ cubejsApi }) => {

  return (
    <QueryRenderer
      query={{
        "measures": [
          "Order.averageTotal"
        ],
        "timeDimensions": [
          {
            "dimension": "Order.shippingTimeRange",
            "granularity": "day",
            "dateRange": "Last 30 days"
          }
        ],
        "order": {
          "Order.shippingTimeRange": "asc"
        },
        "filters": [
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
        chartType: 'line',
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
