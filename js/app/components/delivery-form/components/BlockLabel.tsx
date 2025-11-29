import React from 'react';
import { Typography } from 'antd';

const { Text } = Typography;

type Props = {
  label: string;
};

export default function BlockLabel({ label }: Props) {
  return (
    <div className="mb-2">
      <Text strong>{label}</Text>
    </div>
  );
}
