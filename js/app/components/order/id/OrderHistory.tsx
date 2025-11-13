import React, { useMemo } from 'react';
import { Spin, Timeline } from 'antd';
import moment from 'moment';
import {
  IncidentEvent,
  Order,
  OrderEvent,
  TaskEvent,
  TaskPayload,
} from '../../../api/types';
import { useTranslation } from 'react-i18next';
import { HistoryEvent, useOrderHistory } from './hooks/useOrderHistory';
import { IncidentEventView } from '../../../admin/incidents/[id]/components/IncidentEventView';
import { TotalPrice } from '../../delivery-form/components/order/TotalPrice';

const itemColor = (event: HistoryEvent) => {
  switch (event.type) {
    case 'task:done':
      return 'green';
    case 'task:failed':
    case 'task:cancelled':
      return 'red';
    case 'task:rescheduled':
    case 'task:incident-reported':
      return 'orange';
    default:
      return 'blue';
  }
};

const OrderEventDetails = ({ event }: { event: OrderEvent }) => {
  if (!event.data) {
    return null;
  }

  return (
    <>
      {typeof event.data.newState === 'string' ? (
        <p>{event.data.newState}</p>
      ) : null}
      {typeof event.data.new_total === 'number' &&
      typeof event.data.old_total === 'number' ? (
        <div>
          <TotalPrice
            overridePrice={true}
            total={event.data.old_total}
            taxTotal={event.data.old_tax_total}
          />
          <TotalPrice
            overridePrice={false}
            total={event.data.new_total}
            taxTotal={event.data.new_tax_total}
          />
        </div>
      ) : null}
    </>
  );
};

const TaskEventDetails = ({ event }: { event: TaskEvent }) => {
  const { t } = useTranslation();

  if (!event.data) {
    return null;
  }

  return (
    <>
      {event.data.incident_id ? (
        <a
          href={window.Routing.generate('admin_incident', {
            id: event.data.incident_id,
          })}
          target="_blank"
          rel="noopener noreferrer">
          {t('INCIDENT_WITH_ID', { id: event.data.incident_id })}
        </a>
      ) : null}
      {event.data.notes && typeof event.data.notes === 'string' ? (
        <p>
          <i className="fa fa-comment" aria-hidden="true"></i>{' '}
          {event.data.notes}
        </p>
      ) : null}
    </>
  );
};

const EventDetails = ({ event }: { event: HistoryEvent }) => {
  const originalEvent = event.originalEvent;

  switch (event.sourceEntity) {
    case 'ORDER':
      return <OrderEventDetails event={originalEvent as OrderEvent} />;
    case 'TASK':
      return <TaskEventDetails event={originalEvent as TaskEvent} />;
    case 'INCIDENT':
      return <IncidentEventView event={originalEvent as IncidentEvent} />;
    default:
      return null;
  }
};

type Props = {
  order: Order;
  tasks?: TaskPayload[];
};

export function OrderHistory({ order, tasks = [] }: Props) {
  const { allEvents, isLoading } = useOrderHistory({ order, tasks });

  const timelineItems = useMemo(() => {
    return allEvents.map((event, index) => ({
      key: event.createdAt + '-' + event.type + '-' + index,
      color: itemColor(event),
      children: (
        <>
          <p>
            {moment(event.createdAt).format('lll')} {event.source} {event.type}
          </p>
          <EventDetails event={event} />
        </>
      ),
    }));
  }, [allEvents]);

  if (isLoading) {
    return <Spin />;
  }

  return <Timeline items={timelineItems} />;
}
