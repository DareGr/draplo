import React from 'react';

export default function Chip({ label, dotColor, className = '' }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 px-2 py-0.5 bg-background text-on-surface-variant text-[10px] font-mono rounded ${className}`}
        >
            {dotColor && (
                <span className={`w-2 h-2 rounded-full ${dotColor}`} />
            )}
            {label}
        </span>
    );
}
