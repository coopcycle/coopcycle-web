import { useEffect } from 'react';
import { openTaskTaskList, selectTask, setCurrentTask } from '../redux/actions';
import { useDispatch, useSelector } from 'react-redux';
import { selectInitialTask } from '../redux/selectors';
import { selectTaskById } from '../../../shared/src/logistics/redux/selectors';

export function usePreloadedState() {
  const dispatch = useDispatch();

  const initialTaskUri = useSelector(selectInitialTask);
  const initialTask = useSelector(state =>
    selectTaskById(state, initialTaskUri),
  );

  // Effect for handling initial task selection from URL
  useEffect(() => {
    if (initialTask) {
      setTimeout(() => {
        // highlight task in the list
        dispatch(selectTask(initialTask));
        dispatch(openTaskTaskList(initialTask));
        // open task modal
        dispatch(setCurrentTask(initialTask));
      }, 500);
    }
  }, []); // no deps to run once

  return {};
}
