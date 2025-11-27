import React from 'react';
import { Timeline } from 'antd';
import { taskColor, taskListIcon } from '../../styles';
import { asText } from '../ShippingTimeRange';
import { Task, TaskPayload } from '../../api/types';
import { TaskLabel } from '../TaskLabel';

const Dot = ({
  type,
  status,
}: {
  type: Task['type'];
  status: Task['status'];
}) => {
  return (
    <i
      className={`fa ${taskListIcon(type, status)}`}
      style={{ color: taskColor(type, status) }}
    />
  );
};

type Props = {
  tasks: TaskPayload[] | Task[];
  withTaskLinks?: boolean;
  withTimeRange?: boolean;
  withDescription?: boolean;
  withPackages?: boolean;
};

const IsCancelledWrapper = ({
  task,
  children,
}: {
  task: TaskPayload | Task;
  children: React.ReactNode;
}) => (task.status === 'CANCELLED' ? <del>{children}</del> : <>{children}</>);

export default ({
  tasks,
  withTaskLinks = false,
  withTimeRange = false,
  withDescription = false,
  withPackages = false,
}: Props) => {
  const timelineItems = tasks.map((task, index) => {
    return {
      key: `task-${index}`,
      dot: <Dot type={task.type} status={task.status} />,
      children: (
        <IsCancelledWrapper task={task}>
          <>
            <div className="d-flex justify-content-between align-items-center">
              <TaskLabel task={task} withLink={withTaskLinks} />
              {withTimeRange ? (
                <span>
                  <i className="fa fa-clock-o" />
                  {' ' + asText([task.after, task.before])}
                </span>
              ) : null}
            </div>
            {task.address?.streetAddress ? (
              <div>{task.address?.streetAddress}</div>
            ) : null}
            {withDescription && task.address.description ? (
              <div className="speech-bubble">
                <i className="fa fa-quote-left" />{' '}
                {' ' + task.address.description}
              </div>
            ) : null}
            {withPackages ? (
              <ul>
                {task.packages?.map((p, index) =>
                  p.quantity > 0 ? (
                    <li key={index}>
                      {p.quantity} {p.type}
                    </li>
                  ) : null,
                )}
              </ul>
            ) : null}
          </>
        </IsCancelledWrapper>
      ),
    };
  });

  return <Timeline data-testid="delivery-itinerary" items={timelineItems} />;
};
