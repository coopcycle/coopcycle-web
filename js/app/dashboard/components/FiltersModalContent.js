import React, { useState } from "react"
import _ from "lodash"
import { connect } from "react-redux"
import { useTranslation } from "react-i18next"
import { Form, Slider, Switch } from "antd"
import { Formik } from "formik"

import Avatar from "../../components/Avatar"
import {
  closeFiltersModal,
  setFilterValue,
  onlyFilter,
} from "../redux/actions"
import {
  selectAllTags,
  selectBookedUsernames,
  selectFiltersSetting,
} from "../redux/selectors"

import "antd/lib/grid/style/index.css"
import OrganizationsOrTagsSelect from "./OrganizationsOrTagsSelect"

function isHidden(hiddenCouriers, username) {
  return !!_.find(hiddenCouriers, (u) => u === username)
}

const timeSteps = {
  0: "00:00",
  4: "04:00",
  8: "08:00",
  9: "09:00",
  10: "10:00",
  11: "11:00",
  12: "12:00",
  13: "13:00",
  14: "14:00",
  15: "15:00",
  16: "16:00",
  17: "17:00",
  18: "18:00",
  19: "19:00",
  20: "20:00",
  21: "21:00",
  22: "22:00",
  24: "23:59",
}

const timeStepsWithStyle = _.mapValues(timeSteps, (value) => ({
  label: value,
  style: {
    fontSize: "10px",
  },
}))

const onlyFilterColor = "#8250df"

