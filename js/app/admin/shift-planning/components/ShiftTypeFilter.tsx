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
import { activityDisplayLabel } from '../utils/activityLabel';
import { ShiftActivity } from '../../../api/types';

export type ShiftTypeFilterHandle = {
  focus: () => void;
};

type Props = {
  activities: ShiftActivity[];
  value: string[];
  onChange: (value: string[]) => void;
  typeColors?: Record<string, string>;
};

const ShiftTypeFilter = forwardRef<ShiftTypeFilterHandle, Props>(
  ({ activities, value, onChange, typeColors }, ref) => {
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
        options={activities.map(a => ({
          value: a.slug,
          label: (
            <span>
              <span
                className="shift-type-dot"
                style={{ backgroundColor: shiftTypeColor(a.slug, typeColors) }}
              />
              {activityDisplayLabel(a, t)}
            </span>
          ),
        }))}
      />
    );
  },
);

ShiftTypeFilter.displayName = 'ShiftTypeFilter';

export default ShiftTypeFilter;
