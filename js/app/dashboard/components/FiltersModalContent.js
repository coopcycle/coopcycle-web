import React from "react";
import _ from "lodash";
import { connect } from "react-redux";
import { withTranslation } from "react-i18next";
import { Form, Slider, Switch } from "antd";
import { Formik } from "formik";

import Avatar from "../../components/Avatar";
import {
  closeFiltersModal,
  setFilterValue,
  onlyFilter,
} from "../redux/actions";
import {
  selectAllTags,
  selectBookedUsernames,
  selectFiltersSetting,
} from "../redux/selectors";

import "antd/lib/grid/style/index.css";
import OrganizationsOrTagsSelect from "./OrganizationsOrTagsSelect";

function isHidden(hiddenCouriers, username) {
  return !!_.find(hiddenCouriers, (u) => u === username);
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
};

const timeStepsWithStyle = _.mapValues(timeSteps, (value) => ({
  label: value,
  style: {
    fontSize: "10px",
  },
}));

const onlyFilterColor = "#8250df";

class FiltersModalContent extends React.Component {
  _onSubmit(values) {
    this.props.setFilterValue("onlyFilter", null);
    this.props.setFilterValue("showFinishedTasks", values.showFinishedTasks);
    this.props.setFilterValue("showCancelledTasks", values.showCancelledTasks);
    this.props.setFilterValue(
      "showIncidentReportedTasks",
      values.showIncidentReportedTasks,
    );
    this.props.setFilterValue(
      "alwayShowUnassignedTasks",
      values.alwayShowUnassignedTasks,
    );
    this.props.setFilterValue("tags", values.tags);
    this.props.setFilterValue("excludedTags", values.excludedTags);
    this.props.setFilterValue("includedOrgs", values.includedOrgs);
    this.props.setFilterValue("excludedOrgs", values.excludedOrgs);
    this.props.setFilterValue("hiddenCouriers", values.hiddenCouriers);
    this.props.setFilterValue("timeRange", values.timeRange);

    this.props.closeFiltersModal();
  }

