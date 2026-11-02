import React, { useState } from 'react';
import { Alert, Button, Divider, Select } from 'antd';
import { useTranslation } from 'react-i18next';
import { useSelector } from 'react-redux';
import { skipToken } from '@reduxjs/toolkit/query';
import AddressAutosuggest from '../../../../../components/AddressAutosuggest';
import { useGetStoreAddressesQuery } from '../../../../../api/slice';
import { selectStoreUri } from '../../../[id]/redux/incidentSlice';
import { Address, Incident, Uri } from '../../../../../api/types';

// Either the IRI of an address from the store address book, or a geocoded location
async function _handleUpdateAddressSubmit(
  id: number,
  address: Uri | Address,
  close = false,
) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.put(
    window.Routing.generate('_api_/incidents/{id}/action_put', { id }),
    {
      action: 'updated_address',
      address:
        typeof address === 'string'
          ? address
          : {
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

function _addressLabel(address: Address) {
  return address.name
    ? `${address.name} - ${address.streetAddress}`
    : address.streetAddress;
}

export default function ({ incident }: { incident: Incident }) {
  const storeUri = useSelector(selectStoreUri);
  const { data: storeAddresses } = useGetStoreAddressesQuery(
    storeUri ?? skipToken,
  );

  const [storeAddress, setStoreAddress] = useState<Uri | null>(null);
  const [address, setAddress] = useState<Address | null>(null);
  const { t } = useTranslation();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(false);

  const selected = storeAddress ?? address;

  const submit = async (close = false) => {
    if (!selected) {
      return;
    }

    setSubmitting(true);
    const { error } = await _handleUpdateAddressSubmit(
      incident.id,
      selected,
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
      {storeAddresses && storeAddresses.length > 0 && (
        <>
          <Select
            data-testid="update-address-store-select"
            style={{ width: '100%' }}
            showSearch
            allowClear
            placeholder={t('TASK_FORM_SEARCH_SAVED_ADDRESS_BY_NAME')}
            value={storeAddress}
            optionFilterProp="label"
            onChange={value => {
              setStoreAddress(value ?? null);
              setAddress(null);
            }}
            options={storeAddresses.map(storeAddress => ({
              value: storeAddress['@id'],
              label: _addressLabel(storeAddress),
            }))}
          />
          <Divider>{t('OR')}</Divider>
        </>
      )}
      <AddressAutosuggest
        address={address ?? ''}
        preciseOnly={true}
        error={error}
        onAddressSelected={(_value: string, selected: Address) => {
          setAddress(selected);
          setStoreAddress(null);
        }}
        onClear={() => setAddress(null)}
      />
      {error && (
        <Alert
          className="mt-3"
          type="error"
          showIcon
          message={t('SOMETHING_WENT_WRONG')}
        />
      )}
      <p className="mt-3">
        <Button
          disabled={selected === null || submitting}
          onClick={() => submit()}>
          {t('INCIDENTS_UPDATE_ADDRESS')}
        </Button>
      </p>
      <p>
        <Button
          danger
          disabled={selected === null || submitting}
          onClick={() => submit(true)}>
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
