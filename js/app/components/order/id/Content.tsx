import React, { useContext, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Map from '../../DeliveryMap';
import Itinerary from '../../DeliveryItinerary';
import { Button, Modal } from 'antd';
import { OrderHistory } from './OrderHistory';
import { Order, PutDeliveryRequest } from '../../../api/types';
import { UserContext } from '../../../UserContext';

type Props = {
  order: Order;
  delivery: PutDeliveryRequest;
};

export function Content({ order, delivery }: Props) {
  const { t } = useTranslation();
  const { isDispatcher } = useContext(UserContext);

  const [isHistoryModalOpen, setIsHistoryModalOpen] = useState(false);

  const showHistoryModal = () => {
    setIsHistoryModalOpen(true);
  };

  const handleHistoryModalClose = () => {
    setIsHistoryModalOpen(false);
  };

  return (
    <>
      <div className="py-3">
        <Button type="default" onClick={showHistoryModal}>
          {t('ADMIN_DASHBOARD_ORDER_SHOW_HISTORY')}
        </Button>
      </div>
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
      <Modal
        title={t('ADMIN_DASHBOARD_ORDER_HISTORY')}
        open={isHistoryModalOpen}
        onCancel={handleHistoryModalClose}
        footer={null}
        zIndex={1002} // Needed to show above the 'Distance' element that is shown on top of Leaflet map
        width={800}>
        <OrderHistory order={order} tasks={delivery?.tasks} />
      </Modal>
    </>
  );
}
