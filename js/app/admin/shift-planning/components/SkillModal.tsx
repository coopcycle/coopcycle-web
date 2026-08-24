import React, { useEffect } from 'react';
import { App, Button, Form, Input, Modal, Select } from 'antd';
import { useTranslation } from 'react-i18next';
import {
  usePostSkillMutation,
  usePutSkillMutation,
} from '../../../api/slice';
import { PlanningUser, SkillWithUsers, Uri } from '../../../api/types';

export type SkillModalState = { skill?: SkillWithUsers } | null;

type Props = {
  state: SkillModalState;
  users: PlanningUser[];
  onClose: () => void;
};

type FormValues = {
  name: string;
  users: Uri[];
};

export default function SkillModal({ state, users, onClose }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();

  const [postSkill, { isLoading: isCreating }] = usePostSkillMutation();
  const [putSkill, { isLoading: isUpdating }] = usePutSkillMutation();

  const skill = state?.skill;

  useEffect(() => {
    if (!state) {
      return;
    }
    form.setFieldsValue({
      name: state.skill?.name ?? '',
      users: state.skill?.users ?? [],
    });
  }, [state, form]);

  const onFinish = async (values: FormValues) => {
    try {
      if (skill) {
        await putSkill({
          '@id': skill['@id'],
          name: values.name,
          users: values.users,
        }).unwrap();
      } else {
        await postSkill({ name: values.name, users: values.users }).unwrap();
      }
      message.success(t('SHIFT_PLANNING_SAVED'));
      onClose();
    } catch {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Modal
      open={state !== null}
      title={skill ? t('SHIFT_PLANNING_SKILL_EDIT') : t('SHIFT_PLANNING_SKILL_NEW')}
      onCancel={onClose}
      destroyOnHidden
      footer={[
        <Button
          key="submit"
          type="primary"
          loading={isCreating || isUpdating}
          onClick={() => form.submit()}>
          {t('SHIFT_PLANNING_SAVE')}
        </Button>,
      ]}>
      <Form form={form} layout="vertical" onFinish={onFinish}>
        <Form.Item
          name="name"
          label={t('SHIFT_PLANNING_SKILL_NAME')}
          rules={[{ required: true }]}>
          <Input maxLength={255} />
        </Form.Item>
        <Form.Item name="users" label={t('SHIFT_PLANNING_SKILL_TRAINED_USERS')}>
          <Select
            mode="multiple"
            optionFilterProp="label"
            placeholder={t('SHIFT_PLANNING_SKILL_TRAINED_USERS_PLACEHOLDER')}
            options={users.map(u => ({
              value: u['@id'],
              label: u.username,
            }))}
          />
        </Form.Item>
      </Form>
    </Modal>
  );
}
