import React, { useState } from 'react'
import { Modal, DatePicker, Card } from 'antd'

export default function DeliveriesProofs() {
  const [visible, setVisible] = useState(false)

  return (
    <>
      <a href="#" onClick={() => setVisible(true)}>
        <i class="fa fa-flag-checkered mr-2"></i>
        <span>Deliveries proofs</span>
      </a>
      <Modal
        title="Download Proof of Deliveries"
        open={visible}
        onCancel={() => setVisible(false)}
        okText="Download">
        <p>Select week: <DatePicker picker="week" /></p>
        <Card>
          <div className="d-flex align-items-center justify-content-between">
            <strong>Deliveries</strong>
            <span>7 items</span>
          </div>
          <div className="d-flex align-items-center justify-content-between">
            <strong>Includes</strong>
            <span>Proofs + Report</span>
          </div>
          <div className="d-flex align-items-center justify-content-between">
            <strong>Format</strong>
            <span>ZIP file</span>
          </div>
        </Card>
      </Modal>
    </>
  )
}
