import React from 'react';
import { useCubeQuery } from '@cubejs-client/react';
import { Spin } from 'antd';
import { getCubeDateRange } from '../utils'

import { ResponsiveHeatMap } from '@nivo/heatmap'

import dayjs from 'dayjs'
import localeData from 'dayjs/plugin/localeData'
import isoWeek from 'dayjs/plugin/isoWeek'

dayjs.extend(localeData);
dayjs.extend(isoWeek);

// https://echarts.apache.org/examples/en/editor.html?c=heatmap-cartesian
// https://nivo.rocks/heatmap/
// https://www.william-troup.com/heat-js/documentation/index.html
// https://cal-heatmap.com/

let getHours = () => {
  let hours = []
  let s = dayjs().startOf('day')
  let e = dayjs().endOf('day')

  for (var m = dayjs(s); m.isBefore(e); m = m.add(1, 'hour')) {
      hours.push(m.format('HH'));
  }
  return hours;
}

const hours = getHours()
const weekdays = [1, 2, 3, 4, 5, 6, 7].map(d => dayjs().isoWeekday(d).format('dd'));

const App = ({ dateRange, fixedCosts }) => {

  const { resultSet, isLoading, error } = useCubeQuery({
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
  });

  if (isLoading) {
    return <Spin />;
  }

  if (error) {
    return <div>{ error.toString() }</div>;
  }

  if (!resultSet) {
    return null;
  }

  const pivotResult = resultSet.chartPivot({
    x: [],
    y: ['OrderExport.day_of_week', 'OrderExport.hour']
  })

  // Convert monthly fixed costs to weekly
  const weeklyFixedCosts = ((fixedCosts * 12) / 365) * 7

  const totalIncome = resultSet.rawData().reduce((total, row) => total + parseInt(row['OrderExport.income'], 10), 0)

  // https://apexcharts.com/docs/chart-types/heatmap-chart/
  const series = weekdays.map((wd, index) => ({
    id: wd,
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

      return {
        x: h,
        y: income - fixedCostsDecrement,
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

  /*
  // Add totals for each day of week
  series.forEach((s, index) => {

    const dayOfWeekTotal = s.data.reduce((total, d) => total + d.y, 0)

    series[index].data.push({
      x: 'Total',
      y: dayOfWeekTotal
    })
  })

  // Add totals for each hour
  // https://stackoverflow.com/a/54884962
  series.push({
    name: 'Total',
    data: Object.values([].concat(...series.map(s => s.data)).reduce((acc, { x, y }) => {
      acc[x] = { x, y: (acc[x] ? acc[x].y : 0) + y };
      return acc;
    }, {}))
  })
  */

  const min = Math.min(...values)
  const max = Math.max(...values)


  // https://nivo.rocks/heatmap/
  return (
    <div style={{ height: '400px' }}>
      <ResponsiveHeatMap
        data={series}
        margin={{ top: 60, right: 90, bottom: 60, left: 90 }}
        valueFormat=">-.2s"
        axisTop={{
          tickSize: 5,
          tickPadding: 5,
          // tickRotation: -90,
          legend: '',
          legendOffset: 46,
          truncateTickAt: 0
        }}
        axisRight={{
          tickSize: 5,
          tickPadding: 5,
          tickRotation: 0,
          legend: 'Day of week',
          legendPosition: 'middle',
          legendOffset: 70,
          truncateTickAt: 0
        }}
        axisLeft={{
          tickSize: 5,
          tickPadding: 5,
          tickRotation: 0,
          legend: 'Day of week',
          legendPosition: 'middle',
          legendOffset: -72,
          truncateTickAt: 0
        }}
        colors={{
          type: 'diverging',
          scheme: min < 0 ? 'red_yellow_green' : 'yellow_green',
          divergeAt: max < 0 ? 1 : 0.5,
        }}
        emptyColor="#FFFFFF"
        legends={[
          {
            anchor: 'bottom',
            translateX: 0,
            translateY: 30,
            length: 400,
            thickness: 8,
            direction: 'row',
            tickPosition: 'after',
            tickSize: 3,
            tickSpacing: 4,
            tickOverlap: false,
            tickFormat: '>-.2s',
            title: 'Value →',
            titleAlign: 'start',
            titleOffset: 4
          }
        ]}
      />
    </div>
  )

};

export default App;
