import React from 'react';
import { TaskPayload } from '../api/types';
import { useTranslation } from 'react-i18next';
import { TaskWithNumberLink } from './TaskWithNumberLink';

type Props = {
  task: Pick<TaskPayload, '@id' | 'type' | 'address' | 'before' | 'metadata'>;
  withLink?: boolean;
};

export function TaskLabel({ task, withLink = false }: Props) {
  const { t } = useTranslation();

  return (
    <span>
      {withLink && task['@id'] ? (
        <>
          <TaskWithNumberLink task={task} />{' '}
        </>
      ) : null}
      {task.type === 'PICKUP' ? t('DELIVERY_PICKUP') : t('DELIVERY_DROPOFF')}
      {task.address?.name ? (
        <span>
          : <span className="font-weight-bold">{task.address?.name}</span>
        </span>
      ) : null}
    </span>
  );
}
