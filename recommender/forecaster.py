"""Demand forecasting for shift planning, backed by Facebook Prophet.

Stateless by design: the caller sends the full historical series and gets the
forecast back in one request. Fitting on ~1-2 years of hourly buckets takes a
few seconds, which is acceptable for a dispatcher-triggered action, and avoids
having to persist/refresh models per instance like the recommenders do.
"""

import logging

import pandas as pd
from prophet import Prophet

# Prophet's yearly seasonality needs to have seen the seasons at least once to
# be anything but noise
YEARLY_SEASONALITY_MIN_DAYS = 300

# Below this the model has nothing meaningful to learn from
MIN_POINTS = 100

# cmdstanpy is extremely chatty at INFO level
logging.getLogger("cmdstanpy").setLevel(logging.WARNING)
logging.getLogger("prophet").setLevel(logging.WARNING)


def forecast_demand(
    series: list[dict],
    horizon: list[str],
    quantile: float = 0.8,
    country_holidays: str | None = None,
) -> list[dict]:
    """Fits Prophet on an hourly demand series and predicts the given quantile
    of demand for each timestamp of the horizon.

    series: [{"ds": "YYYY-MM-DD HH:MM:SS", "y": float}] in instance-local time
    horizon: ["YYYY-MM-DD HH:MM:SS", ...] timestamps to predict
    quantile: demand quantile to staff for (0.5 = median, 0.8 = busy week)
    country_holidays: ISO country code for Prophet's built-in holiday calendar

    Returns [{"ds": ..., "yhat": float}] with yhat clamped at 0.
    """
    df = pd.DataFrame(series)
    df["ds"] = pd.to_datetime(df["ds"])

    if len(df) < MIN_POINTS:
        raise ValueError(f"Not enough history: {len(df)} points, need {MIN_POINTS}")
    if df["y"].sum() <= 0:
        raise ValueError("Series has no demand at all")

    span_days = (df["ds"].max() - df["ds"].min()).days

    # A symmetric interval of width w spans quantiles [(1-w)/2, (1+w)/2], so the
    # upper bound of a (2q - 1)-wide interval is the q-quantile we staff for
    interval_width = max(0.0, 2 * quantile - 1)

    model = Prophet(
        interval_width=interval_width,
        daily_seasonality=True,
        weekly_seasonality=True,
        yearly_seasonality=span_days >= YEARLY_SEASONALITY_MIN_DAYS,
        # Demand seasonality is proportional to the level: a slow summer takes
        # a bigger absolute bite out of the lunch peak than out of a quiet hour
        seasonality_mode="multiplicative",
        uncertainty_samples=300,
    )

    if country_holidays:
        try:
            model.add_country_holidays(country_name=country_holidays.upper())
        except Exception:
            # Unknown country code in the holidays package — forecast without
            pass

    model.fit(df)

    future = pd.DataFrame({"ds": pd.to_datetime(horizon)})
    prediction = model.predict(future)

    column = "yhat_upper" if quantile > 0.5 else "yhat"

    return [
        {"ds": row["ds"].strftime("%Y-%m-%d %H:%M:%S"), "yhat": max(0.0, float(row[column]))}
        for _, row in prediction[["ds", column]].iterrows()
    ]
