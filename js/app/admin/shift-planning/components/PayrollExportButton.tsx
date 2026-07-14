import React, { useState } from 'react';
import { App, Button, DatePicker, Modal, Radio } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';
import dayjs, { Dayjs } from 'dayjs';
import { useSelector } from 'react-redux';
import { useTranslation } from 'react-i18next';
import { selectAccessToken } from '../../../entities/account/reduxSlice';

/**
 * Downloads the monthly payroll variables (planned/worked/overtime hours,
 * holiday days per employee) as CSV or XLSX.
 */
export default function PayrollExportButton() {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const accessToken = useSelector(selectAccessToken);

  const [open, setOpen] = useState(false);
  // Payroll is usually processed for the month that just ended
  const [month, setMonth] = useState<Dayjs>(() =>
    dayjs().subtract(1, 'month').startOf('month'),
  );
  const [format, setFormat] = useState<'csv' | 'xlsx'>('csv');
  const [isDownloading, setIsDownloading] = useState(false);

  const onDownload = async () => {
    setIsDownloading(true);
    try {
      const monthKey = month.format('YYYY-MM');
      const response = await fetch(
        `/api/payroll_export?month=${monthKey}&format=${format}`,
        { headers: { Authorization: `Bearer ${accessToken}` } },
      );
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `payroll_${monthKey}.${format}`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
      setOpen(false);
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    } finally {
      setIsDownloading(false);
    }
  };

  return (
    <>
      <Button icon={<DownloadOutlined />} onClick={() => setOpen(true)}>
        {t('SHIFT_PAYROLL_EXPORT')}
      </Button>
      <Modal
        open={open}
        title={t('SHIFT_PAYROLL_EXPORT_TITLE')}
        onCancel={() => setOpen(false)}
        footer={[
          <Button key="cancel" onClick={() => setOpen(false)}>
            {t('SHIFT_PLANNING_CANCEL')}
          </Button>,
          <Button
            key="download"
            type="primary"
            icon={<DownloadOutlined />}
            loading={isDownloading}
            onClick={onDownload}>
            {t('SHIFT_PAYROLL_EXPORT_DOWNLOAD')}
          </Button>,
        ]}>
        <p>{t('SHIFT_PAYROLL_EXPORT_HELP')}</p>
        <div className="report-time-modal__row">
          <span>{t('SHIFT_PAYROLL_EXPORT_MONTH')}</span>
          <DatePicker
            picker="month"
            allowClear={false}
            value={month}
            onChange={value => value && setMonth(value)}
          />
        </div>
        <div className="report-time-modal__row">
          <span>{t('SHIFT_PAYROLL_EXPORT_FORMAT')}</span>
          <Radio.Group
            value={format}
            onChange={e => setFormat(e.target.value)}
            options={[
              { value: 'csv', label: 'CSV' },
              { value: 'xlsx', label: 'XLSX' },
            ]}
            optionType="button"
          />
        </div>
      </Modal>
    </>
  );
}
