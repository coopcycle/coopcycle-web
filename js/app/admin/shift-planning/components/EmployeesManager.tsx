import React, { useEffect, useMemo, useState } from 'react';
import {
  App,
  Button,
  DatePicker,
  Drawer,
  Form,
  Input,
  InputNumber,
  Select,
  Table,
  Tag,
} from 'antd';
import { SearchOutlined } from '@ant-design/icons';
import dayjs, { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import {
  useGetEmployeeProfilesQuery,
  useGetPlanningUsersQuery,
  useGetSkillsQuery,
  usePostEmployeeProfileMutation,
  usePutEmployeeProfileMutation,
  usePutSkillMutation,
} from '../../../api/slice';
import { EmployeeProfile, PlanningUser, Uri } from '../../../api/types';
import Avatar from '../../../components/Avatar';
import { datePickerProps } from '../../../utils/antd';

type FormValues = {
  skills: Uri[];
  contractStartDate: Dayjs | null;
  dateOfBirth: Dayjs | null;
  addressStreet: string | null;
  addressPostalCode: string | null;
  addressLocality: string | null;
  addressCountry: string | null;
  salaryType: 'hourly' | 'monthly' | null;
  salaryAmount: number | null;
  weeklyContractedHours: number | null;
};

const fullNameOf = (user: PlanningUser): string =>
  [user.givenName, user.familyName].filter(Boolean).join(' ');

// Date fields serialize with the server's UTC offset; keep the calendar date
// as-is instead of letting dayjs shift it into the browser's timezone
const asDate = (iso: string): Dayjs => dayjs(iso.slice(0, 10));

/**
 * HR view of the planning: searchable list of employees with their skills,
 * and a drawer to edit the employee profile (contract, address, salary) and
 * skill assignments — same data as the legacy "HR" tab on the user page,
 * centralized in the planning.
 */
export default function EmployeesManager() {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();

  const { data: users, isFetching } = useGetPlanningUsersQuery();
  const { data: profiles } = useGetEmployeeProfilesQuery();
  const { data: skills } = useGetSkillsQuery();

  const [postProfile] = usePostEmployeeProfileMutation();
  const [putProfile] = usePutEmployeeProfileMutation();
  const [putSkill] = usePutSkillMutation();
  const [isSaving, setIsSaving] = useState(false);

  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState<PlanningUser | null>(null);

  const profileByUser = useMemo(() => {
    const map = new Map<Uri, EmployeeProfile>();
    (profiles ?? []).forEach(p => map.set(p.user, p));
    return map;
  }, [profiles]);

  const filteredUsers = useMemo(() => {
    const needle = search.trim().toLowerCase();
    const sorted = [...(users ?? [])].sort((a, b) =>
      a.username.localeCompare(b.username),
    );
    if (!needle) {
      return sorted;
    }
    return sorted.filter(user =>
      [
        user.username,
        fullNameOf(user),
        ...(user.skills ?? []).map(s => s.name),
      ]
        .join(' ')
        .toLowerCase()
        .includes(needle),
    );
  }, [users, search]);

  // The drawer can open before the profiles query has resolved; seed the form
  // once both the selection and the data are there, otherwise saving a
  // not-yet-seeded form would overwrite the profile with blanks
  const profilesLoaded = profiles !== undefined;
  useEffect(() => {
    if (!selected || !profilesLoaded) {
      return;
    }
    const user = selected;
    const profile = profileByUser.get(user['@id']);
    form.setFieldsValue({
      skills: (user.skills ?? []).map(s => s['@id']),
      contractStartDate: profile?.contractStartDate
        ? asDate(profile.contractStartDate)
        : null,
      dateOfBirth: profile?.dateOfBirth ? asDate(profile.dateOfBirth) : null,
      addressStreet: profile?.addressStreet ?? null,
      addressPostalCode: profile?.addressPostalCode ?? null,
      addressLocality: profile?.addressLocality ?? null,
      addressCountry: profile?.addressCountry ?? null,
      salaryType: profile?.salaryType ?? null,
      salaryAmount: profile?.salaryAmount
        ? parseFloat(profile.salaryAmount)
        : null,
      weeklyContractedHours: profile?.weeklyContractedHours
        ? parseFloat(profile.weeklyContractedHours)
        : null,
    });
    // Reseed when the selection changes or when profiles first arrive; a
    // later background refetch must not clobber in-progress edits
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selected, profilesLoaded]);

  const onSave = async () => {
    if (!selected) {
      return;
    }
    const values = await form.validateFields();
    setIsSaving(true);
    try {
      const payload = {
        user: selected['@id'],
        contractStartDate:
          values.contractStartDate?.format('YYYY-MM-DD') ?? null,
        dateOfBirth: values.dateOfBirth?.format('YYYY-MM-DD') ?? null,
        addressStreet: values.addressStreet || null,
        addressPostalCode: values.addressPostalCode || null,
        addressLocality: values.addressLocality || null,
        addressCountry: values.addressCountry || null,
        salaryType: values.salaryType ?? null,
        salaryAmount:
          values.salaryAmount != null ? String(values.salaryAmount) : null,
        weeklyContractedHours:
          values.weeklyContractedHours != null
            ? String(values.weeklyContractedHours)
            : null,
      };

      const existing = profileByUser.get(selected['@id']);
      if (existing) {
        await putProfile({ '@id': existing['@id'], ...payload }).unwrap();
      } else {
        await postProfile(payload).unwrap();
      }

      // Sync skill assignments: skills are the owning side, so update every
      // skill whose membership for this user changed
      const wanted = new Set(values.skills);
      const updates = (skills ?? [])
        .filter(skill => {
          const had = skill.users.includes(selected['@id']);
          return had !== wanted.has(skill['@id']);
        })
        .map(skill =>
          putSkill({
            '@id': skill['@id'],
            name: skill.name,
            users: wanted.has(skill['@id'])
              ? [...skill.users, selected['@id']]
              : skill.users.filter(u => u !== selected['@id']),
          }).unwrap(),
        );
      await Promise.all(updates);

      message.success(t('SHIFT_PLANNING_SAVED'));
      setSelected(null);
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    } finally {
      setIsSaving(false);
    }
  };

  const columns = [
    {
      title: t('SHIFT_HR_EMPLOYEE'),
      key: 'employee',
      render: (_: unknown, user: PlanningUser) => (
        <span className="shift-planning__user">
          <Avatar username={user.username} size="24" />
          <span className="shift-planning__user-names">
            <span>{user.username}</span>
            {fullNameOf(user) && (
              <span className="shift-planning__user-fullname">
                {fullNameOf(user)}
              </span>
            )}
          </span>
        </span>
      ),
    },
    {
      title: t('SHIFT_PLANNING_VIEW_SKILLS'),
      key: 'skills',
      render: (_: unknown, user: PlanningUser) =>
        (user.skills ?? []).map(skill => (
          <Tag key={skill['@id']}>{skill.name}</Tag>
        )),
    },
    {
      title: t('SHIFT_HR_CONTRACTED_HOURS'),
      key: 'contractedHours',
      render: (_: unknown, user: PlanningUser) => {
        const hours = profileByUser.get(user['@id'])?.weeklyContractedHours;
        return hours ? `${parseFloat(hours)}h` : '—';
      },
    },
    {
      title: t('SHIFT_HR_CONTRACT_START'),
      key: 'contractStart',
      render: (_: unknown, user: PlanningUser) => {
        const date = profileByUser.get(user['@id'])?.contractStartDate;
        return date ? asDate(date).format('DD MMM YYYY') : '—';
      },
    },
  ];

  return (
    <div className="shift-planning__employees">
      <Input
        allowClear
        prefix={<SearchOutlined />}
        placeholder={t('SHIFT_HR_SEARCH_PLACEHOLDER')}
        value={search}
        onChange={e => setSearch(e.target.value)}
        style={{ maxWidth: 360, marginBottom: 16 }}
      />
      <Table
        rowKey="@id"
        loading={isFetching}
        dataSource={filteredUsers}
        columns={columns}
        pagination={false}
        onRow={user => ({
          onClick: () => setSelected(user),
          style: { cursor: 'pointer' },
        })}
      />
      <Drawer
        open={selected !== null}
        width={480}
        title={selected?.username}
        onClose={() => setSelected(null)}
        destroyOnHidden
        extra={
          <Button
            type="primary"
            loading={isSaving}
            disabled={!profilesLoaded}
            onClick={onSave}>
            {t('SHIFT_PLANNING_SAVE')}
          </Button>
        }>
        <Form form={form} layout="vertical">
          <Form.Item name="skills" label={t('SHIFT_PLANNING_VIEW_SKILLS')}>
            <Select
              mode="multiple"
              optionFilterProp="label"
              placeholder={t('SHIFT_HR_SKILLS_PLACEHOLDER')}
              options={(skills ?? []).map(s => ({
                value: s['@id'],
                label: s.name,
              }))}
            />
          </Form.Item>
          <Form.Item
            name="contractStartDate"
            label={t('SHIFT_HR_CONTRACT_START')}>
            <DatePicker
              style={{ width: '100%' }}
              format={datePickerProps.format}
            />
          </Form.Item>
          <Form.Item name="dateOfBirth" label={t('SHIFT_HR_DATE_OF_BIRTH')}>
            <DatePicker
              style={{ width: '100%' }}
              format={datePickerProps.format}
            />
          </Form.Item>
          <Form.Item name="addressStreet" label={t('SHIFT_HR_ADDRESS_STREET')}>
            <Input />
          </Form.Item>
          <div style={{ display: 'flex', gap: 12 }}>
            <Form.Item
              name="addressPostalCode"
              label={t('SHIFT_HR_ADDRESS_POSTAL_CODE')}
              style={{ flex: 1 }}>
              <Input />
            </Form.Item>
            <Form.Item
              name="addressLocality"
              label={t('SHIFT_HR_ADDRESS_LOCALITY')}
              style={{ flex: 2 }}>
              <Input />
            </Form.Item>
          </div>
          <Form.Item name="addressCountry" label={t('SHIFT_HR_ADDRESS_COUNTRY')}>
            <Input />
          </Form.Item>
          <div style={{ display: 'flex', gap: 12 }}>
            <Form.Item
              name="salaryType"
              label={t('SHIFT_HR_SALARY_TYPE')}
              style={{ flex: 1 }}>
              <Select
                allowClear
                options={[
                  { value: 'hourly', label: t('SHIFT_HR_SALARY_HOURLY') },
                  { value: 'monthly', label: t('SHIFT_HR_SALARY_MONTHLY') },
                ]}
              />
            </Form.Item>
            <Form.Item
              name="salaryAmount"
              label={t('SHIFT_HR_SALARY_AMOUNT')}
              style={{ flex: 1 }}>
              <InputNumber min={0} step={0.01} style={{ width: '100%' }} />
            </Form.Item>
          </div>
          <Form.Item
            name="weeklyContractedHours"
            label={t('SHIFT_HR_CONTRACTED_HOURS')}>
            <InputNumber min={0} max={60} step={0.5} addonAfter="h" />
          </Form.Item>
        </Form>
      </Drawer>
    </div>
  );
}
