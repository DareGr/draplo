import React from 'react';

export default function Toggle({ checked, onChange, label, description }) {
    return (
        <label className="flex items-center justify-between cursor-pointer">
            <div>
                <span className="text-on-surface font-medium text-sm">
                    {label}
                </span>
                {description && (
                    <p className="text-on-surface-variant text-xs mt-0.5">
                        {description}
                    </p>
                )}
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                onClick={() => onChange(!checked)}
                className={`relative inline-flex items-center w-11 h-6 rounded-full transition-colors ${
                    checked
                        ? 'bg-primary-container'
                        : 'bg-surface-container-highest'
                }`}
            >
                <span
                    className={`w-5 h-5 rounded-full bg-on-surface shadow transition-transform ${
                        checked ? 'translate-x-5' : 'translate-x-0.5'
                    }`}
                />
            </button>
        </label>
    );
}
