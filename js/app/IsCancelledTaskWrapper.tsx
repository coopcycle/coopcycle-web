import { Task, TaskPayload } from './api/types';
import React from 'react';

type Props = {
  task: TaskPayload | Task;
  children: React.ReactNode;
};

export default function IsCancelledTaskWrapper({ task, children }: Props) {
  return task.status === 'CANCELLED' ? <del>{children}</del> : <>{children}</>;
}
