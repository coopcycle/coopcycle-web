import React from 'react'
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Bar } from 'react-chartjs-2';
import moment from 'moment'
import _ from 'lodash'

const COLORS_SERIES = ['#FF6492', '#141446', '#7A77FF'];
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

  // Make it work for days with zero orders
  const labels = []
  for (let d = 1; d <= 7; d++) {
    labels.push(moment().isoWeekday(d).format('dddd'))
  }

  const data = {
    labels,
    datasets: resultSet.series().map((s, index) => ({
      label: s.title,
      data: labels.map((label, index) => {
        const isoWeekday = index + 1
        const r = _.find(s.series, s => parseInt(s.category, 10) === isoWeekday)
        return r ? r.value : 0
      }),
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
            "dateRange": getCubeDateRange(dateRange)
          }
        ],
        "order": [
          [
            "Order.dayOfWeek",
            "asc"
          ]
        ],
        "dimensions": [
          "Order.dayOfWeek"
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
            "Order.dayOfWeek"
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
