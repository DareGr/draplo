import React, { useEffect } from 'react';

export default function Toast({ message, type = 'success', onDismiss }) {
    useEffect(() => {
        const timer = setTimeout(onDismiss, 5000);
        return () => clearTimeout(timer);
    }, [onDismiss]);

    const borderColor =
        type === 'error'
            ? 'border-tertiary-container/30'
            : 'border-secondary/30';

    const icon = type === 'error' ? 'error' : 'check_circle';

    return (
        <div
            className={`fixed bottom-6 right-6 z-50 bg-surface-container-high border ${borderColor} rounded-xl px-5 py-3 shadow-xl flex items-center gap-3`}
        >
            <span className="material-symbols-outlined text-lg">
                {icon}
            </span>
            <span className="text-on-surface text-sm">{message}</span>
            <button
                onClick={onDismiss}
                className="ml-2 text-on-surface-variant hover:text-on-surface transition-colors"
            >
                <span className="material-symbols-outlined text-lg">
                    close
                </span>
            </button>
        </div>
    );
}
