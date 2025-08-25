import React from 'react';
import { useTranslation } from 'react-i18next';

export default function PickerIsLoading() {
  const { t } = useTranslation();

  return <div>{t('LOADING')}</div>;
}
