import React from 'react';

function formatTokens(n) {
    return n?.toLocaleString() ?? '0';
}

function formatCost(n) {
    return `$${(n ?? 0).toFixed(4)}`;
}

function formatDuration(ms) {
    return `${((ms ?? 0) / 1000).toFixed(1)}s`;
}

function formatTimestamp(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function InfoRow({ label, children }) {
    return (
        <div className="flex items-center justify-between py-2">
            <span className="font-label text-[11px] uppercase tracking-widest text-outline">
                {label}
            </span>
            <span className="font-mono text-sm text-on-surface">{children}</span>
        </div>
    );
}

function Badge({ children, variant = 'default' }) {
    const variants = {
        default: 'bg-surface-container-highest text-on-surface-variant',
        green: 'bg-green-900/30 text-green-400',
        amber: 'bg-amber-900/30 text-amber-400',
    };

    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-label ${variants[variant]}`}>
            {children}
        </span>
    );
}

export default function GenerationInfo({ generation }) {
    if (!generation) {
        return (
            <div className="bg-surface-container rounded-xl p-5 border border-outline-variant/5">
                <div className="flex items-center gap-2 mb-4">
                    <span className="material-symbols-outlined text-[20px] text-outline">analytics</span>
                    <h3 className="font-headline text-sm font-semibold text-on-surface">Generation Info</h3>
                </div>
                <p className="text-sm text-outline text-center py-6">No generation data</p>
            </div>
        );
    }

    return (
        <div className="bg-surface-container rounded-xl p-5 border border-outline-variant/5">
            <div className="flex items-center gap-2 mb-4">
                <span className="material-symbols-outlined text-[20px] text-outline">analytics</span>
                <h3 className="font-headline text-sm font-semibold text-on-surface">Generation Info</h3>
            </div>

            <div className="divide-y divide-outline-variant/5">
                <InfoRow label="Provider">
                    <Badge>{generation.provider ?? 'anthropic'}</Badge>
                </InfoRow>

                <InfoRow label="Model">
                    {generation.model ?? 'unknown'}
                </InfoRow>

                <InfoRow label="Input Tokens">
                    {formatTokens(generation.prompt_tokens)}
                </InfoRow>

                <InfoRow label="Output Tokens">
                    {formatTokens(generation.completion_tokens)}
                </InfoRow>

                <InfoRow label="Cache Read">
                    <span className={generation.cache_read_tokens > 0 ? 'text-green-400' : ''}>
                        {formatTokens(generation.cache_read_tokens)}
                    </span>
                </InfoRow>

                <InfoRow label="Cost">
                    {formatCost(generation.cost_usd)}
                </InfoRow>

                <InfoRow label="Duration">
                    {formatDuration(generation.duration_ms)}
                </InfoRow>

                <InfoRow label="Cached">
                    {generation.cached ? (
                        <Badge variant="green">Yes</Badge>
                    ) : (
                        <Badge variant="amber">No</Badge>
                    )}
                </InfoRow>

                <InfoRow label="Generated">
                    <span className="text-xs">{formatTimestamp(generation.created_at)}</span>
                </InfoRow>
            </div>
        </div>
    );
}
