import React from 'react';
import { Typography } from 'antd';

const { Link: AntdLink } = Typography;

type Props = {
  href: string;
  openInNewTab?: boolean;
  children: React.ReactNode;
  testId?: string;
};

export function Link({ children, href, testId, openInNewTab = false }: Props) {
  return (
    <AntdLink
      href={href}
      target={openInNewTab ? '_blank' : '_self'}
      data-testid={testId}>
      {children}
    </AntdLink>
  );
}
