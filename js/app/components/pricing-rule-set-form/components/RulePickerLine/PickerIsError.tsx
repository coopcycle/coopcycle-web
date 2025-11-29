import React from 'react';
import { useTranslation } from 'react-i18next';

export default function PickerIsError() {
  const { t } = useTranslation();

  return <div>{t('SOMETHING_WENT_WRONG')}</div>;
}