  render() {
    const { onlyFilter } = this.props;
    let initialValues = {
      showFinishedTasks: this.props.showFinishedTasks,
      showCancelledTasks: this.props.showCancelledTasks,
      showIncidentReportedTasks: this.props.showIncidentReportedTasks,
      alwayShowUnassignedTasks: this.props.alwayShowUnassignedTasks,
      tags: this.props.selectedTags,
      excludedTags: this.props.excludedTags,
      includedOrgs: this.props.includedOrgs,
      excludedOrgs: this.props.excludedOrgs,
      hiddenCouriers: this.props.hiddenCouriers,
      timeRange: this.props.timeRange,
    };

    return (
      <Formik
        initialValues={initialValues}
        onSubmit={this._onSubmit.bind(this)}
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
                  {this.props.t("ADMIN_DASHBOARD_FILTERS_TAB_GENERAL")}
                </a>
              </li>
              <li role="presentation">
                <a
                  href="#filters_tags"
                  aria-controls="filters_tags"
                  role="tab"
                  data-toggle="tab"
                >
                  {this.props.t("ADMIN_DASHBOARD_FILTERS_TAB_TAGS_AND_ORGS")}
                </a>
              </li>
              <li role="presentation">
                <a
                  href="#filters_couriers"
                  aria-controls="filters_couriers"
                  role="tab"
                  data-toggle="tab"
                >
                  {this.props.t("ADMIN_DASHBOARD_FILTERS_TAB_COURIERS")}
                </a>
              </li>
              <li role="presentation">
                <a
                  href="#filters_timerange"
                  aria-controls="filters_timerange"
                  role="tab"
                  data-toggle="tab"
                >
                  {this.props.t("ADMIN_DASHBOARD_FILTERS_TAB_TIMERANGE")}
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
                      label={this.props.t(
                        "ADMIN_DASHBOARD_FILTERS_COMPLETED_TASKS",
                      )}
                    >
                      <Switch
                        checkedChildren={this.props.t(
                          "ADMIN_DASHBOARD_FILTERS_SHOW",
                        )}
                        unCheckedChildren={this.props.t(
                          "ADMIN_DASHBOARD_FILTERS_HIDE",
                        )}
                        defaultChecked={values.showFinishedTasks}
                        onChange={(checked) =>
                          setFieldValue("showFinishedTasks", checked)
                        }
                      />
                    </Form.Item>
                    <Form.Item
                      label={this.props.t(
                        "ADMIN_DASHBOARD_FILTERS_CANCELLED_TASKS",
                      )}
                    >
                      <Switch
                        checkedChildren={
                          onlyFilter === "showCancelledTasks"
                            ? this.props.t("ONLY_THIS_FILTER")
                            : this.props.t("ADMIN_DASHBOARD_FILTERS_SHOW")
                        }
                        unCheckedChildren={this.props.t(
                          "ADMIN_DASHBOARD_FILTERS_HIDE",
                        )}
                        defaultChecked={values.showCancelledTasks}
                        style={{
                          backgroundColor:
                            onlyFilter === "showCancelledTasks"
                              ? onlyFilterColor
                              : null,
                        }}
                        onChange={(checked) => {
                          if (onlyFilter === "showCancelledTasks") {
                            this.props.setFilterValue("onlyFilter", null);
                          }
                          setFieldValue("showCancelledTasks", checked);
                        }}
                      />
                      <button
                        type="button"
                        onClick={() =>
                          this.props.setOnlyFilter("showCancelledTasks")
                        }
                        className="btn btn-link"
                      >
                        {this.props.t("ONLY_SHOW_THESE")}
                      </button>
                    </Form.Item>
                    <Form.Item
                      label={this.props.t(
                        "ADMIN_DASHBOARD_FILTERS_INCIDENT_REPORTED_TASKS",
                      )}
                    >
                      <Switch
                        checkedChildren={
                          onlyFilter === "showIncidentReportedTasks"
                            ? this.props.t("ONLY_THIS_FILTER")
                            : this.props.t("ADMIN_DASHBOARD_FILTERS_SHOW")
                        }
                        unCheckedChildren={this.props.t(
                          "ADMIN_DASHBOARD_FILTERS_HIDE",
                        )}
                        defaultChecked={values.showIncidentReportedTasks}
                        style={{
                          backgroundColor:
                            onlyFilter === "showIncidentReportedTasks"
                              ? onlyFilterColor
                              : null,
                        }}
                        onChange={(checked) => {
                          if (onlyFilter === "showIncidentReportedTasks") {
                            this.props.setFilterValue("onlyFilter", null);
                          }
                          setFieldValue("showIncidentReportedTasks", checked);
                        }}
                      />
                      <button
                        type="button"
                        onClick={() =>
                          this.props.setOnlyFilter("showIncidentReportedTasks")
                        }
                        className="btn btn-link"
                      >
                        {this.props.t("ONLY_SHOW_THESE")}
                      </button>
                    </Form.Item>
                    <Form.Item
                      label={this.props.t(
                        "ADMIN_DASHBOARD_FILTERS_ALWAYS_SHOW_UNASSIGNED",
                      )}
                      help={
                        <span className="help-block mt-1">
                          <i className="fa fa-info-circle mr-1"></i>
                          <span>
                            {this.props.t(
                              "ADMIN_DASHBOARD_FILTERS_ALWAYS_SHOW_UNASSIGNED_HELP_TEXT",
                            )}
                          </span>
                        </span>
                      }
                    >
                      <Switch
                        checkedChildren={this.props.t(
                          "ADMIN_DASHBOARD_FILTERS_SHOW",
                        )}
                        unCheckedChildren={this.props.t(
                          "ADMIN_DASHBOARD_FILTERS_HIDE",
                        )}
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
                  <OrganizationsOrTagsSelect
                    setFieldValue={setFieldValue}
                  />
                </div>
              </div>
              <div role="tabpanel" className="tab-pane" id="filters_couriers">
                <div className="dashboard__modal-filters__tabpane my-4">
                  <div>
                    <a
                      className="text-muted pull-right"
                      onClick={() => setFieldValue("hiddenCouriers", [])}
                    >
                      {this.props.t("ADMIN_DASHBOARD_FILTERS_SHOW_ALL")}
                    </a>
                  </div>
                  {this.props.couriers.map((username) => (
                    <div
                      className="dashboard__modal-filters__courier"
                      key={username}
                    >
                      <span>
                        <Avatar username={username} /> <span>{username}</span>
                      </span>
                      <div>
                        <Switch
                          checkedChildren={this.props.t(
                            "ADMIN_DASHBOARD_FILTERS_SHOW",
                          )}
                          unCheckedChildren={this.props.t(
                            "ADMIN_DASHBOARD_FILTERS_HIDE",
                          )}
                          defaultChecked={
                            !isHidden(values.hiddenCouriers, username)
                          }
                          checked={
                            !isHidden(values.hiddenCouriers, username)
                          }
                          onChange={(checked) => {
                            if (checked) {
                              setFieldValue(
                                "hiddenCouriers",
                                _.filter(
                                  values.hiddenCouriers,
                                  (u) => u !== username,
                                ),
                              );
                            } else {
                              setFieldValue(
                                "hiddenCouriers",
                                values.hiddenCouriers.concat([username]),
                              );
                            }
                          }}
                        />
                        <a
                          className="text-muted ml-4"
                          onClick={() => setFieldValue("hiddenCouriers", this.props.couriers.filter(c => c !== username))}
                        >
                          {this.props.t("ONLY")}
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
              {this.props.t("ADMIN_DASHBOARD_FILTERS_APPLY")}
            </button>
          </form>
        )}
      </Formik>
    );
  }
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
  } = selectFiltersSetting(state);

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
  };
}

function mapDispatchToProps(dispatch) {
  return {
    closeFiltersModal: () => dispatch(closeFiltersModal()),
    setFilterValue: (key, value) => dispatch(setFilterValue(key, value)),
    setOnlyFilter: (filter) => dispatch(onlyFilter(filter)),
  };
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(withTranslation()(FiltersModalContent));
