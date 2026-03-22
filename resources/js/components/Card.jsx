import React from 'react';

export default function Card({
    children,
    className = '',
    onClick,
    hover = true,
}) {
    return (
        <div
            onClick={onClick}
            className={`bg-surface-container rounded-xl p-6 border border-outline-variant/5 transition-all duration-300 ${
                hover ? 'hover:bg-surface-container-high' : ''
            } ${onClick ? 'cursor-pointer' : ''} ${className}`}
        >
            {children}
        </div>
    );
}
