import { useEffect, useMemo, useState } from 'react';
import Modal from 'react-modal';
import { useTranslation } from 'react-i18next';
import { Alert, Checkbox, Radio } from 'antd';
import { Moment } from 'moment';

import Button from '../../../../components/core/Button';
import { prepareParams, SettlementFilter } from '../../redux/actions';
import { useGetPendingInvoiceLineItemsQuery } from '../../../../api/slice';
import {
  dateRangeFromParams,
  syncDateRangeToUrl,
} from '../../utils/dateRangeUrl';
import ExportModalContent from '../ExportModalContent';
import OrganizationsTable from '../OrganizationsTable';
import RangePicker from './RangePicker';

export default () => {
  const [selectedOrganizationIds, setSelectedOrganizationIds] = useState(
    [] as string[],
  );
  const [dateRange, setDateRange] = useState<Moment[] | null>(() =>
    dateRangeFromParams(window.location.search),
  );
  const [onlyNotInvoiced, setOnlyNotInvoiced] = useState(false);
  const [settlement, setSettlement] = useState<SettlementFilter>('all');

  useEffect(() => {
    if (dateRange) {
      syncDateRangeToUrl(dateRange);
    }
  }, [dateRange]);

  const [reloadKey, setReloadKey] = useState(0);

  const [isModalOpen, setModalOpen] = useState(false);

  const { t } = useTranslation();

  // Orders left in an intermediary state are excluded from invoicing
  // server-side; warn rather than silently under-invoicing
  const pendingParams = useMemo(() => {
    if (!dateRange) {
      return null;
    }

    return prepareParams({
      dateRange: [
        dateRange[0].format('YYYY-MM-DD'),
        dateRange[1].format('YYYY-MM-DD'),
      ],
      onlyNotInvoiced: false,
    });
  }, [dateRange]);

  const { data: pendingData } = useGetPendingInvoiceLineItemsQuery(
    { params: pendingParams as string[] },
    { skip: !pendingParams },
  );

  const pendingCount = pendingData?.['hydra:totalItems'] ?? 0;

  const params = useMemo(() => {
    if (selectedOrganizationIds.length === 0) {
      return null;
    }

    if (!dateRange) {
      return null;
    }

    return prepareParams({
      organization: selectedOrganizationIds,
      dateRange: [
        dateRange[0].format('YYYY-MM-DD'),
        dateRange[1].format('YYYY-MM-DD'),
      ],
      onlyNotInvoiced: onlyNotInvoiced,
      settlement: settlement,
    });
  }, [selectedOrganizationIds, dateRange, onlyNotInvoiced, settlement]);

  return (
    // marginTop: 48px: h5 marginTop (10px) + 38px
    <div style={{ marginTop: '38px' }}>
      <h5>{t('ADMIN_ORDERS_TO_INVOICE_TITLE')}</h5>
      <div className="d-flex" style={{ marginTop: '12px', gap: '24px' }}>
        {t('ADMIN_DASHBOARD_NAV_FILTERS')}:
        <RangePicker initialDateRange={dateRange} setDateRange={setDateRange} />
        <div className="d-flex flex-column">
          {t('ADMIN_ORDERS_TO_INVOICE_FILTER_STATUS')}
          <Checkbox
            checked={onlyNotInvoiced}
            onChange={() => setOnlyNotInvoiced(!onlyNotInvoiced)}>
            {t('ADMIN_ORDERS_TO_INVOICE_FILTER_STATUS_NOT_INVOICED')}
          </Checkbox>
        </div>
        <div className="d-flex flex-column">
          {t('ADMIN_ORDERS_TO_INVOICE_FILTER_SETTLEMENT')}
          <Radio.Group
            value={settlement}
            onChange={e => setSettlement(e.target.value)}
            optionType="button">
            <Radio.Button value="all">
              {t('ADMIN_ORDERS_TO_INVOICE_SETTLEMENT_ALL')}
            </Radio.Button>
            <Radio.Button value="needs_invoicing">
              {t('ADMIN_ORDERS_TO_INVOICE_SETTLEMENT_NEEDS_INVOICING')}
            </Radio.Button>
            <Radio.Button value="settled">
              {t('ADMIN_ORDERS_TO_INVOICE_SETTLEMENT_SETTLED')}
            </Radio.Button>
          </Radio.Group>
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
      {pendingCount > 0 && (
        <Alert
          style={{ marginTop: '24px' }}
          type="warning"
          showIcon
          message={t('ADMIN_ORDERS_TO_INVOICE_PENDING_ORDERS_WARNING', {
            count: pendingCount,
          })}
        />
      )}
      <OrganizationsTable
        dateRange={dateRange}
        onlyNotInvoiced={onlyNotInvoiced}
        settlement={settlement}
        reloadKey={reloadKey}
        setSelectedOrganizationIds={setSelectedOrganizationIds}
      />
      <div className="d-flex justify-content-end" style={{ marginTop: '24px' }}>
        <Button
          testID="invoicing.download"
          primary
          // `params` is null until at least one organization is selected
          disabled={!params}
          onClick={() => {
            setModalOpen(true);
          }}>
          {t('ADMIN_ORDERS_TO_INVOICE_DOWNLOAD')}
        </Button>
      </div>
      <Modal
        isOpen={isModalOpen}
        appElement={document.getElementById('invoicing')}
        className="ReactModal__Content--no-default" // disable additional inline style from react-modal
        // The overlay has no z-index of its own, so a checked antd Radio.Button
        // (z-index: 1) on the page behind would paint on top of the modal
        overlayClassName="ReactModal__Overlay ReactModal__Overlay--zIndex-1001"
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
