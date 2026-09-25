import { useMemo, useState } from 'react';
import Modal from 'react-modal';
import { useTranslation } from 'react-i18next';
import { Checkbox, notification } from 'antd';
import { Moment } from 'moment';

import Button from '../../../../components/core/Button';
import { prepareParams } from '../../redux/actions';
import { useChargeSepaMutation } from '../../../../api/slice';
import ExportModalContent from '../ExportModalContent';
import OrganizationsTable from '../OrganizationsTable';
import RangePicker from './RangePicker';

const ordersStates = ['new', 'accepted', 'fulfilled'];

export default () => {
  const [selectedStoreIds, setSelectedStoreIds] = useState([] as string[]);
  const [dateRange, setDateRange] = useState(null as Moment[] | null);
  const [onlyNotInvoiced, setOnlyNotInvoiced] = useState(false);

  const [reloadKey, setReloadKey] = useState(0);

  const [isModalOpen, setModalOpen] = useState(false);

  const [chargeSepa, { isLoading: isCharging }] = useChargeSepaMutation();

  const { t } = useTranslation();

  const params = useMemo(() => {
    if (selectedStoreIds.length === 0) {
      return null;
    }

    if (!dateRange) {
      return null;
    }

    return prepareParams({
      store: selectedStoreIds,
      dateRange: [
        dateRange[0].format('YYYY-MM-DD'),
        dateRange[1].format('YYYY-MM-DD'),
      ],
      state: ordersStates,
      onlyNotInvoiced: onlyNotInvoiced,
    });
  }, [selectedStoreIds, dateRange, onlyNotInvoiced]);

  return (
    // marginTop: 48px: h5 marginTop (10px) + 38px
    <div style={{ marginTop: '38px' }}>
      <h5>{t('ADMIN_ORDERS_TO_INVOICE_TITLE')}</h5>
      <div className="d-flex" style={{ marginTop: '12px', gap: '24px' }}>
        {t('ADMIN_DASHBOARD_NAV_FILTERS')}:
        <RangePicker setDateRange={setDateRange} />
        <div className="d-flex flex-column">
          {t('ADMIN_ORDERS_TO_INVOICE_FILTER_STATUS')}
          <Checkbox
            checked={onlyNotInvoiced}
            onChange={() => setOnlyNotInvoiced(!onlyNotInvoiced)}>
            {t('ADMIN_ORDERS_TO_INVOICE_FILTER_STATUS_NOT_INVOICED')}
          </Checkbox>
        </div>
        <div className="d-flex flex-column">
          {/*invisible text is used to align the Refresh button*/}
          <span style={{ visibility: 'hidden' }}>{'invisible text'}</span>
          <Button
            testID="invoicing.refresh"
            primary
            onClick={() => {
              setReloadKey(reloadKey + 1);
            }}>
            {t('ADMIN_ORDERS_TO_INVOICE_REFRESH')}
          </Button>
        </div>
      </div>
      <OrganizationsTable
        ordersStates={ordersStates}
        dateRange={dateRange}
        onlyNotInvoiced={onlyNotInvoiced}
        reloadKey={reloadKey}
        setSelectedStoreIds={setSelectedStoreIds}
      />
      <div
        className="d-flex justify-content-end"
        style={{ marginTop: '24px', gap: '12px' }}>
        <Button
          testID="invoicing.charge_sepa"
          loading={isCharging}
          onClick={() => {
            if (!params) {
              return;
            }

            chargeSepa({ params })
              .unwrap()
              .then(results => {
                const charged = results.filter(r => r.status === 'charged');
                const skipped = results.filter(
                  r => r.status === 'skipped_no_mandate',
                );
                const failed = results.filter(r => r.status === 'failed');

                if (charged.length > 0) {
                  notification.success({
                    message: t('ADMIN_ORDERS_TO_INVOICE_CHARGE_SEPA_SUCCESS', {
                      count: charged.length,
                    }),
                  });
                }
                if (skipped.length > 0) {
                  notification.warning({
                    message: t('ADMIN_ORDERS_TO_INVOICE_CHARGE_SEPA_SKIPPED', {
                      count: skipped.length,
                    }),
                  });
                }
                if (failed.length > 0) {
                  notification.error({
                    message: t('ADMIN_ORDERS_TO_INVOICE_CHARGE_SEPA_FAILED', {
                      count: failed.length,
                    }),
                  });
                }

                setReloadKey(reloadKey + 1);
              })
              .catch(() => {
                notification.error({ message: t('SOMETHING_WENT_WRONG') });
              });
          }}>
          {t('ADMIN_ORDERS_TO_INVOICE_CHARGE_SEPA')}
        </Button>
        <Button
          testID="invoicing.download"
          primary
          onClick={() => {
            if (!params) {
              return;
            }

            setModalOpen(true);
          }}>
          {t('ADMIN_ORDERS_TO_INVOICE_DOWNLOAD')}
        </Button>
      </div>
      <Modal
        isOpen={isModalOpen}
        appElement={document.getElementById('invoicing')}
        className="ReactModal__Content--no-default" // disable additional inline style from react-modal
        shouldCloseOnOverlayClick={true}
        shouldCloseOnEsc={true}
        style={{ content: { overflow: 'unset' } }}>
        <ExportModalContent
          dateRange={dateRange}
          params={params}
          setModalOpen={setModalOpen}
        />
      </Modal>
    </div>
  );
};
