import { warehouseAdapter } from "../../coopcycle-frontend-js/logistics/redux"
import { loadWarehousesSuccess } from "./actions"

const initialState = warehouseAdapter.getInitialState()

export default (state = initialState, action) => {
    switch (action.type) {
        case loadWarehousesSuccess.type:
            return warehouseAdapter.upsertMany(warehouseAdapter.getInitialState(), action.payload)
    }

    return state
}
