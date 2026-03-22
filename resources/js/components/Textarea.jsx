import React from 'react';

export default function Textarea({
    label,
    value,
    onChange,
    placeholder,
    rows = 3,
    required = false,
    error,
    className = '',
}) {
    return (
        <div className={className}>
            {label && (
                <label className="block text-on-surface-variant text-sm font-label mb-1.5">
                    {label}
                    {required && <span className="text-tertiary ml-1">*</span>}
                </label>
            )}
            <textarea
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                rows={rows}
                required={required}
                className={`w-full bg-surface-container-lowest font-mono text-on-surface px-4 py-3 rounded-md border outline-none transition-all placeholder:text-outline resize-y ${
                    error
                        ? 'border-error/50'
                        : 'border-outline-variant/15 focus:border-primary/50 focus:ring-2 focus:ring-primary-container/5'
                }`}
            />
            {error && (
                <p className="text-error text-xs mt-1">{error}</p>
            )}
        </div>
    );
}
