import { trailerAdapter } from "../../coopcycle-frontend-js/logistics/redux"
import { loadTrailersSuccess } from "./actions"

const initialState = trailerAdapter.getInitialState()

export default (state = initialState, action) => {
    switch (action.type) {
        case loadTrailersSuccess.type:
            return trailerAdapter.upsertMany(trailerAdapter.getInitialState(), action.payload)
    }

    return state
}
