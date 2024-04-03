import React, { useState } from "react";
import { Button, Input } from "antd";

async function _handleCommentSubmit(id, comment) {
  return fetch(
    window.Routing.generate("api_incidents_add_comment_item", { id }),
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${window._auth.jwt}`,
      },
      body: JSON.stringify({ comment }),
    },
  );
}

export default function ({ id }) {
  const [comment, setComment] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(false);
  return (
    <div>
      <Input.TextArea
        placeholder="Add a comment"
        status={error ? "error" : null}
        value={comment}
        showCount
        onChange={(e) => setComment(e.target.value)}
        autoSize={{ minRows: 2, maxRows: 6 }}
      />
      <Button
        type="primary"
        disabled={!comment.trim()}
        loading={submitting}
        onClick={async () => {
          setSubmitting(true);
          const response = await _handleCommentSubmit(id, comment);
          if (response.ok) {
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
