import React from 'react'
import { Col, Divider, Row } from 'antd'

export const TopNav = ({ children }) => {
  return (
    <>
      <Row>
        <Col span={24}>
          <h3>{children}</h3>
        </Col>
      </Row>
      {/* marginTop = h3 marginTop (20px) - h3 marginBottom (10px) + the margin added by the breadcrumb (20px)  */}
      <Divider style={{ marginTop: '30px', marginBottom: '0px' }} />
    </>
  )
}
