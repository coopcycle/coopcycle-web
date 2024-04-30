import React, { useState } from "react";
import { Button, InputNumber } from "antd";
import { useTranslation } from "react-i18next";
import { money } from "../utils";

async function _handleApplyPriceDiff(id, diff) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.put(
    window.Routing.generate("api_incidents_action_item", { id }),
    { action: "applied_price_diff", diff },
  );
}

export default function ({ incident, order }) {
  const { currencySymbol } = document.body.dataset;
  const { t } = useTranslation();

  const [value, setValue] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(false);

  return (
    <div>
      <InputNumber
        addonAfter={currencySymbol}
        status={error ? "error" : null}
        size="large"
        style={{ width: "100%" }}
        value={value}
        onChange={setValue}
      />
      {value && (
        <p className="text-center my-2" style={{ fontSize: "1.2em" }}>
          <p>{t("CURRENT_PRICE")}: {money(order.total)}</p>
          <p>{t("NEW_PRICE")}: {money(order.total + value * 100)}</p>
        </p>
      )}
      <p className="mt-3">
        <Button
          disabled={!value || submitting}
          onClick={async () => {
            setSubmitting(true);
            const { error } = await _handleApplyPriceDiff(
              incident.id,
              value * 100,
            );
            if (!error) {
              location.reload();
            } else {
              setError(true);
              setSubmitting(false);
            }
          }}
        >
          {t("APPLY")}
        </Button>
      </p>
      {false &&<p>
        <Button type="danger" ghost disabled={!value || submitting}>
          Apply {t("ADMIN_DASHBOARD_AND_CLOSE_THE_INCIDENT")}
        </Button>
      </p>}
    </div>
  );
}
