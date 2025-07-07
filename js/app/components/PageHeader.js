import React from 'react'
import {
  Row,
  Col,
  Space,
  Button,
  Avatar,
  Tag,
  Breadcrumb,
  Typography,
  Divider,
} from 'antd'
import { ArrowLeftOutlined } from '@ant-design/icons'

const { Title, Text } = Typography

const PageHeader = ({
  title,
  subTitle,
  onBack,
  backIcon = <ArrowLeftOutlined />,
  extra,
  tags,
  avatar,
  breadcrumb,
  children,
  className,
  style,
  ghost = true,
  footer,
  ...restProps
}) => {
  const renderBreadcrumb = () => {
    if (!breadcrumb) return null

    if (breadcrumb.routes) {
      const items = breadcrumb.routes.map((route, index) => ({
        key: index,
        title: route.breadcrumbName,
        href: route.path,
      }))
      return <Breadcrumb items={items} {...breadcrumb} />
    }

    return <Breadcrumb {...breadcrumb} />
  }

  const renderBack = () => {
    if (!onBack) return null

    return (
      <Button
        type="text"
        size="small"
        icon={backIcon}
        onClick={onBack}
        style={{ marginRight: 16 }}
      />
    )
  }

  const renderAvatar = () => {
    if (!avatar) return null

    return (
      <Avatar
        {...(typeof avatar === 'object' ? avatar : { src: avatar })}
        style={{ marginRight: 16 }}
      />
    )
  }

  const renderTitle = () => {
    if (!title) return null

    return (
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          marginBottom: subTitle ? 4 : 0,
        }}>
        {renderBack()}
        {renderAvatar()}
        <Title
          level={4}
          style={{ margin: 0, fontSize: '20px', fontWeight: 600 }}>
          {title}
        </Title>
        {tags && (
          <Space style={{ marginLeft: 12 }}>
            {Array.isArray(tags)
              ? tags.map((tag, index) => (
                <Tag
                  key={index}
                  {...(typeof tag === 'object' ? tag : { children: tag })}
                />
              ))
              : tags}
          </Space>
        )}
      </div>
    )
  }

  const renderSubTitle = () => {
    if (!subTitle) return null

    return (
      <Text type="secondary" style={{ fontSize: '14px' }}>
        {subTitle}
      </Text>
    )
  }

  const renderExtra = () => {
    if (!extra) return null

    return <Space>{Array.isArray(extra) ? extra : [extra]}</Space>
  }

  const headerStyle = {
    padding: '16px 24px',
    backgroundColor: ghost ? 'transparent' : '#fff',
    borderBottom: ghost ? 'none' : '1px solid #f0f0f0',
    ...style,
  }

  return (
    <div
      className={`page-header ${className || ''}`}
      style={headerStyle}
      {...restProps}>
      {breadcrumb && (
        <div style={{ marginBottom: 16 }}>{renderBreadcrumb()}</div>
      )}

      <Row justify="space-between" align="top">
        <Col flex="auto">
          {renderTitle()}
          {renderSubTitle()}
        </Col>

        {extra && <Col flex="none">{renderExtra()}</Col>}
      </Row>

      {children && <div style={{ marginTop: 16 }}>{children}</div>}

      {footer && (
        <>
          <Divider style={{ margin: '16px 0' }} />
          <div>{footer}</div>
        </>
      )}
    </div>
  )
}

export default PageHeader
