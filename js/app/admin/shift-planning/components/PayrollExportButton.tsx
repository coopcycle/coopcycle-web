import React, { useState } from 'react';
import { App, Button, DatePicker, Modal, Radio } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';
import dayjs, { Dayjs } from 'dayjs';
import { useSelector } from 'react-redux';
import { useTranslation } from 'react-i18next';
import { selectAccessToken } from '../../../entities/account/reduxSlice';
import { datePickerProps } from '../../../utils/antd';

type RangeMode = 'month' | 'range';

/**
 * Downloads payroll variables (planned/worked/overtime hours, holiday days
 * per employee) as CSV or XLSX, either for a whole month or a custom date
 * range — see PayrollExportController.
 */
export default function PayrollExportButton() {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const accessToken = useSelector(selectAccessToken);

  const [open, setOpen] = useState(false);
  const [rangeMode, setRangeMode] = useState<RangeMode>('month');
  // Payroll is usually processed for the month that just ended
  const [month, setMonth] = useState<Dayjs>(() =>
    dayjs().subtract(1, 'month').startOf('month'),
  );
  const [range, setRange] = useState<[Dayjs, Dayjs] | null>(null);
  const [format, setFormat] = useState<'csv' | 'xlsx'>('csv');
  const [isDownloading, setIsDownloading] = useState(false);

  const isRangeValid = rangeMode === 'month' || (range?.[0] && range?.[1]);

  const onDownload = async () => {
    setIsDownloading(true);
    try {
      let query: string;
      let filenameStem: string;

      if (rangeMode === 'range' && range) {
        const after = range[0].format('YYYY-MM-DD');
        const before = range[1].format('YYYY-MM-DD');
        query = `after=${after}&before=${before}`;
        filenameStem = `${after}_${before}`;
      } else {
        const monthKey = month.format('YYYY-MM');
        query = `month=${monthKey}`;
        filenameStem = monthKey;
      }

      const response = await fetch(
        `/api/payroll_export?${query}&format=${format}`,
        { headers: { Authorization: `Bearer ${accessToken}` } },
      );
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `payroll_${filenameStem}.${format}`;
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
      <Button
        type="text"
        icon={<DownloadOutlined />}
        onClick={() => setOpen(true)}>
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
            disabled={!isRangeValid}
            onClick={onDownload}>
            {t('SHIFT_PAYROLL_EXPORT_DOWNLOAD')}
          </Button>,
        ]}>
        <p>{t('SHIFT_PAYROLL_EXPORT_HELP')}</p>
        <div className="report-time-modal__row">
          <span>{t('SHIFT_PAYROLL_EXPORT_PERIOD')}</span>
          <Radio.Group
            value={rangeMode}
            onChange={e => setRangeMode(e.target.value)}
            options={[
              { value: 'month', label: t('SHIFT_PAYROLL_EXPORT_PERIOD_MONTH') },
              { value: 'range', label: t('SHIFT_PAYROLL_EXPORT_PERIOD_RANGE') },
            ]}
            optionType="button"
          />
        </div>
        {rangeMode === 'month' ? (
          <div className="report-time-modal__row">
            <span>{t('SHIFT_PAYROLL_EXPORT_MONTH')}</span>
            <DatePicker
              picker="month"
              allowClear={false}
              value={month}
              onChange={value => value && setMonth(value)}
            />
          </div>
        ) : (
          <div className="report-time-modal__row">
            <span>{t('SHIFT_PAYROLL_EXPORT_RANGE')}</span>
            <DatePicker.RangePicker
              allowClear={false}
              format={datePickerProps.format}
              value={range}
              onChange={value =>
                setRange(value && value[0] && value[1] ? [value[0], value[1]] : null)
              }
            />
          </div>
        )}
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
