import React, { useEffect, useMemo } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { App, Button, Col, Collapse, Flex, Row } from 'antd';
import { useIncidentActionMutation } from '../../../../api/slice';
import { selectIncident } from '../redux/incidentSlice';
import {
  IncidentEvent,
  IncidentMetadataSuggestion,
} from '../../../../api/types';
import { useTranslation } from 'react-i18next';
import { PriceChangeSuggestion } from './PriceChangeSuggestion';
import { usePriceChangeSuggestion } from '../hooks/usePriceChangeSuggestion';
import { JsonViewer } from '../../../../components/JsonViewer';
import { useSuggestionPreview } from '../hooks/useSuggestionPreview';

type Props = {
  event: IncidentEvent;
};

export const OrderDetailsSuggestion = ({ event }: Props) => {
  const { t } = useTranslation();
  const dispatch = useDispatch();

  const { notification } = App.useApp();

  const incident = useSelector(selectIncident);

  const suggestion = useMemo(() => {
    const suggestionObj = event.metadata.find(el => Boolean(el.suggestion));

    return suggestionObj.suggestion as IncidentMetadataSuggestion;
  }, [event.metadata]);

  const suggestionPreview = useSuggestionPreview(suggestion);

  const {
    storeUri,
    isLoading,
    error,
    existingOrder,
    suggestedOrder,
    suggestionPriceDiff,
    diff,
  } = usePriceChangeSuggestion(suggestion);

  const [
    incidentAction,
    {
      isLoading: isActionLoading,
      isSuccess: isActionSuccess,
      isError: isActionError,
      data: actionData,
    },
  ] = useIncidentActionMutation();

  const isButtonDisabled = isActionLoading;

  const handleAcceptSuggestion = async () => {
    if (!incident?.id) return;

    await incidentAction({
      incidentId: incident.id,
      action: 'accepted_suggestion',
      diff: suggestionPriceDiff,
    });
  };

  const handleRejectSuggestion = async () => {
    if (!incident?.id) return;

    await incidentAction({
      incidentId: incident.id,
      action: 'rejected_suggestion',
      diff: suggestionPriceDiff,
    });
  };

  useEffect(() => {
    if (isActionSuccess) {
      //FIXME: update redux state instead of reloading the entire page
      window.location.reload();

      notification.success({
        message: t('INCIDENTS_ACTION_COMPLETED_SUCCESSFULLY'),
      });
    }
    if (isActionError) {
      notification.error({
        message: t('INCIDENTS_ACTION_FAILED'),
      });
    }
  }, [
    isActionSuccess,
    isActionError,
    actionData?.events,
    dispatch,
    notification,
    t,
  ]);

  return (
    <Flex vertical gap="middle" data-testid="suggestion-content">
      <Row>
        <Col span={24}>
          <Collapse
            defaultActiveKey={['suggestion-preview']}
            items={[
              {
                key: 'suggestion-preview',
                label: t('INCIDENTS_SUGGESTED_CHANGES'),
                children: <JsonViewer data={suggestionPreview} />,
              },
            ]}
          />
        </Col>
      </Row>
      {
        // price change suggestion only makes sense for local commerce and last mile orders
        storeUri ? (
          <PriceChangeSuggestion
            isLoading={isLoading}
            error={error}
            existingOrder={existingOrder}
            suggestedOrder={suggestedOrder}
            suggestionPriceDiff={suggestionPriceDiff}
            diff={diff}
          />
        ) : null
      }
      <Row gutter={16}>
        <Col span={12}>
          <Button
            data-testid="suggestion-reject-button"
            danger
            block
            onClick={handleRejectSuggestion}
            disabled={isButtonDisabled}
            loading={isActionLoading}>
            {t('INCIDENTS_REFUSE_SUGGESTIONS')}
          </Button>
        </Col>
        <Col span={12}>
          <Button
            data-testid="suggestion-accept-button"
            type="primary"
            block
            onClick={handleAcceptSuggestion}
            disabled={isButtonDisabled}
            loading={isActionLoading}>
            {t('INCIDENTS_APPLY_SUGGESTIONS')}
          </Button>
        </Col>
      </Row>
    </Flex>
  );
};
