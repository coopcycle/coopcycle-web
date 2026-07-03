import React from 'react'
import { useCubeQuery } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { Pie } from 'react-chartjs-2';
ChartJS.register(ArcElement, Tooltip, Legend)
import chroma from 'chroma-js'

const commonOptions = {
  maintainAspectRatio: false,
};

import { getCubeDateRange } from '../utils'

const Chart = ({ dateRange }) => {

  const { resultSet, isLoading, error } = useCubeQuery({
    "dimensions": [
      "OrderExport.restaurant"
    ],
    "measures": [
      "OrderExport.count"
    ],
    "filters": [
      {
        // FIXME
        // Naming things is hard...
        // This is all we have to distinguish foodtech/last-mile orders
        "member": "OrderExport.applied_billing",
        "operator": "equals",
        "values": [
          "FOODTECH"
        ]
      }
    ],
    "timeDimensions": [
      {
        "dimension": "OrderExport.completed_at",
        "dateRange": getCubeDateRange(dateRange)
      }
    ],
    "order": {
      "OrderExport.count": "desc"
    },
    "limit": 10
  });

  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (isLoading || !resultSet) {
    return <Spin />;
  }

  const data = {
    labels: resultSet.categories().map((c) => c.x),
    datasets: resultSet.series().map((s) => {

      const colorScale = chroma.scale(['#10ac84', '#feca57']).domain([ 0, s.series.length - 1 ])
      const colors = s.series.map((r, i) => colorScale(i).hex())

      return {
        label: 'Number of orders',
        data: s.series.map((r) => r.value),
        backgroundColor: colors,
        hoverBackgroundColor: colors,
      }
    }),
  };

  const options = {
    ...commonOptions,
    plugins: {
      legend: {
        position: 'left'
      },
    },
  };

  return <Pie data={data} options={options} />;
};

export default Chart
