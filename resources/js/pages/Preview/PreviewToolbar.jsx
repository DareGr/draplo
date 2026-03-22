import React from 'react';
import { Link } from 'react-router-dom';
import Button from '../../components/Button';
import ExportDropdown from './ExportDropdown';

export default function PreviewToolbar({ filePath = '', editable = false, onToggleEdit, onRegenerate, projectId, projectSlug, onExportComplete }) {
    const pathParts = filePath ? filePath.split('/') : [];

    return (
        <div className="flex items-center justify-between px-4 py-2 bg-surface-container-lowest border-b border-outline-variant/5">
            {/* Left: Back link + breadcrumb */}
            <div className="flex items-center gap-3 min-w-0">
                <Link
                    to={`/wizard/${projectId}`}
                    className="flex items-center gap-1.5 text-on-surface-variant hover:text-on-surface transition-colors shrink-0"
                >
                    <span className="material-symbols-outlined text-[20px]">arrow_back</span>
                    <span className="text-sm">Back to Wizard</span>
                </Link>

                {pathParts.length > 0 && (
                    <div className="flex items-center gap-1 font-mono text-[13px] text-outline min-w-0 truncate">
                        <span className="text-outline-variant">/</span>
                        {pathParts.map((part, i) => (
                            <React.Fragment key={i}>
                                {i > 0 && <span className="text-outline-variant">/</span>}
                                <span className={i === pathParts.length - 1 ? 'text-on-surface-variant' : ''}>
                                    {part}
                                </span>
                            </React.Fragment>
                        ))}
                    </div>
                )}
            </div>

            {/* Center: Edit toggle */}
            <button
                onClick={onToggleEdit}
                className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low transition-colors"
            >
                <span className="material-symbols-outlined text-[18px]">
                    {editable ? 'edit_off' : 'edit'}
                </span>
                <span>{editable ? 'Read-only' : 'Edit'}</span>
            </button>

            {/* Right: Action buttons */}
            <div className="flex items-center gap-2 shrink-0">
                <Button variant="secondary" onClick={onRegenerate} className="text-sm">
                    <span className="inline-flex items-center gap-1.5">
                        <span className="material-symbols-outlined text-[18px]">refresh</span>
                        Regenerate
                    </span>
                </Button>
                <ExportDropdown
                    projectId={projectId}
                    projectSlug={projectSlug}
                    onExportComplete={onExportComplete}
                />
            </div>
        </div>
    );
}
