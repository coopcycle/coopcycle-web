import React, { useState } from 'react';
import { App, Button, Popconfirm, Space, Table } from 'antd';
import {
  DeleteOutlined,
  EditOutlined,
  PlusOutlined,
} from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import {
  useDeleteSkillMutation,
  useGetPlanningUsersQuery,
  useGetSkillsQuery,
} from '../../../api/slice';
import { SkillWithUsers } from '../../../api/types';
import SkillModal, { SkillModalState } from './SkillModal';

export default function SkillsManager() {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const { data: skills, isFetching } = useGetSkillsQuery();
  const { data: users } = useGetPlanningUsersQuery();
  const [deleteSkill] = useDeleteSkillMutation();

  const [modalState, setModalState] = useState<SkillModalState>(null);

  const usernameOf = (uri: string) =>
    users?.find(u => u['@id'] === uri)?.username ?? uri;

  const onDelete = async (skill: SkillWithUsers) => {
    try {
      await deleteSkill(skill['@id']).unwrap();
      message.success(t('SHIFT_PLANNING_DELETED'));
    } catch {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const columns = [
    {
      title: t('SHIFT_PLANNING_SKILL_NAME'),
      dataIndex: 'name',
      key: 'name',
    },
    {
      title: t('SHIFT_PLANNING_SKILL_TRAINED_USERS'),
      key: 'users',
      render: (_: unknown, skill: SkillWithUsers) =>
        skill.users.length > 0
          ? skill.users.map(usernameOf).join(', ')
          : '—',
    },
    {
      title: '',
      key: 'actions',
      width: 100,
      render: (_: unknown, skill: SkillWithUsers) => (
        <Space>
          <Button
            type="text"
            size="small"
            icon={<EditOutlined />}
            onClick={() => setModalState({ skill })}
          />
          <Popconfirm
            title={t('SHIFT_PLANNING_SKILL_DELETE_CONFIRM')}
            onConfirm={() => onDelete(skill)}>
            <Button type="text" size="small" danger icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div className="shift-planning__skills">
      <div className="mb-3">
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => setModalState({})}>
          {t('SHIFT_PLANNING_SKILL_NEW')}
        </Button>
      </div>
      <Table
        rowKey="@id"
        size="small"
        loading={isFetching}
        dataSource={skills ?? []}
        columns={columns}
        pagination={false}
      />
      <SkillModal
        state={modalState}
        users={users ?? []}
        onClose={() => setModalState(null)}
      />
    </div>
  );
}
