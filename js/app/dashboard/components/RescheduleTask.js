import React from "react";
import {DatePicker} from "antd";
import moment from "moment";
import {connect} from "react-redux";
import {useTranslation, withTranslation} from "react-i18next";
import {rescheduleTask} from "../redux/actions";

const RescheduleTask = ({task, rescheduleTask}) => {

  const [value, setValue] = React.useState(null);
  const { t } = useTranslation()

  const doneAfter = moment(task.doneAfter);
  const doneBefore = moment(task.doneBefore);
  const ranges = {
    'Next day': [doneAfter.clone().add(1, 'days'), doneBefore.clone().add(1, 'days')],
    'Next week': [doneAfter.clone().add(7, 'days'), doneBefore.clone().add(7, 'days')],
  }

  return (
    <div>
    <DatePicker.RangePicker
      ranges={ranges}
      defaultValue={[doneAfter, doneBefore]}
      showTime={{ format: 'HH:mm' }}
      onChange={dates => setValue(dates)}
    />
      <button className="btn btn-primary btn-sm ml-3"
              disabled={value === null}
              onClick={() => rescheduleTask(task, value[0].format(), value[1].format()) }>
        {t('ADMIN_DASHBOARD_RESCHEDULE')}
      </button>
    </div>
  )
}

function mapStateToProps () {
  return {  }
}

function mapDispatchToProps(dispatch) {
  return {
    rescheduleTask: (task, after, before) => dispatch(rescheduleTask(task, after, before)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(RescheduleTask))
