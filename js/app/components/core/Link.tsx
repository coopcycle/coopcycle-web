import React from 'react';
import { Typography } from 'antd';

const { Link: AntdLink } = Typography;

type Props = {
  href: string;
  openInNewTab?: boolean;
  children: React.ReactNode;
};

export function Link({ children, href, openInNewTab = false }: Props) {
  return (
    <AntdLink href={href} target={openInNewTab ? '_blank' : '_self'}>
      {children}
    </AntdLink>
  );
}
