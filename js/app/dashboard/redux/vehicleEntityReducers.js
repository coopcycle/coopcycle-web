import { vehicleAdapter } from "../../coopcycle-frontend-js/logistics/redux"
import { loadVehiclesSuccess } from "./actions"

const initialState = vehicleAdapter.getInitialState()

export default (state = initialState, action) => {
    switch (action.type) {
        case loadVehiclesSuccess.type:
            return vehicleAdapter.upsertMany(vehicleAdapter.getInitialState(), action.payload)
    }

    return state
}
