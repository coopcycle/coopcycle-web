import React, { useEffect, useState } from 'react';
import { Spin } from 'antd';
import TaskStatusBadge from '../../../../dashboard/components/TaskStatusBadge';
import { money } from '../../utils';
import { useTranslation } from 'react-i18next';

async function _fetchTaskContect(id) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(
    window.Routing.generate('_api_/tasks/{id}/context_get', { id }),
  );
}

function ContextDetails({ context }) {
  const { t } = useTranslation();

  const { delivery, order } = context;

  if (order) {
    const orderID = order?.number
      ? t('INCIDENTS_ORDER_NUMBER', { number: order.number })
      : t('INCIDENTS_ORDER_ID', { id: order.id });

    return (
      <>
        <hr className="my-2" />
        <p className="font-weight-bold">{t('ORDER_DETAILS')}</p>
        <div>{orderID}</div>
        {order?.restaurant && <div>{order?.restaurant.name}</div>}
        <div>{money(order.itemsTotal)}</div>
        <div>{order?.customer?.fullName ?? order?.customer?.username}</div>
      </>
    );
  }

  if (delivery) {
    return (
      <>
        <hr className="my-2" />
        <p className="font-weight-bold">{t('INCIDENTS_DELIVERY_DETAILS')}</p>
      </>
    );
  }

  return null;
}

export default function ({ task }) {
  const [context, setContext] = useState(null);
  const { t } = useTranslation();

  useEffect(() => {
    async function _fetch() {
      const { response, error } = await _fetchTaskContect(task.id);
      if (!error) {
        setContext(response);
      }
    }
    _fetch();
  }, []);

  if (!context) {
    return <Spin />;
  }

  return (
    <div className="p-2">
      <p>
        <span className="font-weight-bold mr-2">
          {t('INCIDENTS_TASK_ID', { id: task.id })}
        </span>
        <TaskStatusBadge task={task} />
      </p>
      <div data-testid="task-type" className="text-capitalize">
        {t('INCIDENTS_TASK_TYPE')}: {task.type.toLowerCase()}
      </div>
      <ContextDetails context={context} />
    </div>
  );
}
