import React from 'react';

export default function CategoryFilter({ categories, active, onChange }) {
    return (
        <div
            className="flex items-center gap-2 overflow-x-auto pb-4"
            style={{ scrollbarWidth: 'none' }}
        >
            {categories.map((category) => {
                const isActive = category === active;
                return (
                    <button
                        key={category}
                        onClick={() => onChange(category)}
                        className={
                            isActive
                                ? 'px-6 py-2 bg-primary-container text-on-primary-container rounded-full text-sm font-semibold whitespace-nowrap'
                                : 'px-6 py-2 bg-surface-container-highest text-on-surface-variant hover:text-on-surface rounded-full text-sm font-medium whitespace-nowrap transition-colors border border-outline-variant/10'
                        }
                    >
                        {category}
                    </button>
                );
            })}
        </div>
    );
}
