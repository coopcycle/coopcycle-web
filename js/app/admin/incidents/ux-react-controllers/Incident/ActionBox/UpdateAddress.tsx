import React, { useState } from 'react';
import { Alert, Button, Descriptions, Skeleton } from 'antd';
import { useTranslation } from 'react-i18next';
import { useSelector } from 'react-redux';
import { skipToken } from '@reduxjs/toolkit/query';
import AddressAutosuggest from '../../../../../components/AddressAutosuggest';
import { useGetStoreAddressesQuery } from '../../../../../api/slice';
import { selectStoreUri } from '../../../[id]/redux/incidentSlice';
import { Address, Incident, Task } from '../../../../../api/types';

// A saved address of the store is sent as an IRI, any other one as a location
async function _handleUpdateAddressSubmit(
  id: number,
  address: Address,
  close = false,
) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.put(
    window.Routing.generate('_api_/incidents/{id}/action_put', { id }),
    {
      action: 'updated_address',
      address: address['@id'] ?? {
        streetAddress: address.streetAddress,
        postalCode: address.postalCode,
        addressLocality: address.addressLocality,
        latitude: address.geo.latitude,
        longitude: address.geo.longitude,
      },
      close,
    },
  );
}

// The address as sent by the transporter, when the incident was reported on import
function _transporterAddress(incident: Incident): string | null {
  const metadata = Array.isArray(incident.metadata)
    ? incident.metadata
    : [incident.metadata];

  const item = metadata.find(m => m?.transporter_address);

  return item?.transporter_address ?? null;
}

// Saved addresses are precise, the geocoder tells for the other ones
function _isPrecise(address: Address & { isPrecise?: boolean }) {
  return address.isPrecise !== false;
}

export default function ({
  incident,
  task,
}: {
  incident: Incident;
  task: Task;
}) {
  const storeUri = useSelector(selectStoreUri);
  const { data: storeAddresses, isLoading } = useGetStoreAddressesQuery(
    storeUri ?? skipToken,
  );

  const transporterAddress = _transporterAddress(incident);

  const [address, setAddress] = useState<Address | null>(null);
  // The field does not show an address picked on the map, so it is mounted again with it
  const [fieldAddress, setFieldAddress] = useState<Partial<Address>>({
    streetAddress: transporterAddress ?? '',
  });
  const [fieldKey, setFieldKey] = useState(0);
  const { t } = useTranslation();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(false);

  const isPrecise = address ? _isPrecise(address) : true;
  const canSubmit = address !== null && isPrecise && !submitting;

  const submit = async (close = false) => {
    if (!address) {
      return;
    }

    setSubmitting(true);
    const { error } = await _handleUpdateAddressSubmit(
      incident.id,
      address,
      close,
    );
    if (!error) {
      location.reload();
    } else {
      setError(true);
      setSubmitting(false);
    }
  };

  return (
    <div data-testid="update-address-form">
      <Descriptions column={1} size="small" className="mb-3">
        <Descriptions.Item label={t('INCIDENTS_CURRENT_ADDRESS')}>
          <span data-testid="update-address-current">
            {task.address?.streetAddress}
          </span>
        </Descriptions.Item>
        {transporterAddress && (
          <Descriptions.Item label={t('INCIDENTS_TRANSPORTER_ADDRESS')}>
            <span data-testid="update-address-transporter">
              {transporterAddress}
            </span>
          </Descriptions.Item>
        )}
      </Descriptions>
      {/* Saved addresses are only read when the field is mounted */}
      {isLoading ? (
        <Skeleton.Input active block />
      ) : (
        // Typing again discards the address picked before;
        // picking a suggestion does not fire an input event.
        <div onInput={() => setAddress(null)}>
          <AddressAutosuggest
            key={fieldKey}
            address={fieldAddress}
            addresses={storeAddresses ?? []}
            // Only the saved addresses that look like what was typed
            fuseOptions={{ threshold: 0.4, minMatchCharLength: 2 }}
            fuseSearchOptions={{ limit: 5 }}
            mapPickerEnabled={true}
            error={error || !isPrecise}
            onAddressSelected={(value: string, selected: Address) => {
              setAddress(selected);
              if (value === '[MAP_PICKER]') {
                setFieldAddress(selected);
                setFieldKey(key => key + 1);
              }
            }}
            onClear={() => setAddress(null)}
          />
        </div>
      )}
      {address && !isPrecise && (
        <Alert
          className="mt-3"
          type="warning"
          showIcon
          message={t('CART_ADDRESS_NOT_ENOUGH_PRECISION')}
        />
      )}
      {address && isPrecise && (
        <Alert
          className="mt-3"
          type="info"
          data-testid="update-address-new"
          message={t('INCIDENTS_NEW_ADDRESS')}
          description={
            address.name
              ? `${address.name} - ${address.streetAddress}`
              : address.streetAddress
          }
        />
      )}
      {error && (
        <Alert
          className="mt-3"
          type="error"
          showIcon
          message={t('SOMETHING_WENT_WRONG')}
        />
      )}
      <p className="mt-3">
        <Button disabled={!canSubmit} onClick={() => submit()}>
          {t('INCIDENTS_UPDATE_ADDRESS')}
        </Button>
      </p>
      <p>
        <Button danger disabled={!canSubmit} onClick={() => submit(true)}>
          {t('ADMIN_DASHBOARD_AND_CLOSE_THE_INCIDENT', {
            action: t('INCIDENTS_UPDATE_ADDRESS'),
            // Rendered as text by React, which already escapes it
            interpolation: { escapeValue: false },
          })}
        </Button>
      </p>
    </div>
  );
}
