import React, { useState } from 'react';
import { Button, Modal, Typography } from 'antd';
import { CalendarOutlined, DownloadOutlined } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import { useGetShiftCalendarQuery } from '../../../api/slice';

/**
 * Lets the user subscribe to their personal shifts iCalendar feed from
 * Google Calendar / Apple Calendar / Outlook, or download a one-off .ics.
 */
export default function CalendarSyncButton() {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);

  const { data, isFetching } = useGetShiftCalendarQuery(undefined, {
    skip: !open,
  });

  return (
    <>
      <Button icon={<CalendarOutlined />} onClick={() => setOpen(true)}>
        {t('SHIFT_CALENDAR_SYNC')}
      </Button>
      <Modal
        open={open}
        title={t('SHIFT_CALENDAR_SYNC_TITLE')}
        onCancel={() => setOpen(false)}
        footer={[
          <Button
            key="download"
            icon={<DownloadOutlined />}
            disabled={!data}
            href={data?.feedUrl}
            download="shifts.ics">
            {t('SHIFT_CALENDAR_DOWNLOAD')}
          </Button>,
          <Button key="close" type="primary" onClick={() => setOpen(false)}>
            {t('SHIFT_CALENDAR_CLOSE')}
          </Button>,
        ]}>
        <p>{t('SHIFT_CALENDAR_SYNC_HELP')}</p>
        <Typography.Paragraph
          copyable={data ? { text: data.feedUrl } : false}
          className="shift-calendar-feed-url">
          <code>{isFetching || !data ? '…' : data.feedUrl}</code>
        </Typography.Paragraph>
        <p className="text-muted">{t('SHIFT_CALENDAR_SYNC_GOOGLE_HINT')}</p>
        <p className="text-muted">{t('SHIFT_CALENDAR_SYNC_PRIVACY')}</p>
      </Modal>
    </>
  );
}
