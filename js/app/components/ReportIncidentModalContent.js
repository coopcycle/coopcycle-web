import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  Button,
  Divider,
  Empty,
  Form,
  Input,
  List,
  notification,
  Select,
  Skeleton,
  Spin,
} from 'antd';
import moment from 'moment';
import { useTranslation } from 'react-i18next';

async function _fetchFailureReason(id) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(
    window.Routing.generate('_api_/tasks/{id}/failure_reasons_get', { id }),
  );
}

async function _fetchTaskIncidents(taskIri) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(`${taskIri}/incidents`);
}

async function _createIncident(task, data) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.post(
    window.Routing.generate('_api_/incidents{._format}_get_collection'),
    {
      task: task['@id'],
      ...data,
    },
  );
}

function FailureReasonSelector({ failureReasons, value, onChange }) {
  const { t } = useTranslation();

  return (
    <Skeleton title={false} loading={!failureReasons}>
      <Select
        placeholder={t('SELECT_REASON')}
        value={value}
        onChange={onChange}
        options={failureReasons}
      />
    </Skeleton>
  );
}

function ReportIncidentModalContent({ task }) {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [failureReasons, setFailureReasons] = useState(null);
  const [incidents, setIncidents] = useState([]);
  const [incidentsLoading, setIncidentsLoading] = useState(true);
  const [highlightedId, setHighlightedId] = useState(null);
  const listRef = useRef(null);
  const highlightTimerRef = useRef(null);
  const { t } = useTranslation();

  useEffect(() => {
    let cancelled = false;

    async function _fetchReasons() {
      const { response, error } = await _fetchFailureReason(task.id);
      if (cancelled || error) return;
      const value = (response['hydra:member'] || []).map(v => ({
        value: v.code,
        label: v.description,
      }));
      setFailureReasons(value);
    }
    async function _fetchIncidents() {
      const { response, error } = await _fetchTaskIncidents(task['@id']);
      if (cancelled) return;
      if (!error) {
        const list = (response['hydra:member'] || []).slice();
        list.sort(
          (a, b) =>
            new Date(b.createdAt || 0).getTime() -
            new Date(a.createdAt || 0).getTime(),
        );
        setIncidents(list);
      }
      setIncidentsLoading(false);
    }
    _fetchReasons();
    _fetchIncidents();

    return () => {
      cancelled = true;
    };
  }, [task.id, task['@id']]);

  useEffect(() => {
    return () => {
      if (highlightTimerRef.current) {
        clearTimeout(highlightTimerRef.current);
      }
    };
  }, []);

  useEffect(() => {
    if (highlightedId !== null && listRef.current) {
      listRef.current.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }, [highlightedId]);

  const reasonsByCode = useMemo(() => {
    const map = {};
    if (failureReasons) {
      failureReasons.forEach(r => {
        map[r.value] = r.label;
      });
    }
    return map;
  }, [failureReasons]);

  const renderReason = code => {
    if (code && reasonsByCode[code]) return reasonsByCode[code];
    if (code) return code;
    return '—';
  };

  return (
    <>
      <Form
        form={form}
        layout="vertical"
        onFinish={async data => {
          setLoading(true);
          const { response, error } = await _createIncident(task, data);
          if (!error) {
            setIncidents(prev => [response, ...prev]);
            form.resetFields();
            if (highlightTimerRef.current) {
              clearTimeout(highlightTimerRef.current);
            }
            setHighlightedId(response.id);
            highlightTimerRef.current = setTimeout(() => {
              setHighlightedId(null);
              highlightTimerRef.current = null;
            }, 1500);
          } else {
            notification.error({
              message: t('SOMETHING_WENT_WRONG'),
            });
          }
          setLoading(false);
        }}>
        <Form.Item label={t('FAILURE_REASON')} name="failureReasonCode">
          <FailureReasonSelector failureReasons={failureReasons} />
        </Form.Item>

        <Form.Item
          label="Description"
          name="description"
          style={{ marginBottom: 24 }}>
          <Input.TextArea placeholder="Description" autoSize={{ minRows: 2 }} />
        </Form.Item>
        <Form.Item className="pull-right">
          <Button type="primary" loading={loading} htmlType="submit">
            {t('REPORT')}
          </Button>
        </Form.Item>
      </Form>

      <Divider plain>
        {t('PRIOR_INCIDENTS', { count: incidents.length })}
      </Divider>

      <div ref={listRef} style={{ maxHeight: 240, overflowY: 'auto' }}>
        {incidentsLoading && incidents.length === 0 ? (
          <div
            style={{ display: 'flex', justifyContent: 'center', padding: 24 }}>
            <Spin />
          </div>
        ) : incidents.length === 0 ? (
          <Empty
            image={Empty.PRESENTED_IMAGE_SIMPLE}
            description={t('NO_INCIDENTS_YET')}
          />
        ) : (
          <List
            size="small"
            dataSource={incidents}
            renderItem={item => {
              const highlighted = item.id === highlightedId;
              const itemStyle = {
                transition: 'background-color 1.5s ease',
                backgroundColor: highlighted ? '#fff7e6' : undefined,
              };
              return (
                <List.Item
                  key={item.id}
                  style={itemStyle}
                  extra={
                    <a
                      target="_blank"
                      rel="noopener noreferrer"
                      href={window.Routing.generate('admin_incident', {
                        id: item.id,
                      })}>
                      {t('VIEW_INCIDENT')}
                      <i
                        className="fa fa-external-link ml-1"
                        style={{ fontSize: '12px' }}
                      />
                    </a>
                  }>
                  <List.Item.Meta
                    title={renderReason(item.failureReasonCode)}
                    description={
                      <>
                        {item.description && <div>{item.description}</div>}
                        {item.createdAt && (
                          <small style={{ color: '#999' }}>
                            {moment(item.createdAt).fromNow()}
                          </small>
                        )}
                      </>
                    }
                  />
                </List.Item>
              );
            }}
          />
        )}
      </div>
    </>
  );
}

export default ReportIncidentModalContent;
