import React, { useMemo, useState } from 'react';
import {
  Alert,
  Button,
  Input,
  InputNumber,
  Popconfirm,
  Select,
  Space,
  Table,
  message,
} from 'antd';
import { useTranslation } from 'react-i18next';

import { MatrixAxis, PricingMatrix, Uri } from '../../../api/types';
import {
  useCreatePricingMatrixMutation,
  useDeletePricingMatrixMutation,
  useUpdatePricingMatrixMutation,
} from '../../../api/slice';
import AxisEditor from './AxisEditor';
import { cellKey, entryLabel, findAxisWarnings } from './axis';

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
    // Dropping an entry leaves its cells pointing at nothing, which the API refuses
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

  const gridColumns = [
    {
      title: '',
      key: 'rowHeader',
      fixed: 'left' as const,
      render: (_: unknown, row: { key: string; label: string }) => (
        <strong>{row.label}</strong>
      ),
    },
    ...draft.columnAxis.entries.map(column => ({
      title: entryLabel(column),
      key: column.key,
      render: (_: unknown, row: { key: string }) => (
        <InputNumber
          min={0}
          step={0.5}
          precision={2}
          addonAfter="€"
          style={{ width: 130 }}
          value={
            draft.cells[cellKey(row.key, column.key)] !== undefined
              ? draft.cells[cellKey(row.key, column.key)] / 100
              : null
          }
          onChange={value => setCell(row.key, column.key, value as number)}
        />
      ),
    })),
  ];

  const gridRows = draft.rowAxis.entries.map(row => ({
    key: row.key,
    label: entryLabel(row),
  }));

  const canEditGrid =
    draft.rowAxis.entries.length > 0 && draft.columnAxis.entries.length > 0;

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

      <AxisEditor
        title={t('PRICING_MATRIX_ROW_AXIS')}
        axis={draft.rowAxis}
        onChange={axis => setAxis('rowAxis', axis)}
      />

      <AxisEditor
        title={t('PRICING_MATRIX_COLUMN_AXIS')}
        axis={draft.columnAxis}
        onChange={axis => setAxis('columnAxis', axis)}
      />

      {warnings.length > 0 ? (
        <Alert
          className="mb-3"
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

      {canEditGrid ? (
        <Table
          className="mb-3"
          size="small"
          dataSource={gridRows}
          columns={gridColumns}
          pagination={false}
          scroll={{ x: true }}
        />
      ) : (
        <Alert
          className="mb-3"
          type="info"
          showIcon
          message={t('PRICING_MATRIX_NEEDS_BOTH_AXES')}
        />
      )}

      <Space>
        <Button
          type="primary"
          loading={isCreating || isUpdating}
          disabled={!canEditGrid}
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
