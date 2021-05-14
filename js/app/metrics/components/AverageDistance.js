import React from 'react'
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Bar } from 'react-chartjs-2';
import moment from 'moment'

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

  const data = {
    labels: resultSet.categories().map((c) => moment(c.category).format('ll')),
    datasets: resultSet.series().map((s, index) => ({
      label: 'Average distance',
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
      xAxes: [
        {
          stacked: true,
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
          "TaskList.averageDistance"
        ],
        "timeDimensions": [
          {
            "dimension": "TaskList.date",
            "granularity": "day",
            "dateRange": getCubeDateRange(dateRange)
          }
        ],
        "order": {
          "TaskList.date": "asc"
        },
        "filters": []
      }}
      cubejsApi={cubejsApi}
      resetResultSetOnChange={false}
      render={(props) => renderChart({
        ...props,
        chartType: 'bar',
        pivotConfig: {
          "x": [
            "TaskList.date.day"
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
