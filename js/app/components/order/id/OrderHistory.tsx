import React, { useMemo } from 'react';
import { Spin, Timeline } from 'antd';
import moment from 'moment';
import { Order, TaskPayload } from '../../../api/types';
import { useTranslation } from 'react-i18next';
import { useOrderHistory } from './hooks/useOrderHistory';

type HistoryEvent = {
  source: string;
  type: string;
  createdAt: string;
  data?: Record<string, unknown>;
};

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

type Props = {
  order: Order;
  tasks?: TaskPayload[];
};

export function OrderHistory({ order, tasks = [] }: Props) {
  const { t } = useTranslation();

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
          {event.data?.incident_id ? (
            <a
              href={window.Routing.generate('admin_incident', {
                id: event.data.incident_id,
              })}
              target="_blank"
              rel="noopener noreferrer">
              {t('INCIDENT_WITH_ID', { id: event.data.incident_id })}
            </a>
          ) : null}
          {event.data?.notes ? (
            <p>
              <i className="fa fa-comment" aria-hidden="true"></i>{' '}
              {event.data.notes}
            </p>
          ) : null}
        </>
      ),
    }));
  }, [allEvents, t]);

  if (isLoading) {
    return <Spin />;
  }

  return <Timeline items={timelineItems} />;
}
