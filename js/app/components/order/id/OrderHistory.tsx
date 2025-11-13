import React, { useEffect, useMemo, useState } from 'react';
import { Spin, Timeline } from 'antd';
import moment from 'moment';
import { useDispatch } from 'react-redux';
import { Order, OrderEvent, TaskEvent, TaskPayload } from '../../../api/types';
import { apiSlice } from '../../../api/slice';
import { formatTaskNumber } from '../../../utils/taskUtils';
import { useTranslation } from 'react-i18next';

type HistoryEvent = {
  source: string;
  type: string;
  createdAt: string;
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
  const dispatch = useDispatch();
  const { t } = useTranslation();

  const [isLoading, setIsLoading] = useState(false);

  const orderEvents = useMemo(() => {
    if (!order.events) {
      return [];
    }

    return order.events.map((event: OrderEvent) => ({
      source: t('ORDER_WITH_NUMBER', { number: order.number }),
      type: event.type,
      createdAt: event.createdAt,
    }));
  }, [order.events, order.number, t]);
  const [taskEvents, setTaskEvents] = useState<HistoryEvent[]>([]);

  const allEvents = useMemo(() => {
    const mergedEvents: HistoryEvent[] = [...orderEvents, ...taskEvents];

    mergedEvents.sort((a, b) => {
      return moment(a.createdAt).isBefore(moment(b.createdAt)) ? -1 : 1;
    });

    return mergedEvents;
  }, [orderEvents, taskEvents]);

  const timelineItems = useMemo(() => {
    return allEvents.map((event, index) => ({
      key: event.createdAt + '-' + event.type + '-' + index,
      color: itemColor(event),
      children: (
        <>
          <p>
            {moment(event.createdAt).format('lll')} {event.source} {event.type}
          </p>
          {/*{event.data.incident_id && (*/}
          {/*  <a*/}
          {/*    href={window.Routing.generate('admin_incident', {*/}
          {/*      id: event.data.incident_id,*/}
          {/*    })}*/}
          {/*    target="_blank"*/}
          {/*    rel="noopener noreferrer">*/}
          {/*    Incident #{event.data.incident_id}*/}
          {/*  </a>*/}
          {/*)}*/}
          {/*{event.data.notes && (*/}
          {/*  <p>*/}
          {/*    <i className="fa fa-comment" aria-hidden="true"></i>{' '}*/}
          {/*    {event.data.notes}*/}
          {/*  </p>*/}
          {/*)}*/}
        </>
      ),
    }));
  }, [allEvents]);

  useEffect(() => {
    if (tasks.length === 0) {
      return;
    }

    const fetchTaskEvents = async () => {
      setIsLoading(true);

      const events: HistoryEvent[] = [];

      try {
        for (const task of tasks) {
          const promise = dispatch(
            apiSlice.endpoints.getTaskEvents.initiate(task['@id']),
          );
          const result = await promise;

          if ('data' in result && result.data) {
            const taskEvents = result.data as TaskEvent[];
            events.push(
              ...taskEvents.map(event => ({
                source: t('TASK_WITH_NUMBER', {
                  number: formatTaskNumber(task),
                }),
                type: event.name,
                createdAt: event.createdAt,
              })),
            );
          }

          // Unsubscribe from the queries
          if ('unsubscribe' in promise) {
            (promise as any).unsubscribe();
          }
        }

        setTaskEvents(events);
      } catch (error) {
        console.error('Failed to fetch task events:', error);
        setTaskEvents([]);
      } finally {
        setIsLoading(false);
      }
    };

    fetchTaskEvents();
  }, [tasks, dispatch, t]);

  if (isLoading) {
    return <Spin />;
  }

  return <Timeline items={timelineItems} />;
}
