import React, { useState } from 'react';
import { Alert, Button, Collapse } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import { v4 as uuidv4 } from 'uuid';

import { PricingMatrix, Uri } from '../../../api/types';
import PricingMatrixEditor from './PricingMatrixEditor';

type Props = {
  matrices: PricingMatrix[];
  ruleSetUri: Uri | null;
  onSaved: () => void;
};

const emptyMatrix = (): PricingMatrix =>
  ({
    '@id': `temp-${uuidv4()}`,
    name: '',
    target: 'TASK',
    taskType: null,
    rowAxis: { variable: 'delivery.packages.totalVolumeUnits()', entries: [] },
    columnAxis: { variable: 'zone', addressSource: 'task', entries: [] },
    cells: {},
  }) as unknown as PricingMatrix;

const PricingMatrixSection = ({ matrices, ruleSetUri, onSaved }: Props) => {
  const { t } = useTranslation();
  const [newMatrices, setNewMatrices] = useState<PricingMatrix[]>([]);

  if (!ruleSetUri) {
    // A matrix points at its rule set, so it can only be added once that exists
    return (
      <Alert
        type="info"
        showIcon
        message={t('PRICING_MATRIX_SAVE_RULE_SET_FIRST')}
      />
    );
  }

  const addMatrix = () => setNewMatrices([...newMatrices, emptyMatrix()]);

  const dropNewMatrix = (id: string) =>
    setNewMatrices(newMatrices.filter(matrix => matrix['@id'] !== id));

  const items = [...matrices, ...newMatrices].map((matrix, index) => ({
    key: matrix['@id'],
    label: matrix.name?.trim()
      ? matrix.name
      : t('PRICING_MATRIX_UNNAMED', { index: index + 1 }),
    children: (
      <PricingMatrixEditor
        matrix={matrix}
        ruleSetUri={ruleSetUri}
        onSaved={() => {
          dropNewMatrix(matrix['@id']);
          onSaved();
        }}
        onCancelNew={() => dropNewMatrix(matrix['@id'])}
      />
    ),
  }));

  return (
    <div>
      <Alert
        className="mb-3"
        type="info"
        showIcon
        message={t('PRICING_MATRIX_HELP')}
      />
      {/*
        NOTE: collapsing a panel unmounts its editor, which throws away whatever was
        typed into the grid since the last save. Worth lifting the draft out of
        PricingMatrixEditor, or keeping the panels expanded.
      */}
      {items.length > 0 ? (
        <Collapse
          items={items}
          className="mb-3"
          defaultActiveKey={items.map(item => item.key)}
        />
      ) : null}
      <Button icon={<PlusOutlined />} onClick={addMatrix}>
        {t('PRICING_MATRIX_ADD')}
      </Button>
    </div>
  );
};

export default PricingMatrixSection;
