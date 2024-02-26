import classNames from 'classnames'
import React from 'react'

export default function CustomLink({ title, href, onClick, isActive }) {
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
        onClick={ onClick }>{ title }</a>
    </div>
  )
}
