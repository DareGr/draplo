import React, { useState, useEffect, useRef } from 'react';
import api from '../../api';

export default function ExportDropdown({ projectId, projectSlug, onExportComplete }) {
    const [open, setOpen] = useState(false);
    const [repoName, setRepoName] = useState(projectSlug || '');
    const [exporting, setExporting] = useState(false);
    const [showRepoInput, setShowRepoInput] = useState(false);
    const [downloading, setDownloading] = useState(false);
    const [error, setError] = useState(null);
    const dropdownRef = useRef(null);
    const pollRef = useRef(null);

    // Update repoName when projectSlug changes
    useEffect(() => {
        if (projectSlug) setRepoName(projectSlug);
    }, [projectSlug]);

    // Close dropdown on outside click
    useEffect(() => {
        function handleClickOutside(e) {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
                if (!exporting) {
                    setOpen(false);
                    setShowRepoInput(false);
                    setError(null);
                }
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [exporting]);

    // Cleanup poll on unmount
    useEffect(() => {
        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, []);

    const handleGitHubPush = async () => {
        if (!repoName.trim()) return;

        setExporting(true);
        setError(null);

        try {
            await api.post(`/projects/${projectId}/export/github`, {
                repo_name: repoName.trim(),
            });

            // Poll for export status
            pollRef.current = setInterval(async () => {
                try {
                    const { data } = await api.get(`/projects/${projectId}/export/status`);

                    if (data.status === 'exported') {
                        clearInterval(pollRef.current);
                        pollRef.current = null;
                        setExporting(false);
                        setOpen(false);
                        setShowRepoInput(false);
                        if (onExportComplete) {
                            onExportComplete({
                                github_repo_url: data.github_repo_url,
                                github_repo_name: data.github_repo_name,
                            });
                        }
                    } else if (data.status === 'failed') {
                        clearInterval(pollRef.current);
                        pollRef.current = null;
                        setExporting(false);
                        setError(data.error || 'Export failed. Please try again.');
                    }
                } catch {
                    clearInterval(pollRef.current);
                    pollRef.current = null;
                    setExporting(false);
                    setError('Error checking export status.');
                }
            }, 2000);
        } catch (err) {
            setExporting(false);
            if (err.response?.status === 422) {
                setError('GitHub not connected.');
            } else {
                setError(err.response?.data?.message || 'Export failed. Please try again.');
            }
        }
    };

    const handleZipDownload = async () => {
        setDownloading(true);
        setError(null);

        try {
            const res = await api.get(`/projects/${projectId}/export/zip`, {
                responseType: 'blob',
            });

            const url = URL.createObjectURL(res.data);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${projectSlug || 'project'}.zip`;
            a.click();
            URL.revokeObjectURL(url);
            setOpen(false);
        } catch {
            setError('Failed to download ZIP.');
        } finally {
            setDownloading(false);
        }
    };

    return (
        <div className="relative" ref={dropdownRef}>
            {/* Trigger button */}
            <button
                onClick={() => setOpen(!open)}
                className="bg-gradient-to-r from-primary to-primary-container text-on-primary font-bold rounded-md px-6 py-2.5 active:scale-95 transition-all text-sm inline-flex items-center gap-1.5"
            >
                <span className="material-symbols-outlined text-[18px]">download</span>
                Export
                <span className="material-symbols-outlined text-[16px]">
                    {open ? 'expand_less' : 'expand_more'}
                </span>
            </button>

            {/* Dropdown panel */}
            {open && (
                <div className="absolute right-0 top-full mt-2 w-80 bg-surface-container-high rounded-xl border border-outline-variant/10 shadow-xl p-3 z-50">
                    {/* Push to GitHub */}
                    {!showRepoInput ? (
                        <button
                            onClick={() => setShowRepoInput(true)}
                            disabled={exporting}
                            className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left hover:bg-surface-container transition-colors disabled:opacity-50"
                        >
                            <span className="material-symbols-outlined text-primary">cloud_upload</span>
                            <div>
                                <div className="text-on-surface text-sm font-medium">Push to GitHub</div>
                                <div className="text-on-surface-variant text-xs">Create private repo with all files</div>
                            </div>
                        </button>
                    ) : (
                        <div className="px-3 py-2.5">
                            <div className="flex items-center gap-2 mb-2">
                                <span className="material-symbols-outlined text-primary text-[20px]">cloud_upload</span>
                                <span className="text-on-surface text-sm font-medium">Push to GitHub</span>
                            </div>
                            <div className="flex gap-2">
                                <input
                                    type="text"
                                    value={repoName}
                                    onChange={(e) => setRepoName(e.target.value)}
                                    placeholder="repository-name"
                                    disabled={exporting}
                                    className="flex-1 bg-surface-container-lowest border border-outline-variant/15 rounded-md px-3 py-1.5 text-sm text-on-surface font-mono placeholder:text-outline focus:outline-none focus:border-primary/50 transition-colors disabled:opacity-50"
                                />
                                <button
                                    onClick={handleGitHubPush}
                                    disabled={exporting || !repoName.trim()}
                                    className="bg-gradient-to-r from-primary to-primary-container text-on-primary font-bold rounded-md px-4 py-1.5 text-sm active:scale-95 transition-all disabled:opacity-50 disabled:pointer-events-none inline-flex items-center gap-1.5"
                                >
                                    {exporting ? (
                                        <>
                                            <svg className="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" fill="none">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            Pushing...
                                        </>
                                    ) : (
                                        'Push'
                                    )}
                                </button>
                            </div>
                            {!exporting && (
                                <button
                                    onClick={() => { setShowRepoInput(false); setError(null); }}
                                    className="text-on-surface-variant text-xs mt-2 hover:text-on-surface transition-colors"
                                >
                                    Cancel
                                </button>
                            )}
                        </div>
                    )}

                    {/* Divider */}
                    <div className="my-1 border-t border-outline-variant/10" />

                    {/* Download ZIP */}
                    <button
                        onClick={handleZipDownload}
                        disabled={downloading || exporting}
                        className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left hover:bg-surface-container transition-colors disabled:opacity-50"
                    >
                        <span className="material-symbols-outlined text-primary">folder_zip</span>
                        <div>
                            <div className="text-on-surface text-sm font-medium">
                                {downloading ? 'Downloading...' : 'Download ZIP'}
                            </div>
                            <div className="text-on-surface-variant text-xs">Save all files as ZIP archive</div>
                        </div>
                    </button>

                    {/* Error message */}
                    {error && (
                        <div className="mt-2 px-3 py-2 rounded-lg bg-tertiary-container/10 text-tertiary text-xs">
                            {error}
                            {error === 'GitHub not connected.' && (
                                <a
                                    href="/auth/github"
                                    className="block mt-1 text-primary hover:underline"
                                >
                                    Connect GitHub account
                                </a>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
