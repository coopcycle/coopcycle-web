import React, { useState } from "react";
import { Button, Input } from "antd";

import store from "./incidentStore";

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

  return (
    <div>
      <Input.TextArea
        placeholder="Add a comment"
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
          const { error } = await _handleCommentSubmit(incident.id, comment);
          if (!error) {
            location.reload();
          } else {
            setError(true);
            setSubmitting(false);
          }
        }}
        className="mt-2"
      >
        Comment
      </Button>
    </div>
  );
}
