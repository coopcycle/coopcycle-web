import React from 'react'
import clsx from 'clsx'

export default function CustomLink({
  title,
  href,
  onClick,
  isActive,
  rightIcon,
}) {
  return (
    <div
      className={ clsx(
        'ant-anchor-link',
        { 'ant-anchor-link-active': isActive })
      }>
      <a
        className={ clsx(
          'ant-anchor-link-title',
          { 'ant-anchor-link-title-active': isActive })
        }
        href={ href }
        onClick={ onClick }>
        <span>{ title }</span>
        { rightIcon
          ? (<>&nbsp;<i className={ clsx('fa', rightIcon) } /></>)
          : null }
      </a>
    </div>
  )
}
