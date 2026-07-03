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
};

const ShiftTypeFilter = forwardRef<ShiftTypeFilterHandle, Props>(
  ({ shiftTypes, value, onChange }, ref) => {
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
        onDropdownVisibleChange={setOpen}
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
                style={{ backgroundColor: shiftTypeColor(type) }}
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
