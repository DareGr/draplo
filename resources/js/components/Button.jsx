import React from 'react';

const variantClasses = {
    primary:
        'bg-gradient-to-r from-primary to-primary-container text-on-primary font-bold rounded-md px-6 py-2.5 active:scale-95 transition-all',
    secondary:
        'bg-surface-container-highest text-on-surface border border-outline-variant/15 rounded-md px-6 py-2.5 transition-all',
    tertiary: 'text-primary font-medium px-4 py-2 transition-all',
};

export default function Button({
    variant = 'primary',
    children,
    disabled = false,
    loading = false,
    onClick,
    className = '',
    type = 'button',
}) {
    const isDisabled = disabled || loading;

    return (
        <button
            type={type}
            onClick={onClick}
            disabled={isDisabled}
            className={`${variantClasses[variant] || variantClasses.primary} ${
                isDisabled ? 'opacity-50 pointer-events-none' : ''
            } ${className}`}
        >
            {loading ? (
                <span className="inline-flex items-center gap-2">
                    <svg
                        className="animate-spin h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <circle
                            className="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            strokeWidth="4"
                        />
                        <path
                            className="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                        />
                    </svg>
                    {children}
                </span>
            ) : (
                children
            )}
        </button>
    );
}
