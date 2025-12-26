import { useEffect, useRef } from 'react';
import CodeMirror from 'codemirror/lib/codemirror';
import 'codemirror/mode/javascript/javascript'; // JSON mode
import 'codemirror/lib/codemirror.css';
import 'codemirror/theme/monokai.css'; // or any other theme

type Props = {
  data: unknown;
  mode?: { name: string; json: boolean };
  theme?: string;
  lineNumbers?: boolean;
  lineWrapping?: boolean;
};

export const JsonViewer = ({
  data,
  mode = { name: 'javascript', json: true },
  theme = 'default',
  lineNumbers = false,
  lineWrapping = true,
}: Props) => {
  const textareaRef = useRef(null);
  const editorRef = useRef(null);

  useEffect(() => {
    if (textareaRef.current && !editorRef.current) {
      editorRef.current = CodeMirror.fromTextArea(textareaRef.current, {
        mode,
        theme,
        lineNumbers,
        lineWrapping,
        readOnly: true,
      });

      // Make the editor as tall as its content
      editorRef.current.getWrapperElement().style.height = 'auto';
    }

    if (editorRef.current) {
      editorRef.current.setValue(JSON.stringify(data, null, 2));
    }

    // Cleanup function
    return () => {
      if (editorRef.current) {
        editorRef.current.toTextArea(); // Restore original textarea
        editorRef.current = null;
      }
    };
  }, [data, mode, theme, lineNumbers, lineWrapping]);

  return (
    <div>
      <textarea ref={textareaRef} style={{ display: 'none' }} />
    </div>
  );
};
