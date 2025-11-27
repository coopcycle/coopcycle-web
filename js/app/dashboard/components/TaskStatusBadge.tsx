import React from 'react';
import { Tag } from 'antd';
import { Task } from '../../api/types';

type Props = {
  task: Task;
  className?: string;
};

type StatusConfig = {
  color: string;
  icon: string;
};

const statusConfig: Record<Task['status'], StatusConfig> = {
  TODO: {
    color: 'default',
    icon: 'fa-clock-o',
  },
  DOING: {
    color: 'processing',
    icon: 'fa-bicycle',
  },
  FAILED: {
    color: 'error',
    icon: 'fa-exclamation-triangle',
  },
  DONE: {
    color: 'success',
    icon: 'fa-check',
  },
  CANCELLED: {
    color: 'error',
    icon: 'fa-times',
  },
};

export default function TaskStatusBadge({ task, className }: Props) {
  const { status } = task;
  const config = statusConfig[status];

  return (
    <Tag
      color={config.color}
      icon={<i className={`fa ${config.icon} mr-1`} aria-hidden="true"></i>}
      style={{ textTransform: 'capitalize' }}
      className={className}>
      {status.toLowerCase()}
    </Tag>
  );
}
