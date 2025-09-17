import React, { useState } from 'react';
import { useDispatch } from 'react-redux';
import { RRule, rrulestr } from 'rrule';
import _ from 'lodash';
import Select from 'react-select';
import { Button, Checkbox, Collapse, Input } from 'antd';
import moment from 'moment';
import { Formik } from 'formik';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';

import TimeRange from '../../../../utils/TimeRange';
import RecurrenceRuleAsText from './RecurrenceRuleAsText';
import { closeRecurrenceModal } from '../../redux/recurrenceSlice';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import HelpIcon from '../../../HelpIcon';

type FreqOption = {
  value: number;
  label: string;
};

type ByDayOption = {
  label: string;
  value: number;
};

const { Panel } = Collapse;

const freqOptions: FreqOption[] = [
  { value: RRule.DAILY, label: 'Every day' },
  { value: RRule.WEEKLY, label: 'Every week' },
];

const locale = $('html').attr('lang') || 'en';
const weekdays = TimeRange.weekdaysShort(locale);

const byDayOptions: ByDayOption[] = weekdays.map(weekday => ({
  label: weekday.name,
  value: RRule[weekday.key.toUpperCase()].weekday,
}));

const RecurrenceEditor = ({ recurrence, onChange }) => {
  const ruleObj = rrulestr(recurrence);

  const defaultValue = _.find(
    freqOptions,
    option => option.value === ruleObj.options.freq,
  );

  return (
    <div>
      {/* It is not possible to change FREQ atm */}
      <div className="mb-4 d-none">
        <Select
          options={freqOptions}
          defaultValue={defaultValue}
          onChange={option =>
            onChange({ ...ruleObj.options, freq: option.value })
          }
        />
      </div>
      <div className="mb-2">
        <Checkbox.Group
          options={byDayOptions}
          defaultValue={ruleObj.options.byweekday}
          onChange={opts => onChange({ ...ruleObj.options, byweekday: opts })}
        />
      </div>
      <div>
        <small className="text-muted">
          <RecurrenceRuleAsText rrule={ruleObj} />
        </small>
      </div>
    </div>
  );
};

const getByDayValue = recurrenceRule => {
  const match = recurrenceRule.match(/BYDAY=([^;]+)/);
  return match ? match[1] : null;
};

const validateForm = values => {
  let errors = {};

  if (!values.rule) {
    errors.rule = 'Required';
  }

  try {
    rrulestr(values.rule);
  } catch (e) {
    errors.rule = 'Invalid recurrence rule';
  }

  return errors;
};

const defaultRecurrenceRule =
  'FREQ=WEEKLY;BYDAY=' + moment().locale('en').format('dd').toUpperCase();

const ModalContent = () => {
  const { rruleValue: recurrenceRule, setFieldValue: setSharedFieldValue } =
    useDeliveryFormFormikContext();

  const [isOverrideRule, setIsOverrideRule] = useState<boolean>(() => {
    if (!recurrenceRule) {
      return false;
    }

    // Check if it's a standard FREQ=WEEKLY;BYDAY= rule
    const ruleParts = recurrenceRule.split(';');
    if (
      ruleParts.length === 2 &&
      ruleParts.includes('FREQ=WEEKLY') &&
      ruleParts.some(part => part.startsWith('BYDAY='))
    ) {
      return false;
    }

    return true;
  });

  const { t } = useTranslation();

  const dispatch = useDispatch();

  return (
    <div data-testid="recurrence__modal__content">
      <div className="modal-header">
        <button
          type="button"
          className="close"
          onClick={() => dispatch(closeRecurrenceModal())}
          aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 className="modal-title">
          {t('ADMIN_DASHBOARD_RECURRENCE_RULE_MODAL_TITLE')}
        </h4>
      </div>
      <Formik
        initialValues={{ rule: recurrenceRule ?? defaultRecurrenceRule }}
        validate={validateForm}
        onSubmit={values => {
          let rrule;
          if (isOverrideRule) {
            rrule = values.rule.trim();
          } else {
            // If no days are selected, consider it as no recurrence
            rrule = getByDayValue(values.rule) ? values.rule : null;
          }
          setSharedFieldValue('rrule', rrule);
          dispatch(closeRecurrenceModal());
        }}
        validateOnBlur={true}
        validateOnChange={false}>
        {({ values, errors, handleSubmit, setFieldValue }) => (
          <div>
            {!isOverrideRule ? (
              <div className="p-4 border-bottom">
                <RecurrenceEditor
                  recurrence={values.rule}
                  onChange={newOpts => {
                    const cleanOpts = _.pick(newOpts, ['freq', 'byweekday']);
                    setFieldValue('rule', RRule.optionsToString(cleanOpts));
                  }}
                />
                {errors.rule && (
                  <div className="text-danger">
                    {t('RECURRENCE_RULE_DAYS_REQUIRED')}
                  </div>
                )}
              </div>
            ) : null}

            <Collapse defaultActiveKey={isOverrideRule ? ['1'] : []}>
              <Panel header={t('RECURRENCE_ADVANCED')} key="1">
                <div>
                  <Checkbox
                    data-testid="recurrence-override-rule-checkbox"
                    checked={isOverrideRule}
                    onChange={e => {
                      const isChecked = e.target.checked;

                      // Reset rule to default to prevent errors from invalid manual input
                      if (!isChecked) {
                        setFieldValue('rule', defaultRecurrenceRule);
                      }

                      setIsOverrideRule(isChecked);
                    }}>
                    {t('RECURRENCE_OVERRIDE_RULE')}
                  </Checkbox>
                  <HelpIcon
                    className="ml-1"
                    docsPath="/en/package-delivery/local-commerce/recurrence-rules/"
                  />
                </div>
                <Input
                  data-testid="recurrence-override-rule-input"
                  disabled={!isOverrideRule}
                  className="mt-2"
                  type="text"
                  value={values.rule}
                  onChange={e => {
                    setFieldValue('rule', e.target.value);
                  }}
                />
                {errors.rule && (
                  <div className="text-danger">
                    {t('RECURRENCE_RULE_INVALID')}
                  </div>
                )}
              </Panel>
            </Collapse>
            <div
              className={classNames({
                'd-flex': true,
                'p-4': true,
                'justify-content-end': true,
              })}>
              <span data-testid="save">
                <Button type="primary" size="large" onClick={handleSubmit}>
                  {t('ADMIN_DASHBOARD_TASK_FORM_SAVE')}
                </Button>
              </span>
            </div>
          </div>
        )}
      </Formik>
    </div>
  );
};

export default ModalContent;
