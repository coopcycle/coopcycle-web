import React from 'react';
import moment from 'moment/moment';
import { formatTaskNumber } from '../utils/taskUtils';
import { Link } from './core/Link';
import { Task } from '../api/types';
import { useTranslation } from 'react-i18next';

type Props = {
  task: Pick<Task, '@id' | 'before' | 'metadata'>;
};

export function TaskWithNumberLink({ task }: Props) {
  const { t } = useTranslation();

  return (
    <Link
      href={window.Routing.generate('admin_dashboard_fullscreen', {
        date: moment(task.before).format('YYYY-MM-DD'),
        task: task['@id'],
      })}
      openInNewTab
      testId="taskWithNumberLink">
      {t('TASK_WITH_NUMBER', {
        number: formatTaskNumber(task),
      })}
    </Link>
  );
}
