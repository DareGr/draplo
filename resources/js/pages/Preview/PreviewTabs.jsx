import React from 'react';

function basename(path) {
    const parts = path.split('/');
    return parts[parts.length - 1];
}

export default function PreviewTabs({ tabs = [], activeTab, onSelectTab, onCloseTab }) {
    return (
        <div className="flex items-center border-b border-outline-variant/5 bg-surface-container-low overflow-x-auto">
            {tabs.map((tab) => {
                const isActive = activeTab === tab.path;
                return (
                    <button
                        key={tab.path}
                        className={`flex items-center gap-2 px-4 py-2.5 text-sm whitespace-nowrap transition-colors shrink-0 ${
                            isActive
                                ? 'bg-surface-container text-primary border-t-2 border-primary'
                                : 'bg-surface-container-low text-on-surface-variant hover:text-on-surface border-t-2 border-transparent'
                        }`}
                        onClick={() => onSelectTab(tab.path)}
                    >
                        {tab.modified && (
                            <span className="w-1.5 h-1.5 rounded-full bg-primary shrink-0" />
                        )}
                        <span className="font-mono text-[13px]">{basename(tab.path)}</span>
                        <span
                            className="material-symbols-outlined text-[14px] text-outline hover:text-on-surface transition-colors ml-1"
                            onClick={(e) => {
                                e.stopPropagation();
                                onCloseTab(tab.path);
                            }}
                        >
                            close
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
