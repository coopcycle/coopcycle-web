import React from 'react'
import { Button as AntdButton } from 'antd'

export const Button = ({
  children,
  success,
  // danger,
  ...props
}) => {
  let className = ''

  if (success) className += 'btn-success-color'

  return (
    <AntdButton className={className} {...props}>
      {children}
    </AntdButton>
  )
}
