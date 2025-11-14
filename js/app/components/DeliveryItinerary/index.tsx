import React from 'react';
import { Timeline } from 'antd';
import { taskTypeColor, taskTypeListIcon } from '../../styles';
import { asText } from '../ShippingTimeRange';
import { TaskPayload } from '../../api/types';
import { TaskLabel } from '../TaskLabel';

const Dot = ({ type }) => {
  return (
    <i
      className={`fa ${taskTypeListIcon(type)}`}
      style={{ color: taskTypeColor(type) }}
    />
  );
};

type Props = {
  tasks: TaskPayload[];
  withTaskLinks?: boolean;
  withTimeRange?: boolean;
  withDescription?: boolean;
  withPackages?: boolean;
};

export default ({
  tasks,
  withTaskLinks = false,
  withTimeRange = false,
  withDescription = false,
  withPackages = false,
}: Props) => {
  const timelineItems = tasks.map((task, index) => ({
    key: `task-${index}`,
    dot: <Dot type={task.type} />,
    children: (
      <>
        <div>
          <TaskLabel task={task} withLink={withTaskLinks} />
          {withTimeRange ? (
            <span className="pull-right">
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
            <i className="fa fa-quote-left" /> {' ' + task.address.description}
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
    ),
  }));

  return <Timeline data-testid="delivery-itinerary" items={timelineItems} />;
};
