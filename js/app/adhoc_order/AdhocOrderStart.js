import React, { Component } from "react";
import { withTranslation } from "react-i18next";

import AdhocOrderForm from './AdhocOrderForm'
import SearchOrder from "./SearchOrder";

class AdhocOrderStart extends Component {

  constructor(props) {
    super(props)

    this.state = {
      createNewOrder: false,
    }
  }

  render() {
    return (
      <div>
        { this.state.createNewOrder
          ?
            <AdhocOrderForm
              onSearchOrderPressed={() => this.setState({createNewOrder: false})}>
            </AdhocOrderForm>
          :
            <SearchOrder
              onCreateNewOrderPressed={() => this.setState({createNewOrder: true})}>
            </SearchOrder>
        }
      </div>
    )
  }
}

export default withTranslation()(AdhocOrderStart)
