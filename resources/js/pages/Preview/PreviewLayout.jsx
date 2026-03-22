import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../../api';
import AppLayout from '../../components/AppLayout';
import Button from '../../components/Button';
import Toast from '../../components/Toast';
import FileTree from './FileTree';
import CodeViewer from './CodeViewer';
import PreviewTabs from './PreviewTabs';
import PreviewToolbar from './PreviewToolbar';
import GenerationInfo from './GenerationInfo';

const MAX_TABS = 5;

export default function PreviewLayout() {
    const { projectId } = useParams();
    const navigate = useNavigate();

    const [files, setFiles] = useState([]);
    const [generation, setGeneration] = useState(null);
    const [loading, setLoading] = useState(true);
    const [selectedFile, setSelectedFile] = useState(null);
    const [openTabs, setOpenTabs] = useState([]);
    const [editable, setEditable] = useState(false);
    const [modifiedContent, setModifiedContent] = useState({});
    const [regenerating, setRegenerating] = useState(false);
    const [saving, setSaving] = useState(false);
    const [toast, setToast] = useState(null);

    const pollRef = useRef(null);

    const getFileContent = useCallback(
        (path) => {
            const file = files.find((f) => f.path === path);
            return file ? file.content : '';
        },
        [files]
    );

    // Load preview data on mount
    useEffect(() => {
        let cancelled = false;

        async function load() {
            try {
                const [previewRes, genRes] = await Promise.all([
                    api.get(`/projects/${projectId}/preview`),
                    api.get(`/projects/${projectId}/generation`),
                ]);

                if (cancelled) return;

                if (genRes.data.status !== 'generated') {
                    navigate('/projects', { replace: true });
                    return;
                }

                const loadedFiles = previewRes.data.files || [];
                setFiles(loadedFiles);
                setGeneration(genRes.data.generation || null);

                if (loadedFiles.length > 0) {
                    const first = loadedFiles[0].path;
                    setSelectedFile(first);
                    setOpenTabs([{ path: first, modified: false }]);
                }
            } catch {
                if (!cancelled) {
                    navigate('/projects', { replace: true });
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        load();
        return () => { cancelled = true; };
    }, [projectId, navigate]);

    // Cleanup poll on unmount
    useEffect(() => {
        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, []);

    const handleSelectFile = useCallback(
        (path) => {
            if (path === selectedFile) return;

            // Warn about unsaved changes
            if (editable && selectedFile && modifiedContent[selectedFile]) {
                if (!window.confirm('Discard unsaved changes?')) return;
                setModifiedContent((prev) => {
                    const next = { ...prev };
                    delete next[selectedFile];
                    return next;
                });
                setOpenTabs((prev) =>
                    prev.map((t) => (t.path === selectedFile ? { ...t, modified: false } : t))
                );
            }

            setSelectedFile(path);

            // Add to tabs if not present
            setOpenTabs((prev) => {
                if (prev.some((t) => t.path === path)) return prev;
                const next = [...prev, { path, modified: false }];
                if (next.length > MAX_TABS) next.shift(); // evict oldest
                return next;
            });
        },
        [selectedFile, editable, modifiedContent]
    );

    const handleCloseTab = useCallback(
        (path) => {
            setOpenTabs((prev) => {
                const next = prev.filter((t) => t.path !== path);
                // If closing the active tab, select another
                if (path === selectedFile && next.length > 0) {
                    setSelectedFile(next[next.length - 1].path);
                } else if (next.length === 0) {
                    setSelectedFile(null);
                }
                return next;
            });

            // Clean up modified content for closed tab
            setModifiedContent((prev) => {
                const next = { ...prev };
                delete next[path];
                return next;
            });
        },
        [selectedFile]
    );

    const handleContentChange = useCallback((path, content) => {
        setModifiedContent((prev) => ({ ...prev, [path]: content }));
        setOpenTabs((prev) =>
            prev.map((t) => (t.path === path ? { ...t, modified: true } : t))
        );
    }, []);

    const handleSave = useCallback(async () => {
        if (!selectedFile || !modifiedContent[selectedFile]) return;

        setSaving(true);
        try {
            const encodedPath = encodeURIComponent(selectedFile);
            await api.put(`/projects/${projectId}/preview/${encodedPath}`, {
                content: modifiedContent[selectedFile],
            });

            // Update file in local state
            setFiles((prev) =>
                prev.map((f) =>
                    f.path === selectedFile
                        ? { ...f, content: modifiedContent[selectedFile] }
                        : f
                )
            );

            // Clear modified content for this file
            setModifiedContent((prev) => {
                const next = { ...prev };
                delete next[selectedFile];
                return next;
            });

            // Unmark tab
            setOpenTabs((prev) =>
                prev.map((t) => (t.path === selectedFile ? { ...t, modified: false } : t))
            );

            setToast({ message: 'File saved', type: 'success' });
        } catch {
            setToast({ message: 'Failed to save file', type: 'error' });
        } finally {
            setSaving(false);
        }
    }, [selectedFile, modifiedContent, projectId]);

    const handleRegenerate = useCallback(async () => {
        if (
            !window.confirm(
                'This will regenerate all files. Any unsaved edits will be lost. Continue?'
            )
        )
            return;

        setRegenerating(true);

        try {
            await api.post(`/projects/${projectId}/regenerate`);

            pollRef.current = setInterval(async () => {
                try {
                    const { data } = await api.get(
                        `/projects/${projectId}/generation`
                    );

                    if (data.status === 'generated') {
                        clearInterval(pollRef.current);
                        pollRef.current = null;

                        // Reload files and generation
                        const previewRes = await api.get(
                            `/projects/${projectId}/preview`
                        );
                        const loadedFiles = previewRes.data.files || [];
                        setFiles(loadedFiles);
                        setGeneration(data.generation || null);

                        // Reset state
                        setModifiedContent({});
                        setEditable(false);
                        if (loadedFiles.length > 0) {
                            const first = loadedFiles[0].path;
                            setSelectedFile(first);
                            setOpenTabs([{ path: first, modified: false }]);
                        } else {
                            setSelectedFile(null);
                            setOpenTabs([]);
                        }

                        setRegenerating(false);
                        setToast({
                            message: 'Regeneration complete',
                            type: 'success',
                        });
                    } else if (data.status === 'failed') {
                        clearInterval(pollRef.current);
                        pollRef.current = null;
                        setRegenerating(false);
                        setToast({
                            message: 'Regeneration failed',
                            type: 'error',
                        });
                    }
                } catch {
                    clearInterval(pollRef.current);
                    pollRef.current = null;
                    setRegenerating(false);
                    setToast({
                        message: 'Error checking regeneration status',
                        type: 'error',
                    });
                }
            }, 2000);
        } catch {
            setRegenerating(false);
            setToast({ message: 'Failed to start regeneration', type: 'error' });
        }
    }, [projectId]);

    if (loading) {
        return (
            <AppLayout activePage="preview">
                <div className="flex items-center justify-center h-[calc(100vh-4rem)]">
                    <div className="text-center">
                        <span className="material-symbols-outlined text-5xl text-primary animate-spin">
                            progress_activity
                        </span>
                        <p className="text-on-surface-variant font-mono mt-4">
                            Loading preview...
                        </p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout activePage="preview">
            <div className="flex h-[calc(100vh-4rem)]">
                {/* Left: File Tree */}
                <div className="w-72 shrink-0 border-r border-outline-variant/10">
                    <FileTree
                        files={files}
                        activeFile={selectedFile}
                        onSelectFile={handleSelectFile}
                    />
                </div>

                {/* Center: Code */}
                <div className="flex-1 flex flex-col min-w-0">
                    <PreviewToolbar
                        filePath={selectedFile}
                        editable={editable}
                        onToggleEdit={() => setEditable(!editable)}
                        onRegenerate={handleRegenerate}
                        projectId={projectId}
                    />
                    <PreviewTabs
                        tabs={openTabs}
                        activeTab={selectedFile}
                        onSelectTab={handleSelectFile}
                        onCloseTab={handleCloseTab}
                    />
                    <div className="flex-1 overflow-hidden">
                        <CodeViewer
                            content={
                                modifiedContent[selectedFile] ??
                                getFileContent(selectedFile)
                            }
                            filePath={selectedFile || ''}
                            editable={editable}
                            onChange={(content) =>
                                handleContentChange(selectedFile, content)
                            }
                        />
                    </div>
                    {editable && modifiedContent[selectedFile] && (
                        <div className="px-4 py-2 bg-surface-container border-t border-outline-variant/5 flex justify-end">
                            <Button
                                variant="primary"
                                onClick={handleSave}
                                loading={saving}
                            >
                                Save Changes
                            </Button>
                        </div>
                    )}
                </div>

                {/* Right: Generation Info */}
                <div className="w-64 shrink-0 p-4 overflow-y-auto border-l border-outline-variant/10">
                    <GenerationInfo generation={generation} />
                </div>
            </div>

            {/* Regenerating overlay */}
            {regenerating && (
                <div className="fixed inset-0 z-50 bg-background/80 backdrop-blur-sm flex items-center justify-center">
                    <div className="text-center">
                        <span className="material-symbols-outlined text-5xl text-primary animate-spin">
                            progress_activity
                        </span>
                        <p className="text-primary font-mono mt-4">
                            Regenerating...
                        </p>
                    </div>
                </div>
            )}

            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onDismiss={() => setToast(null)}
                />
            )}
        </AppLayout>
    );
}
