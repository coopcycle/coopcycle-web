import React, { useState, useEffect } from 'react';
import moment from 'moment';
import { Button, Skeleton, Tooltip } from 'antd';
import './OrderDetails.scss';
import { money, weight } from '../../utils';
import TaskStatusBadge from '../../../../dashboard/components/TaskStatusBadge';

import store from '../../[id]/redux/incidentStore';
import {
  selectIncident,
  selectLoaded,
  selectOrder,
  selectServiceTaxRate,
} from '../../[id]/redux/incidentSlice';
import { useTranslation } from 'react-i18next';

function formatTime(task) {
  return moment(task.after).format('LL');
}

function _externalLink(link, testID) {
  return (
    <Button
      data-testid={testID}
      onClick={() => window.open(link, '_blank')}
      type="dashed"
      style={{ float: 'right' }}
      shape="circle"
      size="small"
      icon={<i className="fa fa-external-link" style={{ fontSize: '12px' }} />}
    />
  );
}

function Heading({ task, delivery, order }) {
  const { t } = useTranslation();
  const header = (title, btn = null) => (
    <h4 style={{ lineHeight: '24px' }}>
      {title}
      {btn}
    </h4>
  );

  if (order) {
    const orderWithNumber = t('ORDER_WITH_NUMBER', {
      number: order?.number ?? `#${order.id}`,
    });

    const link = window.Routing.generate('admin_order', { id: order.id });
    return header(orderWithNumber, _externalLink(link, 'view-order'));
  }

  if (delivery?.id) {
    const link = window.Routing.generate('admin_delivery', { id: delivery.id });
    return header(`${t('DELIVERY')} #${delivery.id}`, _externalLink(link));
  }

  return header(`${t('TASK')} #${task.id}`);
}

function CustomerName({ customer }) {
  const { t } = useTranslation();
  let customerName = customer?.username;
  if (customer?.fullName != null) {
    customerName = customer.fullName;
  }
  return (
    <p title={customer?.username}>
      {t('CUSTOMER')}
      <span>{customerName}</span>
    </p>
  );
}

function _sumAdjustments(adjustments, adjustmentType): number {
  return (adjustments[adjustmentType] ?? []).reduce(
    (total, adjustment) => total + adjustment.amount,
    0,
  );
}

function Adjustment(adjustments, adjustmentType) {
  const total = _sumAdjustments(adjustments, adjustmentType);
  if (total === 0) {
    return;
  }
  return adjustments[adjustmentType].map(adjustment => (
    <p key={adjustment.id}>
      {adjustment.label}
      <span>{money(adjustment.amount)}</span>
    </p>
  ));
}

function OrderDetails({ order }) {
  const { t } = useTranslation();

  //FIXME: replace with useAppSelector after migrating away from ux-react-controllers
  const serviceTaxRate = selectServiceTaxRate(store.getState());
  // The incident amount is stored the same way the service tax rate is
  const taxIncluded = serviceTaxRate?.includedInPrice ?? true;

  const incidentTotal = _sumAdjustments(order.adjustments, 'incident');
  const incidentTaxTotal = order.incidentTaxTotal ?? 0;
  const incidentTotalTaxIncluded = taxIncluded
    ? incidentTotal
    : incidentTotal + incidentTaxTotal;

  const hasIncidents = order.adjustments.incident?.length > 0;
  // Delivery fees only exist on foodtech orders
  const hasDeliveryFees = order.adjustments.delivery?.length > 0;

  return (
    <>
      <h5>{t('ORDER_DETAILS')}</h5>
      {hasDeliveryFees && (
        <>
          <p>
            {t('SUBTOTAL')}
            <span>{money(order.itemsTotal)}</span>
          </p>
          {Adjustment(order.adjustments, 'delivery')}
        </>
      )}
      {hasIncidents && (
        <>
          <p>
            {t('INCIDENTS_INITIAL_PRICE_TAX_INCLUDED')}
            <span data-testid="order-initial-total">
              {money(order.total - incidentTotalTaxIncluded)}
            </span>
          </p>
          {order.adjustments.incident.map(adjustment => (
            <p key={adjustment.id}>
              {taxIncluded
                ? t('INCIDENTS_INCIDENT_AMOUNT_TAX_INCLUDED')
                : t('INCIDENTS_INCIDENT_AMOUNT_TAX_EXCLUDED')}
              <span>{money(adjustment.amount)}</span>
            </p>
          ))}
          {incidentTaxTotal !== 0 && (
            <p className="text-muted">
              {t('INCIDENTS_INCIDENT_TAX')}
              <span data-testid="order-incident-tax">
                {money(incidentTaxTotal)}
              </span>
            </p>
          )}
        </>
      )}
      <p>
        <strong>
          {hasIncidents
            ? t('INCIDENTS_NEW_TOTAL_TAX_INCLUDED')
            : t('INCIDENTS_TOTAL_TAX_INCLUDED')}
        </strong>
        <span data-testid="order-total">{money(order.total)}</span>
      </p>
      <p className="text-muted">
        {hasIncidents
          ? t('INCIDENTS_NEW_TOTAL_TAX_EXCLUDED')
          : t('INCIDENTS_TOTAL_TAX_EXCLUDED')}
        <span data-testid="order-total-tax-excluded">
          {money(order.total - order.taxTotal)}
        </span>
      </p>
      <hr />
    </>
  );
}

