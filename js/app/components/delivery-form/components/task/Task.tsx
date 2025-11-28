import React, { useCallback, useContext, useMemo } from 'react';
import { Field } from 'formik';
import AddressBookNew from './AddressBook';
import { Button, Input } from 'antd';
import Packages from './Packages';
import { useTranslation } from 'react-i18next';
import TotalWeight from './TotalWeight';

import './Task.scss';
import TagsSelect from '../../../TagsSelect';
import { TaskDateTime } from './TaskDateTime';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import {
  useGetStorePackagesQuery,
  useGetStoreTimeSlotsQuery,
} from '../../../../api/slice';
import { Mode, modeIn } from '../../mode';
import { useSelector } from 'react-redux';
import { selectMode } from '../../redux/formSlice';
import type { Address, Store, Tag } from '../../../../api/types';
import { UserContext } from '../../../../UserContext';
import { isTemporaryId } from '../../idUtils';
import { DeleteOutlined, UndoOutlined } from '@ant-design/icons';
import { FA_CANCELLED, taskTypeListIcon } from '../../../../styles';
import IsCancelledTaskWrapper from '../../../../IsCancelledTaskWrapper';

type Props = {
  storeNodeId: string;
  addresses: Address[];
  taskId: string;
  storeDeliveryInfos: Partial<Store>;
  onDelete: (index: number) => void;
  canDelete: boolean;
  tags: Tag[];
  isExpanded: boolean;
  onToggleExpanded: (expanded: boolean) => void;
  showPackages: boolean;
};

const Task = ({
  storeNodeId,
  addresses,
  taskId,
  storeDeliveryInfos,
  onDelete,
  canDelete,
  tags,
  isExpanded,
  onToggleExpanded,
  showPackages,
}: Props) => {
  const { t } = useTranslation();

  const { isDispatcher } = useContext(UserContext);

  const mode = useSelector(selectMode);
  const { values, taskValues, setFieldValue, taskIndex } =
    useDeliveryFormFormikContext({
      taskId: taskId,
    });

  const showDeleteButton =
    (modeIn(mode, [Mode.DELIVERY_CREATE, Mode.RECURRENCE_RULE_UPDATE]) ||
      (mode === Mode.DELIVERY_UPDATE && isTemporaryId(taskId))) &&
    canDelete;

  const isExistingTask =
    mode === Mode.DELIVERY_UPDATE && !isTemporaryId(taskId);

  const taskIcon = useMemo(() => {
    if (isExistingTask && taskValues.status === 'CANCELLED') {
      return FA_CANCELLED;
    } else {
      return taskTypeListIcon(taskValues.type);
    }
  }, [taskValues.type, taskValues.type]);

  const { data: timeSlotLabels } = useGetStoreTimeSlotsQuery(storeNodeId);
  const { data: packages } = useGetStorePackagesQuery(storeNodeId);

  const _onDelete = useCallback(() => {
    onDelete(taskIndex);
  }, [onDelete, taskIndex]);

  const onCancel = useCallback(() => {
    setFieldValue(`tasks[${taskIndex}].status`, 'CANCELLED');
  }, [taskIndex, setFieldValue]);

  const onRestore = useCallback(() => {
    setFieldValue(`tasks[${taskIndex}].status`, 'TODO');
  }, [taskIndex, setFieldValue]);

  return (
    <div
      className="task border p-4 mb-4"
      data-testid={`form-task-${taskIndex}`}>
      <div
        className={`task__header task__header--${taskValues.type.toLowerCase()}`}
        onClick={() => {
          onToggleExpanded(!isExpanded);
        }}>
        <i className={`fa ${taskIcon}`} />
        <IsCancelledTaskWrapper task={taskValues}>
          <h4 className="task__header__title ml-2 mb-4">
            {taskValues.address?.streetAddress
              ? taskValues.address.streetAddress
              : t(`DELIVERY_FORM_${taskValues.type}_INFORMATIONS`)}
          </h4>
        </IsCancelledTaskWrapper>

        <button
          data-testid="toggle-button"
          type="button"
          className="task__button">
          <i
            className={isExpanded ? 'fa fa-chevron-up' : 'fa fa-chevron-down'}
            title={
              isExpanded
                ? t('DELIVERY_FORM_SHOW_LESS')
                : t('DELIVERY_FORM_SHOW_MORE')
            }></i>
        </button>

        {showDeleteButton && (
          <i
            data-testid="task-remove"
            className="fa fa-trash cursor-pointer"
            onClick={_onDelete}
          />
        )}
      </div>

      <div
        className={isExpanded ? 'task__body' : 'task__body task__body--hidden'}>
        <AddressBookNew
          addresses={addresses}
          taskId={taskId}
          storeDeliveryInfos={storeDeliveryInfos}
          shallPrefillAddress={Boolean(
            taskValues.type === 'PICKUP' &&
              mode === Mode.DELIVERY_CREATE &&
              storeDeliveryInfos.prefillPickupAddress,
          )}
        />

        <TaskDateTime
          isDispatcher={isDispatcher}
          storeNodeId={storeNodeId}
          timeSlots={timeSlotLabels}
          taskId={taskId}
        />

        {showPackages ? (
          <div className="mt-4">
            {packages && packages.length ? (
              <Packages taskId={taskId} packages={packages} />
            ) : null}
            <TotalWeight taskId={taskId} />
          </div>
        ) : null}

        <div className="mt-4 mb-4">
          <label
            htmlFor={`tasks[${taskIndex}].comments`}
            className="block mb-2 font-weight-bold">
            {t('ADMIN_DASHBOARD_TASK_FORM_COMMENTS_LABEL')}
          </label>
          <Field
            as={Input.TextArea}
            name={`tasks[${taskIndex}].comments`}
            placeholder={t('ADMIN_DASHBOARD_TASK_FORM_COMMENTS_PLACEHOLDER')}
            rows={4}
            style={{ resize: 'none' }}
          />
        </div>

        {isDispatcher && (
          <div className="mt-4 mb-4">
            <div className="tags__title block mb-2 font-weight-bold">Tags</div>
            <div data-testid="tags-select">
              <TagsSelect
                tags={tags}
                defaultValue={values.tasks[taskIndex].tags || []}
                onChange={values => {
                  const tags = values.map(tag => tag.value);
                  setFieldValue(`tasks[${taskIndex}].tags`, tags);
                }}
              />
            </div>
          </div>
        )}
      </div>
      <div className={isExpanded ? 'task__footer' : 'task__footer--hidden'}>
        {showDeleteButton ? (
          <Button onClick={_onDelete} type="default" danger>
            {t(`DELIVERY_FORM_REMOVE_${taskValues.type}`)}
          </Button>
        ) : null}
        {isDispatcher && isExistingTask && taskValues.status === 'TODO' ? (
          <Button
            data-testid="task-cancel"
            onClick={onCancel}
            type="primary"
            icon={<DeleteOutlined />}
            danger>
            {t('ADMIN_DASHBOARD_CANCEL_TASK')}
          </Button>
        ) : null}
        {isDispatcher && isExistingTask && taskValues.status === 'CANCELLED' ? (
          <Button
            data-testid="task-restore"
            onClick={onRestore}
            color="green"
            variant="solid"
            icon={<UndoOutlined />}>
            {t('ADMIN_DASHBOARD_RESTORE')}
          </Button>
        ) : null}
      </div>
    </div>
  );
};

export default Task;
