import React, { useRef, useEffect } from 'react';
import { EditorView, lineNumbers, highlightActiveLine, keymap } from '@codemirror/view';
import { EditorState } from '@codemirror/state';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { syntaxHighlighting, defaultHighlightStyle, foldGutter } from '@codemirror/language';
import { searchKeymap, highlightSelectionMatches } from '@codemirror/search';
import { oneDark } from '@codemirror/theme-one-dark';
import { php } from '@codemirror/lang-php';
import { markdown } from '@codemirror/lang-markdown';
import { json } from '@codemirror/lang-json';
import { javascript } from '@codemirror/lang-javascript';

const draploTheme = EditorView.theme({
    '&': { backgroundColor: '#0d0e11' },
    '.cm-gutters': {
        backgroundColor: '#121316',
        color: '#908fa0',
        borderRight: '1px solid rgba(70, 69, 84, 0.1)',
    },
    '.cm-activeLineGutter': { backgroundColor: '#1b1b1f' },
    '.cm-activeLine': { backgroundColor: '#1b1b1f' },
    '&.cm-focused .cm-cursor': { borderLeftColor: '#c0c1ff' },
    '&.cm-focused .cm-selectionBackground, .cm-selectionBackground': {
        backgroundColor: 'rgba(192, 193, 255, 0.2)',
    },
    '.cm-content': { fontFamily: "'Berkeley Mono', monospace" },
}, { dark: true });

function getLanguage(path) {
    if (path.endsWith('.php')) return php();
    if (path.endsWith('.md')) return markdown();
    if (path.endsWith('.json')) return json();
    if (path.endsWith('.js') || path.endsWith('.jsx')) return javascript();
    return [];
}

export default function CodeViewer({ content = '', filePath = '', editable = false, onChange }) {
    const containerRef = useRef(null);
    const viewRef = useRef(null);

    useEffect(() => {
        if (!containerRef.current) return;

        if (viewRef.current) {
            viewRef.current.destroy();
            viewRef.current = null;
        }

        const lang = getLanguage(filePath);
        const extensions = [
            lineNumbers(),
            highlightActiveLine(),
            foldGutter(),
            history(),
            highlightSelectionMatches(),
            syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
            keymap.of([...defaultKeymap, ...historyKeymap, ...searchKeymap]),
            oneDark,
            draploTheme,
            EditorState.readOnly.of(!editable),
            ...(Array.isArray(lang) ? lang : [lang]),
        ];

        if (editable && onChange) {
            extensions.push(
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        onChange(update.state.doc.toString());
                    }
                })
            );
        }

        const state = EditorState.create({
            doc: content,
            extensions,
        });

        const view = new EditorView({
            state,
            parent: containerRef.current,
        });

        viewRef.current = view;

        return () => {
            view.destroy();
            viewRef.current = null;
        };
    }, [content, filePath, editable]);

    return (
        <div
            ref={containerRef}
            className="h-full w-full overflow-auto [&_.cm-editor]:h-full [&_.cm-scroller]:overflow-auto"
        />
    );
}
