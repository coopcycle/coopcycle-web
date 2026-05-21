import { createRoot } from 'react-dom/client'
import { Select } from 'antd'
import debounce from 'lodash/debounce'
import cubejs from '@cubejs-client/core';
import { QueryRenderer } from '@cubejs-client/react';
import { Spin, Popover, Button } from 'antd';
import React, { useImperativeHandle, createRef, forwardRef, useState, useCallback } from 'react';
import { Chart as ChartJS, CategoryScale, LinearScale, LineElement, PointElement, Tooltip, Legend } from 'chart.js'
import { Line } from 'react-chartjs-2';
ChartJS.register(CategoryScale, LinearScale, LineElement, PointElement, Tooltip, Legend)
import moment from 'moment'
import { ThreeDots } from 'react-loader-spinner'
import { parsePhoneNumberFromString } from 'libphonenumber-js';
import { phoneNumber as formatPhoneNumber } from '../utils/format';

import dayjs from 'dayjs'
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';

import { useTranslation } from 'react-i18next';

import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(relativeTime);

const locale = $('html').attr('lang')

dayjs.locale(locale);

const COLORS_SERIES = ['#FF6492', '#141446', '#7A77FF'];
const commonOptions = {
  maintainAspectRatio: false,
};

const rootElement = document.getElementById('cubejs');

if (rootElement) {

  const cubeApi = cubejs(
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
      labels: resultSet.categories().map((c) => moment(c.x).format('L')),
      datasets: resultSet.series().map((s, index) => ({
        label: s.title,
        data: s.series.map((r) => r.value),
        borderColor: COLORS_SERIES[index],
        fill: false,
      })),
    };
    const options = { ...commonOptions };
    return <Line data={data} options={options} />;

  };

  const ChartRenderer = () => {
    return (
      <QueryRenderer
        query={{
          "measures": [
            "PlatformFee.totalAmount"
          ],
          "timeDimensions": [
            {
              "dimension": "Order.shippingTimeRange",
              "granularity": "day",
              "dateRange": "last 90 days"
            }
          ],
          "order": {},
          "dimensions": [],
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
        cubeApi={cubeApi}
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

  createRoot(rootElement).render(<ChartRenderer />);
}

const httpClient = new window._auth.httpClient();

const CustomerPopoverContent = ({ isLoading, customer, customerInsights, link }) => {

  const { t } = useTranslation();

  if (isLoading) {
    return (
      <ThreeDots wrapperClass="justify-content-center" width="24" height="24" />
    )
  }

  return (
    <div>
      <div className="text-right"><i className="fa fa-info-circle"></i></div>
      <ul className="list-unstyled">
        <li><a href={`mailto:${customer.email}`}>{customer.email}</a></li>
        <li>{formatPhoneNumber(customer.phoneNumber)}</li>
      </ul>
      <div className="text-right"><i className="fa fa-bar-chart"></i></div>
      <ul className="list-unstyled">
        <li>{ t('CUSTOMER_INSIGHTS.NUMBER_OF_ORDERS', { count: customerInsights.numberOfOrders }) }</li>
        { customerInsights.firstOrderedAt ? (
        <li>{ t('CUSTOMER_INSIGHTS.FIRST_ORDER', { date: dayjs().to(dayjs(customerInsights.firstOrderedAt)) }) }</li>) : null }
        { customerInsights.lastOrderedAt ? (
        <li>{ t('CUSTOMER_INSIGHTS.LAST_ORDER', { date: dayjs().to(dayjs(customerInsights.lastOrderedAt)) }) }</li>) : null }
        { customerInsights.favoriteRestaurant ? (
        <li>{ t('CUSTOMER_INSIGHTS.FAVORITE_RESTAURANT', { name: customerInsights.favoriteRestaurant.name }) }</li>) : null }
      </ul>
      {link !== '#' ? (
        <div className="text-right">
          <a href={ link }>→ {t('SEE_ALL')}</a>
        </div>
      ) : null }
    </div>
  )
}

const CustomerPopover = forwardRef(({ iri, link }, ref) => {

  const [isOpen, setIsOpen] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [customer, setCustomer] = useState(null)
  const [customerInsights, setCustomerInsights] = useState(null)

  useImperativeHandle(ref, () => ({
    async open() {

      setIsLoading(true)
      setIsOpen(true)

      // TODO Make requests in parallel
      const { response: customer } = await httpClient.get(iri);
      const { response: insights } = await httpClient.get(`${iri}/insights`);

      let favoriteRestaurant = null
      if (insights.favoriteRestaurant) {
        const { response: fav } = await httpClient.get(insights.favoriteRestaurant)
        favoriteRestaurant = fav
      }
      setCustomer(customer)
      setCustomerInsights({
        ...insights,
        favoriteRestaurant
      })
      setIsLoading(false)

    },
  }), []);

  return (
    <Popover
      content={
        <CustomerPopoverContent
          isLoading={isLoading}
          customer={customer}
          customerInsights={customerInsights}
          link={link} />
      }
      trigger="click"
      open={isOpen}
      onOpenChange={setIsOpen}
      placement="right">
    </Popover>
  )
})

document.querySelectorAll('[data-customer]').forEach(customerEl => {

  const container = document.createElement("span");

  customerEl.appendChild(container)

  const ref = createRef()

  createRoot(container).render(<CustomerPopover ref={ref} iri={ customerEl.dataset.customer } link={customerEl.getAttribute('href')} />)

  customerEl.addEventListener('click', (e) => {
    e.preventDefault();
    ref.current.open();
  })
})

const OrderSearch = ({ searchUrl, placeholder }) => {
  const { t } = useTranslation()
  const [options, setOptions] = useState([])
  const [fetching, setFetching] = useState(false)
  const [query, setQuery] = useState('')

  const fetchOptions = useCallback(
    debounce(async (q) => {
      setQuery(q)
      if (!q) { setOptions([]); return }
      setFetching(true)
      const res = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`)
      const data = await res.json()
      setOptions(data.map(order => ({ value: order.path, order })))
      setFetching(false)
    }, 300),
    [searchUrl]
  )

  return (
    <Select
      showSearch
      filterOption={false}
      onSearch={fetchOptions}
      options={options}
      loading={fetching}
      notFoundContent={null}
      placeholder={placeholder}
      onChange={(path) => { window.location.href = path }}
      optionRender={({ data: { order } }) => (
        <div>
          <div style={{ display: 'flex', justifyContent: 'space-between' }}>
            <strong>{order.number}</strong>
            {order.date && <span style={{ color: '#aaa', fontSize: '0.85em' }}>{order.date}</span>}
          </div>
          {(order.fullName || order.email) && (
            <div style={{ fontSize: '0.85em', color: '#888' }}>
              {order.fullName && <span>{order.fullName}</span>}
              {order.fullName && order.email && <span> · </span>}
              {order.email && <span>{order.email}</span>}
            </div>
          )}
        </div>
      )}
      popupRender={(menu) => (
        <>
          {menu}
          {query && (
            <div style={{ borderTop: '1px solid #f0f0f0', padding: '6px 12px' }}>
              <a href={window.Routing.generate('admin_orders', { q: query })}>
                {t('SHOW_MORE_RESULTS', { query })}
              </a>
            </div>
          )}
        </>
      )}
      style={{ width: '100%' }}
    />
  )
}

const searchEl = document.querySelector('#orders-search')
if (searchEl) {
  createRoot(searchEl).render(
    <OrderSearch
      searchUrl={searchEl.dataset.url}
      placeholder={searchEl.dataset.placeholder}
    />
  )
}
