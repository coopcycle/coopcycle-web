import React from 'react'
import classNames from 'classnames'

export default function CustomLink({
  title,
  href,
  onClick,
  isActive,
  rightIcon,
}) {
  return (
    <div
      className={ classNames(
        'ant-anchor-link',
        { 'ant-anchor-link-active': isActive })
      }>
      <a
        className={ classNames(
          'ant-anchor-link-title',
          { 'ant-anchor-link-title-active': isActive })
        }
        href={ href }
        onClick={ onClick }>
        <span>{ title }</span>
        { rightIcon
          ? (<>&nbsp;<i className={ classNames('fa', rightIcon) } /></>)
          : null }
      </a>
    </div>
  )
}
