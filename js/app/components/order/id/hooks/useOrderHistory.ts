import { useEffect, useMemo, useState } from 'react';
import { useDispatch } from 'react-redux';
import moment from 'moment';
import { useTranslation } from 'react-i18next';
import {
  Incident,
  Order,
  OrderEvent,
  TaskEvent,
  TaskPayload,
} from '../../../../api/types';
import { apiSlice } from '../../../../api/slice';
import { formatTaskNumber } from '../../../../utils/taskUtils';

type HistoryEvent = {
  source: string;
  type: string;
  createdAt: string;
  data?: Record<string, unknown>;
};

type Params = {
  order: Order;
  tasks?: TaskPayload[];
};

export function useOrderHistory({ order, tasks = [] }: Params) {
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
      data: event.data,
    }));
  }, [order.events, order.number, t]);

  const [taskEvents, setTaskEvents] = useState<HistoryEvent[]>([]);
  const [incidentEvents, setIncidentEvents] = useState<HistoryEvent[]>([]);

  const allEvents = useMemo(() => {
    const mergedEvents: HistoryEvent[] = [
      ...orderEvents,
      ...taskEvents,
      ...incidentEvents,
    ];

    mergedEvents.sort((a, b) => {
      return moment(a.createdAt).isBefore(moment(b.createdAt)) ? -1 : 1;
    });

    return mergedEvents;
  }, [orderEvents, taskEvents, incidentEvents]);

  useEffect(() => {
    if (tasks.length === 0) {
      return;
    }

    const fetchTaskEventsAndIncidents = async () => {
      setIsLoading(true);

      const events: HistoryEvent[] = [];
      const incidentEvents: HistoryEvent[] = [];

      try {
        for (const task of tasks) {
          // Fetch task events
          const taskEventsPromise = dispatch(
            apiSlice.endpoints.getTaskEvents.initiate(task['@id']),
          );
          const taskEventsResult = await taskEventsPromise;

          if ('data' in taskEventsResult && taskEventsResult.data) {
            const taskEvents = taskEventsResult.data as TaskEvent[];
            events.push(
              ...taskEvents.map(event => ({
                source: t('TASK_WITH_NUMBER', {
                  number: formatTaskNumber(task),
                }),
                type: event.name,
                createdAt: event.createdAt,
                data: event.data,
              })),
            );
          }

          // Unsubscribe from the events query
          if ('unsubscribe' in taskEventsPromise) {
            (taskEventsPromise as any).unsubscribe();
          }

          // Fetch task incidents
          const incidentsPromise = dispatch(
            apiSlice.endpoints.getTaskIncidents.initiate(task['@id']),
          );
          const incidentsResult = await incidentsPromise;

          if ('data' in incidentsResult && incidentsResult.data) {
            const taskIncidents = incidentsResult.data as Incident[];
            incidentEvents.push(
              ...taskIncidents.flatMap(incident =>
                incident.events.map(event => ({
                  source: t('INCIDENT_WITH_ID', { id: incident.id }),
                  type: event.type,
                  createdAt: event.createdAt,
                  data: event.metadata,
                })),
              ),
            );
          }

          // Unsubscribe from the incidents query
          if ('unsubscribe' in incidentsPromise) {
            (incidentsPromise as any).unsubscribe();
          }
        }

        setTaskEvents(events);
        setIncidentEvents(incidentEvents);
      } catch (error) {
        console.error('Failed to fetch task events and incidents:', error);
        setTaskEvents([]);
        setIncidentEvents([]);
      } finally {
        setIsLoading(false);
      }
    };

    fetchTaskEventsAndIncidents();
  }, [tasks, dispatch, t]);

  return {
    allEvents,
    isLoading,
  };
}