function FiltersModalContent(props) {
  const { t } = useTranslation()
  
  const onSubmit = (values) => {
    props.setFilterValue("onlyFilter", null)
    props.setFilterValue("showFinishedTasks", values.showFinishedTasks)
    props.setFilterValue("showCancelledTasks", values.showCancelledTasks)
    props.setFilterValue(
      "showIncidentReportedTasks",
      values.showIncidentReportedTasks,
    )
    props.setFilterValue(
      "alwayShowUnassignedTasks",
      values.alwayShowUnassignedTasks,
    )
    props.setFilterValue("tags", values.tags)
    props.setFilterValue("excludedTags", values.excludedTags)
    props.setFilterValue("includedOrgs", values.includedOrgs)
    props.setFilterValue("excludedOrgs", values.excludedOrgs)
    props.setFilterValue("hiddenCouriers", values.hiddenCouriers)
    props.setFilterValue("timeRange", values.timeRange)

    props.closeFiltersModal()
  }

  const { onlyFilter } = props
  const initialValues = {
    showFinishedTasks: props.showFinishedTasks,
    showCancelledTasks: props.showCancelledTasks,
    showIncidentReportedTasks: props.showIncidentReportedTasks,
    alwayShowUnassignedTasks: props.alwayShowUnassignedTasks,
    tags: props.selectedTags,
    excludedTags: props.excludedTags,
    includedOrgs: props.includedOrgs,
    excludedOrgs: props.excludedOrgs,
    hiddenCouriers: props.hiddenCouriers,
    timeRange: props.timeRange,
  }

  return (
    <Formik
      initialValues={initialValues}
      onSubmit={onSubmit}
      validateOnBlur={false}
      validateOnChange={false}
    >
      {({ values, handleSubmit, setFieldValue }) => (
        <form
          onSubmit={handleSubmit}
          autoComplete="off"
          className="form-horizontal"
        >
          <ul className="nav nav-tabs" role="tablist">
            <li role="presentation" className="active">
              <a
                href="#filters_general"
                aria-controls="filters_general"
                role="tab"
                data-toggle="tab"
              >
                {t("ADMIN_DASHBOARD_FILTERS_TAB_GENERAL")}
              </a>
            </li>
            <li role="presentation">
              <a
                href="#filters_tags"
                aria-controls="filters_tags"
                role="tab"
                data-toggle="tab"
              >
                {t("ADMIN_DASHBOARD_FILTERS_TAB_TAGS_AND_ORGS")}
              </a>
            </li>
            <li role="presentation">
              <a
                href="#filters_couriers"
                aria-controls="filters_couriers"
                role="tab"
                data-toggle="tab"
              >
                {t("ADMIN_DASHBOARD_FILTERS_TAB_COURIERS")}
              </a>
            </li>
            <li role="presentation">
              <a
                href="#filters_timerange"
                aria-controls="filters_timerange"
                role="tab"
                data-toggle="tab"
              >
                {t("ADMIN_DASHBOARD_FILTERS_TAB_TIMERANGE")}
              </a>
            </li>
          </ul>
          <div className="tab-content">
            <div
              role="tabpanel"
              className="tab-pane active"
              id="filters_general"
            >
              <div className="dashboard__modal-filters__tabpane">
                <Form
                  layout="horizontal"
                  component="div"
                  labelCol={{ span: 8 }}
                  colon={false}
                >
                  <Form.Item
                    label={t("ADMIN_DASHBOARD_FILTERS_COMPLETED_TASKS")}
                  >
                    <Switch
                      checkedChildren={t("ADMIN_DASHBOARD_FILTERS_SHOW")}
                      unCheckedChildren={t("ADMIN_DASHBOARD_FILTERS_HIDE")}
                      defaultChecked={values.showFinishedTasks}
                      onChange={(checked) =>
                        setFieldValue("showFinishedTasks", checked)
                      }
                    />
                  </Form.Item>
                  <Form.Item
                    label={t("ADMIN_DASHBOARD_FILTERS_CANCELLED_TASKS")}
                  >
                    <Switch
                      checkedChildren={
                        onlyFilter === "showCancelledTasks"
                          ? t("ONLY_THIS_FILTER")
                          : t("ADMIN_DASHBOARD_FILTERS_SHOW")
                      }
                      unCheckedChildren={t("ADMIN_DASHBOARD_FILTERS_HIDE")}
                      defaultChecked={values.showCancelledTasks}
                      style={{
                        backgroundColor:
                          onlyFilter === "showCancelledTasks"
                            ? onlyFilterColor
                            : null,
                      }}
                      onChange={(checked) => {
                        if (onlyFilter === "showCancelledTasks") {
                          props.setFilterValue("onlyFilter", null)
                        }
                        setFieldValue("showCancelledTasks", checked)
                      }}
                    />
                    <button
                      type="button"
                      onClick={() => props.setOnlyFilter("showCancelledTasks")}
                      className="btn btn-link"
                    >
                      {t("ONLY_SHOW_THESE")}
                    </button>
                  </Form.Item>
                  <Form.Item
                    label={t("ADMIN_DASHBOARD_FILTERS_INCIDENT_REPORTED_TASKS")}
                  >
                    <Switch
                      checkedChildren={
                        onlyFilter === "showIncidentReportedTasks"
                          ? t("ONLY_THIS_FILTER")
                          : t("ADMIN_DASHBOARD_FILTERS_SHOW")
                      }
                      unCheckedChildren={t("ADMIN_DASHBOARD_FILTERS_HIDE")}
                      defaultChecked={values.showIncidentReportedTasks}
                      style={{
                        backgroundColor:
                          onlyFilter === "showIncidentReportedTasks"
                            ? onlyFilterColor
                            : null,
                      }}
                      onChange={(checked) => {
                        if (onlyFilter === "showIncidentReportedTasks") {
                          props.setFilterValue("onlyFilter", null)
                        }
                        setFieldValue("showIncidentReportedTasks", checked)
                      }}
                    />
                    <button
                      type="button"
                      onClick={() =>
                        props.setOnlyFilter("showIncidentReportedTasks")
                      }
                      className="btn btn-link"
                    >
                      {t("ONLY_SHOW_THESE")}
                    </button>
                  </Form.Item>
                  <Form.Item
                    label={t("ADMIN_DASHBOARD_FILTERS_ALWAYS_SHOW_UNASSIGNED")}
                    help={
                      <span className="help-block mt-1">
                        <i className="fa fa-info-circle mr-1"></i>
                        <span>
                          {t(
                            "ADMIN_DASHBOARD_FILTERS_ALWAYS_SHOW_UNASSIGNED_HELP_TEXT",
                          )}
                        </span>
                      </span>
                    }
                  >
                    <Switch
                      checkedChildren={t("ADMIN_DASHBOARD_FILTERS_SHOW")}
                      unCheckedChildren={t("ADMIN_DASHBOARD_FILTERS_HIDE")}
                      defaultChecked={values.alwayShowUnassignedTasks}
                      onChange={(checked) =>
                        setFieldValue("alwayShowUnassignedTasks", checked)
                      }
                    />
                  </Form.Item>
                </Form>
              </div>
            </div>
            <div role="tabpanel" className="tab-pane" id="filters_tags">
              <div className="dashboard__modal-filters__tabpane">
                <OrganizationsOrTagsSelect setFieldValue={setFieldValue} />
              </div>
            </div>
            <div role="tabpanel" className="tab-pane" id="filters_couriers">
              <div className="dashboard__modal-filters__tabpane my-4">
                <div>
                  <a
                    className="text-muted pull-right"
                    onClick={() => setFieldValue("hiddenCouriers", [])}
                  >
                    {t("ADMIN_DASHBOARD_FILTERS_SHOW_ALL")}
                  </a>
                </div>
                {props.couriers.map((username) => (
                  <div
                    className="dashboard__modal-filters__courier"
                    key={username}
                  >
                    <span>
                      <Avatar username={username} /> <span>{username}</span>
                    </span>
                    <div>
                      <Switch
                        checkedChildren={t("ADMIN_DASHBOARD_FILTERS_SHOW")}
                        unCheckedChildren={t("ADMIN_DASHBOARD_FILTERS_HIDE")}
                        defaultChecked={
                          !isHidden(values.hiddenCouriers, username)
                        }
                        checked={!isHidden(values.hiddenCouriers, username)}
                        onChange={(checked) => {
                          if (checked) {
                            setFieldValue(
                              "hiddenCouriers",
                              _.filter(
                                values.hiddenCouriers,
                                (u) => u !== username,
                              ),
                            )
                          } else {
                            setFieldValue(
                              "hiddenCouriers",
                              values.hiddenCouriers.concat([username]),
                            )
                          }
                        }}
                      />
                      <a
                        className="text-muted ml-4"
                        onClick={() =>
                          setFieldValue(
                            "hiddenCouriers",
                            props.couriers.filter((c) => c !== username),
                          )
                        }
                      >
                        {t("ONLY")}
                      </a>
                    </div>
                  </div>
                ))}
              </div>
            </div>
            <div role="tabpanel" className="tab-pane" id="filters_timerange">
              <div className="dashboard__modal-filters__tabpane mx-4">
                <Slider
                  range
                  marks={timeStepsWithStyle}
                  defaultValue={values.timeRange}
                  max={24}
                  step={null}
                  onAfterChange={(value) => setFieldValue("timeRange", value)}
                />
              </div>
            </div>
          </div>
          <button type="submit" className="btn btn-block btn-primary">
            {t("ADMIN_DASHBOARD_FILTERS_APPLY")}
          </button>
        </form>
      )}
    </Formik>
  )
}

function mapStateToProps(state) {
  const {
    showFinishedTasks,
    showCancelledTasks,
    showIncidentReportedTasks,
    alwayShowUnassignedTasks,
    hiddenCouriers,
    timeRange,
    tags,
    excludedTags,
    includedOrgs,
    excludedOrgs,
    onlyFilter,
  } = selectFiltersSetting(state)

  return {
    tags: selectAllTags(state),
    excludedTags,
    includedOrgs,
    excludedOrgs,
    showFinishedTasks,
    showCancelledTasks,
    showIncidentReportedTasks,
    alwayShowUnassignedTasks,
    selectedTags: tags,
    couriers: selectBookedUsernames(state),
    hiddenCouriers,
    timeRange,
    onlyFilter,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    closeFiltersModal: () => dispatch(closeFiltersModal()),
    setFilterValue: (key, value) => dispatch(setFilterValue(key, value)),
    setOnlyFilter: (filter) => dispatch(onlyFilter(filter)),
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(FiltersModalContent)
