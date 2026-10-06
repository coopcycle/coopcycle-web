import React, { useMemo, useState } from 'react';
import {
  Alert,
  Button,
  Input,
  Popconfirm,
  Select,
  Space,
  message,
} from 'antd';
import { useTranslation } from 'react-i18next';

import { MatrixAxis, PricingMatrix, Uri } from '../../../api/types';
import {
  useCreatePricingMatrixMutation,
  useDeletePricingMatrixMutation,
  useUpdatePricingMatrixMutation,
} from '../../../api/slice';
import PricingMatrixGrid from './PricingMatrixGrid';
import PricingMatrixWizard from './PricingMatrixWizard';
import { cellKey, findAxisWarnings } from './axis';

type Props = {
  matrix: PricingMatrix;
  ruleSetUri: Uri;
  onSaved: () => void;
  onCancelNew: () => void;
};

const isNew = (matrix: PricingMatrix): boolean => !matrix.id;

const PricingMatrixEditor = ({
  matrix,
  ruleSetUri,
  onSaved,
  onCancelNew,
}: Props) => {
  const { t } = useTranslation();
  const [draft, setDraft] = useState<PricingMatrix>(matrix);

  // A new grid is drawn by the wizard first; an existing one opens on the grid
  const [inWizard, setInWizard] = useState(
    isNew(matrix) && matrix.rowAxis.entries.length === 0,
  );

  const [createMatrix, { isLoading: isCreating }] =
    useCreatePricingMatrixMutation();
  const [updateMatrix, { isLoading: isUpdating }] =
    useUpdatePricingMatrixMutation();
  const [deleteMatrix, { isLoading: isDeleting }] =
    useDeletePricingMatrixMutation();

  const warnings = useMemo(
    () => [
      ...findAxisWarnings(draft.rowAxis, t),
      ...findAxisWarnings(draft.columnAxis, t),
    ],
    [draft.rowAxis, draft.columnAxis, t],
  );

  const setAxis = (which: 'rowAxis' | 'columnAxis', axis: MatrixAxis) => {
    // Removing a row or a column leaves its cells pointing at nothing, which the
    // API refuses, so they go with it
    const next = { ...draft, [which]: axis };
    const rowKeys = next.rowAxis.entries.map(entry => entry.key);
    const columnKeys = next.columnAxis.entries.map(entry => entry.key);

    next.cells = Object.fromEntries(
      Object.entries(draft.cells).filter(([key]) => {
        const [rowKey, columnKey] = key.split(':');
        return rowKeys.includes(rowKey) && columnKeys.includes(columnKey);
      }),
    );

    setDraft(next);
  };

  const setCell = (rowKey: string, columnKey: string, euros: number | null) => {
    const key = cellKey(rowKey, columnKey);
    const cells = { ...draft.cells };

    if (euros === null || Number.isNaN(euros)) {
      delete cells[key];
    } else {
      cells[key] = Math.round(euros * 100);
    }

    setDraft({ ...draft, cells });
  };

  const save = async () => {
    const payload = {
      ruleSet: ruleSetUri,
      name: draft.name,
      target: draft.target,
      taskType: draft.target === 'TASK' ? (draft.taskType ?? null) : null,
      rowAxis: draft.rowAxis,
      columnAxis: draft.columnAxis,
      cells: draft.cells,
    };

    try {
      if (isNew(draft)) {
        await createMatrix(payload).unwrap();
      } else {
        await updateMatrix({ id: draft.id, ...payload }).unwrap();
      }
      message.success(t('SAVE_SUCCESS'));
      onSaved();
    } catch (error) {
      // The API explains what it refused, which is more useful than a generic failure
      const description = (error as { data?: Record<string, string> })?.data?.[
        'hydra:description'
      ];
      message.error(
        description ? `${t('SAVE_ERROR')}: ${description}` : t('SAVE_ERROR'),
      );
    }
  };

  const remove = async () => {
    try {
      await deleteMatrix(draft.id).unwrap();
      message.success(t('SAVE_SUCCESS'));
      onSaved();
    } catch (error) {
      message.error(t('SAVE_ERROR'));
    }
  };

  if (inWizard) {
    return (
      <PricingMatrixWizard
        onDone={(rowAxis, columnAxis) => {
          setDraft({ ...draft, rowAxis, columnAxis, cells: {} });
          setInWizard(false);
        }}
        onCancel={onCancelNew}
      />
    );
  }

  return (
    <div>
      <Space className="mb-3" wrap>
        <Input
          style={{ minWidth: 220 }}
          value={draft.name ?? ''}
          placeholder={t('PRICING_MATRIX_NAME')}
          onChange={e => setDraft({ ...draft, name: e.target.value })}
        />
        <Select
          style={{ minWidth: 220 }}
          value={draft.target}
          onChange={target =>
            setDraft({
              ...draft,
              target,
              taskType: target === 'TASK' ? draft.taskType : null,
            })
          }
          options={[
            { label: t('PRICING_MATRIX_TARGET_TASK'), value: 'TASK' },
            { label: t('PRICING_MATRIX_TARGET_DELIVERY'), value: 'DELIVERY' },
          ]}
        />
        {draft.target === 'TASK' ? (
          <Select
            style={{ minWidth: 200 }}
            value={draft.taskType ?? null}
            onChange={taskType => setDraft({ ...draft, taskType })}
            options={[
              { label: t('PRICING_MATRIX_TASK_TYPE_ALL'), value: null },
              { label: t('PRICING_MATRIX_TASK_TYPE_PICKUP'), value: 'PICKUP' },
              { label: t('PRICING_MATRIX_TASK_TYPE_DROPOFF'), value: 'DROPOFF' },
            ]}
          />
        ) : null}
      </Space>

      <PricingMatrixGrid
        rowAxis={draft.rowAxis}
        columnAxis={draft.columnAxis}
        cells={draft.cells}
        onRowAxisChange={axis => setAxis('rowAxis', axis)}
        onColumnAxisChange={axis => setAxis('columnAxis', axis)}
        onCellChange={setCell}
      />

      {warnings.length > 0 ? (
        <Alert
          className="mt-3"
          type="warning"
          showIcon
          message={t('PRICING_MATRIX_WARNINGS_TITLE')}
          description={
            <ul className="mb-0">
              {warnings.map((warning, index) => (
                <li key={index}>{warning.message}</li>
              ))}
            </ul>
          }
        />
      ) : null}

      <Space className="mt-3">
        <Button
          type="primary"
          loading={isCreating || isUpdating}
          disabled={
            draft.rowAxis.entries.length === 0 ||
            draft.columnAxis.entries.length === 0
          }
          onClick={save}>
          {t('SAVE_BUTTON')}
        </Button>
        {isNew(draft) ? (
          <Button onClick={onCancelNew}>{t('CANCEL')}</Button>
        ) : (
          <Popconfirm
            title={t('PRICING_MATRIX_DELETE_CONFIRM')}
            onConfirm={remove}
            okText={t('YES')}
            cancelText={t('NO')}>
            <Button danger loading={isDeleting}>
              {t('ADMIN_DASHBOARD_DELETE')}
            </Button>
          </Popconfirm>
        )}
      </Space>
    </div>
  );
};

export default PricingMatrixEditor;
