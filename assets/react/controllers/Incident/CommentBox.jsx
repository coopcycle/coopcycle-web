import React, { useState } from "react";
import { Button, Input } from "antd";

import store, { setEvents } from "./incidentStore";
import { useTranslation } from "react-i18next";

async function _handleCommentSubmit(id, comment) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.post(
    window.Routing.generate("api_incidents_add_comment_item", { id }),
    { comment },
  );
}

export default function () {
  const { incident } = store.getState();
  const [comment, setComment] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(false);
  const { t } = useTranslation();

  return (
    <div>
      <Input.TextArea
        placeholder={t("ADD_A_COMMENT")}
        status={error ? "error" : null}
        value={comment}
        onChange={(e) => setComment(e.target.value)}
        autoSize={{ minRows: 2, maxRows: 6 }}
      />
      <Button
        style={{ float: "right" }}
        type="primary"
        disabled={!comment.trim()}
        loading={submitting}
        onClick={async () => {
          setSubmitting(true);
          const { response, error } = await _handleCommentSubmit(
            incident.id,
            comment,
          );
          if (!error) {
            const { events } = response;
            store.dispatch(setEvents(events));
            setSubmitting(false);
            setComment("");
          } else {
            setError(true);
            setSubmitting(false);
          }
        }}
        className="mt-2"
      >
        {t("COMMENT")}
      </Button>
    </div>
  );
}
