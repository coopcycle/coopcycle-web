import React from 'react'
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import { HorizontalBar } from 'react-chartjs-2';

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
      label: 'Number of orders',
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
          ticks: {
            precision: 0,
          },
        },
      ],
    },
  };
  return <HorizontalBar data={data} options={options} />;

};

const Chart = ({ cubejsApi }) => {

  return (
    <QueryRenderer
      query={{
        "measures": [
          "Restaurant.orderCount"
        ],
        "timeDimensions": [],
        "order": {
          "Restaurant.orderCount": "desc"
        },
        "filters": [
          {
            "member": "Order.state",
            "operator": "equals",
            "values": [
              "fulfilled"
            ]
          }
        ],
        "dimensions": [
          "Restaurant.name"
        ],
        "limit": 10
      }}
      cubejsApi={cubejsApi}
      resetResultSetOnChange={false}
      render={(props) => renderChart({
        ...props,
        chartType: 'horizontalBar',
        pivotConfig: {
          "x": [
            "measures"
          ],
          "y": [
            "Restaurant.name"
          ],
          "fillMissingDates": true,
          "joinDateRange": false
        }
      })}
    />
  );
};

export default Chart
