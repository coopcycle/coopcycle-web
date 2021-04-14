import ReactDOM from 'react-dom';
import cubejs from '@cubejs-client/core';
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import 'antd/dist/antd.css';
import React from 'react';
import { Bar } from 'react-chartjs-2';
import moment from 'moment'

import MonthPicker from '../widgets/MonthPicker'

const COLORS_SERIES = ['#FF6492', '#141446', '#7A77FF'];
const commonOptions = {
  maintainAspectRatio: false,
};

const rootElement = document.getElementById('cubejs');

if (rootElement) {

  const cubejsApi = cubejs(
    rootElement.dataset.token,
    { apiUrl: rootElement.dataset.apiUrl }
  );

  const renderChart = ({ resultSet, error }) => {
    if (error) {
      return <div>{error.toString()}</div>;
    }

    if (!resultSet) {
      return <Spin />;
    }

    const data = {
      labels: resultSet.categories().map((c) => moment(c.category).format('dddd D')),
      datasets: resultSet.series().map((s, index) => ({
        label: 'Orders', // s.title,
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
      },
    };
    return <Bar data={data} options={options} />;

  };

  const ChartRenderer = () => {
    return (
      <QueryRenderer
        query={{
          "measures": [
            "OrderPerVendor.count"
          ],
          "timeDimensions": [
            {
              "dimension": "OrderPerVendor.shippingTimeRange",
              "granularity": "day",
              "dateRange": JSON.parse(rootElement.dataset.dateRange)
            }
          ],
          "order": {},
          "filters": [
            {
              "member": "OrderPerVendor.state",
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
              "OrderPerVendor.shippingTimeRange.day"
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

  ReactDOM.render(<ChartRenderer />, rootElement);
}

const monthPickerEl = document.querySelector('#month-picker')

const routeName = monthPickerEl.dataset.routeName
const restaurant = monthPickerEl.dataset.restaurant
const defaultValue = monthPickerEl.dataset.defaultValue

new MonthPicker(monthPickerEl, {
  defaultValue,
  onChange: function(date, dateString) {
    window.location.href = window.Routing.generate(routeName, { id: restaurant, month: dateString });
  }
})

$('[data-toggle="tooltip"]').tooltip()
