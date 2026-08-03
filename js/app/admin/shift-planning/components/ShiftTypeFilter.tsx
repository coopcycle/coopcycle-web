import React, {
  forwardRef,
  useImperativeHandle,
  useRef,
  useState,
} from 'react';
import { Select } from 'antd';
import type { RefSelectProps } from 'antd/es/select';
import { FilterOutlined } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import { shiftTypeColor } from '../utils/shiftTypeColor';

export type ShiftTypeFilterHandle = {
  focus: () => void;
};

type Props = {
  shiftTypes: string[];
  value: string[];
  onChange: (value: string[]) => void;
  typeColors?: Record<string, string>;
};

const ShiftTypeFilter = forwardRef<ShiftTypeFilterHandle, Props>(
  ({ shiftTypes, value, onChange, typeColors }, ref) => {
    const { t } = useTranslation();
    const selectRef = useRef<RefSelectProps>(null);
    const [open, setOpen] = useState(false);

    useImperativeHandle(ref, () => ({
      focus: () => {
        selectRef.current?.focus();
        setOpen(true);
      },
    }));

    return (
      <Select
        ref={selectRef}
        mode="multiple"
        allowClear
        open={open}
        onOpenChange={setOpen}
        style={{ minWidth: 200 }}
        placeholder={
          <span>
            <FilterOutlined /> {t('SHIFT_PLANNING_FILTER_TYPE')}
          </span>
        }
        value={value}
        onChange={onChange}
        optionFilterProp="value"
        options={shiftTypes.map(type => ({
          value: type,
          label: (
            <span>
              <span
                className="shift-type-dot"
                style={{ backgroundColor: shiftTypeColor(type, typeColors) }}
              />
              {type}
            </span>
          ),
        }))}
      />
    );
  },
);

ShiftTypeFilter.displayName = 'ShiftTypeFilter';

export default ShiftTypeFilter;
