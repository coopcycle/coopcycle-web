import React from 'react';
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import { getCubeDateRange } from '../utils'

import Chart from 'react-apexcharts'
import dayjs from 'dayjs'
import localeData from 'dayjs/plugin/localeData'
import isoWeek from 'dayjs/plugin/isoWeek'
import chroma from 'chroma-js'
import numbro from 'numbro'

dayjs.extend(localeData);
dayjs.extend(isoWeek);

let getHours = () => {
  let hours = []
  let s = dayjs().startOf('day')
  let e = dayjs().endOf('day')

  for (var m = dayjs(s); m.isBefore(e); m = m.add(1, 'hour')) {
      hours.push(m.format('H'));
  }
  return hours;
}

const hours = getHours()
const weekdays = [1, 2, 3, 4, 5, 6, 7].map(d => dayjs().isoWeekday(d).format('dd'));

const options = {
  chart: {
    type: 'heatmap',
    redrawOnParentResize: true,
    width: '100%',
    height: '100%',
    toolbar: {
      show: false,
    }
  },
  stroke: {
    width: 0
  },
  legend: {
      show: false,
  },
  // https://apexcharts.com/docs/options/plotoptions/heatmap/
  plotOptions: {
    heatmap: {
      radius: 0,
      enableShades: false,
    }
  },
  dataLabels: {
    enabled: true,
    style: {
      colors: ['#fff'],
      fontSize: '10px',
    },
    formatter: function (val) {

      if (val === null) {
        return ''
      }

      // return `${val} €`

      return numbro(val).formatCurrency({
        spaceSeparated: true,
        average: val >= 1000,
        mantissa: val >= 1000 ? 2 : 0,
      })
    },
  },
  tooltip: {
    enabled: true,
    custom: function({ series, seriesIndex, dataPointIndex }) {

      const weekday = weekdays[seriesIndex]
      const hour = dayjs().hour(hours[dataPointIndex]).format('HH A')

      const val = series[seriesIndex][dataPointIndex]

      if (val === null) {
        return '<div class="arrow_box" style="padding: 10px;">' +
        '<span>No data</span>' +
        '</div>'
      }

      return `<div class="arrow_box" style="padding: 8px; text-align: left;">
        <strong>${weekday} ${hour}</strong>
        <br>
        <span>Income: ${val} €</span>
        </div>`
    }
  },
  colors: ["#008FFB"],
  xaxis: {
    type: 'category',
    categories: hours.map(h => dayjs().hour(h).format('HH A'))
  },
};

const range = (start, end, step = 1) => {
  let output = [];
  if (typeof end === 'undefined') {
    end = start;
    start = 0;
  }
  for (let i = start; i < end; i += step) {
    output.push(i);
  }
  return output;
};

function createOptions(positiveColorScale, negativeColorScale, min, max) {

  const ranges = range(min, max, 10).map(v => {
    return {
      from: v,
      to: v + 10,
      color: v < 0 ? negativeColorScale(v).hex() : positiveColorScale(v).hex()
    }
  })

  // https://www.w3schools.com/colors/colors_shades.asp
  ranges.push({
    from: 0,
    to: 0,
    color: '#F8F8F8'
  })

  return {
    ...options,
    plotOptions: {
      heatmap: {
        ...options.plotOptions.heatmap,
        colorScale: {
          ranges,
          min,
          max
        }
      }
    }
  }
}

const renderChart = ({ resultSet, error, pivotConfig, fixedCosts }) => {

  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (!resultSet) {
    return <Spin />;
  }

  const pivotResult = resultSet.chartPivot(pivotConfig)

  // Convert monthly fixed costs to weekly
  const yearlyFixedCosts = fixedCosts * 12
  const dailyFixedCosts = yearlyFixedCosts / 365
  const weeklyFixedCosts = dailyFixedCosts * 7

  const totalIncome = resultSet.rawData().reduce((total, row) => total + parseInt(row['OrderExport.income'], 10), 0)

  // https://apexcharts.com/docs/chart-types/heatmap-chart/
  const series = weekdays.map((wd, index) => ({
    name: wd,
    data: hours.map(h => {

      const income = pivotResult[0][`${index + 1},${h},OrderExport.income`] || null

      if (income === null) {

        return {
          x: h,
          y: null
        }
      }

      const incomePercentage = income / totalIncome
      const fixedCostsDecrement = weeklyFixedCosts * incomePercentage
      const incomeMinusFixedCosts = Math.round(income - fixedCostsDecrement)

      return {
        x: h,
        y: incomeMinusFixedCosts === 0 ? -1 : incomeMinusFixedCosts
      }
    })
  }));

  const values = series.reduce((values, item) => {
    return item.data.reduce((values, item2) => {
      if (item2.y !== null) {
        values.push(item2.y)
      }
      return values
    }, values)
  }, [])

  const min = Math.min(...values)
  const max = Math.max(...values)

  const orange = '#f6e58d'
  const green = '#6ab04c'

  const red = '#ff7979'
  const darkRed = '#eb4d4b'

  const positiveColorScale = chroma.scale([orange, green]).domain([ min, max ])
  const negativeColorScale = chroma.scale([darkRed, red]).domain([ min, 0 ])

  return (
    <Chart
      options={createOptions(positiveColorScale, negativeColorScale, min, max)}
      series={series}
      type="heatmap"
      width="100%"
      // height="600px"
    />)

};

const ChartRenderer = ({ cubejsApi, dateRange, fixedCosts = 0 }) => {

  return (
    <QueryRenderer
      query={{
        "timeDimensions": [
          {
            "dimension": "OrderExport.completed_at",
            "dateRange": getCubeDateRange(dateRange)
          }
        ],
        "dimensions": [
          "OrderExport.day_of_week",
          "OrderExport.hour"
        ],
        "measures": [
          "OrderExport.income"
        ],
        "order": [
          [
            "OrderExport.day_of_week",
            "asc"
          ],
          [
            "OrderExport.hour",
            "asc"
          ]
        ]
      }}
      cubejsApi={cubejsApi}
      resetResultSetOnChange={false}
      render={(props) => renderChart({
        ...props,
        chartType: 'bar',
        pivotConfig: {
          x: [],
          y: ['OrderExport.day_of_week', 'OrderExport.hour']
        },
        fixedCosts,
      })}
    />
  );
};

export default ChartRenderer;
