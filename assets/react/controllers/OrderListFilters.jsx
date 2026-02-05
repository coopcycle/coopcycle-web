import React, { useState, useEffect } from 'react'
import { DatePicker, Flex, Select, Tag, Button } from 'antd';
import qs from 'qs'
import _ from 'lodash'
import dayjs from 'dayjs';

const options = [{ value: 'new' }, { value: 'accepted' }, { value: 'fulfilled' }, { value: 'refused' }];

const tagRender = props => {
  const { label, value, closable, onClose } = props;

  const onPreventMouseDown = event => {
    event.preventDefault();
    event.stopPropagation();
  };

  return (
    <Tag
      // color={value}
      onMouseDown={onPreventMouseDown}
      closable={closable}
      onClose={onClose}
      style={{ marginInlineEnd: 4 }}
    >
      {label}
    </Tag>
  );
};

export default function OrderListFilters({ defaultFilters }) {

  const [filters, setFilters] = useState(Array.isArray(defaultFilters) && defaultFilters.length === 0 ? {} : defaultFilters);

  useEffect(() => {
    console.log(qs.stringify(filters))
  }, [filters]);

  let datePickerProps = {}
  if (defaultFilters.date) {
    datePickerProps = {
      ...datePickerProps,
      // FIXME
      // Breaks, maybe need to add ConfigProvider
      // defaultValue: dayjs(defaultFilters.date)
    }
  }

  return (
    <Flex gap="small" wrap>
      <DatePicker
        onChange={(date, dateString) => {
          setFilters({
            ...filters,
            date: dateString
          })
        }}
        {...datePickerProps}
      />
      <Select
        mode="multiple"
        tagRender={tagRender}
        defaultValue={[]}
        style={{ minWidth: '100px' }}
        options={options}
        defaultValue={ defaultFilters.state }
        onChange={(value) => {
          setFilters({
            ...filters,
            state: value
          })
        }}
      />
      <Button
        disabled={ _.isEmpty(filters) }
        onClick={() => {
          window.location.href = `${window.location.pathname}?${qs.stringify(filters)}`;
        }}>Apply filters</Button>
      <Button
        disabled={ _.isEmpty(filters) }
        onClick={() => {
          window.location.href = window.location.pathname;
        }}>Clear filters</Button>
    </Flex>
  )
}
