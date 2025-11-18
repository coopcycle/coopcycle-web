import React, { useContext, useEffect, useState } from 'react';

import Itinerary from '../DeliveryItinerary';
import { TaskPayload } from '../../api/types';
import { UserContext } from '../../UserContext';
import { useSelector } from 'react-redux';
import { selectMode } from './redux/formSlice';
import { Mode } from './mode';

type Props = {
  tasks: TaskPayload[];
};

const DeliveryResume = ({ tasks }: Props) => {
  const { isDispatcher } = useContext(UserContext);
  const mode = useSelector(selectMode);

  const [createdTasks, setCreatedTasks] = useState<TaskPayload[] | null>(null);

  useEffect(() => {
    const createdTasks = tasks.filter(
      task => task.address.streetAddress !== '',
    );
    setCreatedTasks(createdTasks);
  }, [tasks]);

  return (
    <div className="resume mt-3 pt-3">
      {createdTasks ? (
        <Itinerary
          tasks={createdTasks}
          withTaskLinks={isDispatcher && mode === Mode.DELIVERY_UPDATE}
          withPackages
        />
      ) : null}
    </div>
  );
};

export default DeliveryResume;