function CustomerDetails({ customer }) {
  const { t } = useTranslation();
  const link = window.Routing.generate('admin_user_edit', {
    username: customer?.username,
  });
  return (
    <>
      <h5 style={{ lineHeight: '24px' }}>
        {t('CUSTOMER_DETAILS')}
        {_externalLink(link)}
      </h5>
      <CustomerName customer={customer} />
      {customer?.email && (
        <p>
          {t('EMAIL')}
          <span>{customer?.email}</span>
        </p>
      )}
      {customer?.telephone && (
        <p>
          {t('PHONE')}
          <span>{customer?.telephone}</span>
        </p>
      )}
    </>
  );
}

function CouponCode({ promotion }) {

  const [coupon, setCoupon] = useState(null)
  const { t } = useTranslation();

  useEffect(() => {
    const httpClient = new window._auth.httpClient();
    httpClient.get(promotion).then(({ response, error }) => {
      setCoupon(response.coupons[0])
    })
  }, [promotion])

  if (!coupon) {
    return (
      <div>
        <Skeleton.Button size="small" />
      </div>
    )
  }

  return (
    <Tooltip title={coupon.used === 0 ? t('COUPON_CODE_NOT_USED') : t('COUPON_CODE_USED', { ago: moment(coupon.updatedAt).fromNow() })}>
      <span className="text-monospace" style={ coupon.used > 0 ? { textDecoration: 'line-through' } : {} }>{coupon.code}</span>
    </Tooltip>
  )
}

function CreditNotes({ incident }) {
  const { t } = useTranslation();

  if (!Array.isArray(incident.metadata)) {
    return null;
  }

  const hasCreditNotes = incident.metadata.reduce((hasCreditNotes, metadata) => {
    if (hasCreditNotes) {
      return true
    }
    return !!metadata.credit_note
  }, false);

  if (!hasCreditNotes) {
    return null
  }

  return (
    <>
      <h5>{t('CREDIT_NOTES')}</h5>
      {incident.metadata.map((metadata, index) => {
        if (metadata.credit_note) {
          return (
            <CouponCode key={`credit-note-${index}`} promotion={metadata.credit_note} />
          )
        }
      })}
      <hr />
    </>
  )
}

function Refunds({ order }) {

  const { t } = useTranslation();
  const [refunds, setRefunds] = useState([])

  useEffect(() => {
    const httpClient = new window._auth.httpClient();
    httpClient.get(order['@id'] + '/refunds').then(({ response, error }) => {
      setRefunds(response['hydra:member'])
    })
  }, [order])

  if (refunds.length === 0) {
    return null
  }

  return (
    <>
      <h5>{t('REFUNDS')}</h5>
      <ul className="list-unstyled">
        {refunds.map((refund, index) => {
          return (
            <li key={`refund-${index}`} className="text-right">
              <span className="text-monospace">{(refund.amount / 100).formatMoney()}</span>
            </li>
          )
        })}
      </ul>
      <hr />
    </>
  )
}

export default function ({ delivery }) {
  delivery = JSON.parse(delivery);
  const { t } = useTranslation();

  //FIXME: replace with useAppSelector after migrating away from ux-react-controllers
  const state = store.getState();
  const loaded = selectLoaded(state);
  const incident = selectIncident(state);
  const order = selectOrder(state);

  const { task } = incident;

  if (!loaded) {
    return null;
  }

  return (
    <div className="order-details-card" data-testid="order-details-card">
      <Heading task={task} delivery={delivery} order={order} />
      <p className="text-muted" data-testid="task-date">
        {t('DATE')}: {formatTime(task)}
      </p>
      <hr />
      {order && <OrderDetails order={order} />}
      {order && <CreditNotes incident={incident} />}
      {order && <Refunds order={order} />}
      <h5>
        <span style={{ textTransform: 'capitalize' }}>
          {task.type.toLowerCase()}
        </span>{' '}
        {t('INCIDENTS_TASK_DETAILS')}
      </h5>
      {task.address?.name ? (
        <p data-testid="task-address-name">{task.address.name}</p>
      ) : null}
      <p data-testid="task-address-street">{task.address?.streetAddress}</p>
      <p data-testid="task-address-telephone">{task.address?.telephone}</p>
      {task.weight ? (
        <p data-testid="task-weight">{weight(task.weight)}</p>
      ) : null}
      <div className="mt-3">{<TaskStatusBadge task={task} />}</div>
      <hr />
      {order?.customer && <CustomerDetails customer={order.customer} />}
    </div>
  );
}
