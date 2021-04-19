import React from 'react'
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Bar } from 'react-chartjs-2';

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
    labels: resultSet.categories().map((c) => c.category),
    datasets: resultSet.series().map((s, index) => ({
      label: s.title,
      data: s.series.map((r) => r.value),
      backgroundColor: COLORS_SERIES[index],
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
      yAxes: [
        {
          stacked: true,
          ticks: {
            precision: 0,
          },
        },
      ],
    },
  };
  return <Bar data={data} options={options} />;

};

const Chart = ({ cubejsApi }) => {

  return (
    <QueryRenderer
      query={{
        "dimensions": [
          "Order.hourRange"
        ],
        "timeDimensions": [
          {
            "dimension": "Order.shippingTimeRange",
            "dateRange": "Last 30 days"
          }
        ],
        "order": [
          [
            "Order.hourRange",
            "asc"
          ]
        ],
        "measures": [
          "Order.count"
        ],
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
        chartType: 'bar',
        pivotConfig: {
          "x": [
            "Order.hourRange"
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
