import React, { useContext, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Map from '../../DeliveryMap';
import Itinerary from '../../DeliveryItinerary';
import TransporterTimeline from '../../Transporter/TransporterTimeline';
import { Button, Modal, Space } from 'antd';
import { OrderHistory } from './OrderHistory';
import { EDIFACTMessage, Order, PutDeliveryRequest } from '../../../api/types';
import { UserContext } from '../../../UserContext';

type Props = {
  order: Order;
  delivery: PutDeliveryRequest;
  ediMessages: EDIFACTMessage[];
  importReference?: string | null;
};

export function Content({ order, delivery, ediMessages, importReference }: Props) {
  const { t } = useTranslation();
  const { isDispatcher } = useContext(UserContext);

  const [isHistoryModalOpen, setIsHistoryModalOpen] = useState(false);
  const [isTransporterModalOpen, setIsTransporterModalOpen] = useState(false);

  const showHistoryModal = () => {
    setIsHistoryModalOpen(true);
  };

  const handleHistoryModalClose = () => {
    setIsHistoryModalOpen(false);
  };

  const showTransporterModal = () => {
    setIsTransporterModalOpen(true);
  };

  const handleTransporterModalClose = () => {
    setIsTransporterModalOpen(false);
  };

  const showTransporterButton =
    isDispatcher && ediMessages.length > 0;

  return (
    <>
      {isDispatcher ? (
        <div className="py-3">
          <Space>
            <Button type="default" onClick={showHistoryModal}>
              {t('ADMIN_DASHBOARD_ORDER_SHOW_HISTORY')}
            </Button>
            {showTransporterButton ? (
              <Button type="default" onClick={showTransporterModal}>
                {t('ADMIN_DASHBOARD_ORDER_SHOW_TRANSPORTER')}
              </Button>
            ) : null}
          </Space>
        </div>
      ) : null}
      {delivery ? (
        <div>
          <Map
            defaultAddress={delivery.tasks[0].address}
            tasks={delivery.tasks}
          />
          <div className="py-3" />
          <Itinerary
            tasks={delivery.tasks}
            withTaskLinks={isDispatcher}
            withTimeRange
            withDescription
            withPackages
          />
        </div>
      ) : null}
      {isDispatcher ? (
        <Modal
          title={t('ADMIN_DASHBOARD_ORDER_HISTORY')}
          open={isHistoryModalOpen}
          onCancel={handleHistoryModalClose}
          footer={null}
          zIndex={1002} // Needed to show above the 'Distance' element that is shown on top of Leaflet map
          width={800}>
          <OrderHistory order={order} tasks={delivery?.tasks} />
        </Modal>
      ) : null}
      {showTransporterButton ? (
        <Modal
          title={t('ADMIN_DASHBOARD_ORDER_TRANSPORTER')}
          open={isTransporterModalOpen}
          onCancel={handleTransporterModalClose}
          footer={null}
          zIndex={1002} // Needed to show above the 'Distance' element that is shown on top of Leaflet map
          width={800}>
          <TransporterTimeline
            ediMessages={ediMessages}
            importReference={importReference}
          />
        </Modal>
      ) : null}
    </>
  );
}
